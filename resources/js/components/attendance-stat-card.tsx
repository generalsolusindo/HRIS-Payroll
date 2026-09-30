import { type LucideIcon } from 'lucide-react';
import {
    AttendanceStatusBadge,
    type AttendanceStatus,
} from '@/components/attendance-status-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    ATTENDANCE_TONE_CARD_STYLES,
    type AttendanceTone,
} from '@/lib/attendance-tone';
import { cn } from '@/lib/utils';

const ICON_TONE_STYLES = {
    navy: 'bg-brand-navy text-brand-navy-foreground',
    yellow: 'bg-brand-yellow text-brand-yellow-foreground',
} as const;

type Tone = AttendanceTone;

export function AttendanceStatCard({
    label,
    time,
    status,
    minutes,
    photoUrl,
    icon: Icon,
    tone,
    emptyText = 'Belum absen',
}: {
    label: string;
    time: string | null;
    status?: AttendanceStatus | null;
    minutes?: number | null;
    photoUrl?: string | null;
    icon: LucideIcon;
    tone: Tone;
    emptyText?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-4 rounded-3xl p-5',
                ATTENDANCE_TONE_CARD_STYLES[tone],
            )}
        >
            <div className="flex items-start justify-between">
                <span
                    className={cn(
                        'flex size-9 items-center justify-center rounded-full',
                        ICON_TONE_STYLES[tone],
                    )}
                >
                    <Icon className="size-4" />
                </span>

                {photoUrl && (
                    <a
                        href={photoUrl}
                        target="_blank"
                        rel="noreferrer"
                        aria-label={`Lihat foto ${label}`}
                    >
                        <Avatar className="size-9 ring-2 ring-background">
                            <AvatarImage src={photoUrl} alt={`Foto ${label}`} />
                            <AvatarFallback>{label.charAt(0)}</AvatarFallback>
                        </Avatar>
                    </a>
                )}
            </div>

            <div>
                <div className="text-sm text-muted-foreground">{label}</div>
                {time ? (
                    <div className="mt-1 text-3xl font-bold tracking-tight">
                        {time}
                    </div>
                ) : (
                    <div className="mt-1 text-xl font-semibold text-muted-foreground">
                        {emptyText}
                    </div>
                )}
            </div>

            {status && (
                <AttendanceStatusBadge status={status} minutes={minutes} />
            )}
        </div>
    );
}
