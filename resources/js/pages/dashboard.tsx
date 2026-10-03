import { Head, router } from '@inertiajs/react';
import { CheckCircle2, LogIn, LogOut } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AttendanceCaptureScreen } from '@/components/attendance-capture-screen';
import { AttendanceStatCard } from '@/components/attendance-stat-card';
import { AttendanceSuccessScreen } from '@/components/attendance-success-screen';
import { type AttendanceStatus } from '@/components/attendance-status-badge';
import { Button } from '@/components/ui/button';
import {
    buildAttendanceFormData,
    nearestOffice,
    type OfficeLocation,
} from '@/lib/attendance';
import { dashboard } from '@/routes';
import absen from '@/routes/absen';

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

type WorkSchedule = { jam_masuk: string; jam_pulang: string } | null;

type Props = {
    todayAttendance: TodayAttendance;
    officeLocations: OfficeLocation[];
    workSchedule: WorkSchedule;
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

function formatToday(): string {
    return new Date().toLocaleDateString('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function Dashboard({
    todayAttendance,
    officeLocations,
    workSchedule,
}: Props) {
    const [screen, setScreen] = useState<'home' | 'capture' | 'success'>(
        'home',
    );
    const [activeType, setActiveType] = useState<'masuk' | 'pulang'>('masuk');
    const [submitting, setSubmitting] = useState(false);
    const [successData, setSuccessData] = useState<SuccessData | null>(null);

    // router.post's onSuccess fires after Inertia has already applied the
    // fresh props, but this closure was created on a prior render — so read
    // the latest todayAttendance through a ref rather than the stale one
    // captured when handleCaptured was defined.
    const todayAttendanceRef = useRef(todayAttendance);
    useEffect(() => {
        todayAttendanceRef.current = todayAttendance;
    }, [todayAttendance]);

    function handleStart(type: 'masuk' | 'pulang') {
        setActiveType(type);
        setScreen('capture');
    }

    function handleCaptured(photo: File, position: GeolocationPosition) {
        setSubmitting(true);

        const nearest = nearestOffice(
            officeLocations,
            position.coords.latitude,
            position.coords.longitude,
        );
        const formData = buildAttendanceFormData(photo, position);
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

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Absensi Hari Ini
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {formatToday()}
                    </p>
                </div>

                <div className="grid max-w-xl gap-3 sm:grid-cols-2">
                    <AttendanceStatCard
                        label="Absen Masuk (WIB)"
                        time={formatTime(
                            todayAttendance?.clock_in_time ?? null,
                        )}
                        status={todayAttendance?.clock_in_status}
                        minutes={todayAttendance?.clock_in_late_minutes}
                        photoUrl={todayAttendance?.clock_in_photo_url}
                        icon={LogIn}
                        tone="navy"
                    />
                    <AttendanceStatCard
                        label="Absen Pulang (WIB)"
                        time={formatTime(
                            todayAttendance?.clock_out_time ?? null,
                        )}
                        status={todayAttendance?.clock_out_status}
                        minutes={todayAttendance?.clock_out_early_minutes}
                        photoUrl={todayAttendance?.clock_out_photo_url}
                        icon={LogOut}
                        tone="yellow"
                    />
                </div>

                <div className="max-w-xl">
                    {!hasClockedIn && (
                        <Button
                            className="h-12 w-full rounded-full text-base font-semibold"
                            onClick={() => handleStart('masuk')}
                        >
                            Absen Masuk
                        </Button>
                    )}

                    {hasClockedIn && !hasClockedOut && (
                        <Button
                            className="h-12 w-full rounded-full text-base font-semibold"
                            onClick={() => handleStart('pulang')}
                        >
                            Absen Pulang
                        </Button>
                    )}

                    {hasClockedIn && hasClockedOut && (
                        <div className="flex items-center justify-center gap-2 rounded-full bg-muted/60 py-3.5 text-sm font-medium">
                            <CheckCircle2 className="size-4 text-emerald-500" />
                            Absensi hari ini selesai
                        </div>
                    )}
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
