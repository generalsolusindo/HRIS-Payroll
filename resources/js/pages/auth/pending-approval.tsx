import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { logout } from '@/routes';

type Props = {
    status: string;
};

export default function PendingApproval({ status }: Props) {
    const message =
        status === 'pending'
            ? 'Akun Anda berhasil didaftarkan dan sedang menunggu persetujuan HRD. Silakan coba masuk lagi setelah akun disetujui.'
            : 'Akun Anda saat ini tidak aktif. Silakan hubungi HRD untuk informasi lebih lanjut.';

    return (
        <>
            <Head title="Menunggu Persetujuan" />

            <div className="text-center text-sm text-muted-foreground">
                {message}
            </div>

            <TextLink
                href={logout()}
                className="mx-auto mt-6 block text-center text-sm"
            >
                Keluar
            </TextLink>
        </>
    );
}

PendingApproval.layout = {
    title: 'Menunggu Persetujuan',
    description: 'Akun Anda sedang diproses oleh HRD',
};
