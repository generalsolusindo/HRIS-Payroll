import { Head, Link } from '@inertiajs/react';
import { Eye, Wallet } from 'lucide-react';
import PenggajianController from '@/actions/App/Http/Controllers/PenggajianController';
import { Button } from '@/components/ui/button';

type PayslipRow = {
    id: number;
    number: string;
    period_name: string;
    net_amount: string;
    published_at: string | null;
};

type Props = {
    payslips: PayslipRow[];
};

function formatRupiah(value: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function Penggajian({ payslips }: Props) {
    return (
        <>
            <Head title="Penggajian" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Penggajian
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Slip gaji yang sudah diterbitkan HRD untuk kamu.
                    </p>
                </div>

                {payslips.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Wallet className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada slip gaji yang diterbitkan.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Periode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Nomor Slip
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Net
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Diterbitkan
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {payslips.map((payslip) => (
                                    <tr key={payslip.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {payslip.period_name}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {payslip.number}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRupiah(payslip.net_amount)}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {formatDate(payslip.published_at)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={PenggajianController.show(
                                                    payslip.id,
                                                )}
                                            >
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="rounded-full"
                                                >
                                                    <Eye className="size-3.5" />
                                                    Lihat Slip
                                                </Button>
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

Penggajian.layout = {
    breadcrumbs: [
        {
            title: 'Penggajian',
            href: PenggajianController.index(),
        },
    ],
};
