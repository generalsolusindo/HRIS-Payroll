import { Head } from '@inertiajs/react';
import { CalendarOff } from 'lucide-react';
import LeaveRequestApprovalController from '@/actions/App/Http/Controllers/Manager/LeaveRequestApprovalController';
import { ComingSoon } from '@/components/coming-soon';

export default function LeaveRequestApproval() {
    return (
        <>
            <Head title="Persetujuan Izin" />
            <ComingSoon
                title="Persetujuan Izin"
                description="Keputusan akhir atas pengajuan izin yang sudah disetujui HRD."
                icon={CalendarOff}
            />
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
