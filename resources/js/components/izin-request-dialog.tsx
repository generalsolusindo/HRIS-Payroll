import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import LeaveRequestController from '@/actions/App/Http/Controllers/LeaveRequestController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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

export function IzinRequestDialog({ onSuccess }: { onSuccess?: () => void }) {
    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const [type, setType] = useState<LeaveType | ''>('');
    const [leaveDate, setLeaveDate] = useState('');
    const [startTime, setStartTime] = useState('');
    const [estimatedArrival, setEstimatedArrival] = useState('');
    const [duration, setDuration] = useState('');
    const [reason, setReason] = useState('');
    const [emergency, setEmergency] = useState(false);
    const [emergencyReason, setEmergencyReason] = useState('');
    const [contactable, setContactable] = useState(false);
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
        // Only react to the type changing — leaveDate is intentionally
        // left out so typing in the date field doesn't get overwritten.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [type]);

    function resetForm() {
        setType('');
        setLeaveDate('');
        setStartTime('');
        setEstimatedArrival('');
        setDuration('');
        setReason('');
        setEmergency(false);
        setEmergencyReason('');
        setContactable(false);
        setAttachments([]);
    }

    const showStartTime = type === 'half_day' || type === 'temporary_exit';
    const showEstimatedArrival = type === 'late' || type === 'temporary_exit';
    const showDuration = type === 'leave' || type === 'sick';
    const showEmergencyCheckbox = type === 'late' || type === 'sick';
    const showContactable = type === 'emergency' || type === 'sick';
    const showAttachments = type === 'sick';
    const dateIsFixed = type !== '' && DATE_RULES[type].fixedToday;

    function handleSubmit() {
        if (!type || submitting) {
            return;
        }

        setSubmitting(true);

        const formData = new FormData();
        formData.append('type', type);
        formData.append('leave_date', leaveDate);
        if (showStartTime) {
            formData.append('start_time', startTime);
        }
        if (showEstimatedArrival) {
            formData.append('estimated_arrival', estimatedArrival);
        }
        if (showDuration) {
            formData.append('duration', duration);
        }
        if (reason) {
            formData.append('reason', reason);
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
        if (showAttachments) {
            attachments.forEach((file) =>
                formData.append('attachments[]', file),
            );
        }

        router.post(LeaveRequestController.store.url(), formData, {
            forceFormData: true,
            onSuccess: () => {
                toast.success('Pengajuan izin berhasil dikirim.');
                resetForm();
                setOpen(false);
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
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    resetForm();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button className="rounded-full">Ajukan Izin</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Ajukan Izin</DialogTitle>
                    <DialogDescription>
                        Pilih jenis izin — kolom di bawah menyesuaikan otomatis.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="izin-type">Jenis Izin</Label>
                        <Select
                            value={type}
                            onValueChange={(value) =>
                                setType(value as LeaveType)
                            }
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
                            <div className="grid gap-1.5">
                                <Label htmlFor="izin-date">Tanggal Izin</Label>
                                <Input
                                    id="izin-date"
                                    type="date"
                                    value={leaveDate}
                                    min={DATE_RULES[type].min}
                                    disabled={dateIsFixed}
                                    onChange={(e) =>
                                        setLeaveDate(e.target.value)
                                    }
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
                                    {type === 'half_day' && (
                                        <p className="text-xs text-muted-foreground">
                                            Minimal jam 12:30.
                                        </p>
                                    )}
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
                                        step={1}
                                        value={duration}
                                        onChange={(e) =>
                                            setDuration(e.target.value)
                                        }
                                    />
                                </div>
                            )}

                            {showContactable && (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="izin-contactable"
                                        checked={contactable}
                                        onCheckedChange={(checked) =>
                                            setContactable(checked === true)
                                        }
                                    />
                                    <Label htmlFor="izin-contactable">
                                        Bisa dihubungi untuk koordinasi
                                    </Label>
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
                                        <Textarea
                                            placeholder="Jelaskan kondisi daruratnya (opsional)"
                                            value={emergencyReason}
                                            onChange={(e) =>
                                                setEmergencyReason(
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </div>
                            )}

                            {showAttachments && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="izin-attachments">
                                        Surat Dokter{' '}
                                        {Number(duration) > 1
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
                                                Array.from(
                                                    e.target.files ?? [],
                                                ),
                                            )
                                        }
                                    />
                                </div>
                            )}

                            <div className="grid gap-1.5">
                                <Label htmlFor="izin-reason">
                                    Alasan (opsional)
                                </Label>
                                <Textarea
                                    id="izin-reason"
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                />
                            </div>
                        </>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        onClick={handleSubmit}
                        disabled={!type || submitting}
                        className="rounded-full"
                    >
                        Kirim Pengajuan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
