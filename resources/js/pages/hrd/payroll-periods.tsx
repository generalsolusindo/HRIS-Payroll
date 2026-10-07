import { Form, Head, Link, router } from '@inertiajs/react';
import { CalendarRange, Calculator, Eye, Plus } from 'lucide-react';
import { useState } from 'react';
import PayrollPeriodController from '@/actions/App/Http/Controllers/Hrd/PayrollPeriodController';
import PayrollRunController from '@/actions/App/Http/Controllers/Hrd/PayrollRunController';
import InputError from '@/components/input-error';
import { show as showPayrollRun } from '@/routes/hrd/hasil-payroll';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type LatestRun = {
    id: number;
    version: number;
    status: string;
    employee_count: number;
    net_total: string;
};

type PeriodRow = {
    id: number;
    code: string;
    name: string;
    starts_on: string;
    ends_on: string;
    status: string;
    latest_run: LatestRun | null;
};

type Props = {
    periods: PeriodRow[];
};

const RUN_STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    submitted: 'Diajukan',
    revision_requested: 'Perlu Revisi',
    approved: 'Disetujui',
    locked: 'Terkunci',
    partially_paid: 'Dibayar Sebagian',
    paid: 'Lunas',
    superseded: 'Digantikan',
    cancelled: 'Dibatalkan',
};

function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatRupiah(value: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function PeriodFormFields({
    errors,
}: {
    errors: Record<string, string | undefined>;
}) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="code">Kode Periode</Label>
                <Input id="code" name="code" required placeholder="2026-10" />
                <InputError message={errors.code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nama Periode</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    placeholder="Oktober 2026"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="starts_on">Mulai</Label>
                    <Input
                        id="starts_on"
                        name="starts_on"
                        type="date"
                        required
                    />
                    <InputError message={errors.starts_on} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ends_on">Selesai</Label>
                    <Input id="ends_on" name="ends_on" type="date" required />
                    <InputError message={errors.ends_on} />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="attendance_starts_on">
                        Cutoff Absensi Mulai
                    </Label>
                    <Input
                        id="attendance_starts_on"
                        name="attendance_starts_on"
                        type="date"
                        required
                    />
                    <InputError message={errors.attendance_starts_on} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="attendance_ends_on">
                        Cutoff Absensi Selesai
                    </Label>
                    <Input
                        id="attendance_ends_on"
                        name="attendance_ends_on"
                        type="date"
                        required
                    />
                    <InputError message={errors.attendance_ends_on} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="payment_due_on">
                    Rencana Tanggal Bayar (opsional)
                </Label>
                <Input id="payment_due_on" name="payment_due_on" type="date" />
                <InputError message={errors.payment_due_on} />
            </div>
        </div>
    );
}

export default function PayrollPeriods({ periods }: Props) {
    const [createOpen, setCreateOpen] = useState(false);

    function calculate(periodId: number) {
        if (
            !confirm(
                'Hitung payroll untuk periode ini? Hanya karyawan dengan profil gaji yang akan dihitung.',
            )
        ) {
            return;
        }
        router.post(PayrollRunController.store(periodId).url);
    }

    return (
        <>
            <Head title="Periode Payroll" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Periode Payroll
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Buat periode, lalu hitung payroll dari profil gaji
                            yang sudah diatur.
                        </p>
                    </div>

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <Button
                            type="button"
                            className="rounded-full"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-4" />
                            Tambah Periode
                        </Button>
                        <DialogContent className="max-h-[90vh] overflow-y-auto">
                            <Form
                                {...PayrollPeriodController.store.form()}
                                resetOnSuccess
                                disableWhileProcessing
                                onSuccess={() => setCreateOpen(false)}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Tambah Periode Payroll
                                            </DialogTitle>
                                            <DialogDescription>
                                                Kode harus unik dalam satu
                                                instansi.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="py-2">
                                            <PeriodFormFields errors={errors} />
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Simpan
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                {periods.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <CalendarRange className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada periode payroll.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Periode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Rentang Tanggal
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Hasil Perhitungan
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {periods.map((period) => (
                                    <tr key={period.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {period.name}
                                            </div>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {period.code}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            {formatDate(period.starts_on)} –{' '}
                                            {formatDate(period.ends_on)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {period.latest_run ? (
                                                <div>
                                                    <span className="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                                        {RUN_STATUS_LABELS[
                                                            period.latest_run
                                                                .status
                                                        ] ??
                                                            period.latest_run
                                                                .status}
                                                    </span>
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {
                                                            period.latest_run
                                                                .employee_count
                                                        }{' '}
                                                        karyawan ·{' '}
                                                        {formatRupiah(
                                                            period.latest_run
                                                                .net_total,
                                                        )}
                                                    </div>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    Belum dihitung
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                {period.latest_run && (
                                                    <Link
                                                        href={showPayrollRun(
                                                            period.latest_run
                                                                .id,
                                                        )}
                                                    >
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="rounded-full"
                                                        >
                                                            <Eye className="size-3.5" />
                                                            Lihat Hasil
                                                        </Button>
                                                    </Link>
                                                )}
                                                {(!period.latest_run ||
                                                    period.latest_run.status ===
                                                        'revision_requested') && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        className="rounded-full"
                                                        onClick={() =>
                                                            calculate(period.id)
                                                        }
                                                    >
                                                        <Calculator className="size-3.5" />
                                                        {period.latest_run
                                                            ? 'Hitung Ulang'
                                                            : 'Hitung Payroll'}
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

PayrollPeriods.layout = {
    breadcrumbs: [
        {
            title: 'Periode Payroll',
            href: PayrollPeriodController.index(),
        },
    ],
};
