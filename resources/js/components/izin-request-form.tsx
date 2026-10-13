import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import LeaveRequestController from '@/actions/App/Http/Controllers/LeaveRequestController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    LEAVE_TYPE_ICONS,
    LEAVE_TYPE_LABELS,
    LEAVE_TYPES,
    type LeaveType,
} from '@/lib/izin';

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

function addDaysIso(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
}

const DATE_RULES: Record<LeaveType, { min?: string; fixedToday?: boolean }> = {
    late: { fixedToday: true },
    half_day: { min: addDaysIso(1) },
    temporary_exit: { min: todayIso() },
    leave: { min: addDaysIso(7) },
    emergency: { fixedToday: true },
    sick: { min: todayIso() },
};

const DATE_HINTS: Record<LeaveType, string> = {
    late: 'Hanya untuk hari ini.',
    half_day: 'Minimal diajukan H-1 (besok).',
    temporary_exit: 'Hari ini atau tanggal mendatang.',
    leave: 'Minimal diajukan H-7.',
    emergency: 'Hanya untuk hari ini, sebelum jam 08:30.',
    sick: 'Hari ini atau tanggal mendatang.',
};

// Persis mengikuti §3 DOKUMENTASI-SISTEM-IZIN.md — ini aturan resmi per
// jenis izin, bukan ringkasan bebas, supaya karyawan tahu konsekuensinya
// sebelum mengisi field di bawahnya.
const KETENTUAN: Record<LeaveType, string[]> = {
    late: [
        'Hanya berlaku untuk tanggal hari ini.',
        'Batas pengajuan normal maksimal pukul 07.00 WIB.',
        'Setelah pukul 07.00 WIB hanya diizinkan untuk kondisi darurat.',
        'Estimasi kedatangan maksimal pukul 09.30 WIB.',
    ],
    half_day: [
        'Wajib diajukan minimal H-1 (besok atau setelahnya) — tidak bisa untuk hari ini.',
        'Jam mulai bebas, boleh pagi, siang, atau sore.',
        'Durasi maksimal 4 jam dari jam mulai.',
    ],
    temporary_exit: [
        'Bisa diajukan untuk hari ini atau tanggal mendatang.',
        'Tidak ada batas durasi keluar kantor.',
    ],
    leave: [
        'Wajib diajukan minimal H-7 sebelum tanggal cuti.',
        'Durasi 1–30 hari kalender.',
    ],
    emergency: [
        'Hanya berlaku untuk tanggal hari ini.',
        'Batas pengajuan maksimal pukul 08.30 WIB.',
        'Setelah pukul 08.30 WIB, ketidakhadiran dicatat sebagai Alpha oleh HRD dan tidak bisa dialihkan ke jenis izin lain.',
    ],
    sick: [
        'Bisa diajukan untuk hari ini atau tanggal mendatang.',
        'Lapor sebelum pukul 08.30 WIB dianggap normal; lapor pada/setelah itu di hari yang sama wajib centang kondisi darurat.',
        'Surat dokter wajib dilampirkan jika estimasi sakit lebih dari 1 hari.',
    ],
};

function KetentuanBox({ type }: { type: LeaveType }) {
    return (
        <div className="rounded-lg border-l-4 border-brand-yellow bg-brand-yellow/10 p-3 dark:bg-brand-yellow/10">
            <div className="font-semibold text-brand-navy dark:text-brand-yellow">
                Ketentuan {LEAVE_TYPE_LABELS[type]}:
            </div>
            <ul className="mt-1.5 list-disc space-y-1 pl-4 text-sm text-muted-foreground">
                {KETENTUAN[type].map((rule) => (
                    <li key={rule}>{rule}</li>
                ))}
            </ul>
        </div>
    );
}

