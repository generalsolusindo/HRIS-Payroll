import { Form, Head, router } from '@inertiajs/react';
import { History, Search, Users } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import PayrollRunApprovalController from '@/actions/App/Http/Controllers/Manager/PayrollRunApprovalController';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Line = {
    id: number;
    component_name: string;
    type: string;
    amount: string;
    source: string;
};
type Entry = {
    id: number;
    name: string;
    division_id: number;
    division_name: string;
    position_name: string;
    gross_amount: string;
    deduction_amount: string;
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
        attendance_starts_on: string;
        attendance_ends_on: string;
    };
};
type Props = {
    run: Run;
    entries: Entry[];
    divisions: { id: number; name: string; count: number; net_total: string }[];
    readiness_issues: string[];
    capabilities: {
        approve: boolean;
        requestRevision: boolean;
    };
    approvals: {
        id: number;
        action: string;
        actor_name: string;
        note: string | null;
        created_at: string;
    }[];
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
    shared_for_review: 'Dibagikan untuk ditinjau karyawan',
    submitted: 'Diajukan ke Manager',
    revision_requested: 'Manager meminta revisi',
    approved: 'Disetujui Manager',
    locked: 'Dikunci',
    superseded: 'Digantikan versi baru',
    cancelled: 'Dibatalkan',
};

const SOURCE_LABELS: Record<string, string> = {
    salary: 'Master',
    attendance: 'Absensi',
    overtime: 'Lembur',
    reward: 'Reward',
    allowance: 'Tunjangan',
    manual: 'Manual',
    adjustment: 'Penyesuaian',
};

const money = (value: string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value));
const date = (value: string) =>
    new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

