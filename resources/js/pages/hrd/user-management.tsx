import { Form, Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users as UsersIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import UserManagementController from '@/actions/App/Http/Controllers/Hrd/UserManagementController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Auth } from '@/types';

type Position = { id: number; name: string };
type Company = { id: number; name: string; positions: Position[] };

type UserRow = {
    id: number;
    employee_number: string | null;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    status: string;
    company: { id: number; name: string };
    position: { id: number; name: string };
};

type PaginatedUsers = {
    data: UserRow[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    users: PaginatedUsers;
    search: string;
    companies: Company[];
};

const ROLE_LABELS: Record<string, string> = {
    karyawan: 'Karyawan',
    hrd: 'HRD',
    admin: 'Admin (Manager)',
    finance: 'Finance',
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Menunggu',
    active: 'Aktif',
    rejected: 'Nonaktif',
};

const STATUS_BADGE_CLASS: Record<string, string> = {
    pending:
        'border-amber-400 text-amber-600 dark:border-amber-400/70 dark:text-amber-400',
    active: 'border-emerald-400 text-emerald-600 dark:border-emerald-400/70 dark:text-emerald-400',
    rejected:
        'border-red-400 text-red-600 dark:border-red-400/70 dark:text-red-400',
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

function UserFormFields({
    companies,
    defaultValues,
    errors,
    isEdit,
    disableRoleAndStatus,
}: {
    companies: Company[];
    defaultValues?: UserRow;
    errors: Record<string, string | undefined>;
    isEdit: boolean;
    disableRoleAndStatus: boolean;
}) {
    const [companyId, setCompanyId] = useState(
        defaultValues ? String(defaultValues.company.id) : '',
    );
    const [positionId, setPositionId] = useState(
        defaultValues ? String(defaultValues.position.id) : '',
    );
    const [role, setRole] = useState(defaultValues?.role ?? 'karyawan');
    const [status, setStatus] = useState(defaultValues?.status ?? 'pending');

    const positions = useMemo(
        () =>
            companies.find((company) => String(company.id) === companyId)
                ?.positions ?? [],
        [companies, companyId],
    );

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Nama Lengkap</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    defaultValue={defaultValues?.name}
                    placeholder="Nama lengkap"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    defaultValue={defaultValues?.email}
                    placeholder="email@example.com"
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="phone">Nomor WhatsApp</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    defaultValue={defaultValues?.phone ?? ''}
                    placeholder="0812xxxxxxxx"
                />
                <InputError message={errors.phone} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="employee_number">
                    Nomor Karyawan (opsional)
                </Label>
                <Input
                    id="employee_number"
                    name="employee_number"
                    defaultValue={defaultValues?.employee_number ?? ''}
                />
                <InputError message={errors.employee_number} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="company_id">Instansi</Label>
                <input type="hidden" name="company_id" value={companyId} />
                <Select
                    value={companyId}
                    onValueChange={(value) => {
                        setCompanyId(value);
                        setPositionId('');
                    }}
                >
                    <SelectTrigger id="company_id" className="w-full">
                        <SelectValue placeholder="-- Pilih Instansi --" />
                    </SelectTrigger>
                    <SelectContent>
                        {companies.map((company) => (
                            <SelectItem
                                key={company.id}
                                value={String(company.id)}
                            >
                                {company.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.company_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="position_id">Jabatan</Label>
                <input type="hidden" name="position_id" value={positionId} />
                <Select
                    value={positionId}
                    onValueChange={setPositionId}
                    disabled={!companyId}
                >
                    <SelectTrigger id="position_id" className="w-full">
                        <SelectValue placeholder="-- Pilih Jabatan --" />
                    </SelectTrigger>
                    <SelectContent>
                        {positions.map((position) => (
                            <SelectItem
                                key={position.id}
                                value={String(position.id)}
                            >
                                {position.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.position_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="role">Role</Label>
                <input type="hidden" name="role" value={role} />
                <Select
                    value={role}
                    onValueChange={setRole}
                    disabled={disableRoleAndStatus}
                >
                    <SelectTrigger id="role" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(ROLE_LABELS).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.role} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="status">Status</Label>
                <input type="hidden" name="status" value={status} />
                <Select
                    value={status}
                    onValueChange={setStatus}
                    disabled={disableRoleAndStatus}
                >
                    <SelectTrigger id="status" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(STATUS_LABELS).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {disableRoleAndStatus && (
                    <p className="text-xs text-muted-foreground">
                        Role dan status akun sendiri tidak bisa diubah di sini.
                    </p>
                )}
                <InputError message={errors.status} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password">
                    {isEdit
                        ? 'Password baru (kosongkan jika tidak diubah)'
                        : 'Password'}
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required={!isEdit}
                    autoComplete="new-password"
                    placeholder="Password"
                />
                <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">
                    Konfirmasi Password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required={!isEdit}
                    autoComplete="new-password"
                    placeholder="Konfirmasi password"
                />
                <InputError message={errors.password_confirmation} />
            </div>
        </div>
    );
}

export default function UserManagement({ users, search, companies }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [searchValue, setSearchValue] = useState(search);
    const [createOpen, setCreateOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);

    function handleSearchSubmit(event: React.FormEvent) {
        event.preventDefault();
        router.get(
            UserManagementController.index().url,
            searchValue ? { search: searchValue } : {},
            { preserveState: true, preserveScroll: true },
        );
    }

    function goToPage(url: string | null) {
        if (!url) {
            return;
        }
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Manajemen User" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Manajemen User
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Tambah, ubah, atau hapus akun karyawan di semua
                            instansi.
                        </p>
                    </div>

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <Button
                            type="button"
                            className="rounded-full"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-4" />
                            Tambah User
                        </Button>
                        <DialogContent className="max-h-[90vh] overflow-y-auto">
                            <Form
                                {...UserManagementController.store.form()}
                                resetOnSuccess
                                disableWhileProcessing
                                onSuccess={() => setCreateOpen(false)}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Tambah User Baru
                                            </DialogTitle>
                                            <DialogDescription>
                                                Akun langsung dibuat dengan
                                                status yang dipilih — tidak
                                                perlu persetujuan tambahan.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div className="py-2">
                                            <UserFormFields
                                                companies={companies}
                                                errors={errors}
                                                isEdit={false}
                                                disableRoleAndStatus={false}
                                            />
                                        </div>

                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Simpan
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <form
                    onSubmit={handleSearchSubmit}
                    className="flex max-w-sm gap-2"
                >
                    <Input
                        value={searchValue}
                        onChange={(event) => setSearchValue(event.target.value)}
                        placeholder="Cari nama, email, atau nomor karyawan…"
                    />
                    <Button type="submit" variant="secondary">
                        Cari
                    </Button>
                </form>

                {users.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <UsersIcon className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            {search
                                ? 'Tidak ada user yang cocok dengan pencarian.'
                                : 'Belum ada user.'}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Nama
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Instansi / Jabatan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.map((user) => (
                                    <tr key={user.id}>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-9">
                                                    <AvatarFallback className="bg-brand-navy/8 text-xs font-semibold text-brand-navy dark:bg-brand-navy/35 dark:text-foreground">
                                                        {initialsOf(user.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <div className="font-medium">
                                                        {user.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {user.email}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div>{user.company.name}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {user.position.name}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {ROLE_LABELS[user.role] ??
                                                user.role}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ${STATUS_BADGE_CLASS[user.status] ?? ''}`}
                                            >
                                                {STATUS_LABELS[user.status] ??
                                                    user.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="rounded-full"
                                                    aria-label={`Edit ${user.name}`}
                                                    onClick={() =>
                                                        setEditingUser(user)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Form
                                                    {...UserManagementController.destroy.form(
                                                        user.id,
                                                    )}
                                                    disableWhileProcessing
                                                    onBefore={() =>
                                                        confirm(
                                                            `Hapus ${user.name}? Seluruh data absensi dan izin miliknya ikut terhapus permanen. Tindakan ini tidak bisa dibatalkan.`,
                                                        )
                                                    }
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="icon"
                                                            variant="outline"
                                                            className="rounded-full text-destructive hover:text-destructive"
                                                            aria-label={`Hapus ${user.name}`}
                                                            disabled={
                                                                processing ||
                                                                user.id ===
                                                                    auth.user.id
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Halaman {users.current_page} dari {users.last_page}{' '}
                            · {users.total} user
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!users.prev_page_url}
                                onClick={() => goToPage(users.prev_page_url)}
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!users.next_page_url}
                                onClick={() => goToPage(users.next_page_url)}
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <Dialog
                open={editingUser !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingUser(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    {editingUser && (
                        <Form
                            {...UserManagementController.update.form(
                                editingUser.id,
                            )}
                            key={editingUser.id}
                            disableWhileProcessing
                            onSuccess={() => setEditingUser(null)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Edit {editingUser.name}
                                        </DialogTitle>
                                        <DialogDescription>
                                            Ubah data user. Kosongkan password
                                            kalau tidak ingin menggantinya.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="py-2">
                                        <UserFormFields
                                            companies={companies}
                                            defaultValues={editingUser}
                                            errors={errors}
                                            isEdit
                                            disableRoleAndStatus={
                                                editingUser.id === auth.user.id
                                            }
                                        />
                                    </div>

                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Simpan Perubahan
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

UserManagement.layout = {
    breadcrumbs: [
        {
            title: 'Manajemen User',
            href: UserManagementController.index(),
        },
    ],
};
