import { Form, Head, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Download,
    FileText,
    History,
    Lock,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import PaymentProofController from '@/actions/App/Http/Controllers/Finance/PaymentProofController';
import PayrollPaymentController from '@/actions/App/Http/Controllers/Finance/PayrollPaymentController';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Payment = {
    id: number;
    amount: string;
    method: string;
    status: string;
    reference: string | null;
    has_proof: boolean;
    note: string | null;
    created_at: string;
};

type Destination = {
    bank_name: string | null;
    account_number: string | null;
    account_holder: string | null;
};

type Entry = {
    id: number;
    name: string;
    division_name: string;
    net_amount: string;
    paid_amount: string;
    remaining_amount: string;
    has_destination: boolean;
    destination: Destination | null;
    payments: Payment[];
};

type Run = {
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
    approved: 'Disetujui',
    locked: 'Terkunci',
    partially_paid: 'Dibayar Sebagian',
    paid: 'Lunas',
};

const APPROVAL_ACTION_LABELS: Record<string, string> = {
    submitted: 'Diajukan ke Manager',
    revision_requested: 'Manager meminta revisi',
    approved: 'Disetujui Manager',
    locked: 'Dikunci',
    superseded: 'Digantikan versi baru',
    cancelled: 'Dibatalkan',
};

const METHOD_LABELS: Record<string, string> = {
    bank_transfer: 'Transfer Bank',
    cash: 'Tunai',
    other: 'Lainnya',
};

const PAYMENT_STATUS_LABELS: Record<string, string> = {
    pending: 'Menunggu',
    confirmed: 'Terkonfirmasi',
    failed: 'Gagal',
    voided: 'Dibatalkan',
};

function formatRupiah(value: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 2,
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

function PaymentDialog({
    entry,
    open,
    onOpenChange,
}: {
    entry: Entry;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [amount, setAmount] = useState(entry.remaining_amount);
    const [method, setMethod] = useState('bank_transfer');
    const [reference, setReference] = useState('');
    const [note, setNote] = useState('');
    const [proofFile, setProofFile] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    // Generated once per dialog open (not per click) and reused on every
    // submit attempt within this session, so a dropped-response retry
    // resubmits the same key instead of minting a new one — the server
    // recognizes a repeat and reports success without double-recording.
    const [idempotencyKey] = useState(() => crypto.randomUUID());

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const formData = new FormData();
        formData.append('amount', amount);
        formData.append('method', method);
        formData.append('idempotency_key', idempotencyKey);
        if (reference) {
            formData.append('reference', reference);
        }
        if (note) {
            formData.append('note', note);
        }
        if (proofFile) {
            formData.append('proof', proofFile);
        }

        setSubmitting(true);
        router.post(
            PayrollPaymentController.storePayment.url(entry.id),
            formData,
            {
                forceFormData: true,
                onSuccess: () => onOpenChange(false),
                onError: (serverErrors) => {
                    setErrors(serverErrors as Record<string, string>);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            Catat Pembayaran — {entry.name}
                        </DialogTitle>
                        <DialogDescription>
                            Sisa yang belum dibayar:{' '}
                            {formatRupiah(entry.remaining_amount)}. Mendukung
                            pembayaran bertahap — nominal boleh kurang dari
                            sisa.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <div className="rounded-xl border bg-muted/30 p-3 text-sm">
                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                Tujuan transfer
                            </p>
                            {entry.destination ? (
                                <div className="mt-1.5 space-y-0.5">
                                    <p className="font-medium">
                                        {entry.destination.bank_name ?? '—'}
                                    </p>
                                    <p className="font-mono text-sm">
                                        {entry.destination.account_number ??
                                            '—'}
                                    </p>
                                    <p className="text-muted-foreground">
                                        a.n.{' '}
                                        {entry.destination.account_holder ??
                                            '—'}
                                    </p>
                                </div>
                            ) : (
                                <p className="mt-1.5 text-muted-foreground">
                                    Belum ada data rekening — lengkapi dulu
                                    di Manajemen User.
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="amount">Nominal</Label>
                            <Input
                                id="amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value)}
                            />
                            <InputError message={errors.amount} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="method">Metode</Label>
                            <Select value={method} onValueChange={setMethod}>
                                <SelectTrigger id="method" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(METHOD_LABELS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.method} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="reference">
                                Referensi (opsional)
                            </Label>
                            <Input
                                id="reference"
                                value={reference}
                                onChange={(e) => setReference(e.target.value)}
                                placeholder="Nomor transaksi bank"
                            />
                            <p className="text-xs text-muted-foreground">
                                Nomor referensi/transaksi dari bank — buat
                                memudahkan cocokkan catatan ini dengan
                                mutasi rekening nanti.
                            </p>
                            <InputError message={errors.reference} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="proof">Bukti (opsional)</Label>
                            <input
                                id="proof"
                                type="file"
                                className="text-sm"
                                onChange={(e) =>
                                    setProofFile(e.target.files?.[0] ?? null)
                                }
                            />
                            <InputError message={errors.proof} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="payment-note">
                                Catatan (opsional)
                            </Label>
                            <Textarea
                                id="payment-note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                            />
                            <InputError message={errors.note} />
                            <InputError message={errors.status} />
                            <InputError message={errors.idempotency_key} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={submitting}>
                            Simpan Pembayaran
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function VoidDialog({
    payment,
    open,
    onOpenChange,
}: {
    payment: Payment;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function handleVoid() {
        if (!note.trim() || submitting) {
            return;
        }

        setSubmitting(true);
        router.patch(
            PayrollPaymentController.voidPayment.url(payment.id),
            { note },
            {
                onSuccess: () => {
                    setNote('');
                    onOpenChange(false);
                },
                onError: () => toast.error('Gagal membatalkan pembayaran.'),
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Batalkan Pembayaran</DialogTitle>
                    <DialogDescription>
                        Batalkan pembayaran {formatRupiah(payment.amount)} (
                        {METHOD_LABELS[payment.method] ?? payment.method}).
                        Catatan wajib diisi sebagai alasan.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-1.5">
                    <Label htmlFor="void-note">Catatan</Label>
                    <Textarea
                        id="void-note"
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="Jelaskan alasan pembatalan"
                    />
                </div>

                <DialogFooter>
                    <Button
                        variant="destructive"
                        className="rounded-full"
                        disabled={!note.trim() || submitting}
                        onClick={handleVoid}
                    >
                        Batalkan Pembayaran
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function PayrollRunShow({ run, entries, approvals }: Props) {
    const [expandedEntry, setExpandedEntry] = useState<number | null>(null);
    const [payingEntry, setPayingEntry] = useState<Entry | null>(null);
    const [voidingPayment, setVoidingPayment] = useState<Payment | null>(null);

    const paidTotal = run.paid_total;

    return (
        <>
            <Head title={`Pembayaran — ${run.period.name}`} />

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

                    {run.status === 'approved' && (
                        <Form
                            {...PayrollPaymentController.lock.form(run.id)}
                            onError={(errors) =>
                                toast.error(
                                    Object.values(errors)[0] ??
                                        'Penguncian payroll gagal.',
                                )
                            }
                            disableWhileProcessing
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    className="rounded-full"
                                    disabled={processing}
                                >
                                    <Lock className="size-3.5" />
                                    Kunci untuk Dibayar
                                </Button>
                            )}
                        </Form>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div className="rounded-3xl bg-brand-navy/8 p-5 dark:border dark:border-brand-navy-foreground/10 dark:bg-brand-navy/35">
                        <div className="text-sm text-muted-foreground">
                            Total Karyawan
                        </div>
                        <div className="mt-1 text-3xl font-bold tracking-tight">
                            {run.employee_count}
                        </div>
                    </div>
                    <div className="rounded-3xl bg-muted/60 p-5">
                        <div className="text-sm text-muted-foreground">
                            Total Net
                        </div>
                        <div className="mt-1 text-xl font-bold tracking-tight sm:text-2xl">
                            {formatRupiah(run.net_total)}
                        </div>
                    </div>
                    <div className="rounded-3xl bg-emerald-500/10 p-5">
                        <div className="text-sm text-muted-foreground">
                            Total Terbayar
                        </div>
                        <div className="mt-1 text-xl font-bold tracking-tight text-emerald-600 sm:text-2xl dark:text-emerald-400">
                            {formatRupiah(String(paidTotal))}
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-2xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Karyawan
                                </th>
                                <th className="px-4 py-3 font-medium">Net</th>
                                <th className="px-4 py-3 font-medium">
                                    Terbayar
                                </th>
                                <th className="px-4 py-3 font-medium">Sisa</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {entries.map((entry) => (
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
                                                        {entry.division_name}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRupiah(entry.net_amount)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRupiah(entry.paid_amount)}
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {formatRupiah(
                                                entry.remaining_amount,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div
                                                className="flex justify-end gap-2"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <a
                                                    href={
                                                        PayrollPaymentController.downloadSlip(
                                                            entry.id,
                                                        ).url
                                                    }
                                                >
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="rounded-full"
                                                    >
                                                        <Download className="size-3.5" />
                                                        Slip
                                                    </Button>
                                                </a>
                                                {(run.status === 'locked' ||
                                                    run.status ===
                                                        'partially_paid') &&
                                                    Number(
                                                        entry.remaining_amount,
                                                    ) > 0 && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="rounded-full"
                                                            onClick={() =>
                                                                setPayingEntry(
                                                                    entry,
                                                                )
                                                            }
                                                        >
                                                            Catat Pembayaran
                                                        </Button>
                                                    )}
                                            </div>
                                        </td>
                                    </tr>
                                    {expandedEntry === entry.id && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="bg-muted/20 px-4 py-3"
                                            >
                                                {!entry.has_destination && (
                                                    <p className="mb-2 text-xs text-amber-600 dark:text-amber-400">
                                                        Rekening belum diatur di
                                                        profil gaji karyawan
                                                        ini.
                                                    </p>
                                                )}
                                                {entry.payments.length === 0 ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        Belum ada pembayaran
                                                        tercatat.
                                                    </p>
                                                ) : (
                                                    <div className="flex flex-col gap-2 text-xs">
                                                        {entry.payments.map(
                                                            (payment) => (
                                                                <div
                                                                    key={
                                                                        payment.id
                                                                    }
                                                                    className="flex items-center justify-between gap-3 border-l-2 border-muted pl-3"
                                                                >
                                                                    <div>
                                                                        <div className="font-medium">
                                                                            {formatRupiah(
                                                                                payment.amount,
                                                                            )}{' '}
                                                                            ·{' '}
                                                                            {METHOD_LABELS[
                                                                                payment
                                                                                    .method
                                                                            ] ??
                                                                                payment.method}{' '}
                                                                            ·{' '}
                                                                            {PAYMENT_STATUS_LABELS[
                                                                                payment
                                                                                    .status
                                                                            ] ??
                                                                                payment.status}
                                                                        </div>
                                                                        <div className="text-muted-foreground">
                                                                            {formatDateTime(
                                                                                payment.created_at,
                                                                            )}
                                                                            {payment.reference &&
                                                                                ` · ${payment.reference}`}
                                                                        </div>
                                                                        {payment.note && (
                                                                            <p className="mt-0.5 text-muted-foreground">
                                                                                "
                                                                                {
                                                                                    payment.note
                                                                                }
                                                                                "
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                    <div className="flex items-center gap-2">
                                                                        {payment.has_proof && (
                                                                            <a
                                                                                href={
                                                                                    PaymentProofController.show(
                                                                                        payment.id,
                                                                                    )
                                                                                        .url
                                                                                }
                                                                                target="_blank"
                                                                                rel="noreferrer"
                                                                                className="flex items-center gap-1 rounded-full border px-2.5 py-1 hover:bg-muted/40"
                                                                            >
                                                                                <FileText className="size-3" />
                                                                                Bukti
                                                                            </a>
                                                                        )}
                                                                        {payment.status ===
                                                                            'confirmed' && (
                                                                            <Button
                                                                                type="button"
                                                                                size="sm"
                                                                                variant="outline"
                                                                                className="rounded-full"
                                                                                onClick={() =>
                                                                                    setVoidingPayment(
                                                                                        payment,
                                                                                    )
                                                                                }
                                                                            >
                                                                                Batalkan
                                                                            </Button>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {approvals.length > 0 && (
                <details className="rounded-2xl border bg-card p-4">
                    <summary className="cursor-pointer text-sm font-semibold">
                        <History className="mr-2 inline size-4" />
                        Riwayat persetujuan ({approvals.length})
                    </summary>
                    <ol className="mt-4 space-y-4">
                        {approvals.map((item) => (
                            <li key={item.id} className="border-l-2 pl-3 text-sm">
                                <p className="font-medium">
                                    {APPROVAL_ACTION_LABELS[item.action] ??
                                        item.action}{' '}
                                    — {item.actor_name}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {formatDateTime(item.created_at)} WIB
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

            {payingEntry && (
                <PaymentDialog
                    entry={payingEntry}
                    open={payingEntry !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setPayingEntry(null);
                        }
                    }}
                />
            )}

            {voidingPayment && (
                <VoidDialog
                    payment={voidingPayment}
                    open={voidingPayment !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setVoidingPayment(null);
                        }
                    }}
                />
            )}
        </>
    );
}

PayrollRunShow.layout = {
    breadcrumbs: [
        {
            title: 'Pembayaran Payroll',
            href: PayrollPaymentController.index(),
        },
        {
            title: 'Detail',
            href: '#',
        },
    ],
};