export function IzinRequestForm({ onSuccess }: { onSuccess?: () => void }) {
    const [submitting, setSubmitting] = useState(false);

    const [type, setType] = useState<LeaveType | ''>('');
    const [leaveDate, setLeaveDate] = useState('');
    const [startTime, setStartTime] = useState('');
    const [endTime, setEndTime] = useState('');
    const [estimatedArrival, setEstimatedArrival] = useState('');
    const [duration, setDuration] = useState('');
    const [reason, setReason] = useState('');
    const [emergency, setEmergency] = useState(false);
    const [emergencyReason, setEmergencyReason] = useState('');
    const [contactable, setContactable] = useState<boolean | null>(null);
    const [agreement, setAgreement] = useState(false);
    const [attachments, setAttachments] = useState<File[]>([]);

    useEffect(() => {
        if (!type) {
            return;
        }

        const rule = DATE_RULES[type];

        if (rule.fixedToday) {
            setLeaveDate(todayIso());
        } else if (rule.min && (!leaveDate || leaveDate < rule.min)) {
            setLeaveDate(rule.min);
        }

        // Several fields are reused across types under different labels
        // (startTime is "Jam Mulai" for half_day but "Jam Keluar" for
        // temporary_exit; estimatedArrival is "Estimasi Jam Kedatangan"
        // for late but "Estimasi Jam Kembali" for temporary_exit;
        // duration is shared by leave and sick). Switching type must
        // clear them all, or a value typed for one type's field silently
        // reappears under a different type's differently-labeled field.
        // reason and attachments are intentionally left alone — they
        // stay meaningful even after picking a different type.
        setStartTime('');
        setEndTime('');
        setEstimatedArrival('');
        setDuration('');
        setEmergency(false);
        setEmergencyReason('');
        setContactable(null);
        setAgreement(false);
        // Only react to the type changing — leaveDate is intentionally
        // left out so typing in the date field doesn't get overwritten.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [type]);

    function resetForm() {
        setType('');
        setLeaveDate('');
        setStartTime('');
        setEndTime('');
        setEstimatedArrival('');
        setDuration('');
        setReason('');
        setEmergency(false);
        setEmergencyReason('');
        setContactable(null);
        setAgreement(false);
        setAttachments([]);
    }

    const showStartTime = type === 'half_day' || type === 'temporary_exit';
    const showEndTime = type === 'half_day';
    const showEstimatedArrival = type === 'late' || type === 'temporary_exit';
    const showDuration = type === 'leave' || type === 'sick';
    const showEmergencyCheckbox = type === 'late' || type === 'sick';
    const showContactable = type === 'emergency' || type === 'sick';
    const showAgreement = type === 'late';
    const dateIsFixed = type !== '' && DATE_RULES[type].fixedToday;

    // Every field a type actually shows is required — the only input that
    // stays optional for every type is the file attachment.
    const canSubmit =
        type !== '' &&
        leaveDate !== '' &&
        reason.trim() !== '' &&
        (!showStartTime || startTime !== '') &&
        (!showEndTime || endTime !== '') &&
        (!showEstimatedArrival || estimatedArrival !== '') &&
        (!showDuration || duration !== '') &&
        (!showContactable || contactable !== null) &&
        (!emergency || emergencyReason.trim() !== '') &&
        (!showAgreement || agreement);

    function handleSubmit() {
        if (!canSubmit || submitting) {
            return;
        }

        setSubmitting(true);

        const formData = new FormData();
        formData.append('type', type);
        formData.append('leave_date', leaveDate);
        formData.append('reason', reason);
        if (showStartTime) {
            formData.append('start_time', startTime);
        }
        if (showEndTime) {
            formData.append('end_time', endTime);
        }
        if (showEstimatedArrival) {
            formData.append('estimated_arrival', estimatedArrival);
        }
        if (showDuration) {
            formData.append('duration', duration);
        }
        if (showAgreement) {
            formData.append('agreement', agreement ? '1' : '0');
        }
        if (showEmergencyCheckbox) {
            formData.append('emergency', emergency ? '1' : '0');
            if (emergency && emergencyReason) {
                formData.append('emergency_reason', emergencyReason);
            }
        }
        if (showContactable) {
            formData.append('contactable', contactable ? '1' : '0');
        }
        attachments.forEach((file) => formData.append('attachments[]', file));

        router.post(LeaveRequestController.store.url(), formData, {
            forceFormData: true,
            onSuccess: () => {
                toast.success('Pengajuan izin berhasil dikirim.');
                resetForm();
                onSuccess?.();
            },
            onError: (errors) => {
                const message = Object.values(errors)[0];
                toast.error(
                    typeof message === 'string'
                        ? message
                        : 'Gagal mengirim pengajuan.',
                );
            },
            onFinish: () => setSubmitting(false),
        });
    }

    return (
        <div className="flex flex-col gap-4 rounded-2xl border bg-card p-4">
            <div>
                <h2 className="text-lg font-semibold">Ajukan Izin</h2>
                <p className="text-sm text-muted-foreground">
                    Pilih jenis izin — kolom di bawah menyesuaikan otomatis.
                </p>
            </div>

            <div className="grid gap-4">
                <div className="grid gap-1.5">
                    <Label htmlFor="izin-type">Jenis Izin</Label>
                    <Select
                        value={type}
                        onValueChange={(value) => setType(value as LeaveType)}
                    >
                        <SelectTrigger id="izin-type" className="w-full">
                            <SelectValue placeholder="Pilih jenis izin" />
                        </SelectTrigger>
                        <SelectContent>
                            {LEAVE_TYPES.map((leaveType) => {
                                const Icon = LEAVE_TYPE_ICONS[leaveType];

                                return (
                                    <SelectItem
                                        key={leaveType}
                                        value={leaveType}
                                    >
                                        <Icon className="size-4" />
                                        {LEAVE_TYPE_LABELS[leaveType]}
                                    </SelectItem>
                                );
                            })}
                        </SelectContent>
                    </Select>
                </div>

                {type && (
                    <>
                        <KetentuanBox type={type} />

                        <div className="grid gap-1.5">
                            <Label htmlFor="izin-date">Tanggal Izin</Label>
                            <Input
                                id="izin-date"
                                type="date"
                                value={leaveDate}
                                min={DATE_RULES[type].min}
                                disabled={dateIsFixed}
                                onChange={(e) => setLeaveDate(e.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">
                                {DATE_HINTS[type]}
                            </p>
                        </div>

                        {showStartTime && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="izin-start-time">
                                    {type === 'half_day'
                                        ? 'Jam Mulai'
                                        : 'Jam Keluar'}
                                </Label>
                                <Input
                                    id="izin-start-time"
                                    type="time"
                                    value={startTime}
                                    onChange={(e) =>
                                        setStartTime(e.target.value)
                                    }
                                />
                            </div>
                        )}

                        {showEndTime && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="izin-end-time">
                                    Jam Selesai
                                </Label>
                                <Input
                                    id="izin-end-time"
                                    type="time"
                                    value={endTime}
                                    onChange={(e) => setEndTime(e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Jam mulai bebas, tapi durasi maksimal 4 jam
                                    dari jam mulai.
                                </p>
                            </div>
                        )}

                        {showEstimatedArrival && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="izin-estimated-arrival">
                                    {type === 'late'
                                        ? 'Estimasi Jam Kedatangan'
                                        : 'Estimasi Jam Kembali'}
                                </Label>
                                <Input
                                    id="izin-estimated-arrival"
                                    type="time"
                                    value={estimatedArrival}
                                    onChange={(e) =>
                                        setEstimatedArrival(e.target.value)
                                    }
                                />
                                {type === 'late' && (
                                    <p className="text-xs text-muted-foreground">
                                        Maksimal jam 09:30.
                                    </p>
                                )}
                            </div>
                        )}

                        {showDuration && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="izin-duration">
                                    Durasi (hari)
                                </Label>
                                <Input
                                    id="izin-duration"
                                    type="number"
                                    min={1}
                                    max={type === 'leave' ? 30 : undefined}
                                    step={1}
                                    value={duration}
                                    onChange={(e) =>
                                        setDuration(e.target.value)
                                    }
                                />
                                {type === 'leave' && (
                                    <p className="text-xs text-muted-foreground">
                                        Maksimal 30 hari.
                                    </p>
                                )}
                            </div>
                        )}

                        {showContactable && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="izin-contactable">
                                    Bisa Dihubungi untuk Koordinasi?
                                </Label>
                                <Select
                                    value={
                                        contactable === null
                                            ? ''
                                            : contactable
                                              ? 'yes'
                                              : 'no'
                                    }
                                    onValueChange={(value) =>
                                        setContactable(value === 'yes')
                                    }
                                >
                                    <SelectTrigger
                                        id="izin-contactable"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih jawaban" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="yes">
                                            Bisa dihubungi
                                        </SelectItem>
                                        <SelectItem value="no">
                                            Tidak bisa dihubungi
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {showEmergencyCheckbox && (
                            <div className="grid gap-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="izin-emergency"
                                        checked={emergency}
                                        onCheckedChange={(checked) =>
                                            setEmergency(checked === true)
                                        }
                                    />
                                    <Label htmlFor="izin-emergency">
                                        Kondisi darurat
                                    </Label>
                                </div>
                                {emergency && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="izin-emergency-reason">
                                            Alasan Kondisi Darurat
                                        </Label>
                                        <Textarea
                                            id="izin-emergency-reason"
                                            placeholder="Jelaskan kondisi daruratnya"
                                            value={emergencyReason}
                                            onChange={(e) =>
                                                setEmergencyReason(
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="grid gap-1.5">
                            <Label htmlFor="izin-attachments">
                                {type === 'sick'
                                    ? 'Surat Dokter'
                                    : 'Dokumen Pendukung'}{' '}
                                {type === 'sick' && Number(duration) > 1
                                    ? '(wajib, sakit lebih dari 1 hari)'
                                    : '(opsional)'}
                            </Label>
                            <Input
                                id="izin-attachments"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                onChange={(e) =>
                                    setAttachments(
                                        Array.from(e.target.files ?? []),
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Format JPG, PNG, atau PDF, maksimal 5MB per
                                file.
                            </p>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="izin-reason">Alasan</Label>
                            <Textarea
                                id="izin-reason"
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                            />
                        </div>

                        {showAgreement && (
                            <div className="flex items-start gap-2">
                                <Checkbox
                                    id="izin-agreement"
                                    checked={agreement}
                                    onCheckedChange={(checked) =>
                                        setAgreement(checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="izin-agreement"
                                    className="font-normal"
                                >
                                    Saya memahami pengajuan ini memerlukan
                                    persetujuan HRD, kemudian Manager.
                                </Label>
                            </div>
                        )}
                    </>
                )}

                <Button
                    onClick={handleSubmit}
                    disabled={!canSubmit || submitting}
                    className="rounded-full"
                >
                    Kirim Pengajuan
                </Button>
            </div>
        </div>
    );
}
