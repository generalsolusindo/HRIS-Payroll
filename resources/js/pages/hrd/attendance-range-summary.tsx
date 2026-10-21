import { Form, Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import AttendanceRangeSummaryController from '@/actions/App/Http/Controllers/Hrd/AttendanceRangeSummaryController';
import { DateField } from '@/components/date-field';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';

type EmployeeSummary = {
    id: number;
    name: string;
    position: string;
    reviewed_days: number;
    expected_days: number;
    hadir: number;
    telat: number;
    telat_menit: number;
    setengah_hari: number;
    alpha: number;
    izin: number;
    sakit: number;
    cuti: number;
    dinas: number;
    libur: number;
    perlu_konfirmasi: number;
};

type Props = {
    dari: string;
    sampai: string;
    employees: EmployeeSummary[];
    confirmations: {
        id: number;
        user_id: number;
        name: string;
        date: string;
        version: number;
        clock_in: string;
        can_confirm: boolean;
    }[];
};

function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function AttendanceRangeSummary({
    dari,
    sampai,
    employees,
    confirmations,
}: Props) {
    const [activeEmployeeId, setActiveEmployeeId] = useState<number | null>(
        null,
    );

    function handleChange(next: Partial<{ dari: string; sampai: string }>) {
        router.get(
            AttendanceRangeSummaryController.index().url,
            { dari, sampai, ...next },
            { preserveState: true, preserveScroll: true },
        );
    }

    const activeEmployee = useMemo(
        () => employees.find((employee) => employee.id === activeEmployeeId),
        [employees, activeEmployeeId],
    );

    const activeConfirmations = useMemo(
        () =>
            confirmations.filter(
                (confirmation) => confirmation.user_id === activeEmployeeId,
            ),
        [confirmations, activeEmployeeId],
    );

    return (
        <>
            <Head title="Rekap Periode" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Rekap Periode
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Rekap hadir/telat/alpha per karyawan untuk rentang
                        tanggal yang dipilih, dihitung otomatis dari absensi dan
                        izin yang disetujui — sumber yang sama dengan yang
                        dipakai payroll. Hari yang belum terjadi (di masa depan)
                        tidak ikut terhitung di kolom manapun.
                    </p>
                </div>

                <div className="flex flex-wrap gap-4">
                    <DateField
                        id="dari"
                        label="Dari tanggal"
                        type="date"
                        value={dari}
                        max={sampai}
                        onChange={(e) => handleChange({ dari: e.target.value })}
                    />
                    <DateField
                        id="sampai"
                        label="Sampai tanggal"
                        type="date"
                        value={sampai}
                        min={dari}
                        onChange={(e) =>
                            handleChange({ sampai: e.target.value })
                        }
                    />
                </div>

                <p className="text-xs text-muted-foreground">
                    {formatDate(dari)} – {formatDate(sampai)}
                </p>

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
                            <table className="w-full min-w-240 text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium text-muted-foreground">
                                            Nama
                                        </th>
                                        <th className="p-3 font-medium text-muted-foreground">
                                            Jabatan
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Hadir
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Telat
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Menit Telat
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Setengah Hari
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Alpha
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Izin
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Sakit
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Cuti
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Dinas
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Libur
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Perlu Konfirmasi
                                        </th>
                                        <th className="p-3 text-right font-medium text-muted-foreground">
                                            Hari Tercatat
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
                                                {employee.position}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.hadir}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.telat}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.telat_menit}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.setengah_hari}
                                            </td>
                                            <td className="p-3 text-right text-red-600 tabular-nums dark:text-red-400">
                                                {employee.alpha}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.izin}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.sakit}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.cuti}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.dinas}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.libur}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {employee.perlu_konfirmasi >
                                                0 ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setActiveEmployeeId(
                                                                employee.id,
                                                            )
                                                        }
                                                        className="rounded-full border border-amber-400/50 bg-amber-500/10 px-2.5 py-0.5 font-medium text-amber-600 transition-colors hover:bg-amber-500/20 dark:border-amber-400/40 dark:text-amber-400"
                                                    >
                                                        {
                                                            employee.perlu_konfirmasi
                                                        }
                                                    </button>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        0
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-3 text-right text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                                                {employee.reviewed_days} dari{' '}
                                                {employee.expected_days}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>

            <Sheet
                open={activeEmployeeId !== null}
                onOpenChange={(open) => {
                    if (!open) setActiveEmployeeId(null);
                }}
            >
                <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>
                            Konfirmasi Absen Pulang
                            {activeEmployee ? ` — ${activeEmployee.name}` : ''}
                        </SheetTitle>
                        <SheetDescription>
                            Isi jam pulang yang sudah diverifikasi dan alasan
                            koreksi. Catatan absensi asli tetap disimpan.
                            Setelah konfirmasi, perbarui draft payroll.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-4 px-4 pb-4">
                        {activeConfirmations.map((review) => (
                            <Form
                                key={`${review.id}-${review.version}`}
                                action={`/hrd/konfirmasi-absensi/${review.id}`}
                                method="patch"
                                options={{ preserveScroll: true }}
                                className="space-y-3 rounded-xl border p-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <p className="text-sm font-medium">
                                            {formatDate(review.date)} · Masuk{' '}
                                            {review.clock_in} WIB
                                        </p>
                                        <input
                                            type="hidden"
                                            name="version"
                                            value={review.version}
                                        />
                                        <Label htmlFor={`out-${review.id}`}>
                                            Jam pulang terkonfirmasi (WIB)
                                        </Label>
                                        <Input
                                            id={`out-${review.id}`}
                                            type="time"
                                            name="clock_out"
                                            required
                                            disabled={!review.can_confirm}
                                            className="max-w-48"
                                        />
                                        <InputError
                                            message={errors.clock_out}
                                        />
                                        <Label htmlFor={`reason-${review.id}`}>
                                            Alasan / dasar konfirmasi
                                        </Label>
                                        <Textarea
                                            id={`reason-${review.id}`}
                                            name="reason"
                                            required
                                            minLength={5}
                                            maxLength={2000}
                                            disabled={!review.can_confirm}
                                        />
                                        <InputError message={errors.reason} />
                                        <InputError
                                            message={
                                                errors.confirmation ??
                                                errors.version
                                            }
                                        />
                                        <Button
                                            disabled={
                                                processing ||
                                                !review.can_confirm
                                            }
                                        >
                                            Konfirmasi HRD
                                        </Button>
                                        {!review.can_confirm && (
                                            <p className="text-sm text-muted-foreground">
                                                Menunggu hari absensi selesai.
                                            </p>
                                        )}
                                    </>
                                )}
                            </Form>
                        ))}
                        {activeConfirmations.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Tidak ada lagi hari yang perlu dikonfirmasi
                                untuk karyawan ini.
                            </p>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

AttendanceRangeSummary.layout = {
    breadcrumbs: [
        {
            title: 'Rekap Periode',
            href: AttendanceRangeSummaryController.index(),
        },
    ],
};
