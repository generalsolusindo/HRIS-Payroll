import { Head, Link, router } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import LeaveRequestController from '@/actions/App/Http/Controllers/LeaveRequestController';
import { IzinRequestDialog } from '@/components/izin-request-dialog';
import {
    IzinStatusBadge,
    type IzinStatus,
} from '@/components/izin-status-badge';
import {
    LEAVE_TYPE_ICONS,
    LEAVE_TYPE_LABELS,
    type LeaveType,
} from '@/lib/izin';
import { izin } from '@/routes';

type LeaveRequestRow = {
    id: number;
    request_number: string;
    type: LeaveType;
    leave_date: string;
    status: IzinStatus;
};

type Props = {
    leaveRequests: LeaveRequestRow[];
};

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function IzinHistoryRow({ leaveRequest }: { leaveRequest: LeaveRequestRow }) {
    const Icon = LEAVE_TYPE_ICONS[leaveRequest.type];

    return (
        <Link
            href={LeaveRequestController.show(leaveRequest.id)}
            className="flex items-center justify-between gap-4 rounded-2xl border bg-card p-4 transition-colors hover:bg-muted/40"
        >
            <div className="flex items-center gap-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <Icon className="size-4" />
                </span>
                <div>
                    <div className="font-semibold">
                        {LEAVE_TYPE_LABELS[leaveRequest.type]}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {formatDate(leaveRequest.leave_date)}
                    </div>
                </div>
            </div>
            <IzinStatusBadge status={leaveRequest.status} />
        </Link>
    );
}

export default function Izin({ leaveRequests }: Props) {
    return (
        <>
            <Head title="Izin" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Izin
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Ajukan dan pantau izin Anda.
                        </p>
                    </div>
                    <IzinRequestDialog
                        onSuccess={() =>
                            router.reload({ only: ['leaveRequests'] })
                        }
                    />
                </div>

                {leaveRequests.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Inbox className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada pengajuan izin.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {leaveRequests.map((leaveRequest) => (
                            <IzinHistoryRow
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

Izin.layout = {
    breadcrumbs: [
        {
            title: 'Izin',
            href: izin(),
        },
    ],
};
