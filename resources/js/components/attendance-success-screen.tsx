import { CheckCircle2 } from 'lucide-react';
import {
    AttendanceStatusBadge,
    type AttendanceStatus,
} from '@/components/attendance-status-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';

type Props = {
    type: 'masuk' | 'pulang';
    time: string;
    status: AttendanceStatus;
    minutes: number | null;
    photoUrl: string | null;
    officeName: string | null;
    distanceMeter: number | null;
    shift: { jam_masuk: string; jam_pulang: string } | null;
    onBack: () => void;
};

function formatToday(): string {
    return new Date().toLocaleDateString('id-ID', {
        timeZone: 'Asia/Jakarta',
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatShiftTime(value: string): string {
    return value.slice(0, 5);
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

export function AttendanceSuccessScreen({
    type,
    time,
    status,
    minutes,
    photoUrl,
    officeName,
    distanceMeter,
    shift,
    onBack,
}: Props) {
    return (
        <div className="fixed inset-0 z-50 flex flex-col items-center overflow-y-auto bg-background px-4 py-10">
            <div className="flex w-full max-w-sm flex-1 flex-col items-center">
                <span className="flex size-16 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-500/10">
                    <CheckCircle2 className="size-8 text-emerald-600 dark:text-emerald-400" />
                </span>

                <h1 className="mt-4 text-xl font-bold tracking-tight">
                    {type === 'masuk'
                        ? 'Absen masuk tercatat'
                        : 'Absen pulang tercatat'}
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {formatToday()}
                </p>

                <div className="mt-4 font-mono text-4xl font-bold tracking-tight">
                    {time} WIB
                </div>

                <div className="mt-3">
                    <AttendanceStatusBadge status={status} minutes={minutes} />
                </div>

                <div className="mt-6 w-full space-y-3 rounded-3xl border bg-card p-4">
                    <div className="flex items-center gap-3">
                        <Avatar className="size-10">
                            {photoUrl && (
                                <AvatarImage src={photoUrl} alt="Foto selfie" />
                            )}
                            <AvatarFallback>FS</AvatarFallback>
                        </Avatar>
                        <div>
                            <div className="text-sm font-medium">
                                Foto selfie tersimpan
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Dapat dilihat HR &amp; atasan
                            </div>
                        </div>
                    </div>

                    <div className="space-y-2 border-t pt-3">
                        {officeName && (
                            <DetailRow
                                label="Lokasi"
                                value={
                                    distanceMeter !== null
                                        ? `${officeName} · ${distanceMeter} m`
                                        : officeName
                                }
                            />
                        )}
                        <DetailRow label="Verifikasi" value="Selfie + GPS" />
                        {shift && (
                            <DetailRow
                                label="Shift"
                                value={`${formatShiftTime(shift.jam_masuk)}-${formatShiftTime(shift.jam_pulang)}`}
                            />
                        )}
                    </div>
                </div>
            </div>

            <Button
                className="h-12 w-full max-w-sm rounded-full text-base font-semibold"
                onClick={onBack}
            >
                Kembali ke Beranda
            </Button>
        </div>
    );
}
