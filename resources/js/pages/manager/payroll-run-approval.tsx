import { Form, Head, Link, router } from '@inertiajs/react';
import { Archive, Eye, Trash2, Wallet } from 'lucide-react';
import { useState } from 'react';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { show as showPayrollRun } from '@/routes/manager/payroll';

type PendingRun = {
    id: number;
    version: number;
    employee_count: number;
    gross_total: string;
    deduction_total: string;
    reward_total: string;
    net_total: string;
    submitted_at: string | null;
    period: {
        name: string;
        company_name: string;
        starts_on: string;
        ends_on: string;
    };
};

type Props = {
    runs: PendingRun[];
    deletionRequests: PendingDeletionRequest[];
};

type PendingDeletionRequest = {
    id: number;
    reason: string;
    requested_at: string;
    requester_name: string;
    period: {
        id: number;
        name: string;
        starts_on: string;
        ends_on: string;
        run_count: number;
    };
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

function RevisionDialog({
    run,
    open,
    onOpenChange,
}: {
    run: PendingRun;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function handleRequestRevision() {
        if (!note.trim() || submitting) {
            return;
        }

        setSubmitting(true);

        router.patch(
            PayrollRunApprovalController.requestRevision.url(run.id),
            { note },
            {
                onSuccess: () => {
                    toast.success('Permintaan revisi dikirim ke HRD.');
                    setNote('');
                    onOpenChange(false);
                },
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    toast.error(
                        typeof message === 'string'
                            ? message
                            : 'Gagal mengirim permintaan revisi.',
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Minta Revisi</DialogTitle>
                    <DialogDescription>
                        Kembalikan hasil payroll {run.period.name} ke HRD.
                        Catatan wajib diisi sebagai alasan revisi.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-1.5">
                    <Label htmlFor="revision-note">Catatan</Label>
                    <Textarea
                        id="revision-note"
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="Jelaskan apa yang perlu diperbaiki"
                    />
                </div>

                <DialogFooter>
                    <Button
                        variant="destructive"
                        className="rounded-full"
                        disabled={!note.trim() || submitting}
                        onClick={handleRequestRevision}
                    >
                        Kirim Permintaan Revisi
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function PendingRunCard({ run }: { run: PendingRun }) {
    const [revisionOpen, setRevisionOpen] = useState(false);

    return (
        <div className="flex flex-col gap-4 rounded-2xl border bg-card p-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="font-semibold">{run.period.name}</div>
                    <div className="mt-1 flex flex-wrap gap-1.5">
                        <span className="rounded-full bg-brand-navy/8 px-2.5 py-0.5 text-xs font-medium dark:bg-brand-navy/35">
                            {run.period.company_name}
                        </span>
                        <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                            Versi {run.version}
                        </span>
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {formatDate(run.period.starts_on)} –{' '}
                        {formatDate(run.period.ends_on)} · {run.employee_count}{' '}
                        karyawan
                    </div>
                </div>

                <div className="flex gap-2">
                    <Link href={showPayrollRun(run.id)}>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="rounded-full"
                        >
                            <Eye className="size-3.5" />
                            Lihat Detail
                        </Button>
                    </Link>
                    <Form
                        {...PayrollRunApprovalController.approve.form(run.id)}
                        onError={(errors) =>
                            toast.error(
                                Object.values(errors)[0] ??
                                    'Persetujuan gagal. Periksa rincian payroll.',
                            )
                        }
                        disableWhileProcessing
                        onSuccess={() =>
                            toast.success('Hasil payroll disetujui.')
                        }
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="sm"
                                className="rounded-full"
                                disabled={processing}
                            >
                                Setujui
                            </Button>
                        )}
                    </Form>
                    <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        className="rounded-full"
                        onClick={() => setRevisionOpen(true)}
                    >
                        Minta Revisi
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 border-t pt-3 text-sm sm:grid-cols-4">
                <div>
                    <div className="text-xs text-muted-foreground">Gross</div>
                    <div className="font-medium">
                        {formatRupiah(run.gross_total)}
                    </div>
                </div>
                <div>
                    <div className="text-xs text-muted-foreground">
                        Potongan
                    </div>
                    <div className="font-medium">
                        {formatRupiah(run.deduction_total)}
                    </div>
                </div>
                <div>
                    <div className="text-xs text-muted-foreground">Reward</div>
                    <div className="font-medium">
                        {formatRupiah(run.reward_total)}
                    </div>
                </div>
                <div>
                    <div className="text-xs text-muted-foreground">Net</div>
                    <div className="font-medium">
                        {formatRupiah(run.net_total)}
                    </div>
                </div>
            </div>

            <RevisionDialog
                run={run}
                open={revisionOpen}
                onOpenChange={setRevisionOpen}
            />
        </div>
    );
}

export default function PayrollRunApproval({ runs, deletionRequests }: Props) {
    function approveDeletion(request: PendingDeletionRequest) {
        if (
            !window.confirm(
                `Arsipkan periode ${request.period.name}? Periode akan hilang dari daftar aktif HRD, tetapi histori payroll, pembayaran, dan slip tetap tersimpan.`,
            )
        ) {
            return;
        }

        router.patch(
            PayrollRunApprovalController.approvePeriodDeletion(
                request.id,
            ).url,
            {},
            {
                onSuccess: () =>
                    toast.success('Periode berhasil diarsipkan.'),
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    toast.error(
                        typeof message === 'string'
                            ? message
                            : 'Gagal menyetujui permintaan.',
                    );
                },
            },
        );
    }

    function rejectDeletion(request: PendingDeletionRequest) {
        const decision_note = window.prompt(
            `Alasan menolak permintaan penghapusan ${request.period.name}:`,
        );

        if (!decision_note?.trim()) {
            return;
        }

        router.patch(
            PayrollRunApprovalController.rejectPeriodDeletion(
                request.id,
            ).url,
            { decision_note: decision_note.trim() },
            {
                onSuccess: () => toast.success('Permintaan penghapusan ditolak.'),
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    toast.error(
                        typeof message === 'string'
                            ? message
                            : 'Gagal menolak permintaan.',
                    );
                },
            },
        );
    }

    return (
        <>
            <Head title="Persetujuan Payroll" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Persetujuan Payroll
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Hasil perhitungan payroll yang diajukan HRD, menunggu
                        keputusan akhir.
                    </p>
                </div>

                <section className="flex flex-col gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Permintaan penghapusan periode
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Persetujuan akan mengarsipkan periode dari daftar
                            aktif HRD. Data payroll dan riwayat keuangan tidak
                            dihapus permanen.
                        </p>
                    </div>
                    {deletionRequests.length === 0 ? (
                        <div className="rounded-2xl bg-muted/40 px-4 py-6 text-sm text-muted-foreground">
                            Tidak ada permintaan penghapusan periode yang
                            menunggu persetujuan.
                        </div>
                    ) : (
                        deletionRequests.map((request) => (
                            <div
                                key={request.id}
                                className="flex flex-col gap-4 rounded-2xl border border-destructive/20 bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2 font-semibold">
                                        <Archive className="size-4 text-destructive" />
                                        {request.period.name}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {formatDate(request.period.starts_on)}
                                        {' – '}
                                        {formatDate(request.period.ends_on)}
                                        {' · '}
                                        {request.period.run_count} versi payroll
                                        {' · Diminta oleh '}
                                        {request.requester_name}
                                    </div>
                                    <p className="mt-2 text-sm">
                                        {request.reason}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="rounded-full"
                                        onClick={() => rejectDeletion(request)}
                                    >
                                        Tolak
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        className="rounded-full"
                                        onClick={() => approveDeletion(request)}
                                    >
                                        <Trash2 className="size-3.5" />
                                        Setujui Pengarsipan
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </section>

                {runs.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Wallet className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Tidak ada hasil payroll yang menunggu persetujuan.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {runs.map((run) => (
                            <PendingRunCard key={run.id} run={run} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

PayrollRunApproval.layout = {
    breadcrumbs: [
        {
            title: 'Persetujuan Payroll',
            href: PayrollRunApprovalController.index(),
        },
    ],
};
