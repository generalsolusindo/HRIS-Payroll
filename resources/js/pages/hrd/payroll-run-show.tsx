import { Form, Head, router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, History } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';
import PayrollPeriodController from '@/actions/App/Http/Controllers/Hrd/PayrollPeriodController';
import PayrollRunController from '@/actions/App/Http/Controllers/Hrd/PayrollRunController';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types';

type Line = {
    component_name: string;
    type: string;
    amount: string;
};

type Entry = {
    id: number;
    user_id: number;
    name: string;
    division_name: string;
    position_name: string;
    gross_amount: string;
    deduction_amount: string;
    reward_amount: string;
    net_amount: string;
    unresolved_days: number;
    lines: Line[];
};

type Run = {
    id: number;
    version: number;
    status: string;
    employee_count: number;
    gross_total: string;
    deduction_total: string;
    reward_total: string;
    net_total: string;
    revision_reason: string | null;
    period: {
        id: number;
        name: string;
        company_name: string;
        starts_on: string;
        ends_on: string;
    };
};

type Approval = {
    id: number;
    action: string;
    actor_name: string;
    note: string | null;
    created_at: string;
};

type Props = {
    run: Run;
    entries: Entry[];
    approvals: Approval[];
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

const APPROVAL_ACTION_LABELS: Record<string, string> = {
    submitted: 'Diajukan ke Manager',
    revision_requested: 'Manager meminta revisi',
    approved: 'Disetujui Manager',
    locked: 'Dikunci',
    reopen_requested: 'Permintaan buka kembali',
    reopen_approved: 'Buka kembali disetujui',
    reopen_rejected: 'Buka kembali ditolak',
    superseded: 'Digantikan versi baru',
    cancelled: 'Dibatalkan',
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

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function PayrollRunShow({ run, entries, approvals }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isHrd = auth.user.role === 'hrd';
    const [recalculating, setRecalculating] = useState(false);

    function recalculate() {
        if (
            recalculating ||
            !confirm(
                'Hitung ulang payroll periode ini sebagai versi baru? Versi saat ini akan digantikan.',
            )
        ) {
            return;
        }

        setRecalculating(true);
        router.post(PayrollRunController.store(run.period.id).url, undefined, {
            onError: () => {
                toast.error('Gagal menghitung ulang payroll.');
                setRecalculating(false);
            },
        });
    }
    const [expandedDivision, setExpandedDivision] = useState<string | null>(
        null,
    );
    const [expandedEntry, setExpandedEntry] = useState<number | null>(null);

    const byDivision = useMemo(() => {
        const groups = new Map<
            string,
            { name: string; count: number; net: number }
        >();
        for (const entry of entries) {
            const existing = groups.get(entry.division_name) ?? {
                name: entry.division_name,
                count: 0,
                net: 0,
            };
            existing.count += 1;
            existing.net += Number(entry.net_amount);
            groups.set(entry.division_name, existing);
        }
        return Array.from(groups.values()).sort((a, b) =>
            a.name.localeCompare(b.name),
        );
    }, [entries]);

    const visibleEntries = expandedDivision
        ? entries.filter((entry) => entry.division_name === expandedDivision)
        : entries;

    const unresolvedDaysTotal = useMemo(
        () => entries.reduce((sum, entry) => sum + entry.unresolved_days, 0),
        [entries],
    );

    return (
        <>
            <Head title={`Hasil Payroll — ${run.period.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            {run.period.name}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {run.period.company_name} ·{' '}
                            {formatDate(run.period.starts_on)} –{' '}
                            {formatDate(run.period.ends_on)} · Versi{' '}
                            {run.version} ·{' '}
                            <span className="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium">
                                {RUN_STATUS_LABELS[run.status] ?? run.status}
                            </span>
                        </p>
                    </div>

                    {isHrd && run.status === 'draft' && (
                        <Form
                            {...PayrollRunController.submit.form(run.id)}
                            disableWhileProcessing
                            onSuccess={() =>
                                toast.success(
                                    'Hasil payroll diajukan untuk persetujuan Manager.',
                                )
                            }
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    className="rounded-full"
                                    disabled={processing}
                                >
                                    Ajukan Persetujuan
                                </Button>
                            )}
                        </Form>
                    )}

                    {isHrd && run.status === 'revision_requested' && (
                        <Button
                            type="button"
                            className="rounded-full"
                            disabled={recalculating}
                            onClick={recalculate}
                        >
                            Hitung Ulang
                        </Button>
                    )}
                </div>

                {run.status === 'revision_requested' && run.revision_reason && (
                    <div className="rounded-2xl border border-red-400/50 bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-500/10 dark:text-red-300">
                        <span className="font-medium">
                            Manager meminta revisi:
                        </span>{' '}
                        {run.revision_reason}
                    </div>
                )}

                {/* Level 1 — total perusahaan */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div className="rounded-3xl bg-brand-navy/8 p-5 dark:border dark:border-brand-navy-foreground/10 dark:bg-brand-navy/35">
                        <div className="text-sm text-muted-foreground">
                            Total Karyawan
                        </div>
                        <div className="mt-1 text-3xl font-bold tracking-tight">
                            {run.employee_count}
                        </div>
                    </div>
                    <div className="rounded-3xl bg-brand-yellow/20 p-5 dark:bg-brand-yellow/12">
                        <div className="text-sm text-muted-foreground">
                            Total Gross
                        </div>
                        <div className="mt-1 text-xl font-bold tracking-tight sm:text-2xl">
                            {formatRupiah(run.gross_total)}
                        </div>
                    </div>
                    <div className="rounded-3xl bg-muted/60 p-5">
                        <div className="text-sm text-muted-foreground">
                            Total Net (Perusahaan)
                        </div>
                        <div className="mt-1 text-xl font-bold tracking-tight sm:text-2xl">
                            {formatRupiah(run.net_total)}
                        </div>
                    </div>
                    <div className="rounded-3xl bg-red-500/10 p-5">
                        <div className="text-sm text-muted-foreground">
                            Total Potongan Absensi
                        </div>
                        <div className="mt-1 text-xl font-bold tracking-tight text-red-600 sm:text-2xl dark:text-red-400">
                            {formatRupiah(run.deduction_total)}
                        </div>
                    </div>
                    <div className="rounded-3xl bg-emerald-500/10 p-5">
                        <div className="text-sm text-muted-foreground">
                            Total Reward Absensi
                        </div>
                        <div className="mt-1 text-xl font-bold tracking-tight text-emerald-600 sm:text-2xl dark:text-emerald-400">
                            {formatRupiah(run.reward_total)}
                        </div>
                    </div>
                </div>

                {unresolvedDaysTotal > 0 && (
                    <div className="rounded-2xl border border-amber-400/50 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                        {unresolvedDaysTotal} hari absensi di periode ini masih
                        "Perlu Konfirmasi" di Koreksi Absensi — belum dihitung
                        sebagai potongan maupun reward.
                    </div>
                )}

                {/* Level 2 — per divisi */}
                <div className="rounded-2xl border p-4">
                    <h2 className="font-semibold">Per Divisi</h2>
                    <div className="mt-3 flex flex-col gap-2">
                        {byDivision.map((division) => (
                            <button
                                key={division.name}
                                type="button"
                                onClick={() =>
                                    setExpandedDivision((current) =>
                                        current === division.name
                                            ? null
                                            : division.name,
                                    )
                                }
                                className={`flex items-center justify-between rounded-xl border px-4 py-3 text-left transition hover:bg-muted/40 ${
                                    expandedDivision === division.name
                                        ? 'border-brand-navy bg-muted/40'
                                        : ''
                                }`}
                            >
                                <span className="flex items-center gap-2">
                                    {expandedDivision === division.name ? (
                                        <ChevronDown className="size-4 text-muted-foreground" />
                                    ) : (
                                        <ChevronRight className="size-4 text-muted-foreground" />
                                    )}
                                    <span className="font-medium">
                                        {division.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        ({division.count} karyawan)
                                    </span>
                                </span>
                                <span className="font-semibold">
                                    {formatRupiah(String(division.net))}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Level 3 — per karyawan, filtered by the division clicked above */}
                <div className="overflow-x-auto rounded-2xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Karyawan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Divisi
                                </th>
                                <th className="px-4 py-3 font-medium">Gross</th>
                                <th className="px-4 py-3 font-medium">
                                    Potongan
                                </th>
                                <th className="px-4 py-3 font-medium">Net</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {visibleEntries.map((entry) => (
                                <Fragment key={entry.id}>
                                    <tr
                                        className="cursor-pointer hover:bg-muted/30"
                                        onClick={() =>
                                            setExpandedEntry((current) =>
                                                current === entry.id
                                                    ? null
                                                    : entry.id,
                                            )
                                        }
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                {expandedEntry === entry.id ? (
                                                    <ChevronDown className="size-4 text-muted-foreground" />
                                                ) : (
                                                    <ChevronRight className="size-4 text-muted-foreground" />
                                                )}
                                                <div>
                                                    <div className="font-medium">
                                                        {entry.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {entry.position_name}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {entry.division_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRupiah(entry.gross_amount)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRupiah(
                                                entry.deduction_amount,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {formatRupiah(entry.net_amount)}
                                        </td>
                                    </tr>
                                    {expandedEntry === entry.id && (
                                        <tr key={`${entry.id}-lines`}>
                                            <td
                                                colSpan={5}
                                                className="bg-muted/20 px-4 py-3"
                                            >
                                                <div className="flex flex-col gap-1 text-xs">
                                                    {entry.lines.map(
                                                        (line, index) => (
                                                            <div
                                                                key={index}
                                                                className="flex items-center justify-between"
                                                            >
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        line.component_name
                                                                    }
                                                                </span>
                                                                <span>
                                                                    {line.type ===
                                                                    'deduction'
                                                                        ? '− '
                                                                        : ''}
                                                                    {formatRupiah(
                                                                        line.amount,
                                                                    )}
                                                                </span>
                                                            </div>
                                                        ),
                                                    )}
                                                    {entry.unresolved_days >
                                                        0 && (
                                                        <div className="mt-1 text-amber-600 dark:text-amber-400">
                                                            {
                                                                entry.unresolved_days
                                                            }{' '}
                                                            hari belum ditinjau
                                                            di Koreksi Absensi.
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>

                {approvals.length > 0 && (
                    <div className="rounded-2xl border p-4">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <History className="size-4 text-muted-foreground" />
                            Riwayat Persetujuan
                        </h2>
                        <div className="mt-3 flex flex-col gap-3">
                            {approvals.map((approval) => (
                                <div
                                    key={approval.id}
                                    className="flex flex-col gap-0.5 border-l-2 border-muted pl-3 text-sm"
                                >
                                    <div className="font-medium">
                                        {APPROVAL_ACTION_LABELS[
                                            approval.action
                                        ] ?? approval.action}{' '}
                                        — {approval.actor_name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatDateTime(approval.created_at)}
                                    </div>
                                    {approval.note && (
                                        <p className="mt-0.5 text-muted-foreground">
                                            "{approval.note}"
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

PayrollRunShow.layout = {
    breadcrumbs: [
        {
            title: 'Periode Payroll',
            href: PayrollPeriodController.index(),
        },
        {
            title: 'Hasil Payroll',
            href: '#',
        },
    ],
};
