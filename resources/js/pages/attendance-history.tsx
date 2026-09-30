import { Head, router } from '@inertiajs/react';
import { Inbox, LogIn, LogOut, type LucideIcon } from 'lucide-react';
import AttendanceHistoryController from '@/actions/App/Http/Controllers/AttendanceHistoryController';
import {
    AttendanceStatusBadge,
    type AttendanceStatus,
} from '@/components/attendance-status-badge';
import { DateField } from '@/components/date-field';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

type AttendanceRow = {
    tanggal: string;
    clock_in_time: string | null;
    clock_in_status: AttendanceStatus | null;
    clock_in_late_minutes: number | null;
    clock_in_photo_url: string | null;
    clock_out_time: string | null;
    clock_out_status: AttendanceStatus | null;
    clock_out_early_minutes: number | null;
    clock_out_photo_url: string | null;
};

type Props = {
    bulan: string;
    attendances: AttendanceRow[];
};

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

function formatTime(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

const TONE_STYLES = {
    navy: 'bg-brand-navy/8 dark:bg-brand-navy/35 text-brand-navy dark:text-brand-navy-foreground',
    yellow: 'bg-brand-yellow/20 dark:bg-brand-yellow/12 text-brand-yellow-foreground',
} as const;

function AttendanceMiniStatus({
    label,
    time,
    status,
    minutes,
    photoUrl,
    icon: Icon,
    tone,
}: {
    label: string;
    time: string | null;
    status: AttendanceStatus | null;
    minutes: number | null;
    photoUrl: string | null;
    icon: LucideIcon;
    tone: keyof typeof TONE_STYLES;
}) {
    return (
        <div className="flex items-center gap-3">
            {photoUrl ? (
                <a href={photoUrl} target="_blank" rel="noreferrer">
                    <Avatar className="size-9 ring-2 ring-background">
                        <AvatarImage src={photoUrl} alt={`Foto ${label}`} />
                        <AvatarFallback className="text-xs">
                            {label.charAt(0)}
                        </AvatarFallback>
                    </Avatar>
                </a>
            ) : (
                <span
                    className={cn(
                        'flex size-9 items-center justify-center rounded-full',
                        TONE_STYLES[tone],
                    )}
                >
                    <Icon className="size-4" />
                </span>
            )}
            <div className="flex flex-col gap-0.5">
                <span className="text-xs text-muted-foreground">
                    {label}
                </span>
                {time ? (
                    <span className="text-sm font-semibold">{time}</span>
                ) : (
                    <span className="text-sm text-muted-foreground">-</span>
                )}
                {status && (
                    <AttendanceStatusBadge status={status} minutes={minutes} />
                )}
            </div>
        </div>
    );
}

function AttendanceHistoryRow({ attendance }: { attendance: AttendanceRow }) {
    return (
        <div className="flex flex-col gap-4 rounded-2xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="text-sm font-semibold capitalize">
                {formatDate(attendance.tanggal)}
            </div>
            <div className="flex items-center gap-6">
                <AttendanceMiniStatus
                    label="Masuk"
                    time={formatTime(attendance.clock_in_time)}
                    status={attendance.clock_in_status}
                    minutes={attendance.clock_in_late_minutes}
                    photoUrl={attendance.clock_in_photo_url}
                    icon={LogIn}
                    tone="navy"
                />
                <AttendanceMiniStatus
                    label="Pulang"
                    time={formatTime(attendance.clock_out_time)}
                    status={attendance.clock_out_status}
                    minutes={attendance.clock_out_early_minutes}
                    photoUrl={attendance.clock_out_photo_url}
                    icon={LogOut}
                    tone="yellow"
                />
            </div>
        </div>
    );
}

export default function AttendanceHistory({ bulan, attendances }: Props) {
    function handleMonthChange(value: string) {
        router.get(
            AttendanceHistoryController.index().url,
            { bulan: value },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Riwayat Absensi" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Riwayat Absensi
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Riwayat absen masuk dan pulang Anda.
                    </p>
                </div>

                <DateField
                    id="bulan"
                    label="Bulan"
                    type="month"
                    defaultValue={bulan}
                    onChange={(e) => handleMonthChange(e.target.value)}
                />

                {attendances.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Inbox className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Tidak ada riwayat absen di bulan ini.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {attendances.map((attendance) => (
                            <AttendanceHistoryRow
                                key={attendance.tanggal}
                                attendance={attendance}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

AttendanceHistory.layout = {
    breadcrumbs: [
        {
            title: 'Riwayat Absensi',
            href: AttendanceHistoryController.index(),
        },
    ],
};
