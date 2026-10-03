import { Head, router } from '@inertiajs/react';
import { CheckCircle2, LogIn, LogOut } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AttendanceStatCard } from '@/components/attendance-stat-card';
import { Button } from '@/components/ui/button';
import {
    buildAttendanceFormData,
    captureSelfie,
    getCurrentPosition,
} from '@/lib/attendance';
import { dashboard } from '@/routes';
import absen from '@/routes/absen';

type TodayAttendance = {
    clock_in_time: string | null;
    clock_in_status: 'tepat_waktu' | 'telat' | null;
    clock_in_late_minutes: number | null;
    clock_in_photo_url: string | null;
    clock_out_time: string | null;
    clock_out_status: 'tepat_waktu' | 'pulang_cepat' | null;
    clock_out_early_minutes: number | null;
    clock_out_photo_url: string | null;
} | null;

type Props = {
    todayAttendance: TodayAttendance;
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

export default function Dashboard({ todayAttendance }: Props) {
    const [submitting, setSubmitting] = useState(false);

    async function handleAbsen(type: 'masuk' | 'pulang') {
        setSubmitting(true);

        try {
            const photo = await captureSelfie();
            const position = await getCurrentPosition();
            const formData = buildAttendanceFormData(photo, position);
            const url =
                type === 'masuk' ? absen.masuk.url() : absen.pulang.url();

            router.post(url, formData, {
                forceFormData: true,
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    toast.error(message ?? 'Gagal mengirim absen.');
                },
                onFinish: () => setSubmitting(false),
            });
        } catch (error) {
            setSubmitting(false);
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Gagal mengambil lokasi/foto.',
            );
        }
    }

    const hasClockedIn = !!todayAttendance?.clock_in_time;
    const hasClockedOut = !!todayAttendance?.clock_out_time;

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
                            onClick={() => handleAbsen('masuk')}
                            disabled={submitting}
                        >
                            Absen Masuk
                        </Button>
                    )}

                    {hasClockedIn && !hasClockedOut && (
                        <Button
                            className="h-12 w-full rounded-full text-base font-semibold"
                            onClick={() => handleAbsen('pulang')}
                            disabled={submitting}
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
