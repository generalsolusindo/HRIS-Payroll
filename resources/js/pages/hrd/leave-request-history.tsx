import { Head, router } from '@inertiajs/react';
import { FileText, History } from 'lucide-react';
import { useState } from 'react';
import LeaveRequestApprovalController from '@/actions/App/Http/Controllers/Hrd/LeaveRequestApprovalController';
import LeaveRequestAttachmentController from '@/actions/App/Http/Controllers/LeaveRequestAttachmentController';
import { SummaryStat } from '@/components/attendance-summary-stat';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { LEAVE_TYPE_LABELS, type LeaveType } from '@/lib/izin';

type Attachment = {
    id: number;
    file_name: string;
};

type LeaveRequestRow = {
    id: number;
    request_number: string;
    type: LeaveType;
    leave_date: string;
    start_time: string | null;
    end_time: string | null;
    estimated_arrival: string | null;
    duration: string | null;
    reason: string | null;
    emergency: boolean;
    emergency_reason: string | null;
    status: string;
    user: {
        name: string;
        division: { name: string } | null;
    };
    hrd_processed_by: { name: string } | null;
    hrd_decision: string | null;
    hrd_note: string | null;
    processed_by: { name: string } | null;
    approval_note: string | null;
    rejection_reason: string | null;
    attachments: Attachment[];
};

