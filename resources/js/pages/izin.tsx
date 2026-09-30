import { Head } from '@inertiajs/react';
import { CalendarOff } from 'lucide-react';
import { ComingSoon } from '@/components/coming-soon';
import { izin } from '@/routes';

export default function Izin() {
    return (
        <>
            <Head title="Izin" />
            <ComingSoon
                title="Izin"
                description="Pengajuan dan riwayat izin/cuti karyawan."
                icon={CalendarOff}
            />
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
