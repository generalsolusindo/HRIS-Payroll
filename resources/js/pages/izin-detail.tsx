import { Head } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import LeaveRequestAttachmentController from '@/actions/App/Http/Controllers/LeaveRequestAttachmentController';
import {
    IzinStatusBadge,
    type IzinStatus,
} from '@/components/izin-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    LEAVE_TYPE_ICONS,
    LEAVE_TYPE_LABELS,
    type LeaveRequestUserRef,
    type LeaveType,
} from '@/lib/izin';
import { izin } from '@/routes';

type Attachment = {
    id: number;
    file_name: string;
    mime_type: string;
    file_size: number;
};

type LeaveRequestDetail = {
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
    status: IzinStatus;
    processed_by: LeaveRequestUserRef;
    processed_at: string | null;
    approval_note: string | null;
    rejection_reason: string | null;
    hrd_processed_by: LeaveRequestUserRef;
    hrd_processed_at: string | null;
    hrd_decision: 'approved' | 'rejected' | null;
    hrd_note: string | null;
    user: {
        name: string;
        company: { name: string } | null;
        division: { name: string } | null;
    };
    attachments: Attachment[];
};

type Props = {
    leaveRequest: LeaveRequestDetail;
};

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
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

function formatTime(value: string): string {
    return value.slice(0, 5);
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

export default function IzinDetail({ leaveRequest }: Props) {
    const Icon = LEAVE_TYPE_ICONS[leaveRequest.type];

    const showStartTime =
        leaveRequest.type === 'half_day' ||
        leaveRequest.type === 'temporary_exit';
    const showEndTime = leaveRequest.type === 'half_day';
    const showEstimatedArrival =
        leaveRequest.type === 'late' || leaveRequest.type === 'temporary_exit';
    const showDuration =
        leaveRequest.type === 'leave' || leaveRequest.type === 'sick';
    const showContactable =
        leaveRequest.type === 'emergency' || leaveRequest.type === 'sick';

    return (
        <>
            <Head title={`Detail Izin — ${leaveRequest.request_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="-ml-2 w-fit"
                    onClick={() => window.history.back()}
                >
                    <ArrowLeft className="size-4" />
                    Kembali
                </Button>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Icon className="size-4" />
                            </span>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                    {LEAVE_TYPE_LABELS[leaveRequest.type]}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {leaveRequest.request_number}
                                </p>
                            </div>
                        </div>
                    </div>
                    <IzinStatusBadge status={leaveRequest.status} />
                </div>

                <Card className="rounded-3xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Detail Pengajuan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <DetailRow
                            label="Pemohon"
                            value={leaveRequest.user.name}
                        />
                        <DetailRow
                            label="Instansi"
                            value={leaveRequest.user.company?.name ?? '-'}
                        />
                        <DetailRow
                            label="Jabatan"
                            value={leaveRequest.user.division?.name ?? '-'}
                        />
                        <DetailRow
                            label="Tanggal Izin"
                            value={formatDate(leaveRequest.leave_date)}
                        />
                        {showStartTime && leaveRequest.start_time && (
                            <DetailRow
                                label={leaveRequest.type === 'half_day' ? 'Jam Mulai' : 'Jam Keluar'}
                                value={formatTime(leaveRequest.start_time)}
                            />
                        )}
                        {showEndTime && leaveRequest.end_time && (
                            <DetailRow
                                label='Jam Selesai'
                                value={formatTime(leaveRequest.end_time)}
                            />
                        )}
                        {showEstimatedArrival &&
                            leaveRequest.estimated_arrival && (
                                <DetailRow
                                    label={
                                        leaveRequest.type === 'late'
                                            ? 'Estimasi Kedatangan'
                                            : 'Estimasi Kembali'
                                    }
                                    value={formatTime(
                                        leaveRequest.estimated_arrival,
                                    )}
                                />
                            )}
                        {showDuration && leaveRequest.duration && (
                            <DetailRow
                                label="Durasi"
                                value={`${Number(leaveRequest.duration)} hari`}
                            />
                        )}
                        {showContactable && (
                            <DetailRow
                                label="Bisa Dihubungi"
                                value={
                                    leaveRequest.contactable ? 'Ya' : 'Tidak'
                                }
                            />
                        )}
                        {leaveRequest.emergency && (
                            <DetailRow label="Kondisi Darurat" value="Ya" />
                        )}
                        {leaveRequest.reason && (
                            <div className="pt-2">
                                <div className="text-sm text-muted-foreground">
                                    Alasan
                                </div>
                                <p className="mt-1 text-sm">
                                    {leaveRequest.reason}
                                </p>
                            </div>
                        )}
                        {leaveRequest.emergency_reason && (
                            <div className="pt-2">
                                <div className="text-sm text-muted-foreground">
                                    Keterangan Darurat
                                </div>
                                <p className="mt-1 text-sm">
                                    {leaveRequest.emergency_reason}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {leaveRequest.attachments.length > 0 && (
                    <Card className="rounded-3xl">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Lampiran
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
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
                                    className="flex items-center gap-3 rounded-2xl border p-3 text-sm transition-colors hover:bg-muted/40"
                                >
                                    <FileText className="size-4 text-muted-foreground" />
                                    <span className="flex-1 font-medium">
                                        {attachment.file_name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {formatFileSize(attachment.file_size)}
                                    </span>
                                </a>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {(leaveRequest.hrd_processed_by ||
                    leaveRequest.processed_by) && (
                    <Card className="rounded-3xl">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Jejak Keputusan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {leaveRequest.hrd_processed_by && (
                                <div className="space-y-1">
                                    <DetailRow
                                        label="HRD"
                                        value={
                                            leaveRequest.hrd_processed_by.name
                                        }
                                    />
                                    <DetailRow
                                        label="Keputusan"
                                        value={
                                            leaveRequest.hrd_decision ===
                                            'approved'
                                                ? 'Disetujui'
                                                : 'Ditolak'
                                        }
                                    />
                                    {leaveRequest.hrd_processed_at && (
                                        <DetailRow
                                            label="Waktu"
                                            value={formatDateTime(
                                                leaveRequest.hrd_processed_at,
                                            )}
                                        />
                                    )}
                                    {leaveRequest.hrd_note && (
                                        <p className="pt-1 text-sm text-muted-foreground">
                                            "{leaveRequest.hrd_note}"
                                        </p>
                                    )}
                                </div>
                            )}

                            {/* HRD rejecting is final by itself and also
                                fills processed_by with the same HRD user —
                                only show this as a separate "Manager" step
                                when HRD actually approved and passed it on. */}
                            {leaveRequest.processed_by &&
                                leaveRequest.hrd_decision === 'approved' && (
                                    <div className="space-y-1 border-t pt-4">
                                        <DetailRow
                                            label="Manager"
                                            value={
                                                leaveRequest.processed_by.name
                                            }
                                        />
                                        <DetailRow
                                            label="Keputusan"
                                            value={
                                                leaveRequest.status ===
                                                'approved'
                                                    ? 'Disetujui'
                                                    : 'Ditolak'
                                            }
                                        />
                                        {leaveRequest.processed_at && (
                                            <DetailRow
                                                label="Waktu"
                                                value={formatDateTime(
                                                    leaveRequest.processed_at,
                                                )}
                                            />
                                        )}
                                        {(leaveRequest.approval_note ||
                                            leaveRequest.rejection_reason) && (
                                            <p className="pt-1 text-sm text-muted-foreground">
                                                "
                                                {leaveRequest.approval_note ??
                                                    leaveRequest.rejection_reason}
                                                "
                                            </p>
                                        )}
                                    </div>
                                )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

IzinDetail.layout = {
    breadcrumbs: [
        {
            title: 'Izin',
            href: izin(),
        },
    ],
};
