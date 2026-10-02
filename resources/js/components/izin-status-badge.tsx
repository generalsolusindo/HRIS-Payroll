import { cn } from '@/lib/utils';

export type IzinStatus =
    | 'pending'
    | 'pending_manager'
    | 'approved'
    | 'rejected';

// Same blue/yellow/green/red coding already used in the real Telegram
// notifications (🔵🟡🟢🔴), so the status reads the same wherever
// someone sees it.
const STATUS_CONFIG: Record<
    IzinStatus,
    { label: string; dot: string; pill: string }
> = {
    pending: {
        label: 'Menunggu HRD',
        dot: 'bg-blue-500',
        pill: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
    },
    pending_manager: {
        label: 'Menunggu Manager',
        dot: 'bg-amber-500',
        pill: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    },
    approved: {
        label: 'Disetujui',
        dot: 'bg-emerald-500',
        pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    },
    rejected: {
        label: 'Ditolak',
        dot: 'bg-red-500',
        pill: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
    },
};

export function IzinStatusBadge({ status }: { status: IzinStatus }) {
    const config = STATUS_CONFIG[status];

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                config.pill,
            )}
        >
            <span className={cn('size-1.5 rounded-full', config.dot)} />
            {config.label}
        </span>
    );
}
