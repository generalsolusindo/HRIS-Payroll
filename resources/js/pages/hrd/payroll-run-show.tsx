import { Head } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import PayrollPeriodController from '@/actions/App/Http/Controllers/Hrd/PayrollPeriodController';

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
    net_amount: string;
    lines: Line[];
};

type Run = {
    id: number;
    version: number;
    status: string;
    employee_count: number;
    gross_total: string;
    net_total: string;
    period: {
        name: string;
        company_name: string;
        starts_on: string;
        ends_on: string;
    };
};

type Props = {
    run: Run;
    entries: Entry[];
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

export default function PayrollRunShow({ run, entries }: Props) {
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

    return (
        <>
            <Head title={`Hasil Payroll — ${run.period.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        {run.period.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {run.period.company_name} ·{' '}
                        {formatDate(run.period.starts_on)} –{' '}
                        {formatDate(run.period.ends_on)} · Versi {run.version} ·{' '}
                        <span className="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium">
                            {RUN_STATUS_LABELS[run.status] ?? run.status}
                        </span>
                    </p>
                </div>

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
                </div>

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
                                <>
                                    <tr
                                        key={entry.id}
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
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </>
                            ))}
                        </tbody>
                    </table>
                </div>
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