type PaginatedLeaveRequests = {
    data: LeaveRequestRow[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
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
    leaveRequests: PaginatedLeaveRequests;
    stats: Stats;
    filters: {
        search?: string;
        type?: string;
        division_id?: string;
        status?: string;
    };
    divisions: Division[];
    leaveTypes: Record<string, string>;
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Menunggu HRD',
    pending_manager: 'Menunggu Manager',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'destructive'> =
    {
        pending: 'secondary',
        pending_manager: 'secondary',
        approved: 'default',
        rejected: 'destructive',
    };

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function processedBySummary(leaveRequest: LeaveRequestRow): string {
    if (leaveRequest.processed_by) {
        return leaveRequest.processed_by.name;
    }
    if (leaveRequest.hrd_processed_by) {
        return `${leaveRequest.hrd_processed_by.name} (HRD)`;
    }
    return '-';
}

export default function LeaveRequestHistory({
    leaveRequests,
    stats,
    filters,
    divisions,
    leaveTypes,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? '');
    const [divisionId, setDivisionId] = useState(filters.division_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selected, setSelected] = useState<LeaveRequestRow | null>(null);

    function applyFilters(next: Partial<Props['filters']>) {
        router.get(
            LeaveRequestApprovalController.riwayat().url,
            {
                search: next.search ?? search,
                type: next.type ?? type,
                division_id: next.division_id ?? divisionId,
                status: next.status ?? status,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function goToPage(url: string | null) {
        if (!url) {
            return;
        }
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Riwayat Izin" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Riwayat Izin
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Seluruh pengajuan izin perusahaan ini, apapun
                            statusnya.
                        </p>
                    </div>
                    <Button variant="outline" className="rounded-full" asChild>
                        <a href={LeaveRequestApprovalController.laporan().url}>
                            Unduh Laporan (CSV)
                        </a>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <SummaryStat
                        label="Total"
                        value={stats.total}
                        tone="navy"
                    />
                    <SummaryStat
                        label="Menunggu HRD"
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
                        value={status || 'all'}
                        onValueChange={(value) => {
                            const next = value === 'all' ? '' : value;
                            setStatus(next);
                            applyFilters({ status: next });
                        }}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            {Object.entries(STATUS_LABELS).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>

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
                                        {label ??
                                            LEAVE_TYPE_LABELS[
                                                value as LeaveType
                                            ]}
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

                {leaveRequests.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <History className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Tidak ada pengajuan izin yang cocok dengan filter
                            ini.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        No. Pengajuan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Nama Pengaju
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Jenis Izin
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal Izin
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Diproses oleh
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {leaveRequests.data.map((leaveRequest) => (
                                    <tr
                                        key={leaveRequest.id}
                                        onClick={() =>
                                            setSelected(leaveRequest)
                                        }
                                        className="cursor-pointer hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {leaveRequest.request_number}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div>{leaveRequest.user.name}</div>
                                            <div className="text-xs text-muted-foreground">
                                                                {leaveRequest.user.division?.name ??
                                                    '-'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {LEAVE_TYPE_LABELS[
                                                leaveRequest.type
                                            ] ?? leaveRequest.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDate(
                                                leaveRequest.leave_date,
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANTS[
                                                        leaveRequest.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {STATUS_LABELS[
                                                    leaveRequest.status
                                                ] ?? leaveRequest.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {processedBySummary(leaveRequest)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {leaveRequests.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Halaman {leaveRequests.current_page} dari{' '}
                            {leaveRequests.last_page} · {leaveRequests.total}{' '}
                            pengajuan
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!leaveRequests.prev_page_url}
                                onClick={() =>
                                    goToPage(leaveRequests.prev_page_url)
                                }
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!leaveRequests.next_page_url}
                                onClick={() =>
                                    goToPage(leaveRequests.next_page_url)
                                }
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    {selected && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {selected.request_number}
                                </DialogTitle>
                            </DialogHeader>

                            <div className="flex flex-col gap-4 text-sm">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="font-medium">
                                            {selected.user.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {selected.user.division?.name ??
                                                '-'}
                                        </div>
                                    </div>
                                    <Badge
                                        variant={
                                            STATUS_VARIANTS[
                                                selected.status
                                            ] ?? 'secondary'
                                        }
                                    >
                                        {STATUS_LABELS[selected.status] ??
                                            selected.status}
                                    </Badge>
                                </div>

                                <dl className="grid grid-cols-2 gap-x-4 gap-y-2">
                                    <dt className="text-muted-foreground">
                                        Jenis Izin
                                    </dt>
                                    <dd>
                                        {LEAVE_TYPE_LABELS[selected.type] ??
                                            selected.type}
                                    </dd>

                                    <dt className="text-muted-foreground">
                                        Tanggal Izin
                                    </dt>
                                    <dd>{formatDate(selected.leave_date)}</dd>

                                    {selected.start_time && (
                                        <>
                                            <dt className="text-muted-foreground">
                                                Jam
                                            </dt>
                                            <dd>
                                                {selected.start_time}
                                                {selected.end_time
                                                    ? ` – ${selected.end_time}`
                                                    : ''}
                                            </dd>
                                        </>
                                    )}

                                    {selected.estimated_arrival && (
                                        <>
                                            <dt className="text-muted-foreground">
                                                Estimasi Tiba
                                            </dt>
                                            <dd>
                                                {selected.estimated_arrival}
                                            </dd>
                                        </>
                                    )}

                                    {selected.duration && (
                                        <>
                                            <dt className="text-muted-foreground">
                                                Durasi
                                            </dt>
                                            <dd>{selected.duration} hari</dd>
                                        </>
                                    )}

                                    {selected.emergency && (
                                        <>
                                            <dt className="text-muted-foreground">
                                                Darurat
                                            </dt>
                                            <dd>
                                                {selected.emergency_reason ??
                                                    'Ya'}
                                            </dd>
                                        </>
                                    )}
                                </dl>

                                {selected.reason && (
                                    <div>
                                        <div className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                            Alasan
                                        </div>
                                        <p className="whitespace-pre-wrap">
                                            {selected.reason}
                                        </p>
                                    </div>
                                )}

                                {selected.attachments.length > 0 && (
                                    <div>
                                        <div className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                            Lampiran
                                        </div>
                                        <div className="flex flex-col gap-1">
                                            {selected.attachments.map(
                                                (attachment) => (
                                                    <a
                                                        key={attachment.id}
                                                        href={
                                                            LeaveRequestAttachmentController.show(
                                                                attachment.id,
                                                            ).url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="flex items-center gap-2 text-primary hover:underline"
                                                    >
                                                        <FileText className="size-4" />
                                                        {attachment.file_name}
                                                    </a>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}

                                {(selected.hrd_note ||
                                    selected.hrd_decision) && (
                                    <div className="rounded-lg border bg-muted/30 p-3">
                                        <div className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                            Catatan HRD
                                            {selected.hrd_processed_by
                                                ? ` — ${selected.hrd_processed_by.name}`
                                                : ''}
                                        </div>
                                        {selected.hrd_note && (
                                            <p className="whitespace-pre-wrap">
                                                {selected.hrd_note}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {(selected.approval_note ||
                                    selected.rejection_reason) && (
                                    <div className="rounded-lg border bg-muted/30 p-3">
                                        <div className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                            Catatan Manager
                                            {selected.processed_by
                                                ? ` — ${selected.processed_by.name}`
                                                : ''}
                                        </div>
                                        <p className="whitespace-pre-wrap">
                                            {selected.approval_note ??
                                                selected.rejection_reason}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

LeaveRequestHistory.layout = {
    breadcrumbs: [
        {
            title: 'Riwayat Izin',
            href: LeaveRequestApprovalController.riwayat(),
        },
    ],
};
