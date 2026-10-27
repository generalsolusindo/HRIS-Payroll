import { Form, Head, router } from '@inertiajs/react';
import { CalendarOff, FileText } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import LeaveRequestApprovalController from '@/actions/App/Http/Controllers/Manager/LeaveRequestApprovalController';
import LeaveRequestController from '@/actions/App/Http/Controllers/LeaveRequestController';
import LeaveRequestAttachmentController from '@/actions/App/Http/Controllers/LeaveRequestAttachmentController';
import { SummaryStat } from '@/components/attendance-summary-stat';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import {
    LEAVE_TYPE_ICONS,
    LEAVE_TYPE_LABELS,
    type LeaveRequestUserRef,
    type LeaveType,
} from '@/lib/izin';

type Attachment = {
    id: number;
    file_name: string;
    file_size: number;
};

type PendingLeaveRequest = {
    id: number;
    request_number: string;
    type: LeaveType;
    leave_date: string;
    start_time: string | null;
    estimated_arrival: string | null;
    duration: string | null;
    reason: string | null;
    emergency: boolean;
    emergency_reason: string | null;
    contactable: boolean | null;
    hrd_processed_by: LeaveRequestUserRef;
    hrd_processed_at: string | null;
    hrd_note: string | null;
    user: {
        name: string;
        company: { name: string };
        division: { name: string } | null;
    };
    attachments: Attachment[];
};

type Stats = {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
    today: number;
};

type Division = { id: number; name: string };

type Props = {
    leaveRequests: PendingLeaveRequest[];
    stats: Stats;
    filters: { search?: string; type?: string; division_id?: string };
    divisions: Division[];
    leaveTypes: Record<string, string>;
};

