import { Head } from '@inertiajs/react';
import { CalendarOff } from 'lucide-react';
import { ComingSoon } from '@/components/coming-soon';
import { izin } from '@/routes';

export default function IzinDetail() {
    return (
        <>
            <Head title="Detail Izin" />
            <ComingSoon
                title="Detail Izin"
                description="Rincian satu pengajuan izin."
                icon={CalendarOff}
            />
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
