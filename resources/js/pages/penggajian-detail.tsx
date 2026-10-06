import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PenggajianController from '@/actions/App/Http/Controllers/PenggajianController';
import { Button } from '@/components/ui/button';

type Line = {
    component_name: string;
    type: string;
    amount: string;
};

type Snapshot = {
    employee: { name: string; email?: string };
    division_name: string;
    position_name: string;
    period: { name: string; starts_on: string; ends_on: string };
    gross_amount: string;
    deduction_amount: string;
    reward_amount: string;
    net_amount: string;
    attendance_summary: {
        late_minutes_total: number;
        absent_days: number;
        perfect_attendance: boolean;
    };
    lines: Line[];
};

type Payslip = {
    id: number;
    number: string;
    version: number;
    status: string;
    published_at: string | null;
    snapshot: Snapshot;
};

type Props = {
    payslip: Payslip;
};

function formatRupiah(value: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function PenggajianDetail({ payslip }: Props) {
    const { snapshot } = payslip;
    const earnings = snapshot.lines.filter((line) => line.type === 'earning');
    const deductions = snapshot.lines.filter(
        (line) => line.type === 'deduction',
    );

    return (
        <>
            <Head title={`Slip Gaji — ${snapshot.period.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Link href={PenggajianController.index()}>
                    <Button variant="ghost" size="sm" className="w-fit">
                        <ArrowLeft className="size-3.5" />
                        Kembali
                    </Button>
                </Link>

                <div className="mx-auto w-full max-w-2xl rounded-3xl border p-6 sm:p-8">
                    <div className="flex flex-col gap-1 border-b pb-4">
                        <h1 className="text-xl font-bold tracking-tight sm:text-2xl">
                            Slip Gaji — {snapshot.period.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {formatDate(snapshot.period.starts_on)} –{' '}
                            {formatDate(snapshot.period.ends_on)}
                        </p>
                        <p className="font-mono text-xs text-muted-foreground">
                            {payslip.number} · versi {payslip.version}
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-4 border-b py-4 text-sm">
                        <div>
                            <div className="text-xs text-muted-foreground">
                                Nama
                            </div>
                            <div className="font-medium">
                                {snapshot.employee.name}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-muted-foreground">
                                Jabatan
                            </div>
                            <div className="font-medium">
                                {snapshot.position_name}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-muted-foreground">
                                Divisi
                            </div>
                            <div className="font-medium">
                                {snapshot.division_name}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-muted-foreground">
                                Kehadiran
                            </div>
                            <div className="font-medium">
                                {snapshot.attendance_summary.perfect_attendance
                                    ? 'Sempurna'
                                    : `Telat ${snapshot.attendance_summary.late_minutes_total} menit · Alpha ${snapshot.attendance_summary.absent_days} hari`}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4 py-4">
                        <div>
                            <h2 className="mb-2 text-sm font-semibold text-muted-foreground uppercase">
                                Penghasilan
                            </h2>
                            <div className="flex flex-col gap-1.5 text-sm">
                                {earnings.map((line, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center justify-between"
                                    >
                                        <span>{line.component_name}</span>
                                        <span>{formatRupiah(line.amount)}</span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {deductions.length > 0 && (
                            <div>
                                <h2 className="mb-2 text-sm font-semibold text-muted-foreground uppercase">
                                    Potongan
                                </h2>
                                <div className="flex flex-col gap-1.5 text-sm">
                                    {deductions.map((line, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between text-red-600 dark:text-red-400"
                                        >
                                            <span>{line.component_name}</span>
                                            <span>
                                                − {formatRupiah(line.amount)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-between border-t pt-4 text-lg font-bold">
                        <span>Take Home Pay</span>
                        <span>{formatRupiah(snapshot.net_amount)}</span>
                    </div>
                </div>
            </div>
        </>
    );
}

PenggajianDetail.layout = {
    breadcrumbs: [
        {
            title: 'Penggajian',
            href: PenggajianController.index(),
        },
        {
            title: 'Slip Gaji',
            href: '#',
        },
    ],
};
