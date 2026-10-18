import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    Briefcase,
    CalendarCheck,
    Camera,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    CalendarOff,
    MapPin,
    Trash2,
    Users,
    Wallet,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import AttendanceDashboardController from '@/actions/App/Http/Controllers/Hrd/AttendanceDashboardController';
import UserManagementController from '@/actions/App/Http/Controllers/Hrd/UserManagementController';
import FinancePayrollPaymentController from '@/actions/App/Http/Controllers/Finance/PayrollPaymentController';
import ManagerLeaveRequestApprovalController from '@/actions/App/Http/Controllers/Manager/LeaveRequestApprovalController';
import ManagerPayrollRunApprovalController from '@/actions/App/Http/Controllers/Manager/PayrollRunApprovalController';
import { AttendanceCaptureScreen } from '@/components/attendance-capture-screen';
import { AttendanceStatCard } from '@/components/attendance-stat-card';
import { AttendanceSuccessScreen } from '@/components/attendance-success-screen';
import { type AttendanceStatus } from '@/components/attendance-status-badge';
import { SummaryStat } from '@/components/attendance-summary-stat';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    buildAttendanceFormData,
    nearestOffice,
    watchBestPosition,
    type OfficeLocation,
} from '@/lib/attendance';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import absen from '@/routes/absen';
import riwayatAbsen from '@/routes/riwayat-absen';

type TodayAttendance = {
    clock_in_time: string | null;
    clock_in_status: AttendanceStatus | null;
    clock_in_late_minutes: number | null;
    clock_in_photo_url: string | null;
    clock_out_time: string | null;
    clock_out_status: AttendanceStatus | null;
    clock_out_early_minutes: number | null;
    clock_out_photo_url: string | null;
} | null;

type WorkSchedule = {
    name: string;
    jam_masuk: string;
    jam_pulang: string;
} | null;

type AttendanceDay = {
    type: 'workday' | 'rest_day' | 'holiday';
    label: string;
    description: string | null;
};

type UpcomingDayOff = {
    type: 'rest_day' | 'holiday';
    description: string | null;
    starts_on: string;
    ends_on: string;
};

type HrdSummary = {
    tanggal: string;
    total_karyawan: number;
    sudah_absen: number;
    belum_absen: number;
    telat: number;
    pending_users: number;
} | null;

type ManagerSummary = {
    pending_leave: number;
    pending_payroll: number;
    pending_period_deletion: number;
} | null;

type FinanceSummary = {
    pending_payment: number;
} | null;

type Props = {
    user: { name: string };
    hrdSummary?: HrdSummary;
    managerSummary?: ManagerSummary;
    financeSummary?: FinanceSummary;
    isTeknisi?: boolean;
    todayAttendance?: TodayAttendance;
    officeLocations?: OfficeLocation[];
    workSchedule?: WorkSchedule;
    attendanceDay?: AttendanceDay;
    upcomingDaysOff?: UpcomingDayOff[];
};

type SuccessData = {
    type: 'masuk' | 'pulang';
    time: string;
    status: AttendanceStatus;
    minutes: number | null;
    photoUrl: string | null;
    officeName: string | null;
    distanceMeter: number | null;
};

function formatTime(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleTimeString('id-ID', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatShiftTime(value: string): string {
    return value.slice(0, 5);
}

function formatToday(): string {
    return new Date().toLocaleDateString('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatDateRange(startsOn: string, endsOn: string): string {
    const format = (value: string) =>
        new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
        });

    return startsOn === endsOn
        ? format(startsOn)
        : `${format(startsOn)} – ${format(endsOn)}`;
}

function currentMonthName(): string {
    return new Date().toLocaleDateString('id-ID', {
        timeZone: 'Asia/Jakarta',
        month: 'long',
    });
}

function jakartaHour(): number {
    return Number(
        new Date().toLocaleString('en-US', {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            hour12: false,
        }),
    );
}

function greeting(): string {
    const hour = jakartaHour();
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 18) return 'Selamat sore';
    return 'Selamat malam';
}

