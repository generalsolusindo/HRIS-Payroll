import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, MessageSquarePlus } from 'lucide-react';
import { useState } from 'react';
import PenggajianController from '@/actions/App/Http/Controllers/PenggajianController';
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
import { Textarea } from '@/components/ui/textarea';

type Line = {
    component_name: string;
    type: string;
    amount: string;
};

type Complaint = {
    id: number;
    message: string;
    status: 'new' | 'in_review' | 'resolved';
    hrd_note: string | null;
    created_at: string;
};

type Preview = {
    id: number;
    run_status: string;
    shared_at: string;
    period: { name: string; starts_on: string; ends_on: string };
    division_name: string;
    position_name: string;
    gross_amount: string;
    deduction_amount: string;
    reward_amount: string;
    net_amount: string;
    attendance_summary: {
        late_minutes_total: number;
        absent_days: number;
        perfect_attendance: boolean;
    };
    lines: Line[];
    complaints: Complaint[];
};

type Props = {
    preview: Preview;
};

const RUN_STATUS_LABELS: Record<string, string> = {
    draft: 'Draft — angka ini masih bisa berubah',
    submitted: 'Sudah diajukan ke Manager',
    revision_requested: 'Sedang direvisi HRD',
    approved: 'Sudah disetujui Manager',
    locked: 'Sudah disetujui Manager',
    partially_paid: 'Sudah disetujui Manager',
    paid: 'Sudah disetujui Manager',
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
        month: 'long',
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

const COMPLAINT_STATUS_LABELS: Record<Complaint['status'], string> = {
    new: 'Baru',
    in_review: 'Sedang Ditinjau',
    resolved: 'Selesai',
};

const COMPLAINT_STATUS_STYLES: Record<Complaint['status'], string> = {
    new: 'border-amber-400 text-amber-600 dark:border-amber-400/70 dark:text-amber-400',
    in_review:
        'border-blue-400 text-blue-600 dark:border-blue-400/70 dark:text-blue-400',
    resolved:
        'border-emerald-400 text-emerald-600 dark:border-emerald-400/70 dark:text-emerald-400',
};

export default function PenggajianPratinjau({ preview }: Props) {
    const [complaintOpen, setComplaintOpen] = useState(false);
    const earnings = preview.lines.filter((line) => line.type === 'earning');
    const deductions = preview.lines.filter(
        (line) => line.type === 'deduction',
    );

    return (
        <>
            <Head title={`Pratinjau Gaji — ${preview.period.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Link href={PenggajianController.index()}>
                    <Button variant="ghost" size="sm" className="w-fit">
                        <ArrowLeft className="size-3.5" />
                        Kembali
                    </Button>
                </Link>

                <div className="mx-auto w-full max-w-2xl rounded-3xl border border-blue-400/50 p-6 sm:p-8">
                    <div className="mb-4 rounded-xl border border-blue-400/50 bg-blue-50 p-3 text-xs text-blue-700 dark:bg-blue-950/30 dark:text-blue-400">
                        <strong>Pratinjau — belum final.</strong> Ini bukan
                        slip resmi. Kalau ada angka yang kamu rasa keliru,
                        ajukan komplain/penyesuaian lewat tombol di bawah
                        sebelum diajukan ke Manager.
                    </div>

                    <div className="flex flex-col gap-1 border-b pb-4">
                        <h1 className="text-xl font-bold tracking-tight sm:text-2xl">
                            Pratinjau Gaji — {preview.period.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {formatDate(preview.period.starts_on)} –{' '}
                            {formatDate(preview.period.ends_on)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {RUN_STATUS_LABELS[preview.run_status] ??
                                preview.run_status}
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-4 border-b py-4 text-sm">
                        <div>
                            <div className="text-xs text-muted-foreground">
                                Jabatan
                            </div>
                            <div className="font-medium">
                                {preview.position_name}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-muted-foreground">
                                Divisi
                            </div>
                            <div className="font-medium">
                                {preview.division_name}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-muted-foreground">
                                Kehadiran
                            </div>
                            <div className="font-medium">
                                {preview.attendance_summary.perfect_attendance
                                    ? 'Sempurna'
                                    : `Telat ${preview.attendance_summary.late_minutes_total} menit · Alpha ${preview.attendance_summary.absent_days} hari`}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4 py-4">
                        <div>
                            <h2 className="mb-2 text-sm font-semibold text-muted-foreground uppercase">
                                Penghasilan
                            </h2>
                            <div className="flex flex-col gap-1.5 text-sm">
                                {earnings.map((line, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center justify-between"
                                    >
                                        <span>{line.component_name}</span>
                                        <span>{formatRupiah(line.amount)}</span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {deductions.length > 0 && (
                            <div>
                                <h2 className="mb-2 text-sm font-semibold text-muted-foreground uppercase">
                                    Potongan
                                </h2>
                                <div className="flex flex-col gap-1.5 text-sm">
                                    {deductions.map((line, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between text-red-600 dark:text-red-400"
                                        >
                                            <span>{line.component_name}</span>
                                            <span>
                                                − {formatRupiah(line.amount)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-between border-t pt-4 text-lg font-bold">
                        <span>Perkiraan Take Home Pay</span>
                        <span>{formatRupiah(preview.net_amount)}</span>
                    </div>

                    <div className="mt-6 border-t pt-4">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-sm font-semibold">
                                Komplain / Penyesuaian
                            </h2>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="rounded-full"
                                onClick={() => setComplaintOpen(true)}
                            >
                                <MessageSquarePlus className="size-3.5" />
                                Ajukan Komplain
                            </Button>
                        </div>

                        {preview.complaints.length === 0 ? (
                            <p className="mt-3 text-xs text-muted-foreground">
                                Belum ada komplain/penyesuaian yang diajukan
                                untuk periode ini.
                            </p>
                        ) : (
                            <div className="mt-3 flex flex-col gap-3">
                                {preview.complaints.map((complaint) => (
                                    <div
                                        key={complaint.id}
                                        className="rounded-xl border p-3 text-sm"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs text-muted-foreground">
                                                {formatDateTime(
                                                    complaint.created_at,
                                                )}
                                            </span>
                                            <span
                                                className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ${COMPLAINT_STATUS_STYLES[complaint.status]}`}
                                            >
                                                {
                                                    COMPLAINT_STATUS_LABELS[
                                                        complaint.status
                                                    ]
                                                }
                                            </span>
                                        </div>
                                        <p className="mt-2 break-words">
                                            {complaint.message}
                                        </p>
                                        {complaint.hrd_note && (
                                            <div className="mt-2 rounded-lg bg-muted/50 p-2 text-xs">
                                                <span className="font-medium">
                                                    Catatan HRD:
                                                </span>{' '}
                                                {complaint.hrd_note}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={complaintOpen} onOpenChange={setComplaintOpen}>
                <DialogContent>
                    <Form
                        {...PenggajianController.storeComplaint.form(
                            preview.id,
                        )}
                        resetOnSuccess
                        disableWhileProcessing
                        onSuccess={() => setComplaintOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        Ajukan Komplain/Penyesuaian
                                    </DialogTitle>
                                    <DialogDescription>
                                        Jelaskan bagian mana yang menurutmu
                                        keliru. HRD akan meninjau dan
                                        menanggapi di sini.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="py-2">
                                    <Textarea
                                        name="message"
                                        required
                                        rows={5}
                                        maxLength={2000}
                                        placeholder="Contoh: Potongan alpha tanggal 5 Oktober seharusnya tidak ada, karena saya sudah izin sakit hari itu."
                                    />
                                    <InputError message={errors.message} />
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Kirim ke HRD
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

PenggajianPratinjau.layout = {
    breadcrumbs: [
        {
            title: 'Penggajian',
            href: PenggajianController.index(),
        },
        {
            title: 'Pratinjau',
            href: '#',
        },
    ],
};
