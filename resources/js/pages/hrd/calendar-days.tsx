import { Form, Head, router } from '@inertiajs/react';
import {
    CalendarDays as CalendarDaysIcon,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import CompanyCalendarDayController from '@/actions/App/Http/Controllers/Hrd/CompanyCalendarDayController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type Company = { id: number; name: string };
type Employee = { id: number; name: string };

type DayRow = {
    id: number;
    company: Company;
    date: string;
    type: string;
    allowed_user_ids: number[] | null;
    allowed_employee_names: string[] | null;
    description: string | null;
};

type Props = {
    days: DayRow[];
    companies: Company[];
    employees: Employee[];
};

const TYPE_LABELS: Record<string, string> = {
    workday: 'Hari Kerja',
    rest_day: 'Hari Istirahat',
    holiday: 'Hari Libur',
};

function CalendarDayCommonFields({
    idSuffix,
    type,
    setType,
    allowedUserIds,
    toggleEmployee,
    employees,
    errors,
    defaultDescription,
}: {
    idSuffix: string;
    type: string;
    setType: (value: string) => void;
    allowedUserIds: number[];
    toggleEmployee: (id: number) => void;
    employees: Employee[];
    errors: Record<string, string | undefined>;
    defaultDescription?: string;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`type${idSuffix}`}>Jenis</Label>
                <input type="hidden" name="type" value={type} />
                <Select value={type} onValueChange={setType}>
                    <SelectTrigger id={`type${idSuffix}`} className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(TYPE_LABELS).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.type} />
            </div>

            <div className="grid gap-2">
                <Label>
                    {type === 'workday'
                        ? 'Karyawan yang Boleh Absen (opsional)'
                        : 'Karyawan yang Tetap Boleh Absen (opsional)'}
                </Label>
                {allowedUserIds.map((id) => (
                    <input
                        key={id}
                        type="hidden"
                        name="allowed_user_ids[]"
                        value={id}
                    />
                ))}
                <div className="max-h-40 overflow-y-auto rounded-xl border p-2">
                    {employees.length === 0 ? (
                        <p className="px-1 py-1 text-xs text-muted-foreground">
                            Belum ada karyawan.
                        </p>
                    ) : (
                        employees.map((employee) => (
                            <label
                                key={employee.id}
                                className="flex items-center gap-2 rounded-lg px-1 py-1.5 text-sm hover:bg-muted/50"
                            >
                                <Checkbox
                                    checked={allowedUserIds.includes(
                                        employee.id,
                                    )}
                                    onCheckedChange={() =>
                                        toggleEmployee(employee.id)
                                    }
                                />
                                {employee.name}
                            </label>
                        ))
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    {type === 'workday'
                        ? 'Kosongkan untuk membuka tanggal ini bagi semua karyawan. Jika dicentang, hanya karyawan yang dipilih yang bisa absen tanggal ini.'
                        : 'Kosongkan jika tanggal ini libur total. Jika dicentang, karyawan yang dipilih tetap bisa absen meski tanggal ini libur — yang lain tetap tidak bisa.'}
                </p>
                <InputError message={errors.allowed_user_ids} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`description${idSuffix}`}>
                    Keterangan (opsional)
                </Label>
                <Input
                    id={`description${idSuffix}`}
                    name="description"
                    placeholder="Hari Kemerdekaan"
                    defaultValue={defaultDescription}
                />
                <InputError message={errors.description} />
            </div>
        </>
    );
}

