import { Head, Link } from '@inertiajs/react';
import { Pencil, Users } from 'lucide-react';
import PayrollProfileController from '@/actions/App/Http/Controllers/Hrd/PayrollProfileController';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';

type EmployeeRow = {
    id: number;
    name: string;
    email: string;
    has_profile: boolean;
    is_payroll_eligible: boolean;
    division_name: string | null;
    monthly_total: string | null;
};

type Props = {
    employees: EmployeeRow[];
};

function initialsOf(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function formatRupiah(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

export default function PayrollProfiles({ employees }: Props) {
    return (
        <>
            <Head title="Profil Gaji" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Profil Gaji
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Atur divisi dan komponen gaji tiap karyawan — dasar
                        perhitungan payroll nanti.
                    </p>
                </div>

                {employees.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Users className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada karyawan aktif.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Karyawan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Divisi
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Total Gaji/Bulan
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {employees.map((employee) => (
                                    <tr key={employee.id}>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-9">
                                                    <AvatarFallback className="bg-brand-navy/8 text-xs font-semibold text-brand-navy dark:bg-brand-navy/35 dark:text-foreground">
                                                        {initialsOf(
                                                            employee.name,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2 font-medium">
                                                        {employee.name}
                                                        {!employee.is_payroll_eligible && (
                                                            <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-normal text-muted-foreground">
                                                                Tidak ikut payroll
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {employee.email}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {employee.division_name ?? (
                                                <span className="text-xs text-muted-foreground">
                                                    Belum diatur
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRupiah(
                                                employee.monthly_total,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={PayrollProfileController.edit(
                                                    employee.id,
                                                )}
                                            >
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant={
                                                        employee.has_profile
                                                            ? 'outline'
                                                            : 'default'
                                                    }
                                                    className="rounded-full"
                                                >
                                                    <Pencil className="size-3.5" />
                                                    {employee.has_profile
                                                        ? 'Edit'
                                                        : 'Atur Gaji'}
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

PayrollProfiles.layout = {
    breadcrumbs: [
        {
            title: 'Profil Gaji',
            href: PayrollProfileController.index(),
        },
    ],
};
