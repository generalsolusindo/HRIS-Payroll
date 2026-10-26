import { Head, Link, router } from '@inertiajs/react';
import { History } from 'lucide-react';
import PayrollRunApprovalController from '@/actions/App/Http/Controllers/Manager/PayrollRunApprovalController';
import { show as showPayrollRun } from '@/routes/manager/payroll';
import { Button } from '@/components/ui/button';

type RunRow = {
    id: number;
    version: number;
    status: string;
    employee_count: number;
    net_total: string;
    period: {
        name: string;
        company_name: string;
        starts_on: string;
        ends_on: string;
    };
};

type PaginatedRuns = {
    data: RunRow[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    runs: PaginatedRuns;
};

const RUN_STATUS_LABELS: Record<string, string> = {
    submitted: 'Diajukan',
    revision_requested: 'Perlu Revisi',
    approved: 'Disetujui',
    locked: 'Terkunci',
    partially_paid: 'Dibayar Sebagian',
    paid: 'Lunas',
    superseded: 'Digantikan',
    cancelled: 'Dibatalkan',
};

const RUN_STATUS_BADGE_CLASS: Record<string, string> = {
    submitted:
        'border-amber-400 text-amber-600 dark:border-amber-400/70 dark:text-amber-400',
    revision_requested:
        'border-red-400 text-red-600 dark:border-red-400/70 dark:text-red-400',
    approved:
        'border-blue-400 text-blue-600 dark:border-blue-400/70 dark:text-blue-400',
    locked:
        'border-blue-400 text-blue-600 dark:border-blue-400/70 dark:text-blue-400',
    partially_paid:
        'border-amber-400 text-amber-600 dark:border-amber-400/70 dark:text-amber-400',
    paid: 'border-emerald-400 text-emerald-600 dark:border-emerald-400/70 dark:text-emerald-400',
    superseded: 'border-muted-foreground/40 text-muted-foreground',
    cancelled: 'border-muted-foreground/40 text-muted-foreground',
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

export default function PayrollRunHistory({ runs }: Props) {
    function goToPage(url: string | null) {
        if (!url) {
            return;
        }
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Riwayat Payroll" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Riwayat Payroll
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Semua hasil payroll yang pernah diajukan ke Anda,
                        termasuk yang sudah disetujui — cek di sini kalau
                        mau tahu apakah Finance sudah membayar semuanya.
                    </p>
                </div>

                {runs.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <History className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada riwayat payroll.
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
                                        Instansi
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Karyawan
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Gaji Bersih
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {runs.data.map((run) => (
                                    <tr key={run.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {run.period.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {formatDate(
                                                    run.period.starts_on,
                                                )}{' '}
                                                –{' '}
                                                {formatDate(
                                                    run.period.ends_on,
                                                )}{' '}
                                                · v{run.version}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {run.period.company_name}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {run.employee_count}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatRupiah(run.net_total)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ${RUN_STATUS_BADGE_CLASS[run.status] ?? ''}`}
                                            >
                                                {RUN_STATUS_LABELS[
                                                    run.status
                                                ] ?? run.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={showPayrollRun(run.id)}
                                            >
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="rounded-full"
                                                >
                                                    Lihat Detail
                                                </Button>
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {runs.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Halaman {runs.current_page} dari {runs.last_page}{' '}
                            · {runs.total} hasil payroll
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!runs.prev_page_url}
                                onClick={() => goToPage(runs.prev_page_url)}
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!runs.next_page_url}
                                onClick={() => goToPage(runs.next_page_url)}
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

PayrollRunHistory.layout = {
    breadcrumbs: [
        {
            title: 'Riwayat Payroll',
            href: PayrollRunApprovalController.riwayat(),
        },
    ],
};
