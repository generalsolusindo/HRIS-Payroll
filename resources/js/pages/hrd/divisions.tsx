import { Form, Head } from '@inertiajs/react';
import { Building2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DivisionController from '@/actions/App/Http/Controllers/Hrd/DivisionController';
import InputError from '@/components/input-error';
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

function formatRupiah(value: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

type DivisionRow = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    default_base_salary: string;
    daily_attendance_allowance: string;
};

type Props = {
    divisions: DivisionRow[];
};

function DivisionFormFields({
    defaultValues,
    errors,
}: {
    defaultValues?: DivisionRow;
    errors: Record<string, string | undefined>;
}) {
    const [isActive, setIsActive] = useState(
        defaultValues ? (defaultValues.is_active ? '1' : '0') : '1',
    );

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="code">Kode</Label>
                <Input
                    id="code"
                    name="code"
                    required
                    defaultValue={defaultValues?.code}
                    placeholder="OPS"
                />
                <InputError message={errors.code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nama Divisi</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    defaultValue={defaultValues?.name}
                    placeholder="Operasional"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="default_base_salary">
                    Gaji Pokok Default (Rp)
                </Label>
                <Input
                    id="default_base_salary"
                    name="default_base_salary"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    defaultValue={defaultValues?.default_base_salary ?? '0'}
                />
                <p className="text-xs text-muted-foreground">
                    Terisi otomatis sebagai Gaji Pokok saat HRD mengatur profil
                    gaji karyawan di divisi ini — tetap bisa diubah manual per
                    karyawan.
                </p>
                <InputError message={errors.default_base_salary} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="daily_attendance_allowance">
                    Tunjangan per Hari Hadir (Rp)
                </Label>
                <Input
                    id="daily_attendance_allowance"
                    name="daily_attendance_allowance"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    defaultValue={
                        defaultValues?.daily_attendance_allowance ?? '0'
                    }
                />
                <p className="text-xs text-muted-foreground">
                    Dihitung otomatis saat payroll diproses: hari hadir penuh
                    dapat tarif penuh, setengah hari dapat separuh tarif. Isi 0
                    untuk menonaktifkan.
                </p>
                <InputError message={errors.daily_attendance_allowance} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="is_active">Status</Label>
                <input type="hidden" name="is_active" value={isActive} />
                <Select value={isActive} onValueChange={setIsActive}>
                    <SelectTrigger id="is_active" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">Aktif</SelectItem>
                        <SelectItem value="0">Nonaktif</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errors.is_active} />
            </div>
        </div>
    );
}

export default function Divisions({ divisions }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editingDivision, setEditingDivision] = useState<DivisionRow | null>(
        null,
    );

    return (
        <>
            <Head title="Divisi" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Divisi
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Dipakai untuk mengelompokkan rekap gaji per divisi.
                        </p>
                    </div>

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <Button
                            type="button"
                            className="rounded-full"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-4" />
                            Tambah Divisi
                        </Button>
                        <DialogContent>
                            <Form
                                {...DivisionController.store.form()}
                                resetOnSuccess
                                disableWhileProcessing
                                onSuccess={() => setCreateOpen(false)}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Tambah Divisi
                                            </DialogTitle>
                                            <DialogDescription>
                                                Kode harus unik dalam satu
                                                instansi.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="py-2">
                                            <DivisionFormFields
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

                {divisions.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Building2 className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada divisi.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Kode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Nama
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Gaji Pokok Default
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tunjangan/Hari
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
                                {divisions.map((division) => (
                                    <tr key={division.id}>
                                        <td className="px-4 py-3 font-mono text-xs">
                                            {division.code}
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {division.name}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatRupiah(
                                                division.default_base_salary,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatRupiah(
                                                division.daily_attendance_allowance,
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ${
                                                    division.is_active
                                                        ? 'border-emerald-400 text-emerald-600 dark:border-emerald-400/70 dark:text-emerald-400'
                                                        : 'border-muted-foreground/30 text-muted-foreground'
                                                }`}
                                            >
                                                {division.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="rounded-full"
                                                    aria-label={`Edit ${division.name}`}
                                                    onClick={() =>
                                                        setEditingDivision(
                                                            division,
                                                        )
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Form
                                                    {...DivisionController.destroy.form(
                                                        division.id,
                                                    )}
                                                    disableWhileProcessing
                                                    onBefore={() =>
                                                        confirm(
                                                            `Hapus divisi ${division.name}?`,
                                                        )
                                                    }
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="icon"
                                                            variant="outline"
                                                            className="rounded-full text-destructive hover:text-destructive"
                                                            aria-label={`Hapus ${division.name}`}
                                                            disabled={
                                                                processing
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
            </div>

            <Dialog
                open={editingDivision !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingDivision(null);
                    }
                }}
            >
                <DialogContent>
                    {editingDivision && (
                        <Form
                            {...DivisionController.update.form(
                                editingDivision.id,
                            )}
                            key={editingDivision.id}
                            disableWhileProcessing
                            onSuccess={() => setEditingDivision(null)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Edit {editingDivision.name}
                                        </DialogTitle>
                                    </DialogHeader>
                                    <div className="py-2">
                                        <DivisionFormFields
                                            defaultValues={editingDivision}
                                            errors={errors}
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

Divisions.layout = {
    breadcrumbs: [
        {
            title: 'Divisi',
            href: DivisionController.index(),
        },
    ],
};
