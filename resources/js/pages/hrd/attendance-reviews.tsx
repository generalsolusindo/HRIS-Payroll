import { Head, router } from '@inertiajs/react';
import { ClipboardCheck, Pencil } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import AttendanceReviewController from '@/actions/App/Http/Controllers/Hrd/AttendanceReviewController';
import { DateField } from '@/components/date-field';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type ReviewRow = {
    id: number;
    user_name: string;
    company_name: string;
    position_name: string;
    classification: string;
    status: string;
    clock_in_at: string | null;
    clock_out_at: string | null;
    late_minutes: number;
    early_departure_minutes: number;
    correction_reason: string | null;
    has_evidence: boolean;
};

type Props = {
    tanggal: string;
    reviews: ReviewRow[];
};

const CLASSIFICATION_LABELS: Record<string, string> = {
    unresolved: 'Perlu Konfirmasi',
    present: 'Hadir',
    leave: 'Cuti',
    sick: 'Sakit',
    permission: 'Izin',
    business_trip: 'Dinas Luar',
    absent: 'Alpha',
    rest_day: 'Hari Istirahat',
    holiday: 'Hari Libur',
};

const CLASSIFICATION_BADGE: Record<string, string> = {
    unresolved:
        'border-amber-400 text-amber-600 dark:border-amber-400/70 dark:text-amber-400',
    present:
        'border-emerald-400 text-emerald-600 dark:border-emerald-400/70 dark:text-emerald-400',
    leave: 'border-muted-foreground/30 text-muted-foreground',
    sick: 'border-muted-foreground/30 text-muted-foreground',
    permission: 'border-muted-foreground/30 text-muted-foreground',
    business_trip: 'border-muted-foreground/30 text-muted-foreground',
    absent: 'border-red-400 text-red-600 dark:border-red-400/70 dark:text-red-400',
    rest_day: 'border-muted-foreground/30 text-muted-foreground',
    holiday: 'border-muted-foreground/30 text-muted-foreground',
};

function formatTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleTimeString('id-ID', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function AttendanceReviews({ tanggal, reviews }: Props) {
    const [editingReview, setEditingReview] = useState<ReviewRow | null>(null);
    const [classification, setClassification] = useState('present');
    const [reason, setReason] = useState('');
    const [evidenceFile, setEvidenceFile] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function handleDateChange(value: string) {
        router.get(
            AttendanceReviewController.index().url,
            { tanggal: value },
            { preserveState: true, preserveScroll: true },
        );
    }

    function openCorrection(review: ReviewRow) {
        setClassification(review.classification);
        setReason(review.correction_reason ?? '');
        setEvidenceFile(null);
        setErrors({});
        setEditingReview(review);
    }

    function submitCorrection(event: React.FormEvent) {
        event.preventDefault();
        if (!editingReview) {
            return;
        }

        const formData = new FormData();
        // Laravel's documented method-spoofing field — multipart/form-data
        // PUT isn't reliably supported by all servers, so this is sent as
        // a POST that Laravel treats as PUT once it sees this field.
        formData.append('_method', 'put');
        formData.append('classification', classification);
        formData.append('correction_reason', reason);
        if (evidenceFile) {
            formData.append('evidence', evidenceFile);
        }

        setSubmitting(true);
        router.post(
            AttendanceReviewController.update.url(editingReview.id),
            formData,
            {
                forceFormData: true,
                onSuccess: () => {
                    toast.success('Koreksi absensi disimpan.');
                    setEditingReview(null);
                },
                onError: (serverErrors) => {
                    setErrors(serverErrors as Record<string, string>);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    const needsAttention = reviews.filter(
        (review) => review.classification === 'unresolved',
    ).length;

    return (
        <>
            <Head title="Koreksi Absensi" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Koreksi Absensi
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Hasil absensi otomatis dibandingkan dengan jadwal kerja
                        — perbaiki yang perlu konfirmasi, dengan alasan.
                    </p>
                </div>

                <DateField
                    id="tanggal"
                    label="Tanggal"
                    type="date"
                    defaultValue={tanggal}
                    max={new Date().toISOString().slice(0, 10)}
                    onChange={(e) => handleDateChange(e.target.value)}
                />

                {needsAttention > 0 && (
                    <div className="rounded-2xl border border-amber-400/50 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                        {needsAttention} catatan perlu konfirmasi — tidak ada
                        absen masuk/pulang dan tidak ada izin yang disetujui
                        untuk tanggal ini.
                    </div>
                )}

                {reviews.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <ClipboardCheck className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Tidak ada karyawan aktif untuk ditinjau.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Karyawan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Klasifikasi
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Masuk
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Pulang
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Telat
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {reviews.map((review) => (
                                    <tr key={review.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {review.user_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {review.company_name} ·{' '}
                                                {review.position_name}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ${CLASSIFICATION_BADGE[review.classification] ?? ''}`}
                                            >
                                                {CLASSIFICATION_LABELS[
                                                    review.classification
                                                ] ?? review.classification}
                                            </span>
                                            {review.correction_reason && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    Dikoreksi:{' '}
                                                    {review.correction_reason}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatTime(review.clock_in_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatTime(review.clock_out_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {review.late_minutes > 0
                                                ? `${review.late_minutes} menit`
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={
                                                    review.classification ===
                                                    'unresolved'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className="rounded-full"
                                                onClick={() =>
                                                    openCorrection(review)
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Koreksi
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Dialog
                open={editingReview !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingReview(null);
                    }
                }}
            >
                <DialogContent>
                    {editingReview && (
                        <form onSubmit={submitCorrection}>
                            <DialogHeader>
                                <DialogTitle>
                                    Koreksi — {editingReview.user_name}
                                </DialogTitle>
                                <DialogDescription>
                                    Alasan koreksi wajib diisi, akan tersimpan
                                    sebagai riwayat.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4 py-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="classification">
                                        Klasifikasi
                                    </Label>
                                    <Select
                                        value={classification}
                                        onValueChange={setClassification}
                                    >
                                        <SelectTrigger
                                            id="classification"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(
                                                CLASSIFICATION_LABELS,
                                            ).map(([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.classification}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="correction_reason">
                                        Alasan
                                    </Label>
                                    <Textarea
                                        id="correction_reason"
                                        required
                                        value={reason}
                                        onChange={(event) =>
                                            setReason(event.target.value)
                                        }
                                        placeholder="Contoh: Karyawan mengirim surat sakit via WhatsApp."
                                    />
                                    <InputError
                                        message={errors.correction_reason}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="evidence">
                                        Bukti Pendukung (opsional)
                                    </Label>
                                    <input
                                        id="evidence"
                                        type="file"
                                        className="text-sm"
                                        onChange={(event) =>
                                            setEvidenceFile(
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    {editingReview.has_evidence && (
                                        <p className="text-xs text-muted-foreground">
                                            Sudah ada bukti tersimpan — unggah
                                            file baru untuk menggantinya.
                                        </p>
                                    )}
                                    <InputError message={errors.evidence} />
                                </div>
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={submitting}>
                                    Simpan Koreksi
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

AttendanceReviews.layout = {
    breadcrumbs: [
        {
            title: 'Koreksi Absensi',
            href: AttendanceReviewController.index(),
        },
    ],
};
