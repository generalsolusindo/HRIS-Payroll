import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Wallet } from 'lucide-react';
import { useState } from 'react';
import PayrollComponentController from '@/actions/App/Http/Controllers/Hrd/PayrollComponentController';
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

type Company = { id: number; name: string };

type ComponentRow = {
    id: number;
    code: string;
    name: string;
    type: string;
    category: string;
    is_active: boolean;
    company: Company;
};

type Props = {
    components: ComponentRow[];
    companies: Company[];
};

const TYPE_LABELS: Record<string, string> = {
    earning: 'Penghasilan',
    deduction: 'Potongan',
    employer_contribution: 'Kontribusi Perusahaan',
};

const CATEGORY_LABELS: Record<string, string> = {
    base_salary: 'Gaji Pokok',
    allowance: 'Tunjangan',
    incentive: 'Insentif',
    overtime: 'Lembur',
    attendance_reward: 'Reward Absensi',
    attendance_deduction: 'Potongan Absensi',
    insurance: 'Asuransi/BPJS',
    tax: 'Pajak',
    other: 'Lainnya',
};

function ComponentFormFields({
    companies,
    defaultValues,
    errors,
}: {
    companies: Company[];
    defaultValues?: ComponentRow;
    errors: Record<string, string | undefined>;
}) {
    const [companyId, setCompanyId] = useState(
        defaultValues ? String(defaultValues.company.id) : '',
    );
    const [type, setType] = useState(defaultValues?.type ?? 'earning');
    const [category, setCategory] = useState(
        defaultValues?.category ?? 'base_salary',
    );
    const [isActive, setIsActive] = useState(
        defaultValues ? (defaultValues.is_active ? '1' : '0') : '1',
    );

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="company_id">Instansi</Label>
                <input type="hidden" name="company_id" value={companyId} />
                <Select value={companyId} onValueChange={setCompanyId}>
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
                <Label htmlFor="code">Kode</Label>
                <Input
                    id="code"
                    name="code"
                    required
                    defaultValue={defaultValues?.code}
                    placeholder="BASE"
                />
                <InputError message={errors.code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nama Komponen</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    defaultValue={defaultValues?.name}
                    placeholder="Gaji Pokok"
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="type">Jenis</Label>
                <input type="hidden" name="type" value={type} />
                <Select value={type} onValueChange={setType}>
                    <SelectTrigger id="type" className="w-full">
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
                <Label htmlFor="category">Kategori</Label>
                <input type="hidden" name="category" value={category} />
                <Select value={category} onValueChange={setCategory}>
                    <SelectTrigger id="category" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(CATEGORY_LABELS).map(
                            ([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ),
                        )}
                    </SelectContent>
                </Select>
                <InputError message={errors.category} />
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

export default function PayrollComponents({ components, companies }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editingComponent, setEditingComponent] =
        useState<ComponentRow | null>(null);

    return (
        <>
            <Head title="Komponen Gaji" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            Komponen Gaji
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Kamus komponen penghasilan dan potongan yang bisa
                            dipakai di profil gaji karyawan.
                        </p>
                    </div>

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <Button
                            type="button"
                            className="rounded-full"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-4" />
                            Tambah Komponen
                        </Button>
                        <DialogContent>
                            <Form
                                {...PayrollComponentController.store.form()}
                                resetOnSuccess
                                disableWhileProcessing
                                onSuccess={() => setCreateOpen(false)}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Tambah Komponen Gaji
                                            </DialogTitle>
                                            <DialogDescription>
                                                Kode harus unik dalam satu
                                                instansi.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="py-2">
                                            <ComponentFormFields
                                                companies={companies}
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

                {components.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <Wallet className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada komponen gaji.
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
                                        Instansi
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Jenis
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Kategori
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
                                {components.map((component) => (
                                    <tr key={component.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {component.name}
                                            </div>
                                            <div className="font-mono text-xs text-muted-foreground">
                                                {component.code}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {component.company.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {TYPE_LABELS[component.type] ??
                                                component.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {CATEGORY_LABELS[
                                                component.category
                                            ] ?? component.category}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium ${
                                                    component.is_active
                                                        ? 'border-emerald-400 text-emerald-600 dark:border-emerald-400/70 dark:text-emerald-400'
                                                        : 'border-muted-foreground/30 text-muted-foreground'
                                                }`}
                                            >
                                                {component.is_active
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
                                                    aria-label={`Edit ${component.name}`}
                                                    onClick={() =>
                                                        setEditingComponent(
                                                            component,
                                                        )
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Form
                                                    {...PayrollComponentController.destroy.form(
                                                        component.id,
                                                    )}
                                                    disableWhileProcessing
                                                    onBefore={() =>
                                                        confirm(
                                                            `Hapus komponen ${component.name}?`,
                                                        )
                                                    }
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="icon"
                                                            variant="outline"
                                                            className="rounded-full text-destructive hover:text-destructive"
                                                            aria-label={`Hapus ${component.name}`}
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
                open={editingComponent !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingComponent(null);
                    }
                }}
            >
                <DialogContent>
                    {editingComponent && (
                        <Form
                            {...PayrollComponentController.update.form(
                                editingComponent.id,
                            )}
                            key={editingComponent.id}
                            disableWhileProcessing
                            onSuccess={() => setEditingComponent(null)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Edit {editingComponent.name}
                                        </DialogTitle>
                                    </DialogHeader>
                                    <div className="py-2">
                                        <ComponentFormFields
                                            companies={companies}
                                            defaultValues={editingComponent}
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

PayrollComponents.layout = {
    breadcrumbs: [
        {
            title: 'Komponen Gaji',
            href: PayrollComponentController.index(),
        },
    ],
};
