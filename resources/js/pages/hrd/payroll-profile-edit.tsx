import { EMPLOYMENT_TYPE_LABELS } from '@/lib/employment-types';
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
type AvailableComponent = { id: number; name: string };

type ProfileComponent = { payroll_component_id: number; amount: string };

type Profile = {
    effective_from: string;
    employment_type: string;
    earningComponents: ProfileComponent[];
    deductionComponents: ProfileComponent[];
} | null;

type Props = {
    employee: {
        id: number;
        name: string;
        company_id: number;
        company_name: string;
        employment_type: string | null;
        employment_started_on: string;
        division: Division;
    };
    profile: Profile;
    availableEarningComponents: AvailableComponent[];
    availableDeductionComponents: AvailableComponent[];
};

type Row = { rowId: number; componentId: string; amount: string };

let nextRowId = 0;

function rowsFrom(components: ProfileComponent[]): Row[] {
    return components.map((component) => ({
        rowId: nextRowId++,
        componentId: String(component.payroll_component_id),
        amount: component.amount,
    }));
}

function ComponentRowsSection({
    title,
    hint,
    rows,
    setRows,
    availableComponents,
    nameOffset,
    errorMessage,
    emptyHint,
    allowEmpty,
}: {
    title: string;
    hint: string;
    rows: Row[];
    setRows: React.Dispatch<React.SetStateAction<Row[]>>;
    availableComponents: AvailableComponent[];
    nameOffset: number;
    errorMessage?: string;
    emptyHint: string;
    allowEmpty: boolean;
}) {
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

    const sectionTotal = rows.reduce(
        (sum, row) => sum + (Number(row.amount) || 0),
        0,
    );

    return (
        <div className="rounded-2xl border p-4">
            <div className="flex items-center justify-between">
                <h2 className="font-semibold">{title}</h2>
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
            <p className="mt-1 text-xs text-muted-foreground">{hint}</p>

            {rows.length === 0 ? (
                <p className="mt-4 text-xs text-muted-foreground italic">
                    {emptyHint}
                </p>
            ) : (
                <div className="mt-4 flex flex-col gap-3">
                    {rows.map((row, index) => (
                        <div
                            key={row.rowId}
                            className="flex items-start gap-2"
                        >
                            <input
                                type="hidden"
                                name={`components[${nameOffset + index}][payroll_component_id]`}
                                value={row.componentId}
                            />
                            <Select
                                value={row.componentId}
                                onValueChange={(value) =>
                                    updateRow(row.rowId, 'componentId', value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="-- Pilih Komponen --" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableComponents.map((component) => (
                                        <SelectItem
                                            key={component.id}
                                            value={String(component.id)}
                                        >
                                            {component.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                name={`components[${nameOffset + index}][amount]`}
                                type="number"
                                min="0"
                                step="0.01"
                                max="999999999999.99"
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
                                onClick={() => removeRow(row.rowId)}
                                disabled={!allowEmpty && rows.length === 1}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}
            <InputError message={errorMessage} className="mt-2" />

            <div className="mt-4 flex items-center justify-between border-t pt-4 text-sm">
                <span className="text-muted-foreground">
                    Total {title.toLowerCase()}
                </span>
                <span className="font-semibold">
                    {new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        maximumFractionDigits: 2,
                    }).format(sectionTotal)}
                </span>
            </div>
        </div>
    );
}

export default function PayrollProfileEdit({
    employee,
    profile,
    availableEarningComponents,
    availableDeductionComponents,
}: Props) {
    const [earningRows, setEarningRows] = useState<Row[]>(() =>
        profile && profile.earningComponents.length > 0
            ? rowsFrom(profile.earningComponents)
            : [{ rowId: nextRowId++, componentId: '', amount: '' }],
    );
    const [deductionRows, setDeductionRows] = useState<Row[]>(() =>
        profile ? rowsFrom(profile.deductionComponents) : [],
    );

    const earningTotal = earningRows.reduce(
        (sum, row) => sum + (Number(row.amount) || 0),
        0,
    );
    const deductionTotal = deductionRows.reduce(
        (sum, row) => sum + (Number(row.amount) || 0),
        0,
    );
    const netTotal = earningTotal - deductionTotal;

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
                                    <Label htmlFor="division_display">
                                        Jabatan / Divisi
                                    </Label>
                                    <Input
                                        id="division_display"
                                        value={employee.division.name}
                                        disabled
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Diisi dari Manajemen User — ubah di
                                        sana kalau salah.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="employment_type_display">
                                        Jenis Hubungan Kerja
                                    </Label>
                                    <Input
                                        id="employment_type_display"
                                        value={
                                            EMPLOYMENT_TYPE_LABELS[
                                                employee.employment_type ?? ''
                                            ] ?? '—'
                                        }
                                        disabled
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Diisi dari Manajemen User — ubah di
                                        sana kalau salah.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="employment_started_on_display">
                                        Tanggal Mulai Kerja
                                    </Label>
                                    <Input
                                        id="employment_started_on_display"
                                        type="date"
                                        value={employee.employment_started_on}
                                        disabled
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Diisi dari Manajemen User — ubah di
                                        sana kalau salah.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="effective_from_display">
                                        Berlaku Sejak
                                    </Label>
                                    <Input
                                        id="effective_from_display"
                                        type="date"
                                        value={
                                            profile?.effective_from ??
                                            employee.employment_started_on
                                        }
                                        disabled
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Ditentukan otomatis saat profil
                                        pertama kali dibuat — tidak bisa
                                        diubah di sini.
                                    </p>
                                </div>
                            </div>

                            <ComponentRowsSection
                                title="Komponen Gaji Bulanan"
                                hint="Isi komponen gaji tetap karyawan ini (Gaji Pokok, Tunjangan, dll) secara manual. Tunjangan harian, Reward Kehadiran Sempurna, dan Lembur dihitung otomatis saat payroll dihitung (dari kehadiran & lembur disetujui), tidak bisa dan tidak perlu ditambah di sini."
                                rows={earningRows}
                                setRows={setEarningRows}
                                availableComponents={
                                    availableEarningComponents
                                }
                                nameOffset={0}
                                errorMessage={errors.components}
                                emptyHint="Belum ada komponen penghasilan."
                                allowEmpty={false}
                            />

                            <ComponentRowsSection
                                title="Potongan Bulanan"
                                hint="Potongan tetap di luar Potongan Alpha/Keterlambatan (yang dihitung otomatis dari absensi) — misalnya cicilan kasbon atau potongan lain yang disepakati. Opsional, kosongkan jika tidak ada."
                                rows={deductionRows}
                                setRows={setDeductionRows}
                                availableComponents={
                                    availableDeductionComponents
                                }
                                nameOffset={earningRows.length}
                                emptyHint="Tidak ada potongan tetap untuk karyawan ini."
                                allowEmpty
                            />

                            <div className="rounded-2xl border p-4">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Total Penghasilan
                                    </span>
                                    <span className="font-medium">
                                        {new Intl.NumberFormat('id-ID', {
                                            style: 'currency',
                                            currency: 'IDR',
                                            maximumFractionDigits: 2,
                                        }).format(earningTotal)}
                                    </span>
                                </div>
                                <div className="mt-2 flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Total Potongan
                                    </span>
                                    <span className="font-medium">
                                        −
                                        {new Intl.NumberFormat('id-ID', {
                                            style: 'currency',
                                            currency: 'IDR',
                                            maximumFractionDigits: 2,
                                        }).format(deductionTotal)}
                                    </span>
                                </div>
                                <div className="mt-2 flex items-center justify-between border-t pt-2 text-sm">
                                    <span className="font-semibold">
                                        Perkiraan Bersih per Bulan
                                    </span>
                                    <span className="font-semibold">
                                        {new Intl.NumberFormat('id-ID', {
                                            style: 'currency',
                                            currency: 'IDR',
                                            maximumFractionDigits: 2,
                                        }).format(netTotal)}
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
