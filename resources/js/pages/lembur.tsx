import { Form, Head } from '@inertiajs/react';
import { Clock, Plus } from 'lucide-react';
import { useState } from 'react';
import OvertimeRequestController from '@/actions/App/Http/Controllers/OvertimeRequestController';
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
import { Textarea } from '@/components/ui/textarea';

type OvertimeRequestRow = {
    id: number;
    starts_at: string;
    ends_at: string;
    break_minutes: number;
    approved_minutes: number | null;
    reason: string;
    status: string;
    decision_note: string | null;
    processed_by: { name: string } | null;
};

type Props = {
    overtimeRequests: OvertimeRequestRow[];
};

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    submitted: 'Menunggu',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const STATUS_BADGE: Record<string, string> = {
    submitted:
        'border-amber-400 text-amber-600 dark:border-amber-400/70 dark:text-amber-400',
    approved:
        'border-emerald-400 text-emerald-600 dark:border-emerald-400/70 dark:text-emerald-400',
    rejected:
        'border-red-400 text-red-600 dark:border-red-400/70 dark:text-red-400',
    cancelled: 'border-muted-foreground/30 text-muted-foreground',
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

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    return remaining > 0 ? `${hours} jam ${remaining} menit` : `${hours} jam`;
}

export default function Lembur({ overtimeRequests }: Props) {
    const [createOpen, setCreateOpen] = useState(false);

    return (
        <>
            <Head title="Lembur" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Lembur
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Ajukan dan pantau lembur Anda. Jam pulang yang lebih
                            lama tidak otomatis dihitung — harus diajukan dan
                            disetujui HRD.
                        </p>
                    </div>

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <Button
                            type="button"
                            className="rounded-full"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-4" />
                            Ajukan Lembur
                        </Button>
                        <DialogContent>
                            <Form
                                {...OvertimeRequestController.store.form()}
                                resetOnSuccess
                                disableWhileProcessing
                                onSuccess={() => setCreateOpen(false)}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Ajukan Lembur
                                            </DialogTitle>
                                            <DialogDescription>
                                                Menunggu persetujuan HRD sebelum
                                                dihitung sebagai lembur
                                                berbayar.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div className="grid gap-4 py-2">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="starts_at">
                                                        Mulai
                                                    </Label>
                                                    <Input
                                                        id="starts_at"
                                                        name="starts_at"
                                                        type="datetime-local"
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.starts_at
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="ends_at">
                                                        Selesai
                                                    </Label>
                                                    <Input
                                                        id="ends_at"
                                                        name="ends_at"
                                                        type="datetime-local"
                                                        required
                                                    />
                                                    <InputError
                                                        message={errors.ends_at}
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="break_minutes">
                                                    Jeda Istirahat (menit,
                                                    opsional)
                                                </Label>
                                                <Input
                                                    id="break_minutes"
                                                    name="break_minutes"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    placeholder="0"
                                                />
                                                <InputError
                                                    message={
                                                        errors.break_minutes
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="reason">
                                                    Alasan
                                                </Label>
                                                <Textarea
                                                    id="reason"
                                                    name="reason"
                                                    required
                                                    placeholder="Jelaskan pekerjaan yang dilembur"
                                                />
                                                <InputError
                                                    message={errors.reason}
                                                />
                                            </div>
                                        </div>

                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Kirim Pengajuan
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                {overtimeRequests.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Clock className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada pengajuan lembur.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {overtimeRequests.map((overtimeRequest) => (
                            <div
                                key={overtimeRequest.id}
                                className="flex flex-col gap-2 rounded-2xl border bg-card p-4"
                            >
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <div className="font-semibold">
                                            {formatDateTime(
                                                overtimeRequest.starts_at,
                                            )}{' '}
                                            –{' '}
                                            {formatDateTime(
                                                overtimeRequest.ends_at,
                                            )}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {overtimeRequest.reason}
                                        </div>
                                    </div>
                                    <span
                                        className={`inline-flex shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-medium ${STATUS_BADGE[overtimeRequest.status] ?? ''}`}
                                    >
                                        {STATUS_LABELS[
                                            overtimeRequest.status
                                        ] ?? overtimeRequest.status}
                                    </span>
                                </div>
                                {overtimeRequest.status === 'approved' &&
                                    overtimeRequest.approved_minutes !==
                                        null && (
                                        <p className="text-sm text-emerald-600 dark:text-emerald-400">
                                            Disetujui{' '}
                                            {formatMinutes(
                                                overtimeRequest.approved_minutes,
                                            )}
                                            {overtimeRequest.processed_by &&
                                                ` oleh ${overtimeRequest.processed_by.name}`}
                                        </p>
                                    )}
                                {overtimeRequest.status === 'rejected' &&
                                    overtimeRequest.decision_note && (
                                        <p className="text-sm text-red-600 dark:text-red-400">
                                            "{overtimeRequest.decision_note}"
                                        </p>
                                    )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Lembur.layout = {
    breadcrumbs: [
        {
            title: 'Lembur',
            href: OvertimeRequestController.index(),
        },
    ],
};
