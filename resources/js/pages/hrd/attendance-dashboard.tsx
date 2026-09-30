import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import AttendanceDashboardController from '@/actions/App/Http/Controllers/Hrd/AttendanceDashboardController';
import {
    AttendanceStatusBadge,
    type AttendanceStatus,
} from '@/components/attendance-status-badge';
import { DateField } from '@/components/date-field';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card } from '@/components/ui/card';
import { ATTENDANCE_TONE_CARD_STYLES } from '@/lib/attendance-tone';
import { cn } from '@/lib/utils';

type Employee = {
    id: number;
    name: string;
    company: string;
    position: string;
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
    tanggal: string;
    employees: Employee[];
};

function formatTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

const SUMMARY_TONE_STYLES = {
    ...ATTENDANCE_TONE_CARD_STYLES,
    red: 'bg-red-50 dark:bg-red-500/10',
    muted: 'bg-muted/60',
} as const;

function SummaryStat({
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

function StatusCell({
    time,
    status,
    minutes,
    photoUrl,
}: {
    time: string | null;
    status: AttendanceStatus | null;
    minutes: number | null;
    photoUrl: string | null;
}) {
    if (!time) {
        return <span className="text-muted-foreground">Belum absen</span>;
    }

    return (
        <div className="flex items-center gap-2.5">
            {photoUrl && (
                <a href={photoUrl} target="_blank" rel="noreferrer">
                    <Avatar className="size-8 ring-2 ring-background">
                        <AvatarImage src={photoUrl} alt="Foto absen" />
                        <AvatarFallback className="text-xs" />
                    </Avatar>
                </a>
            )}
            <div className="flex flex-col gap-1">
                <span className="text-sm font-medium">{formatTime(time)}</span>
                {status && (
                    <AttendanceStatusBadge status={status} minutes={minutes} />
                )}
            </div>
        </div>
    );
}

export default function AttendanceDashboard({ tanggal, employees }: Props) {
    function handleDateChange(value: string) {
        router.get(
            AttendanceDashboardController.index().url,
            { tanggal: value },
            { preserveState: true, preserveScroll: true },
        );
    }

    const totalKaryawan = employees.length;
    const sudahAbsen = employees.filter((e) => e.clock_in_time).length;
    const telat = employees.filter((e) => e.clock_in_status === 'telat').length;
    const belumAbsen = totalKaryawan - sudahAbsen;

    return (
        <>
            <Head title="Rekap Absensi" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Rekap Absensi
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Daftar kehadiran seluruh karyawan aktif, murni untuk
                        dilihat — tidak perlu verifikasi manual.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <SummaryStat
                        label="Total Karyawan"
                        value={totalKaryawan}
                        tone="navy"
                    />
                    <SummaryStat
                        label="Sudah Absen"
                        value={sudahAbsen}
                        tone="yellow"
                    />
                    <SummaryStat
                        label="Telat"
                        value={telat}
                        tone="red"
                        emphasize
                    />
                    <SummaryStat
                        label="Belum Absen"
                        value={belumAbsen}
                        tone="muted"
                    />
                </div>

                <DateField
                    id="tanggal"
                    label="Tanggal"
                    type="date"
                    defaultValue={tanggal}
                    onChange={(e) => handleDateChange(e.target.value)}
                />

                {employees.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Users className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada karyawan aktif.
                        </p>
                    </div>
                ) : (
                    <Card className="gap-0 overflow-hidden rounded-3xl p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-180 text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium text-muted-foreground">
                                            Nama
                                        </th>
                                        <th className="p-3 font-medium text-muted-foreground">
                                            Instansi
                                        </th>
                                        <th className="p-3 font-medium text-muted-foreground">
                                            Jabatan / Divisi
                                        </th>
                                        <th className="p-3 font-medium text-muted-foreground">
                                            Absen Masuk
                                        </th>
                                        <th className="p-3 font-medium text-muted-foreground">
                                            Absen Pulang
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {employees.map((employee) => (
                                        <tr
                                            key={employee.id}
                                            className="border-t"
                                        >
                                            <td className="p-3 font-medium">
                                                {employee.name}
                                            </td>
                                            <td className="p-3">
                                                {employee.company}
                                            </td>
                                            <td className="p-3">
                                                {employee.position}
                                            </td>
                                            <td className="p-3">
                                                <StatusCell
                                                    time={
                                                        employee.clock_in_time
                                                    }
                                                    status={
                                                        employee.clock_in_status
                                                    }
                                                    minutes={
                                                        employee.clock_in_late_minutes
                                                    }
                                                    photoUrl={
                                                        employee.clock_in_photo_url
                                                    }
                                                />
                                            </td>
                                            <td className="p-3">
                                                <StatusCell
                                                    time={
                                                        employee.clock_out_time
                                                    }
                                                    status={
                                                        employee.clock_out_status
                                                    }
                                                    minutes={
                                                        employee.clock_out_early_minutes
                                                    }
                                                    photoUrl={
                                                        employee.clock_out_photo_url
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>
        </>
    );
}

AttendanceDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Rekap Absensi',
            href: AttendanceDashboardController.index(),
        },
    ],
};
