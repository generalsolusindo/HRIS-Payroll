/**
 * Shared navy/yellow card backgrounds for attendance stat cards. The dark
 * variant needs more opacity (and a faint border) than the light one since
 * dark mode's page background is itself navy-tinted — a light navy overlay
 * barely shows up against it otherwise.
 */
export const ATTENDANCE_TONE_CARD_STYLES = {
    navy: 'bg-brand-navy/8 dark:border dark:border-brand-navy-foreground/10 dark:bg-brand-navy/35',
    yellow: 'bg-brand-yellow/20 dark:bg-brand-yellow/12',
} as const;

export type AttendanceTone = keyof typeof ATTENDANCE_TONE_CARD_STYLES;
