import { EMPLOYMENT_TYPE_LABELS } from '@/lib/employment-types';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2, Users as UsersIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import UserManagementController from '@/actions/App/Http/Controllers/Hrd/UserManagementController';
import { ConfirmDialog } from '@/components/confirm-dialog';
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

type Division = { id: number; name: string };
type Company = { id: number; name: string; divisions: Division[] };

type UserRow = {
    id: number;
    employee_number: string | null;
    employment_type: string | null;
    employment_started_on: string | null;
    probation_ends_on: string | null;
    contract_ends_on: string | null;
    employment_ended_on: string | null;
    is_payroll_eligible: boolean;
    is_allowance_eligible: boolean;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    status: string;
    company: { id: number; name: string };
    division: { id: number; name: string } | null;
    bank_name: string | null;
    bank_account_holder: string | null;
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
    const [divisionId, setDivisionId] = useState(
        defaultValues?.division ? String(defaultValues.division.id) : '',
    );
    const [employmentType, setEmploymentType] = useState(
        defaultValues?.employment_type ?? '',
    );
    const [role, setRole] = useState(defaultValues?.role ?? 'karyawan');
    const [status, setStatus] = useState(defaultValues?.status ?? 'pending');
    const [isPayrollEligible, setIsPayrollEligible] = useState(
        defaultValues ? (defaultValues.is_payroll_eligible ? '1' : '0') : '1',
    );
    const [isAllowanceEligible, setIsAllowanceEligible] = useState(
        defaultValues ? (defaultValues.is_allowance_eligible ? '1' : '0') : '1',
    );

    const divisions = useMemo(
        () =>
            companies.find((company) => String(company.id) === companyId)
                ?.divisions ?? [],
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
                    Nomor Karyawan (otomatis)
                </Label>
                <Input
                    id="employee_number"
                    readOnly
                    placeholder="Dibuat otomatis saat karyawan disimpan"
                    defaultValue={defaultValues?.employee_number ?? ''}
                />
                <InputError message={errors.employee_number} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="employment_type">Jenis Karyawan</Label>
                <select
                    id="employment_type"
                    name="employment_type"
                    required
                    value={employmentType}
                    onChange={(e) => setEmploymentType(e.target.value)}
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option
                        value=""
                        disabled={Boolean(defaultValues?.employment_type)}
                    >
                        Belum ditentukan
                    </option>
                    {Object.entries(EMPLOYMENT_TYPE_LABELS).map(
                        ([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ),
                    )}
                </select>
                <InputError message={errors.employment_type} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="employment_started_on">
                    Tanggal Mulai Kerja
                </Label>
                <Input
                    id="employment_started_on"
                    name="employment_started_on"
                    type="date"
                    required
                    defaultValue={defaultValues?.employment_started_on ?? ''}
                />
                <InputError message={errors.employment_started_on} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="probation_ends_on">
                    Tanggal Berakhir Masa Percobaan{' '}
                    <span className="font-normal text-muted-foreground">
                        (opsional)
                    </span>
                </Label>
                <Input
                    id="probation_ends_on"
                    name="probation_ends_on"
                    type="date"
                    defaultValue={defaultValues?.probation_ends_on ?? ''}
                />
                <p className="text-xs text-muted-foreground">
                    Isi kalau karyawan ini masih dalam masa percobaan
                    sebelum diangkat tetap. Kosongkan kalau tidak/sudah
                    tidak lagi masa percobaan.
                </p>
                <InputError message={errors.probation_ends_on} />
            </div>

            {employmentType === 'contract' && (
                <div className="grid gap-2 rounded-xl border p-3">
                    <Label htmlFor="contract_ends_on">
                        Periode Kontrak — Berakhir{' '}
                        <span className="font-normal text-muted-foreground">
                            (opsional)
                        </span>
                    </Label>
                    <Input
                        id="contract_ends_on"
                        name="contract_ends_on"
                        type="date"
                        defaultValue={defaultValues?.contract_ends_on ?? ''}
                    />
                    <p className="text-xs text-muted-foreground">
                        Dari Tanggal Mulai Kerja di atas, sampai tanggal
                        ini. Kosongkan kalau kontraknya belum punya
                        tanggal berakhir pasti.
                    </p>
                    <InputError message={errors.contract_ends_on} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="employment_ended_on">
                    Tanggal Keluar{' '}
                    <span className="font-normal text-muted-foreground">
                        (opsional)
                    </span>
                </Label>
                <Input
                    id="employment_ended_on"
                    name="employment_ended_on"
                    type="date"
                    defaultValue={defaultValues?.employment_ended_on ?? ''}
                />
                <InputError message={errors.employment_ended_on} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="is_payroll_eligible">Ikut Payroll</Label>
                <input
                    type="hidden"
                    name="is_payroll_eligible"
                    value={isPayrollEligible}
                />
                <Select
                    value={isPayrollEligible}
                    onValueChange={setIsPayrollEligible}
                >
                    <SelectTrigger
                        id="is_payroll_eligible"
                        className="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">
                            Ya — digaji lewat sistem ini
                        </SelectItem>
                        <SelectItem value="0">
                            Tidak — misal magang/relawan tanpa gaji
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    Kalau dipilih "Tidak", karyawan ini tidak akan dianggap
                    "belum masuk payroll" dan tidak ikut dihitung saat Proses
                    Gaji dijalankan.
                </p>
                <InputError message={errors.is_payroll_eligible} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="is_allowance_eligible">Dapat Tunjangan</Label>
                <input
                    type="hidden"
                    name="is_allowance_eligible"
                    value={isAllowanceEligible}
                />
                <Select
                    value={isAllowanceEligible}
                    onValueChange={setIsAllowanceEligible}
                >
                    <SelectTrigger
                        id="is_allowance_eligible"
                        className="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">Ya</SelectItem>
                        <SelectItem value="0">Tidak</SelectItem>
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    Kalau dipilih "Tidak", karyawan ini tidak dapat Tunjangan
                    Kehadiran maupun Tunjangan Transport otomatis — tetap
                    dapat Gaji Pokok dan tetap kena Potongan Telat/Alpha
                    seperti biasa.
                </p>
                <InputError message={errors.is_allowance_eligible} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="company_id">Instansi</Label>
                <input type="hidden" name="company_id" value={companyId} />
                <Select
                    value={companyId}
                    onValueChange={(value) => {
                        setCompanyId(value);
                        setDivisionId('');
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
                <Label htmlFor="division_id">Jabatan / Divisi</Label>
                <input type="hidden" name="division_id" value={divisionId} />
                <Select
                    value={divisionId}
                    onValueChange={setDivisionId}
                    disabled={!companyId}
                >
                    <SelectTrigger id="division_id" className="w-full">
                        <SelectValue placeholder="-- Pilih Jabatan / Divisi --" />
                    </SelectTrigger>
                    <SelectContent>
                        {divisions.map((division) => (
                            <SelectItem
                                key={division.id}
                                value={String(division.id)}
                            >
                                {division.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.division_id} />
            </div>

            <div className="grid gap-4 rounded-2xl border p-4">
                <p className="text-xs font-medium text-muted-foreground">
                    Rekening bank (opsional) — dipakai Finance saat mencatat
                    pembayaran gaji.
                </p>
                <div className="grid gap-2">
                    <Label htmlFor="bank_name">Nama Bank</Label>
                    <Input
                        id="bank_name"
                        name="bank_name"
                        defaultValue={defaultValues?.bank_name ?? ''}
                        placeholder="BCA"
                    />
                    <InputError message={errors.bank_name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="bank_account_number">
                        {isEdit
                            ? 'Nomor Rekening (kosongkan jika tidak diubah)'
                            : 'Nomor Rekening'}
                    </Label>
                    <Input
                        id="bank_account_number"
                        name="bank_account_number"
                        placeholder={isEdit ? '••••••••••' : '1234567890'}
                    />
                    <InputError message={errors.bank_account_number} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="bank_account_holder">
                        Nama Pemilik Rekening
                    </Label>
                    <Input
                        id="bank_account_holder"
                        name="bank_account_holder"
                        defaultValue={defaultValues?.bank_account_holder ?? ''}
                    />
                    <InputError message={errors.bank_account_holder} />
                </div>
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
    const [deletingUser, setDeletingUser] = useState<UserRow | null>(null);
    const [deleting, setDeleting] = useState(false);

    function confirmDeleteUser() {
        if (!deletingUser) return;

        setDeleting(true);
        router.delete(UserManagementController.destroy.url(deletingUser.id), {
            preserveScroll: true,
            onSuccess: () => setDeletingUser(null),
            onFinish: () => setDeleting(false),
        });
    }

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
                                                    <div className="flex items-center gap-2 font-medium">
                                                        {user.name}
                                                        {!user.is_payroll_eligible && (
                                                            <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-normal text-muted-foreground">
                                                                Tidak ikut payroll
                                                            </span>
                                                        )}
                                                        {user.is_payroll_eligible &&
                                                            !user.is_allowance_eligible && (
                                                                <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-normal text-muted-foreground">
                                                                    Tanpa tunjangan
                                                                </span>
                                                            )}
                                                        {user.probation_ends_on && (
                                                            <span className="rounded-full border border-amber-400/50 bg-amber-500/10 px-2 py-0.5 text-[11px] font-normal text-amber-600 dark:border-amber-400/40 dark:text-amber-400">
                                                                Masa percobaan
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {user.email}
                                                        <br />
                                                        {user.employee_number ??
                                                            '—'}{' '}
                                                        ·{' '}
                                                        {EMPLOYMENT_TYPE_LABELS[
                                                            user.employment_type ??
                                                                ''
                                                        ] ??
                                                            'Jenis belum ditentukan'}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div>{user.company.name}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {user.division?.name ?? '-'}
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
                                                <Link
                                                    href={UserManagementController.show(
                                                        user.id,
                                                    )}
                                                >
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        className="rounded-full"
                                                        aria-label={`Detail ${user.name}`}
                                                    >
                                                        <Eye className="size-4" />
                                                    </Button>
                                                </Link>
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
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="rounded-full text-destructive hover:text-destructive"
                                                    aria-label={`Hapus ${user.name}`}
                                                    disabled={
                                                        user.id ===
                                                        auth.user.id
                                                    }
                                                    onClick={() =>
                                                        setDeletingUser(user)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
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

            <ConfirmDialog
                open={deletingUser !== null}
                onOpenChange={(open) => {
                    if (!open) setDeletingUser(null);
                }}
                title="Hapus Pengguna"
                description={
                    deletingUser
                        ? `Hapus ${deletingUser.name}? Seluruh data absensi dan izin miliknya ikut terhapus permanen. Tindakan ini tidak bisa dibatalkan.`
                        : ''
                }
                confirmLabel="Hapus"
                destructive
                confirming={deleting}
                onConfirm={confirmDeleteUser}
            />
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
