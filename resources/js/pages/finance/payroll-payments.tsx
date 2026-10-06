import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Lock, Wallet } from 'lucide-react';
import PayrollPaymentController from '@/actions/App/Http/Controllers/Finance/PayrollPaymentController';
import { Button } from '@/components/ui/button';

type RunRow = {
    id: number;
    version: number;
    status: string;
    employee_count: number;
    net_total: string;
    paid_total: string;
    period: {
        name: string;
        company_name: string;
        starts_on: string;
        ends_on: string;
    };
};

type Props = {
    runs: RunRow[];
};

const RUN_STATUS_LABELS: Record<string, string> = {
    approved: 'Disetujui',
    locked: 'Terkunci',
    partially_paid: 'Dibayar Sebagian',
    paid: 'Lunas',
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
        month: 'short',
        year: 'numeric',
    });
}

export default function PayrollPayments({ runs }: Props) {
    return (
        <>
            <Head title="Pembayaran Payroll" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Pembayaran Payroll
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Hasil payroll yang sudah disetujui Manager, menunggu
                        dikunci dan dibayarkan.
                    </p>
                </div>

                {runs.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Wallet className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada hasil payroll yang disetujui.
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
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Net
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Terbayar
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {runs.map((run) => (
                                    <tr key={run.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {run.period.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {run.period.company_name} ·{' '}
                                                {formatDate(
                                                    run.period.starts_on,
                                                )}{' '}
                                                –{' '}
                                                {formatDate(run.period.ends_on)}{' '}
                                                · Versi {run.version}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                                {RUN_STATUS_LABELS[
                                                    run.status
                                                ] ?? run.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRupiah(run.net_total)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRupiah(run.paid_total)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                {run.status === 'approved' && (
                                                    <Form
                                                        {...PayrollPaymentController.lock.form(
                                                            run.id,
                                                        )}
                                                        disableWhileProcessing
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                className="rounded-full"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <Lock className="size-3.5" />
                                                                Kunci
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                                {run.status !== 'approved' && (
                                                    <Link
                                                        href={PayrollPaymentController.show(
                                                            run.id,
                                                        )}
                                                    >
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="rounded-full"
                                                        >
                                                            <Eye className="size-3.5" />
                                                            Kelola
                                                        </Button>
                                                    </Link>
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

PayrollPayments.layout = {
    breadcrumbs: [
        {
            title: 'Pembayaran Payroll',
            href: PayrollPaymentController.index(),
        },
    ],
};
