import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import AttendanceRangeSummaryController from '@/actions/App/Http/Controllers/Hrd/AttendanceRangeSummaryController';
import { DateField } from '@/components/date-field';
import { Card } from '@/components/ui/card';

type EmployeeSummary = {
    id: number;
    name: string;
    position: string;
    reviewed_days: number;
    expected_days: number;
    hadir: number;
    telat: number;
    telat_menit: number;
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
}: Props) {
    function handleChange(next: Partial<{ dari: string; sampai: string }>) {
        router.get(
            AttendanceRangeSummaryController.index().url,
            { dari, sampai, ...next },
            { preserveState: true, preserveScroll: true },
        );
    }

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
                        tanggal yang dipilih, diambil dari status yang sudah
                        dikoreksi HRD di Koreksi Absensi — sumber yang sama
                        dengan yang dipakai payroll. Hari yang belum dikoreksi
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
                                            <td className="p-3 text-right text-amber-600 tabular-nums dark:text-amber-400">
                                                {employee.perlu_konfirmasi}
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