function initialsOf(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatTime(value: string): string {
    return value.slice(0, 5);
}

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function RejectDialog({
    leaveRequest,
    open,
    onOpenChange,
}: {
    leaveRequest: PendingLeaveRequest;
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
            LeaveRequestApprovalController.reject.url(leaveRequest.id),
            { note },
            {
                onSuccess: () => {
                    setNote('');
                    onOpenChange(false);
                },
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    toast.error(
                        typeof message === 'string'
                            ? message
                            : 'Gagal menolak pengajuan.',
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
                    <DialogTitle>Tolak Pengajuan</DialogTitle>
                    <DialogDescription>
                        Tolak pengajuan {LEAVE_TYPE_LABELS[leaveRequest.type]}{' '}
                        dari {leaveRequest.user.name}. Catatan wajib diisi
                        sebagai alasan penolakan.
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
                        Tolak Pengajuan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function PendingLeaveRequestCard({
    leaveRequest,
}: {
    leaveRequest: PendingLeaveRequest;
}) {
    const [rejectOpen, setRejectOpen] = useState(false);
    const Icon = LEAVE_TYPE_ICONS[leaveRequest.type];

    const showStartTime =
        leaveRequest.type === 'half_day' ||
        leaveRequest.type === 'temporary_exit';
    const showEstimatedArrival =
        leaveRequest.type === 'late' || leaveRequest.type === 'temporary_exit';
    const showDuration =
        leaveRequest.type === 'leave' || leaveRequest.type === 'sick';
    const showContactable =
        leaveRequest.type === 'emergency' || leaveRequest.type === 'sick';

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() =>
                router.visit(LeaveRequestController.show(leaveRequest.id))
            }
            onKeyDown={(e) => {
                if (e.key === 'Enter') {
                    router.visit(
                        LeaveRequestController.show(leaveRequest.id),
                    );
                }
            }}
            className="flex cursor-pointer flex-col gap-4 rounded-2xl border bg-card p-4 transition-colors hover:bg-muted/40"
        >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex items-start gap-3">
                    <Avatar className="size-11">
                        <AvatarFallback className="bg-brand-navy/8 font-semibold text-brand-navy dark:bg-brand-navy/35 dark:text-foreground">
                            {initialsOf(leaveRequest.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <div className="font-semibold">
                            {leaveRequest.user.name}
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {leaveRequest.request_number}
                        </div>
                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                            <span className="rounded-full bg-brand-navy/8 px-2.5 py-0.5 text-xs font-medium dark:bg-brand-navy/35">
                                {leaveRequest.user.company.name}
                            </span>
                            <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                {leaveRequest.user.division?.name ?? '-'}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    className="flex gap-2"
                    onClick={(e) => e.stopPropagation()}
                >
                    <Form
                        {...LeaveRequestApprovalController.approve.form(
                            leaveRequest.id,
                        )}
                        disableWhileProcessing
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
                        onClick={() => setRejectOpen(true)}
                    >
                        Tolak
                    </Button>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2 border-t pt-3 text-sm">
                <span className="flex size-7 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <Icon className="size-3.5" />
                </span>
                <span className="font-medium">
                    {LEAVE_TYPE_LABELS[leaveRequest.type]}
                </span>
                <span className="text-muted-foreground">
                    · {formatDate(leaveRequest.leave_date)}
                </span>
                {showStartTime && leaveRequest.start_time && (
                    <span className="text-muted-foreground">
                        · {formatTime(leaveRequest.start_time)}
                    </span>
                )}
                {showEstimatedArrival && leaveRequest.estimated_arrival && (
                    <span className="text-muted-foreground">
                        · est. {formatTime(leaveRequest.estimated_arrival)}
                    </span>
                )}
                {showDuration && leaveRequest.duration && (
                    <span className="text-muted-foreground">
                        · {Number(leaveRequest.duration)} hari
                    </span>
                )}
                {showContactable && (
                    <span className="text-muted-foreground">
                        ·{' '}
                        {leaveRequest.contactable
                            ? 'Bisa dihubungi'
                            : 'Tidak bisa dihubungi'}
                    </span>
                )}
                {leaveRequest.emergency && (
                    <span className="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
                        Darurat
                    </span>
                )}
            </div>

            {(leaveRequest.reason || leaveRequest.emergency_reason) && (
                <p className="text-sm text-muted-foreground">
                    {leaveRequest.reason ?? leaveRequest.emergency_reason}
                </p>
            )}

            {leaveRequest.attachments.length > 0 && (
                <div
                    className="flex flex-wrap gap-2"
                    onClick={(e) => e.stopPropagation()}
                >
                    {leaveRequest.attachments.map((attachment) => (
                        <a
                            key={attachment.id}
                            href={
                                LeaveRequestAttachmentController.show(
                                    attachment.id,
                                ).url
                            }
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors hover:bg-muted/40"
                        >
                            <FileText className="size-3.5 text-muted-foreground" />
                            {attachment.file_name}
                        </a>
                    ))}
                </div>
            )}

            {leaveRequest.hrd_processed_by && (
                <div className="rounded-xl bg-muted/40 p-3 text-sm">
                    <div className="font-medium">
                        Disetujui HRD — {leaveRequest.hrd_processed_by.name}
                    </div>
                    {leaveRequest.hrd_processed_at && (
                        <div className="text-xs text-muted-foreground">
                            {formatDateTime(leaveRequest.hrd_processed_at)}
                        </div>
                    )}
                    {leaveRequest.hrd_note && (
                        <p className="mt-1 text-muted-foreground">
                            "{leaveRequest.hrd_note}"
                        </p>
                    )}
                </div>
            )}

            <RejectDialog
                leaveRequest={leaveRequest}
                open={rejectOpen}
                onOpenChange={setRejectOpen}
            />
        </div>
    );
}

export default function LeaveRequestApproval({
    leaveRequests,
    stats,
    filters,
    divisions,
    leaveTypes,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? '');
    const [divisionId, setDivisionId] = useState(filters.division_id ?? '');

    function applyFilters(next: Partial<Props['filters']>) {
        router.get(
            LeaveRequestApprovalController.index().url,
            {
                search: next.search ?? search,
                type: next.type ?? type,
                division_id: next.division_id ?? divisionId,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Persetujuan Izin" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Persetujuan Izin
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Keputusan akhir atas pengajuan izin yang sudah disetujui
                        HRD.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <SummaryStat
                        label="Total"
                        value={stats.total}
                        tone="navy"
                    />
                    <SummaryStat
                        label="Menunggu Manager"
                        value={stats.pending}
                        tone="yellow"
                    />
                    <SummaryStat
                        label="Disetujui"
                        value={stats.approved}
                        tone="muted"
                    />
                    <SummaryStat
                        label="Ditolak"
                        value={stats.rejected}
                        tone="red"
                        emphasize
                    />
                    <SummaryStat
                        label="Hari Ini"
                        value={stats.today}
                        tone="muted"
                    />
                </div>

                <div className="flex flex-wrap gap-3">
                    <Input
                        placeholder="Cari nama / nomor pengajuan..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') applyFilters({});
                        }}
                        onBlur={() => applyFilters({})}
                        className="w-64"
                    />

                    <Select
                        value={type || 'all'}
                        onValueChange={(value) => {
                            const next = value === 'all' ? '' : value;
                            setType(next);
                            applyFilters({ type: next });
                        }}
                    >
                        <SelectTrigger className="w-56">
                            <SelectValue placeholder="Semua jenis izin" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Semua jenis izin
                            </SelectItem>
                            {Object.entries(leaveTypes).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>

                    <Select
                        value={divisionId || 'all'}
                        onValueChange={(value) => {
                            const next = value === 'all' ? '' : value;
                            setDivisionId(next);
                            applyFilters({ division_id: next });
                        }}
                    >
                        <SelectTrigger className="w-56">
                            <SelectValue placeholder="Semua jabatan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua jabatan</SelectItem>
                            {divisions.map((division) => (
                                <SelectItem
                                    key={division.id}
                                    value={String(division.id)}
                                >
                                    {division.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {leaveRequests.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <CalendarOff className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Tidak ada pengajuan izin yang menunggu persetujuan.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {leaveRequests.map((leaveRequest) => (
                            <PendingLeaveRequestCard
                                key={leaveRequest.id}
                                leaveRequest={leaveRequest}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

LeaveRequestApproval.layout = {
    breadcrumbs: [
        {
            title: 'Persetujuan Izin',
            href: LeaveRequestApprovalController.index(),
        },
    ],
};
