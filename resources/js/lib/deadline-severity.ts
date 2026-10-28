/**
 * Shared "how urgent is this date" logic — same thresholds as the
 * Monitoring HR dashboard (hrd/monitoring-dashboard.tsx / backend
 * MonitoringDashboardController), so a contract/probation end date is
 * colored consistently everywhere it shows up in the app.
 */
export type DeadlineSeverity = 'red' | 'amber' | 'green';

const ATTENTION_WINDOW_DAYS = 45;

export function daysUntil(dateString: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(`${dateString}T00:00:00`);

    return Math.round((target.getTime() - today.getTime()) / 86_400_000);
}

export function deadlineSeverity(daysLeft: number): DeadlineSeverity {
    if (daysLeft < 0) return 'red';
    if (daysLeft <= 7) return 'amber';

    return 'green';
}

export function isWithinAttentionWindow(daysLeft: number): boolean {
    return daysLeft <= ATTENTION_WINDOW_DAYS;
}

export const SEVERITY_BADGE_CLASS: Record<DeadlineSeverity, string> = {
    red: 'border-red-400 bg-red-500/10 text-red-600 dark:border-red-400/60 dark:text-red-400',
    amber: 'border-amber-400 bg-amber-500/10 text-amber-600 dark:border-amber-400/60 dark:text-amber-400',
    green: 'border-emerald-400 bg-emerald-500/10 text-emerald-600 dark:border-emerald-400/60 dark:text-emerald-400',
};

export const SEVERITY_DOT_CLASS: Record<DeadlineSeverity, string> = {
    red: 'bg-red-500',
    amber: 'bg-amber-500',
    green: 'bg-emerald-500',
};