function LineGroup({ title, lines }: { title: string; lines: Line[] }) {
    return (
        <section className="space-y-3">
            <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                {title}
            </h3>
            {lines.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Tidak ada komponen.
                </p>
            ) : (
                <div className="divide-y">
                    {lines.map((line) => (
                        <div
                            key={line.id}
                            className="flex items-start justify-between gap-3 py-3"
                        >
                            <div className="min-w-0 space-y-1">
                                <span className="inline-flex rounded bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                    {SOURCE_LABELS[line.source] ??
                                        line.source}
                                </span>
                                <p className="text-sm font-medium break-words">
                                    {line.component_name}
                                </p>
                            </div>
                            <span className="shrink-0 pt-1 text-sm font-medium tabular-nums">
                                {money(line.amount)}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

export default function PayrollRunShow({
    run,
    entries,
    readiness_issues,
    capabilities,
    approvals,
}: Props) {
    const [search, setSearch] = useState('');
    const [selectedId, setSelectedId] = useState<number | null>(
        entries[0]?.id ?? null,
    );
    const [revisionOpen, setRevisionOpen] = useState(false);
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);

    const visible = entries.filter((entry) =>
        `${entry.name} ${entry.position_name} ${entry.division_name}`
            .toLocaleLowerCase('id-ID')
            .includes(search.trim().toLocaleLowerCase('id-ID')),
    );
    const selected =
        visible.find((entry) => entry.id === selectedId) ?? visible[0];

    function handleRequestRevision() {
        if (!note.trim() || submitting) return;
        setSubmitting(true);
        router.patch(
            PayrollRunApprovalController.requestRevision.url(run.id),
            { note },
            {
                onSuccess: () => {
                    toast.success('Permintaan revisi dikirim ke HRD.');
                    setNote('');
                    setRevisionOpen(false);
                },
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'Gagal mengirim permintaan revisi.',
                    ),
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <>
            <Head title={`Persetujuan Payroll — ${run.period.name}`} />
            <div className="flex min-w-0 flex-1 flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <p className="text-sm text-muted-foreground">
                            {run.period.company_name} · {run.period.name}
                        </p>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight">
                            Persetujuan Payroll
                        </h1>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Absensi {date(run.period.attendance_starts_on)} –{' '}
                            {date(run.period.attendance_ends_on)} · Versi{' '}
                            {run.version}
                        </p>
                        <span className="mt-2 inline-flex rounded-full border bg-card px-2.5 py-1 text-xs font-medium">
                            {RUN_STATUS_LABELS[run.status] ?? run.status}
                        </span>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {capabilities.approve && (
                            <Form
                                {...PayrollRunApprovalController.approve.form(
                                    run.id,
                                )}
                                disableWhileProcessing
                                onError={(errors) =>
                                    toast.error(
                                        Object.values(errors)[0] ??
                                            'Persetujuan gagal. Periksa rincian payroll.',
                                    )
                                }
                                onSuccess={() =>
                                    toast.success('Hasil payroll disetujui.')
                                }
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Menyetujui…'
                                            : 'Setujui'}
                                    </Button>
                                )}
                            </Form>
                        )}
                        {capabilities.requestRevision && (
                            <Dialog
                                open={revisionOpen}
                                onOpenChange={setRevisionOpen}
                            >
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() => setRevisionOpen(true)}
                                >
                                    Minta Revisi
                                </Button>
                                <DialogContent className="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Minta Revisi
                                        </DialogTitle>
                                        <DialogDescription>
                                            Kembalikan hasil payroll{' '}
                                            {run.period.name} ke HRD. Catatan
                                            wajib diisi sebagai alasan revisi.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="revision-note">
                                            Catatan
                                        </Label>
                                        <Textarea
                                            id="revision-note"
                                            value={note}
                                            onChange={(e) =>
                                                setNote(e.target.value)
                                            }
                                            placeholder="Jelaskan apa yang perlu diperbaiki"
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            variant="destructive"
                                            disabled={
                                                !note.trim() || submitting
                                            }
                                            onClick={handleRequestRevision}
                                        >
                                            Kirim Permintaan Revisi
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                {run.revision_reason && run.status === 'revision_requested' && (
                    <p role="status" className="rounded-xl border bg-card p-4 text-sm">
                        <strong>Permintaan revisi:</strong>{' '}
                        {run.revision_reason}
                    </p>
                )}

                {readiness_issues.length > 0 && (
                    <section
                        role="status"
                        className="rounded-xl border border-brand-yellow/50 bg-brand-yellow/10 p-4 text-sm"
                    >
                        <h2 className="font-semibold">
                            Perlu diperhatikan sebelum menyetujui
                        </h2>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            {readiness_issues.map((issue) => (
                                <li key={issue}>{issue}</li>
                            ))}
                        </ul>
                    </section>
                )}

                <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    {[
                        ['Karyawan', String(run.employee_count)],
                        ['Total bruto', money(run.gross_total)],
                        ['Total potongan', money(run.deduction_total)],
                        ['Total gaji bersih', money(run.net_total)],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="min-w-0 rounded-2xl border bg-card p-4"
                        >
                            <p className="text-xs text-muted-foreground">
                                {label}
                            </p>
                            <p className="mt-2 text-lg font-semibold break-words tabular-nums">
                                {value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                    <section
                        aria-label="Daftar karyawan payroll"
                        className="min-w-0 overflow-hidden rounded-2xl border bg-card"
                    >
                        <div className="space-y-3 border-b p-4">
                            <h2 className="font-semibold">
                                Karyawan{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    ({visible.length} dari {entries.length})
                                </span>
                            </h2>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" />
                                <Input
                                    ref={searchRef}
                                    aria-label="Cari nama, jabatan atau divisi"
                                    placeholder="Cari nama, jabatan atau divisi…"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="pl-9"
                                />
                            </div>
                        </div>
                        {visible.length === 0 ? (
                            <div className="space-y-3 p-8 text-center text-sm text-muted-foreground">
                                <Users className="mx-auto size-8" />
                                <p>
                                    {entries.length === 0
                                        ? 'Belum ada karyawan dalam hasil payroll ini.'
                                        : 'Tidak ada karyawan yang sesuai pencarian.'}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <caption className="sr-only">
                                        Pilih nama karyawan untuk membuka
                                        rincian gaji
                                    </caption>
                                    <thead className="border-b bg-muted/40 text-xs text-muted-foreground">
                                        <tr>
                                            <th className="p-3 text-left">
                                                Karyawan
                                            </th>
                                            <th className="p-3 text-right">
                                                Bruto
                                            </th>
                                            <th className="p-3 text-right">
                                                Potongan
                                            </th>
                                            <th className="p-3 text-right">
                                                Gaji bersih
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {visible.map((entry) => (
                                            <tr
                                                key={entry.id}
                                                className={cn(
                                                    'hover:bg-muted/40',
                                                    selected?.id ===
                                                        entry.id &&
                                                        'bg-primary/5',
                                                )}
                                            >
                                                <td className="min-w-40 p-3">
                                                    <button
                                                        type="button"
                                                        aria-pressed={
                                                            selected?.id ===
                                                            entry.id
                                                        }
                                                        onClick={() =>
                                                            setSelectedId(
                                                                entry.id,
                                                            )
                                                        }
                                                        className="w-full rounded text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    >
                                                        <span className="block font-semibold text-primary">
                                                            {entry.name}
                                                        </span>
                                                        <span className="mt-1 block text-xs text-muted-foreground">
                                                            {
                                                                entry.position_name
                                                            }{' '}
                                                            ·{' '}
                                                            {
                                                                entry.division_name
                                                            }
                                                        </span>
                                                        {entry.unresolved_days >
                                                            0 && (
                                                            <span className="mt-2 inline-flex rounded bg-muted px-2 py-1 text-[11px]">
                                                                {
                                                                    entry.unresolved_days
                                                                }{' '}
                                                                hari perlu
                                                                tinjauan
                                                            </span>
                                                        )}
                                                    </button>
                                                </td>
                                                <td className="p-3 text-right whitespace-nowrap tabular-nums">
                                                    {money(
                                                        entry.gross_amount,
                                                    )}
                                                </td>
                                                <td className="p-3 text-right whitespace-nowrap tabular-nums">
                                                    {money(
                                                        entry.deduction_amount,
                                                    )}
                                                </td>
                                                <td className="p-3 text-right font-semibold whitespace-nowrap tabular-nums">
                                                    {money(entry.net_amount)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section
                        aria-label="Rincian gaji terpilih"
                        className="min-w-0 rounded-2xl border bg-card p-4 sm:p-5"
                    >
                        {selected ? (
                            <>
                                <p className="text-xs text-muted-foreground">
                                    Rincian gaji
                                </p>
                                <h2 className="mt-1 text-lg font-semibold break-words">
                                    {selected.name}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {selected.position_name} ·{' '}
                                    {selected.division_name}
                                </p>
                                <div className="mt-5 space-y-5">
                                    <LineGroup
                                        title="Pendapatan"
                                        lines={selected.lines.filter(
                                            (line) =>
                                                line.type === 'earning',
                                        )}
                                    />
                                    <div className="flex justify-between gap-3 border-t pt-3 text-sm font-semibold">
                                        <span>Total bruto</span>
                                        <span className="tabular-nums">
                                            {money(selected.gross_amount)}
                                        </span>
                                    </div>
                                    <LineGroup
                                        title="Potongan"
                                        lines={selected.lines.filter(
                                            (line) =>
                                                line.type === 'deduction',
                                        )}
                                    />
                                    <div className="flex justify-between gap-3 border-t pt-3 text-sm font-semibold">
                                        <span>Total potongan</span>
                                        <span className="tabular-nums">
                                            {money(
                                                selected.deduction_amount,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-primary p-4 text-primary-foreground">
                                        <span className="text-sm font-medium">
                                            Gaji bersih (THP)
                                        </span>
                                        <strong className="text-lg tabular-nums">
                                            {money(selected.net_amount)}
                                        </strong>
                                    </div>
                                </div>
                                {selected.unresolved_days > 0 && (
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        {selected.unresolved_days} hari
                                        absensi masih perlu koreksi HRD.
                                    </p>
                                )}
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Pilih karyawan untuk melihat rincian gajinya.
                            </p>
                        )}
                    </section>
                </div>

                {approvals.length > 0 && (
                    <details className="rounded-2xl border bg-card p-4">
                        <summary className="cursor-pointer text-sm font-semibold">
                            <History className="mr-2 inline size-4" />
                            Riwayat persetujuan ({approvals.length})
                        </summary>
                        <ol className="mt-4 space-y-4">
                            {approvals.map((item) => (
                                <li
                                    key={item.id}
                                    className="border-l-2 pl-3 text-sm"
                                >
                                    <p className="font-medium">
                                        {APPROVAL_ACTION_LABELS[
                                            item.action
                                        ] ?? item.action}{' '}
                                        — {item.actor_name}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {new Date(
                                            item.created_at,
                                        ).toLocaleString('id-ID', {
                                            timeZone: 'Asia/Jakarta',
                                        })}{' '}
                                        WIB
                                    </p>
                                    {item.note && (
                                        <p className="mt-1 break-words text-muted-foreground">
                                            {item.note}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </details>
                )}
            </div>
        </>
    );
}

PayrollRunShow.layout = {
    breadcrumbs: [
        {
            title: 'Persetujuan Payroll',
            href: PayrollRunApprovalController.index(),
        },
        {
            title: 'Detail',
            href: '#',
        },
    ],
};
