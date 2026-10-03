import {
    AttendanceStatusBadge,
    type AttendanceStatus,
} from '@/components/attendance-status-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

export function AttendanceStatCard({
    label,
    time,
    status,
    minutes,
    photoUrl,
    emptyText = 'Belum absen',
}: {
    label: string;
    time: string | null;
    status?: AttendanceStatus | null;
    minutes?: number | null;
    photoUrl?: string | null;
    emptyText?: string;
}) {
    return (
        <div className="flex flex-col gap-2 rounded-2xl border bg-card p-4">
            <div className="flex items-center justify-between gap-2">
                <span className="text-sm font-medium text-muted-foreground">
                    {label}
                </span>
                {photoUrl && (
                    <a
                        href={photoUrl}
                        target="_blank"
                        rel="noreferrer"
                        aria-label={`Lihat foto ${label}`}
                    >
                        <Avatar className="size-7">
                            <AvatarImage src={photoUrl} alt={`Foto ${label}`} />
                            <AvatarFallback>{label.charAt(0)}</AvatarFallback>
                        </Avatar>
                    </a>
                )}
            </div>

            <div className="font-mono text-2xl font-bold tracking-tight">
                {time ?? '--:--'}
            </div>

            {status ? (
                <AttendanceStatusBadge status={status} minutes={minutes} />
            ) : (
                <span className="text-xs text-muted-foreground">
                    {emptyText}
                </span>
            )}
        </div>
    );
}