function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function CalendarDays({ days, companies, employees }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [companyId, setCompanyId] = useState('');
    const [type, setType] = useState('holiday');
    const [allowedUserIds, setAllowedUserIds] = useState<number[]>([]);

    function toggleEmployee(id: number) {
        setAllowedUserIds((current) =>
            current.includes(id)
                ? current.filter((existing) => existing !== id)
                : [...current, id],
        );
    }

    const [editingDay, setEditingDay] = useState<DayRow | null>(null);
    const [deletingDay, setDeletingDay] = useState<DayRow | null>(null);
    const [deleting, setDeleting] = useState(false);

    function confirmDeleteDay() {
        if (!deletingDay) return;

        setDeleting(true);
        router.delete(
            CompanyCalendarDayController.destroy.url(deletingDay.id),
            {
                preserveScroll: true,
                onSuccess: () => setDeletingDay(null),
                onFinish: () => setDeleting(false),
            },
        );
    }
    const [editType, setEditType] = useState('holiday');
    const [editAllowedUserIds, setEditAllowedUserIds] = useState<number[]>(
        [],
    );

    function toggleEditEmployee(id: number) {
        setEditAllowedUserIds((current) =>
            current.includes(id)
                ? current.filter((existing) => existing !== id)
                : [...current, id],
        );
    }

    function openEdit(day: DayRow) {
        setEditingDay(day);
        setEditType(day.type);
        setEditAllowedUserIds(day.allowed_user_ids ?? []);
    }

    return (
        <>
            <Head title="Kalender Kerja" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Kalender Kerja
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Tandai hari libur/istirahat di luar hari Minggu —
                            dipakai saat menyusun koreksi absensi supaya hari
                            libur tidak dianggap alpha.
                        </p>
                    </div>

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <Button
                            type="button"
                            className="rounded-full"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-4" />
                            Tambah Tanggal
                        </Button>
                        <DialogContent>
                            <Form
                                {...CompanyCalendarDayController.store.form()}
                                resetOnSuccess
                                disableWhileProcessing
                                onSuccess={() => {
                                    setCreateOpen(false);
                                    setCompanyId('');
                                    setType('holiday');
                                    setAllowedUserIds([]);
                                }}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Tambah Tanggal Kalender
                                            </DialogTitle>
                                            <DialogDescription>
                                                Minggu sudah otomatis dianggap
                                                hari istirahat — tidak perlu
                                                ditambahkan di sini.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="grid gap-4 py-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="company_id">
                                                    Instansi
                                                </Label>
                                                <input
                                                    type="hidden"
                                                    name="company_id"
                                                    value={companyId}
                                                />
                                                <Select
                                                    value={companyId}
                                                    onValueChange={setCompanyId}
                                                >
                                                    <SelectTrigger
                                                        id="company_id"
                                                        className="w-full"
                                                    >
                                                        <SelectValue placeholder="-- Pilih Instansi --" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {companies.map(
                                                            (company) => (
                                                                <SelectItem
                                                                    key={
                                                                        company.id
                                                                    }
                                                                    value={String(
                                                                        company.id,
                                                                    )}
                                                                >
                                                                    {
                                                                        company.name
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.company_id}
                                                />
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="date">
                                                        Tanggal Mulai
                                                    </Label>
                                                    <Input
                                                        id="date"
                                                        name="date"
                                                        type="date"
                                                        required
                                                    />
                                                    <InputError
                                                        message={errors.date}
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="ends_on">
                                                        Tanggal Selesai
                                                    </Label>
                                                    <Input
                                                        id="ends_on"
                                                        name="ends_on"
                                                        type="date"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.ends_on
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <p className="-mt-2 text-xs text-muted-foreground">
                                                Kosongkan Tanggal Selesai
                                                untuk satu hari saja. Isi
                                                untuk rentang libur
                                                berturut-turut (mis. Idul
                                                Fitri).
                                            </p>

                                            <CalendarDayCommonFields
                                                idSuffix="-create"
                                                type={type}
                                                setType={setType}
                                                allowedUserIds={
                                                    allowedUserIds
                                                }
                                                toggleEmployee={
                                                    toggleEmployee
                                                }
                                                employees={employees}
                                                errors={errors}
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

                {days.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <CalendarDaysIcon className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada tanggal kalender khusus.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Instansi
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Jenis
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Keterangan
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {days.map((day) => (
                                    <tr key={day.id}>
                                        <td className="px-4 py-3">
                                            {formatDate(day.date)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {day.company.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {TYPE_LABELS[day.type] ?? day.type}
                                            {day.allowed_employee_names &&
                                                day.allowed_employee_names
                                                    .length > 0 && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {day.type ===
                                                        'workday'
                                                            ? 'Khusus: '
                                                            : 'Tetap boleh absen: '}
                                                        {day.allowed_employee_names.join(
                                                            ', ',
                                                        )}
                                                    </div>
                                                )}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {day.description ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="rounded-full"
                                                    aria-label={`Edit tanggal ${formatDate(day.date)}`}
                                                    onClick={() =>
                                                        openEdit(day)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="rounded-full text-destructive hover:text-destructive"
                                                    aria-label="Hapus"
                                                    onClick={() =>
                                                        setDeletingDay(day)
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
            </div>

            <Dialog
                open={editingDay !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingDay(null);
                    }
                }}
            >
                <DialogContent>
                    {editingDay && (
                        <Form
                            {...CompanyCalendarDayController.update.form(
                                editingDay.id,
                            )}
                            key={editingDay.id}
                            disableWhileProcessing
                            onSuccess={() => setEditingDay(null)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Edit Tanggal Kalender
                                        </DialogTitle>
                                        <DialogDescription>
                                            {editingDay.company.name}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="grid gap-4 py-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="date-edit">
                                                Tanggal
                                            </Label>
                                            <Input
                                                id="date-edit"
                                                name="date"
                                                type="date"
                                                required
                                                defaultValue={
                                                    editingDay.date
                                                }
                                            />
                                            <InputError
                                                message={errors.date}
                                            />
                                        </div>

                                        <CalendarDayCommonFields
                                            idSuffix="-edit"
                                            type={editType}
                                            setType={setEditType}
                                            allowedUserIds={
                                                editAllowedUserIds
                                            }
                                            toggleEmployee={
                                                toggleEditEmployee
                                            }
                                            employees={employees}
                                            errors={errors}
                                            defaultDescription={
                                                editingDay.description ??
                                                undefined
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
                open={deletingDay !== null}
                onOpenChange={(open) => {
                    if (!open) setDeletingDay(null);
                }}
                title="Hapus Tanggal"
                description={
                    deletingDay
                        ? `Hapus tanggal ${formatDate(deletingDay.date)}?`
                        : ''
                }
                confirmLabel="Hapus"
                destructive
                confirming={deleting}
                onConfirm={confirmDeleteDay}
            />
        </>
    );
}

CalendarDays.layout = {
    breadcrumbs: [
        {
            title: 'Kalender Kerja',
            href: CompanyCalendarDayController.index(),
        },
    ],
};
