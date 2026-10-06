import { Form, Head } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PayrollProfileController from '@/actions/App/Http/Controllers/Hrd/PayrollProfileController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Division = { id: number; name: string };
type AvailableComponent = { id: number; name: string; category: string };

type ProfileComponent = { payroll_component_id: number; amount: string };

type Profile = {
    division_id: number;
    effective_from: string;
    employment_started_on: string;
    employment_type: string;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_account_holder: string | null;
    components: ProfileComponent[];
} | null;

type Props = {
    employee: {
        id: number;
        name: string;
        company_id: number;
        company_name: string;
    };
    profile: Profile;
    divisions: Division[];
    availableComponents: AvailableComponent[];
};

const EMPLOYMENT_TYPE_LABELS: Record<string, string> = {
    permanent: 'Tetap',
    contract: 'Kontrak',
    probation: 'Percobaan',
    intern: 'Magang',
};

let nextRowId = 0;

export default function PayrollProfileEdit({
    employee,
    profile,
    divisions,
    availableComponents,
}: Props) {
    const [divisionId, setDivisionId] = useState(
        profile ? String(profile.division_id) : '',
    );
    const [employmentType, setEmploymentType] = useState(
        profile?.employment_type ?? 'permanent',
    );
    const [rows, setRows] = useState<
        { rowId: number; componentId: string; amount: string }[]
    >(
        profile && profile.components.length > 0
            ? profile.components.map((component) => ({
                  rowId: nextRowId++,
                  componentId: String(component.payroll_component_id),
                  amount: component.amount,
              }))
            : [{ rowId: nextRowId++, componentId: '', amount: '' }],
    );

    function addRow() {
        setRows((current) => [
            ...current,
            { rowId: nextRowId++, componentId: '', amount: '' },
        ]);
    }

    function removeRow(rowId: number) {
        setRows((current) => current.filter((row) => row.rowId !== rowId));
    }

    function updateRow(
        rowId: number,
        field: 'componentId' | 'amount',
        value: string,
    ) {
        setRows((current) =>
            current.map((row) =>
                row.rowId === rowId ? { ...row, [field]: value } : row,
            ),
        );
    }

    const monthlyTotal = rows.reduce(
        (sum, row) => sum + (Number(row.amount) || 0),
        0,
    );

    return (
        <>
            <Head title={`Profil Gaji — ${employee.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Profil Gaji — {employee.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {employee.company_name}
                    </p>
                </div>

                <Form
                    {...PayrollProfileController.update.form(employee.id)}
                    disableWhileProcessing
                    className="max-w-2xl"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-6">
                            <div className="grid gap-4 rounded-2xl border p-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="division_id">Divisi</Label>
                                    <input
                                        type="hidden"
                                        name="division_id"
                                        value={divisionId}
                                    />
                                    <Select
                                        value={divisionId}
                                        onValueChange={setDivisionId}
                                    >
                                        <SelectTrigger
                                            id="division_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="-- Pilih Divisi --" />
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

                                <div className="grid gap-2">
                                    <Label htmlFor="employment_type">
                                        Jenis Hubungan Kerja
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="employment_type"
                                        value={employmentType}
                                    />
                                    <Select
                                        value={employmentType}
                                        onValueChange={setEmploymentType}
                                    >
                                        <SelectTrigger
                                            id="employment_type"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(
                                                EMPLOYMENT_TYPE_LABELS,
                                            ).map(([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.employment_type}
                                    />
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
                                        defaultValue={
                                            profile?.employment_started_on
                                        }
                                    />
                                    <InputError
                                        message={errors.employment_started_on}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="effective_from">
                                        Berlaku Sejak
                                    </Label>
                                    <Input
                                        id="effective_from"
                                        name="effective_from"
                                        type="date"
                                        required
                                        defaultValue={profile?.effective_from}
                                    />
                                    <InputError
                                        message={errors.effective_from}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 rounded-2xl border p-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="bank_name">
                                        Nama Bank (opsional)
                                    </Label>
                                    <Input
                                        id="bank_name"
                                        name="bank_name"
                                        defaultValue={profile?.bank_name ?? ''}
                                    />
                                    <InputError message={errors.bank_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="bank_account_number">
                                        Nomor Rekening (opsional)
                                    </Label>
                                    <Input
                                        id="bank_account_number"
                                        name="bank_account_number"
                                        defaultValue={
                                            profile?.bank_account_number ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.bank_account_number}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="bank_account_holder">
                                        Nama Pemilik Rekening (opsional)
                                    </Label>
                                    <Input
                                        id="bank_account_holder"
                                        name="bank_account_holder"
                                        defaultValue={
                                            profile?.bank_account_holder ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.bank_account_holder}
                                    />
                                </div>
                            </div>

                            <div className="rounded-2xl border p-4">
                                <div className="flex items-center justify-between">
                                    <h2 className="font-semibold">
                                        Komponen Gaji Bulanan
                                    </h2>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="rounded-full"
                                        onClick={addRow}
                                    >
                                        <Plus className="size-3.5" />
                                        Tambah Komponen
                                    </Button>
                                </div>

                                <div className="mt-4 flex flex-col gap-3">
                                    {rows.map((row, index) => (
                                        <div
                                            key={row.rowId}
                                            className="flex items-start gap-2"
                                        >
                                            <input
                                                type="hidden"
                                                name={`components[${index}][payroll_component_id]`}
                                                value={row.componentId}
                                            />
                                            <Select
                                                value={row.componentId}
                                                onValueChange={(value) =>
                                                    updateRow(
                                                        row.rowId,
                                                        'componentId',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="-- Pilih Komponen --" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableComponents.map(
                                                        (component) => (
                                                            <SelectItem
                                                                key={
                                                                    component.id
                                                                }
                                                                value={String(
                                                                    component.id,
                                                                )}
                                                            >
                                                                {component.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <Input
                                                name={`components[${index}][amount]`}
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="Nominal"
                                                value={row.amount}
                                                onChange={(event) =>
                                                    updateRow(
                                                        row.rowId,
                                                        'amount',
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-40"
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="shrink-0 rounded-full text-destructive hover:text-destructive"
                                                aria-label="Hapus komponen"
                                                onClick={() =>
                                                    removeRow(row.rowId)
                                                }
                                                disabled={rows.length === 1}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                                <InputError
                                    message={errors.components}
                                    className="mt-2"
                                />

                                <div className="mt-4 flex items-center justify-between border-t pt-4 text-sm">
                                    <span className="text-muted-foreground">
                                        Total per bulan
                                    </span>
                                    <span className="font-semibold">
                                        {new Intl.NumberFormat('id-ID', {
                                            style: 'currency',
                                            currency: 'IDR',
                                            maximumFractionDigits: 0,
                                        }).format(monthlyTotal)}
                                    </span>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reason">
                                    Alasan Perubahan (opsional)
                                </Label>
                                <Textarea
                                    id="reason"
                                    name="reason"
                                    placeholder="Dicatat di riwayat audit — kosongkan jika tidak perlu dijelaskan"
                                />
                                <InputError message={errors.reason} />
                            </div>

                            <div>
                                <Button type="submit" disabled={processing}>
                                    Simpan Profil Gaji
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

PayrollProfileEdit.layout = {
    breadcrumbs: [
        {
            title: 'Profil Gaji',
            href: PayrollProfileController.index(),
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
