import { Form, Head } from '@inertiajs/react';
import { UserCheck } from 'lucide-react';
import EmployeeApprovalController from '@/actions/App/Http/Controllers/Hrd/EmployeeApprovalController';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';

type PendingUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    created_at: string;
    company: { id: number; name: string };
    position: { id: number; name: string };
};

type Props = {
    pendingUsers: PendingUser[];
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

function PendingUserCard({ user }: { user: PendingUser }) {
    return (
        <div className="flex flex-col gap-4 rounded-2xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3">
                <Avatar className="size-11">
                    <AvatarFallback className="bg-brand-navy/8 font-semibold text-brand-navy dark:bg-brand-navy/35 dark:text-foreground">
                        {initialsOf(user.name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0">
                    <div className="font-semibold">{user.name}</div>
                    <div className="text-sm text-muted-foreground">
                        {user.email}
                    </div>
                    {user.phone && (
                        <div className="text-sm text-muted-foreground">
                            {user.phone}
                        </div>
                    )}
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                        <span className="rounded-full bg-brand-navy/8 px-2.5 py-0.5 text-xs font-medium dark:bg-brand-navy/35">
                            {user.company.name}
                        </span>
                        <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                            {user.position.name}
                        </span>
                    </div>
                </div>
            </div>

            <div className="flex gap-2">
                <Form
                    {...EmployeeApprovalController.approve.form(user.id)}
                    disableWhileProcessing
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            className="rounded-full"
                            disabled={processing}
                        >
                            Setujui
                        </Button>
                    )}
                </Form>
                <Form
                    {...EmployeeApprovalController.reject.form(user.id)}
                    disableWhileProcessing
                    onBefore={() =>
                        confirm(`Tolak pendaftaran ${user.name}?`)
                    }
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            variant="destructive"
                            className="rounded-full"
                            disabled={processing}
                        >
                            Tolak
                        </Button>
                    )}
                </Form>
            </div>
        </div>
    );
}

export default function EmployeeApproval({ pendingUsers }: Props) {
    return (
        <>
            <Head title="Persetujuan Karyawan" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Persetujuan Karyawan
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Karyawan baru mendaftar dengan status menunggu.
                        Setujui untuk mengaktifkan akun mereka, atau tolak
                        jika tidak valid.
                    </p>
                </div>

                {pendingUsers.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <UserCheck className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Tidak ada pendaftaran yang menunggu persetujuan.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {pendingUsers.map((user) => (
                            <PendingUserCard key={user.id} user={user} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

EmployeeApproval.layout = {
    breadcrumbs: [
        {
            title: 'Persetujuan Karyawan',
            href: EmployeeApprovalController.index(),
        },
    ],
};
