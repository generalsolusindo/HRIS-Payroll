import { Head } from '@inertiajs/react';
import { CalendarOff } from 'lucide-react';
import LeaveRequestApprovalController from '@/actions/App/Http/Controllers/Hrd/LeaveRequestApprovalController';
import { ComingSoon } from '@/components/coming-soon';

export default function LeaveRequestApproval() {
    return (
        <>
            <Head title="Persetujuan Izin" />
            <ComingSoon
                title="Persetujuan Izin"
                description="Tinjau dan proses pengajuan izin karyawan."
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
