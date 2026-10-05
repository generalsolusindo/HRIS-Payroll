import { ATTENDANCE_TONE_CARD_STYLES } from '@/lib/attendance-tone';
import { cn } from '@/lib/utils';

export const SUMMARY_TONE_STYLES = {
    ...ATTENDANCE_TONE_CARD_STYLES,
    red: 'bg-red-50 dark:bg-red-500/10',
    muted: 'bg-muted/60',
} as const;

export function SummaryStat({
    label,
    value,
    tone,
    emphasize = false,
}: {
    label: string;
    value: number;
    tone: keyof typeof SUMMARY_TONE_STYLES;
    emphasize?: boolean;
}) {
    return (
        <div className={cn('rounded-3xl p-5', SUMMARY_TONE_STYLES[tone])}>
            <div className="text-sm text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'mt-1 text-3xl font-bold tracking-tight',
                    emphasize && 'text-red-600 dark:text-red-400',
                )}
            >
                {value}
            </div>
        </div>
    );
}
