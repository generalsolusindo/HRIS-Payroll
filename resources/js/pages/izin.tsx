import { Head } from '@inertiajs/react';
import { IzinRequestDialog } from '@/components/izin-request-dialog';
import { izin } from '@/routes';

export default function Izin() {
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
                    <IzinRequestDialog />
                </div>

                <p className="text-sm text-muted-foreground">
                    Riwayat pengajuan akan tampil di sini.
                </p>
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
