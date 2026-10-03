import { cn } from '@/lib/utils';

export type AttendanceStatus = 'tepat_waktu' | 'telat' | 'pulang_cepat';

const STATUS_CONFIG: Record<
    AttendanceStatus,
    { label: string; dot: string; pill: string }
> = {
    tepat_waktu: {
        label: 'Tepat Waktu',
        dot: 'bg-emerald-500',
        pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    },
    telat: {
        label: 'Telat',
        dot: 'bg-red-500',
        pill: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
    },
    pulang_cepat: {
        label: 'Pulang Cepat',
        dot: 'bg-amber-500',
        pill: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    },
};

export function AttendanceStatusBadge({
    status,
    minutes,
}: {
    status: AttendanceStatus;
    minutes?: number | null;
}) {
    const config = STATUS_CONFIG[status];
    const wholeMinutes =
        minutes != null && Number.isFinite(minutes) && minutes >= 0
            ? Math.max(1, Math.floor(minutes))
            : null;
    const label =
        status !== 'tepat_waktu' && wholeMinutes !== null
            ? `${config.label} ${wholeMinutes} menit`
            : config.label;

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                config.pill,
            )}
        >
            <span className={cn('size-1.5 rounded-full', config.dot)} />
            {label}
        </span>
    );
}
