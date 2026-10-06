import { Head, router } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import OvertimeApprovalController from '@/actions/App/Http/Controllers/Hrd/OvertimeApprovalController';
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

type OvertimeRequestRow = {
    id: number;
    user_name: string;
    company_name: string;
    position_name: string;
    starts_at: string;
    ends_at: string;
    break_minutes: number;
    requested_minutes: number;
    reason: string;
};

type Props = {
    overtimeRequests: OvertimeRequestRow[];
};

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function ApproveDialog({
    overtimeRequest,
    open,
    onOpenChange,
}: {
    overtimeRequest: OvertimeRequestRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [approvedMinutes, setApprovedMinutes] = useState(
        String(overtimeRequest.requested_minutes),
    );
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function handleApprove() {
        if (submitting) {
            return;
        }

        setSubmitting(true);
        router.patch(
            OvertimeApprovalController.approve.url(overtimeRequest.id),
            { approved_minutes: approvedMinutes, note },
            {
                onSuccess: () => {
                    toast.success('Lembur disetujui.');
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setError(
                        (errors.approved_minutes as string | undefined) ??
                            'Gagal menyetujui lembur.',
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
                    <DialogTitle>Setujui Lembur</DialogTitle>
                    <DialogDescription>
                        {overtimeRequest.user_name} mengajukan{' '}
                        {overtimeRequest.requested_minutes} menit. Bisa
                        disetujui sebagian.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="approved-minutes">
                            Menit Disetujui
                        </Label>
                        <Input
                            id="approved-minutes"
                            type="number"
                            min="0"
                            max={overtimeRequest.requested_minutes}
                            value={approvedMinutes}
                            onChange={(e) => setApprovedMinutes(e.target.value)}
                        />
                        {error && (
                            <p className="text-sm text-destructive">{error}</p>
                        )}
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="approve-note">Catatan (opsional)</Label>
                        <Textarea
                            id="approve-note"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        className="rounded-full"
                        disabled={submitting}
                        onClick={handleApprove}
                    >
                        Setujui
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function RejectDialog({
    overtimeRequest,
    open,
    onOpenChange,
}: {
    overtimeRequest: OvertimeRequestRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function handleReject() {
        if (!note.trim() || submitting) {
            return;
        }

        setSubmitting(true);
        router.patch(
            OvertimeApprovalController.reject.url(overtimeRequest.id),
            { note },
            {
                onSuccess: () => {
                    toast.success('Lembur ditolak.');
                    setNote('');
                    onOpenChange(false);
                },
                onError: () => toast.error('Gagal menolak lembur.'),
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Tolak Lembur</DialogTitle>
                    <DialogDescription>
                        Tolak pengajuan lembur {overtimeRequest.user_name}.
                        Catatan wajib diisi.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-1.5">
                    <Label htmlFor="reject-note">Catatan</Label>
                    <Textarea
                        id="reject-note"
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="Jelaskan alasan penolakan"
                    />
                </div>

                <DialogFooter>
                    <Button
                        variant="destructive"
                        className="rounded-full"
                        disabled={!note.trim() || submitting}
                        onClick={handleReject}
                    >
                        Tolak
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function OvertimeApproval({ overtimeRequests }: Props) {
    const [approving, setApproving] = useState<OvertimeRequestRow | null>(null);
    const [rejecting, setRejecting] = useState<OvertimeRequestRow | null>(null);

    return (
        <>
            <Head title="Persetujuan Lembur" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Persetujuan Lembur
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Pengajuan lembur yang menunggu persetujuan.
                    </p>
                </div>

                {overtimeRequests.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Clock className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Tidak ada pengajuan lembur yang menunggu.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {overtimeRequests.map((overtimeRequest) => (
                            <div
                                key={overtimeRequest.id}
                                className="flex flex-col gap-3 rounded-2xl border bg-card p-4"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div className="font-semibold">
                                            {overtimeRequest.user_name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {overtimeRequest.company_name} ·{' '}
                                            {overtimeRequest.position_name}
                                        </div>
                                        <div className="mt-1 text-sm">
                                            {formatDateTime(
                                                overtimeRequest.starts_at,
                                            )}{' '}
                                            –{' '}
                                            {formatDateTime(
                                                overtimeRequest.ends_at,
                                            )}{' '}
                                            · diajukan{' '}
                                            {overtimeRequest.requested_minutes}{' '}
                                            menit
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="rounded-full"
                                            onClick={() =>
                                                setApproving(overtimeRequest)
                                            }
                                        >
                                            Setujui
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="destructive"
                                            className="rounded-full"
                                            onClick={() =>
                                                setRejecting(overtimeRequest)
                                            }
                                        >
                                            Tolak
                                        </Button>
                                    </div>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {overtimeRequest.reason}
                                </p>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {approving && (
                <ApproveDialog
                    overtimeRequest={approving}
                    open={approving !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setApproving(null);
                        }
                    }}
                />
            )}

            {rejecting && (
                <RejectDialog
                    overtimeRequest={rejecting}
                    open={rejecting !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setRejecting(null);
                        }
                    }}
                />
            )}
        </>
    );
}

OvertimeApproval.layout = {
    breadcrumbs: [
        {
            title: 'Persetujuan Lembur',
            href: OvertimeApprovalController.index(),
        },
    ],
};