function initialsOf(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function formatClock(): string {
    return new Date().toLocaleTimeString('id-ID', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatSummaryDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * HRD is a monitoring/management role — it never clocks in itself, so the
 * usual clock widget / Absen Masuk button make no sense here. This is a
 * company-wide snapshot of today's attendance plus shortcuts to the two
 * things HRD actually does from this app.
 */
function HrdDashboard({
    userName,
    summary,
}: {
    userName: string;
    summary: NonNullable<HrdSummary>;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="mx-auto flex w-full max-w-xl flex-col gap-4 lg:max-w-5xl">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Avatar className="size-11">
                                <AvatarFallback className="bg-brand-navy/8 font-semibold text-brand-navy dark:bg-brand-navy/35 dark:text-foreground">
                                    {initialsOf(userName)}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {greeting()},
                                </div>
                                <div className="font-semibold">{userName}</div>
                            </div>
                        </div>
                        <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <Bell className="size-4" />
                        </span>
                    </div>

                    <div>
                        <h1 className="text-xl font-bold tracking-tight">
                            Ringkasan Kehadiran
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {formatSummaryDate(summary.tanggal)}
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <SummaryStat
                            label="Total Karyawan"
                            value={summary.total_karyawan}
                            tone="navy"
                        />
                        <SummaryStat
                            label="Sudah Absen"
                            value={summary.sudah_absen}
                            tone="yellow"
                        />
                        <SummaryStat
                            label="Telat"
                            value={summary.telat}
                            tone="red"
                            emphasize
                        />
                        <SummaryStat
                            label="Belum Absen"
                            value={summary.belum_absen}
                            tone="muted"
                        />
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Link
                            href={UserManagementController.index()}
                            className="flex items-center gap-4 rounded-3xl border p-4 text-left transition hover:bg-muted/40"
                        >
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted">
                                <Users className="size-5 text-muted-foreground" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold">
                                    Manajemen User
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {summary.pending_users > 0
                                        ? `${summary.pending_users} user menunggu persetujuan`
                                        : 'Tambah, ubah, atau hapus akun'}
                                </span>
                            </span>
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                        </Link>

                        <Link
                            href={AttendanceDashboardController.index()}
                            className="flex items-center gap-4 rounded-3xl border p-4 text-left transition hover:bg-muted/40"
                        >
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted">
                                <ClipboardList className="size-5 text-muted-foreground" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold">
                                    Rekap Absensi
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Lihat kehadiran seluruh karyawan
                                </span>
                            </span>
                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}

function DashboardLinkCard({
    href,
    icon,
    title,
    description,
}: {
    href: string;
    icon: ReactNode;
    title: string;
    description: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-4 rounded-3xl border p-4 text-left transition hover:bg-muted/40"
        >
            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted">
                {icon}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold">{title}</span>
                <span className="block text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
        </Link>
    );
}

function ManagerDashboard({
    userName,
    summary,
}: {
    userName: string;
    summary: NonNullable<ManagerSummary>;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="mx-auto flex w-full max-w-xl flex-col gap-4 lg:max-w-5xl">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Avatar className="size-11">
                                <AvatarFallback className="bg-brand-navy/8 font-semibold text-brand-navy dark:bg-brand-navy/35 dark:text-foreground">
                                    {initialsOf(userName)}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {greeting()},
                                </div>
                                <div className="font-semibold">
                                    {userName}
                                </div>
                            </div>
                        </div>
                        <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <Bell className="size-4" />
                        </span>
                    </div>

                    <div>
                        <h1 className="text-xl font-bold tracking-tight">
                            Menunggu Persetujuan
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Ringkasan yang butuh keputusan kamu.
                        </p>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <DashboardLinkCard
                            href={ManagerLeaveRequestApprovalController.index().url}
                            icon={
                                <CalendarCheck className="size-5 text-muted-foreground" />
                            }
                            title="Persetujuan Izin"
                            description={
                                summary.pending_leave > 0
                                    ? `${summary.pending_leave} izin menunggu persetujuan`
                                    : 'Tidak ada izin menunggu'
                            }
                        />
                        <DashboardLinkCard
                            href={ManagerPayrollRunApprovalController.index().url}
                            icon={
                                <Wallet className="size-5 text-muted-foreground" />
                            }
                            title="Persetujuan Payroll"
                            description={
                                summary.pending_payroll > 0
                                    ? `${summary.pending_payroll} hasil payroll menunggu persetujuan`
                                    : 'Tidak ada hasil payroll menunggu'
                            }
                        />
                        {summary.pending_period_deletion > 0 && (
                            <DashboardLinkCard
                                href={
                                    ManagerPayrollRunApprovalController.index()
                                        .url
                                }
                                icon={
                                    <Trash2 className="size-5 text-muted-foreground" />
                                }
                                title="Permintaan Hapus Periode"
                                description={`${summary.pending_period_deletion} periode menunggu persetujuan hapus`}
                            />
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function FinanceDashboard({
    userName,
    summary,
}: {
    userName: string;
    summary: NonNullable<FinanceSummary>;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="mx-auto flex w-full max-w-xl flex-col gap-4 lg:max-w-5xl">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Avatar className="size-11">
                                <AvatarFallback className="bg-brand-navy/8 font-semibold text-brand-navy dark:bg-brand-navy/35 dark:text-foreground">
                                    {initialsOf(userName)}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {greeting()},
                                </div>
                                <div className="font-semibold">
                                    {userName}
                                </div>
                            </div>
                        </div>
                        <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <Bell className="size-4" />
                        </span>
                    </div>

                    <div>
                        <h1 className="text-xl font-bold tracking-tight">
                            Pembayaran Payroll
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Ringkasan yang butuh kamu proses.
                        </p>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <DashboardLinkCard
                            href={FinancePayrollPaymentController.index().url}
                            icon={
                                <Wallet className="size-5 text-muted-foreground" />
                            }
                            title="Catat Pembayaran"
                            description={
                                summary.pending_payment > 0
                                    ? `${summary.pending_payment} periode menunggu dibayar`
                                    : 'Tidak ada periode menunggu dibayar'
                            }
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

export default function Dashboard({
    user,
    hrdSummary,
    managerSummary,
    financeSummary,
    isTeknisi = false,
    todayAttendance = null,
    officeLocations = [],
    workSchedule = null,
    attendanceDay,
    upcomingDaysOff = [],
}: Props) {
    const { faceVerificationEnabled } = usePage<{
        faceVerificationEnabled: boolean;
    }>().props;
    const [screen, setScreen] = useState<'home' | 'capture' | 'success'>(
        'home',
    );
    const [activeType, setActiveType] = useState<'masuk' | 'pulang'>('masuk');
    const [submitting, setSubmitting] = useState(false);
    const [successData, setSuccessData] = useState<SuccessData | null>(null);
    const [clock, setClock] = useState(formatClock());

    // A single opportunistic location fix for the "Dalam area kantor" chip
    // — not a continuous watch, since this is passive dashboard display,
    // not the authoritative check (that happens on the capture screen and,
    // independently, again on the server when submitting).
    const [homePosition, setHomePosition] =
        useState<GeolocationPosition | null>(null);

    // router.post's onSuccess fires after Inertia has already applied the
    // fresh props, but this closure was created on a prior render — so read
    // the latest todayAttendance through a ref rather than the stale one
    // captured when handleCaptured was defined.
    const todayAttendanceRef = useRef(todayAttendance);
    useEffect(() => {
        todayAttendanceRef.current = todayAttendance;
    }, [todayAttendance]);

    useEffect(() => {
        // HRD/Manager/Finance never clock in, so there's nothing for this
        // chip to show — skip asking for a location permission none of
        // them have any use for.
        if (hrdSummary || managerSummary || financeSummary || screen !== 'home') {
            return;
        }

        const stop = watchBestPosition(
            (position) => {
                setHomePosition(position);
                stop();
            },
            () => {
                // Silently ignore — the chip just stays hidden.
            },
        );

        return stop;
    }, [screen]);

    useEffect(() => {
        const interval = window.setInterval(
            () => setClock(formatClock()),
            1000,
        );
        return () => window.clearInterval(interval);
    }, []);

    if (hrdSummary) {
        return <HrdDashboard userName={user.name} summary={hrdSummary} />;
    }

    if (managerSummary) {
        return (
            <ManagerDashboard userName={user.name} summary={managerSummary} />
        );
    }

    if (financeSummary) {
        return (
            <FinanceDashboard userName={user.name} summary={financeSummary} />
        );
    }

    function handleStart(type: 'masuk' | 'pulang') {
        setActiveType(type);
        setScreen('capture');
    }

    function handleCaptured(
        photo: File,
        position: GeolocationPosition,
        receipt: string | null,
    ) {
        setSubmitting(true);

        const nearest = nearestOffice(
            officeLocations,
            position.coords.latitude,
            position.coords.longitude,
        );
        const formData = buildAttendanceFormData(photo, position, receipt);
        const url =
            activeType === 'masuk' ? absen.masuk.url() : absen.pulang.url();

        router.post(url, formData, {
            forceFormData: true,
            onSuccess: () => {
                const fresh = todayAttendanceRef.current;
                const time =
                    activeType === 'masuk'
                        ? fresh?.clock_in_time
                        : fresh?.clock_out_time;
                const status =
                    activeType === 'masuk'
                        ? fresh?.clock_in_status
                        : fresh?.clock_out_status;
                const minutes =
                    activeType === 'masuk'
                        ? fresh?.clock_in_late_minutes
                        : fresh?.clock_out_early_minutes;
                const photoUrl =
                    activeType === 'masuk'
                        ? fresh?.clock_in_photo_url
                        : fresh?.clock_out_photo_url;

                if (!time || !status) {
                    // Should not happen — the server just confirmed the
                    // write — but fall back to the home screen rather than
                    // render a success screen with missing data.
                    setScreen('home');
                    return;
                }

                setSuccessData({
                    type: activeType,
                    time: formatTime(time) ?? '',
                    status,
                    minutes: minutes ?? null,
                    photoUrl: photoUrl ?? null,
                    officeName: nearest?.office.name ?? null,
                    distanceMeter: nearest
                        ? Math.round(nearest.distance)
                        : null,
                });
                setScreen('success');
            },
            onError: (errors) => {
                const message = Object.values(errors)[0];
                toast.error(message ?? 'Gagal mengirim absen.');
                setScreen('home');
            },
            onFinish: () => setSubmitting(false),
        });
    }

    const hasClockedIn = !!todayAttendance?.clock_in_time;
    const hasClockedOut = !!todayAttendance?.clock_out_time;

    if (screen === 'capture') {
        return (
            <AttendanceCaptureScreen
                type={activeType}
                officeLocations={officeLocations}
                submitting={submitting}
                faceVerificationEnabled={faceVerificationEnabled}
                onCancel={() => setScreen('home')}
                onSubmit={handleCaptured}
            />
        );
    }

    if (screen === 'success' && successData) {
        return (
            <AttendanceSuccessScreen
                type={successData.type}
                time={successData.time}
                status={successData.status}
                minutes={successData.minutes}
                photoUrl={successData.photoUrl}
                officeName={successData.officeName}
                distanceMeter={successData.distanceMeter}
                shift={workSchedule}
                onBack={() => setScreen('home')}
            />
        );
    }

    const homeNearest = homePosition
        ? nearestOffice(
              officeLocations,
              homePosition.coords.latitude,
              homePosition.coords.longitude,
          )
        : null;
    const homeWithinRadius =
        homeNearest !== null &&
        homeNearest.distance <= homeNearest.office.radius_meter;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="mx-auto flex w-full max-w-xl flex-col gap-4 lg:max-w-5xl">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Avatar className="size-11">
                                <AvatarFallback className="bg-brand-navy/8 font-semibold text-brand-navy dark:bg-brand-navy/35 dark:text-foreground">
                                    {initialsOf(user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <div className="text-sm text-muted-foreground">
                                    {greeting()},
                                </div>
                                <div className="font-semibold">{user.name}</div>
                            </div>
                        </div>
                        <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <Bell className="size-4" />
                        </span>
                    </div>

                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
                        <div className="flex flex-col gap-4 lg:flex-1">
                            <div className="rounded-3xl bg-brand-navy p-5 text-brand-navy-foreground">
                                <div className="flex items-center justify-between text-sm text-brand-navy-foreground/70">
                                    <span>{formatToday()}</span>
                                    {workSchedule && (
                                        <span>Shift {workSchedule.name}</span>
                                    )}
                                </div>
                                <div className="mt-2 flex items-end justify-between">
                                    <div className="font-mono text-4xl font-bold tracking-tight sm:text-5xl">
                                        {clock}{' '}
                                        <span className="text-base font-medium text-brand-navy-foreground/70">
                                            WIB
                                        </span>
                                    </div>
                                    {workSchedule && (
                                        <div className="font-mono text-sm text-brand-navy-foreground/70">
                                            {formatShiftTime(
                                                workSchedule.jam_masuk,
                                            )}
                                            -
                                            {formatShiftTime(
                                                workSchedule.jam_pulang,
                                            )}
                                        </div>
                                    )}
                                </div>
                                {homeNearest && (
                                    <div className="mt-4 flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium">
                                        <MapPin className="size-3.5 shrink-0" />
                                        <span
                                            className={
                                                homeWithinRadius
                                                    ? 'text-emerald-400'
                                                    : 'text-amber-400'
                                            }
                                        >
                                            {homeWithinRadius
                                                ? 'Dalam area kantor'
                                                : 'Di luar area kantor'}
                                        </span>
                                        <span className="text-brand-navy-foreground/70">
                                            · {Math.round(homeNearest.distance)}{' '}
                                            m dari titik absen
                                        </span>
                                    </div>
                                )}
                            </div>

                            {hasClockedOut ? (
                                <div className="flex items-center justify-center gap-2 rounded-3xl bg-muted/60 py-3.5 text-sm font-medium">
                                    <CheckCircle2 className="size-4 text-emerald-500" />
                                    Absensi hari ini selesai
                                </div>
                            ) : attendanceDay &&
                              attendanceDay.type !== 'workday' &&
                              !hasClockedIn ? (
                                <div className="flex items-start gap-3 rounded-3xl border bg-muted/40 p-4 text-sm">
                                    <CalendarOff className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                    <div>
                                        <div className="font-semibold">
                                            {attendanceDay.label}
                                        </div>
                                        <div className="mt-1 text-muted-foreground">
                                            {attendanceDay.description ||
                                                'Absensi dinonaktifkan untuk tanggal ini.'}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() =>
                                        handleStart(
                                            hasClockedIn ? 'pulang' : 'masuk',
                                        )
                                    }
                                    className="flex items-center gap-4 rounded-3xl bg-primary p-4 text-left text-primary-foreground transition hover:bg-primary/90"
                                >
                                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary-foreground/15">
                                        <Camera className="size-5" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-base font-semibold">
                                            {hasClockedIn
                                                ? 'Absen Pulang'
                                                : 'Absen Masuk'}
                                        </span>
                                        <span className="block text-sm text-primary-foreground/80">
                                            Selfie + lokasi GPS
                                        </span>
                                    </span>
                                    <ChevronRight className="size-5 shrink-0" />
                                </button>
                            )}

                            <div className="grid grid-cols-2 gap-3">
                                <AttendanceStatCard
                                    label="Masuk"
                                    time={formatTime(
                                        todayAttendance?.clock_in_time ?? null,
                                    )}
                                    status={todayAttendance?.clock_in_status}
                                    minutes={
                                        todayAttendance?.clock_in_late_minutes
                                    }
                                    photoUrl={
                                        todayAttendance?.clock_in_photo_url
                                    }
                                    emptyText="Belum absen"
                                />
                                <AttendanceStatCard
                                    label="Pulang"
                                    time={formatTime(
                                        todayAttendance?.clock_out_time ?? null,
                                    )}
                                    status={todayAttendance?.clock_out_status}
                                    minutes={
                                        todayAttendance?.clock_out_early_minutes
                                    }
                                    photoUrl={
                                        todayAttendance?.clock_out_photo_url
                                    }
                                    emptyText={
                                        workSchedule
                                            ? `Mulai ${formatShiftTime(workSchedule.jam_pulang)}`
                                            : 'Belum absen'
                                    }
                                />
                            </div>
                        </div>

                        <div className="flex flex-col gap-4 lg:w-72 lg:shrink-0 xl:w-80">
                            <button
                                type="button"
                                disabled={!isTeknisi}
                                onClick={() => {
                                    if (isTeknisi) {
                                        toast.info(
                                            'Fitur absen dinas luar segera hadir.',
                                        );
                                    }
                                }}
                                className={cn(
                                    'flex items-center gap-4 rounded-3xl border p-4 text-left transition',
                                    isTeknisi
                                        ? 'hover:bg-muted/40'
                                        : 'cursor-not-allowed opacity-50',
                                )}
                            >
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted">
                                    <Briefcase className="size-5 text-muted-foreground" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold">
                                        Bertugas di lokasi klien?
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {isTeknisi
                                            ? 'Absen dinas luar dengan foto lokasi'
                                            : 'Khusus untuk Teknisi'}
                                    </span>
                                </span>
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                            </button>

                            <div className="rounded-3xl border p-4">
                                <div className="flex items-center justify-between">
                                    <h2 className="text-sm font-semibold">
                                        Ringkasan {currentMonthName()}
                                    </h2>
                                    <Link
                                        href={riwayatAbsen.index()}
                                        className="text-sm font-medium text-brand-navy hover:underline dark:text-brand-yellow"
                                    >
                                        Lihat riwayat
                                    </Link>
                                </div>
                            </div>

                            {upcomingDaysOff.length > 0 && (
                                <div className="rounded-3xl border p-4">
                                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                                        <CalendarOff className="size-4 text-muted-foreground" />
                                        Libur Mendatang
                                    </h2>
                                    <div className="mt-3 flex flex-col gap-3">
                                        {upcomingDaysOff.map((dayOff) => (
                                            <div
                                                key={`${dayOff.starts_on}-${dayOff.ends_on}`}
                                                className="text-sm"
                                            >
                                                <div className="font-medium">
                                                    {dayOff.description ||
                                                        (dayOff.type ===
                                                        'holiday'
                                                            ? 'Hari libur'
                                                            : 'Hari istirahat')}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDateRange(
                                                        dayOff.starts_on,
                                                        dayOff.ends_on,
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
