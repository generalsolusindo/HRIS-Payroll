import { Head } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { ComingSoon } from '@/components/coming-soon';
import { penggajian } from '@/routes';

export default function Penggajian() {
    return (
        <>
            <Head title="Penggajian" />
            <ComingSoon
                title="Penggajian"
                description="Rekap gaji dan slip pembayaran karyawan."
                icon={Wallet}
            />
        </>
    );
}

Penggajian.layout = {
    breadcrumbs: [
        {
            title: 'Penggajian',
            href: penggajian(),
        },
    ],
};
