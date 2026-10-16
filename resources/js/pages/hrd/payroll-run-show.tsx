import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, History, Search, Users } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import PayrollPayslipController from '@/actions/App/Http/Controllers/Hrd/PayrollPayslipController';
import PayrollRunController from '@/actions/App/Http/Controllers/Hrd/PayrollRunController';
import PenggajianController from '@/actions/App/Http/Controllers/PenggajianController';
import { PayrollAdjustments } from '@/components/payroll-adjustments';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
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
    quantity: string;
    rate: string;
    adjustment_reason: string | null;
    cancelled: boolean;
    cancellation_reason: string | null;
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
    can_publish_slip: boolean;
    latest_payslip: {
        id: number;
        number: string;
        version: number;
        published_at: string | null;
    } | null;
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
    shared_at: string | null;
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
        submit: boolean;
        recalculate: boolean;
        adjust: boolean;
        refresh: boolean;
        cancel: boolean;
        share: boolean;
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
    reopen_requested: 'Permintaan buka kembali',
    reopen_approved: 'Buka kembali disetujui',
    reopen_rejected: 'Buka kembali ditolak',
    superseded: 'Digantikan versi baru',
    cancelled: 'Dibatalkan',
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
const sources: Record<string, string> = {
    salary: 'Master',
    attendance: 'Absensi',
    overtime: 'Lembur',
    reward: 'Reward',
    allowance: 'Tunjangan',
    manual: 'Manual',
    adjustment: 'Penyesuaian',
};

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
                                    {sources[line.source] ?? line.source}
                                </span>
                                <p className="text-sm font-medium break-words">
                                    {line.component_name}
                                    {line.cancelled && ' (dibatalkan)'}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {new Intl.NumberFormat('id-ID', {
                                        maximumFractionDigits: 4,
                                    }).format(Number(line.quantity))}{' '}
                                    × {money(line.rate)}
                                </p>
                                {line.cancellation_reason && (
                                    <p className="text-xs text-muted-foreground">
                                        Alasan pembatalan:{' '}
                                        {line.cancellation_reason}
                                    </p>
                                )}
                                {line.adjustment_reason && (
                                    <p className="text-xs break-words text-muted-foreground">
                                        {line.adjustment_reason}
                                    </p>
                                )}
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
    divisions,
    capabilities,
    readiness_issues,
    approvals,
}: Props) {
    const [search, setSearch] = useState('');
    const [division, setDivision] = useState('all');
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [mobileDetail, setMobileDetail] = useState(false);
    const [recalculating, setRecalculating] = useState(false);
    const [sharing, setSharing] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const detailRef = useRef<HTMLHeadingElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const visible = entries.filter(
        (entry) =>
            (division === 'all' || String(entry.division_id) === division) &&
            `${entry.name} ${entry.position_name} ${entry.division_name}`
                .toLocaleLowerCase('id-ID')
                .includes(search.trim().toLocaleLowerCase('id-ID')),
    );
    const selected =
        visible.find((entry) => entry.id === selectedId) ?? visible[0];
    const unresolved = entries.reduce(
        (sum, entry) => sum + entry.unresolved_days,
        0,
    );

    function recalculate() {
        if (
            recalculating ||
            !confirm(
                'Hitung ulang sebagai versi baru dengan data terbaru? Versi sebelumnya tetap tersimpan dalam histori.',
            )
        )
            return;
        setRecalculating(true);
        router.post(
            (run.status === 'draft'
                ? PayrollRunController.refresh(run.period.id)
                : PayrollRunController.store(run.period.id)
            ).url,
            {},
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'Gagal menghitung ulang payroll.',
                    ),
                onFinish: () => setRecalculating(false),
            },
        );
    }

    function shareForReview() {
        if (
            sharing ||
            !confirm(
                'Bagikan draft ini ke karyawan untuk ditinjau sebelum diajukan ke Manager? Mereka akan bisa melihat rincian gajinya sendiri.',
            )
        )
            return;
        setSharing(true);
        router.patch(
            PayrollRunController.share(run.id).url,
            {},
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'Gagal membagikan draft.',
                    ),
                onFinish: () => setSharing(false),
            },
        );
    }

    function selectEntry(id: number) {
        setSelectedId(id);
        setMobileDetail(true);
        requestAnimationFrame(() => detailRef.current?.focus());
    }

    return (
        <>
            <Head title={`Proses Gaji — ${run.period.name}`} />
            <div className="flex min-w-0 flex-1 flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <p className="text-sm text-muted-foreground">
                            {run.period.company_name} · {run.period.name}
                        </p>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight">
                            Proses Gaji
                        </h1>
                        <p className="mt-2 text-xs text-muted-foreground">
                            Absensi {date(run.period.attendance_starts_on)} –{' '}
                            {date(run.period.attendance_ends_on)} · Versi{' '}
                            {run.version}
                        </p>
                        <span className="mt-2 inline-flex rounded-full border bg-card px-2.5 py-1 text-xs font-medium">
                            {RUN_STATUS_LABELS[run.status] ?? run.status}
                        </span>
                        {run.shared_at && (
                            <span className="mt-2 ml-2 inline-flex rounded-full border border-blue-400 px-2.5 py-1 text-xs font-medium text-blue-600 dark:border-blue-400/70 dark:text-blue-400">
                                Dibagikan ke karyawan ·{' '}
                                {new Date(run.shared_at).toLocaleString(
                                    'id-ID',
                                    { timeZone: 'Asia/Jakarta' },
                                )}{' '}
                                WIB
                            </span>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {capabilities.share && (
                            <Button
                                variant="outline"
                                disabled={sharing}
                                onClick={shareForReview}
                            >
                                {sharing
                                    ? 'Membagikan…'
                                    : 'Bagikan untuk Ditinjau Karyawan'}
                            </Button>
                        )}
                        {(capabilities.recalculate || capabilities.refresh) && (
                            <Button
                                variant="outline"
                                disabled={recalculating}
                                onClick={recalculate}
                            >
                                {recalculating
                                    ? 'Menghitung…'
                                    : run.status === 'draft'
                                      ? 'Perbarui Draft'
                                      : 'Hitung Ulang'}
                            </Button>
                        )}
                        {capabilities.submit && (
                            <Form
                                {...PayrollRunController.submit.form(run.id)}
                                disableWhileProcessing
                            >
                                {({ processing, errors }) => (
                                    <div className="space-y-2">
                                        <Button
                                            disabled={
                                                processing ||
                                                readiness_issues.length > 0
                                            }
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Mengajukan…'
                                                : 'Ajukan Persetujuan'}
                                        </Button>
                                        {Object.values(errors).map(
                                            (error, i) => (
                                                <p
                                                    key={i}
                                                    role="alert"
                                                    className="max-w-sm text-sm text-destructive"
                                                >
                                                    {error}
                                                </p>
                                            ),
                                        )}
                                    </div>
                                )}
                            </Form>
                        )}
                        {capabilities.cancel && (
                            <Dialog
                                open={cancelOpen}
                                onOpenChange={setCancelOpen}
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => setCancelOpen(true)}
                                >
                                    Batalkan Periode
                                </Button>
                                <DialogContent>
                                    <Form
                                        {...PayrollRunController.cancel.form(
                                            run.id,
                                        )}
                                        disableWhileProcessing
                                        onSuccess={() =>
                                            setCancelOpen(false)
                                        }
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        Batalkan hasil payroll
                                                        ini?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Riwayat tetap
                                                        tersimpan (tidak
                                                        dihapus) dan tercatat
                                                        di Audit Log. Setelah
                                                        dibatalkan, periode
                                                        ini bisa dihitung
                                                        ulang dari awal kalau
                                                        diperlukan.
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <div className="grid gap-2 py-2">
                                                    <Label htmlFor="cancel-reason">
                                                        Alasan pembatalan
                                                    </Label>
                                                    <Textarea
                                                        id="cancel-reason"
                                                        name="reason"
                                                        required
                                                        placeholder="Contoh: salah periode, data karyawan keliru, dll."
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.reason
                                                        }
                                                    />
                                                </div>
                                                <DialogFooter>
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        disabled={processing}
                                                    >
                                                        {processing
                                                            ? 'Membatalkan…'
                                                            : 'Batalkan'}
                                                    </Button>
                                                </DialogFooter>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>
                {run.revision_reason && run.status === 'revision_requested' && (
                    <p
                        role="status"
                        className="rounded-xl border bg-card p-4 text-sm"
                    >
                        <strong>Permintaan revisi:</strong>{' '}
                        {run.revision_reason}
                    </p>
                )}
                {readiness_issues.length > 0 &&
                    ['draft', 'submitted'].includes(run.status) && (
                        <section
                            role="status"
                            className="rounded-xl border border-brand-yellow/50 bg-brand-yellow/10 p-4 text-sm"
                        >
                            <h2 className="font-semibold">
                                Perlu diselesaikan sebelum persetujuan
                            </h2>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {readiness_issues.map((issue) => (
                                    <li key={issue}>{issue}</li>
                                ))}
                            </ul>
                            <p className="mt-2">
                                {run.status === 'draft'
                                    ? 'Setelah memperbaiki data, gunakan Perbarui Draft. Penyesuaian manual tetap dibawa ke versi baru.'
                                    : 'Minta revisi kepada HRD agar data diperbaiki dan payroll dihitung ulang.'}
                            </p>
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
                {unresolved > 0 && (
                    <p
                        role="status"
                        className="rounded-xl border border-brand-yellow/50 bg-brand-yellow/10 p-3 text-sm"
                    >
                        {unresolved} hari absensi masih perlu ditinjau. Potongan
                        dan reward terkait belum lengkap; periksa koreksi
                        absensi sebelum mengajukan.
                    </p>
                )}
                <p className="text-xs text-muted-foreground">
                    Reward absensi {money(run.reward_total)} sudah termasuk
                    dalam total bruto. Status tinjauan di bawah hanya
                    menunjukkan kelengkapan absensi yang tercatat, bukan
                    validasi akhir payroll.
                </p>
                <div className="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                    <section
                        aria-label="Daftar karyawan payroll"
                        className={cn(
                            'min-w-0 overflow-hidden rounded-2xl border bg-card',
                            mobileDetail && selected && 'hidden xl:block',
                        )}
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
                            <label className="flex flex-col gap-1.5 text-xs font-medium">
                                Divisi
                                <select
                                    value={division}
                                    onChange={(event) =>
                                        setDivision(event.target.value)
                                    }
                                    className="h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="all">Semua divisi</option>
                                    {divisions.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name} · {item.count} karyawan
                                            · {money(item.net_total)}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        {visible.length === 0 ? (
                            <div className="space-y-3 p-8 text-center text-sm text-muted-foreground">
                                <Users className="mx-auto size-8" />
                                <p>
                                    {entries.length === 0
                                        ? 'Belum ada karyawan dalam hasil payroll ini. Periksa profil gaji sebelum perhitungan berikutnya.'
                                        : 'Tidak ada karyawan yang sesuai pencarian.'}
                                </p>
                                {entries.length > 0 && (
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setSearch('');
                                            setDivision('all');
                                        }}
                                    >
                                        Reset filter
                                    </Button>
                                )}
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
                                                    selected?.id === entry.id &&
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
                                                        aria-controls="payroll-detail"
                                                        onClick={() =>
                                                            selectEntry(
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
                                                        <span className="mt-2 inline-flex rounded bg-muted px-2 py-1 text-[11px]">
                                                            {entry.unresolved_days >
                                                            0
                                                                ? `${entry.unresolved_days} hari perlu tinjauan`
                                                                : 'Absensi tercatat'}
                                                        </span>
                                                        {entry.lines.some(
                                                            (line) =>
                                                                [
                                                                    'manual',
                                                                    'adjustment',
                                                                ].includes(
                                                                    line.source,
                                                                ),
                                                        ) && (
                                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                                Ada penyesuaian
                                                            </span>
                                                        )}
                                                    </button>
                                                </td>
                                                <td className="p-3 text-right whitespace-nowrap tabular-nums">
                                                    {money(entry.gross_amount)}
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
                        id="payroll-detail"
                        aria-label="Rincian gaji terpilih"
                        className={cn(
                            'min-w-0 rounded-2xl border bg-card p-4 sm:p-5',
                            !mobileDetail && 'hidden xl:block',
                        )}
                    >
                        {selected ? (
                            <>
                                <Button
                                    variant="ghost"
                                    className="mb-3 xl:hidden"
                                    onClick={() => {
                                        setMobileDetail(false);
                                        requestAnimationFrame(() =>
                                            searchRef.current?.focus(),
                                        );
                                    }}
                                >
                                    <ArrowLeft className="size-4" />
                                    Daftar karyawan
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    Rincian gaji
                                </p>
                                <h2
                                    ref={detailRef}
                                    tabIndex={-1}
                                    className="mt-1 text-lg font-semibold break-words outline-none"
                                >
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
                                            (line) => line.type === 'earning',
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
                                            (line) => line.type === 'deduction',
                                        )}
                                    />
                                    <div className="flex justify-between gap-3 border-t pt-3 text-sm font-semibold">
                                        <span>Total potongan</span>
                                        <span className="tabular-nums">
                                            {money(selected.deduction_amount)}
                                        </span>
                                    </div>
                                    {selected.lines.some(
                                        (line) =>
                                            line.type ===
                                            'employer_contribution',
                                    ) && (
                                        <LineGroup
                                            title="Kontribusi perusahaan (di luar THP)"
                                            lines={selected.lines.filter(
                                                (line) =>
                                                    line.type ===
                                                    'employer_contribution',
                                            )}
                                        />
                                    )}
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
                                        {selected.unresolved_days} hari absensi
                                        masih perlu koreksi HRD.
                                    </p>
                                )}
                                {capabilities.adjust && (
                                    <PayrollAdjustments
                                        key={selected.id}
                                        entryId={selected.id}
                                        employeeName={selected.name}
                                        lines={selected.lines}
                                    />
                                )}
                                {selected.can_publish_slip && (
                                    <div
                                        key={selected.id}
                                        className="mt-5 space-y-3 border-t pt-4"
                                    >
                                        {selected.latest_payslip && (
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={PenggajianController.show(
                                                        selected.latest_payslip
                                                            .id,
                                                    )}
                                                >
                                                    Lihat slip terbit · v
                                                    {
                                                        selected.latest_payslip
                                                            .version
                                                    }
                                                </Link>
                                            </Button>
                                        )}
                                        <Form
                                            {...PayrollPayslipController.store.form(
                                                selected.id,
                                            )}
                                            disableWhileProcessing
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <Button
                                                        variant="outline"
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        {processing
                                                            ? 'Menerbitkan…'
                                                            : selected.latest_payslip
                                                              ? 'Terbitkan ulang slip'
                                                              : 'Terbitkan slip'}
                                                    </Button>
                                                    {Object.values(errors).map(
                                                        (error, i) => (
                                                            <p
                                                                key={i}
                                                                role="alert"
                                                                className="mt-2 text-sm text-destructive"
                                                            >
                                                                {error}
                                                            </p>
                                                        ),
                                                    )}
                                                </>
                                            )}
                                        </Form>
                                    </div>
                                )}
                            </>
                        ) : (
                            <p className="py-12 text-center text-sm text-muted-foreground">
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
                                        {APPROVAL_ACTION_LABELS[item.action] ??
                                            item.action}{' '}
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

PayrollRunShow.layout = { breadcrumbs: [{ title: 'Proses Gaji', href: '#' }] };
