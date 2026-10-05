import { Form, Head } from '@inertiajs/react';
import { CalendarDays as CalendarDaysIcon, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CompanyCalendarDayController from '@/actions/App/Http/Controllers/Hrd/CompanyCalendarDayController';
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

type DayRow = {
    id: number;
    company: Company;
    date: string;
    type: string;
    description: string | null;
};

type Props = {
    days: DayRow[];
    companies: Company[];
};

const TYPE_LABELS: Record<string, string> = {
    workday: 'Hari Kerja',
    rest_day: 'Hari Istirahat',
    holiday: 'Hari Libur',
};

function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function CalendarDays({ days, companies }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [companyId, setCompanyId] = useState('');
    const [type, setType] = useState('holiday');

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

                                            <div className="grid gap-2">
                                                <Label htmlFor="date">
                                                    Tanggal
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
                                                <Label htmlFor="type">
                                                    Jenis
                                                </Label>
                                                <input
                                                    type="hidden"
                                                    name="type"
                                                    value={type}
                                                />
                                                <Select
                                                    value={type}
                                                    onValueChange={setType}
                                                >
                                                    <SelectTrigger
                                                        id="type"
                                                        className="w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {Object.entries(
                                                            TYPE_LABELS,
                                                        ).map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => (
                                                                <SelectItem
                                                                    key={value}
                                                                    value={
                                                                        value
                                                                    }
                                                                >
                                                                    {label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.type}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="description">
                                                    Keterangan (opsional)
                                                </Label>
                                                <Input
                                                    id="description"
                                                    name="description"
                                                    placeholder="Hari Kemerdekaan"
                                                />
                                                <InputError
                                                    message={errors.description}
                                                />
                                            </div>
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
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {day.description ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Form
                                                {...CompanyCalendarDayController.destroy.form(
                                                    day.id,
                                                )}
                                                disableWhileProcessing
                                                onBefore={() =>
                                                    confirm(
                                                        `Hapus tanggal ${formatDate(day.date)}?`,
                                                    )
                                                }
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        size="icon"
                                                        variant="outline"
                                                        className="rounded-full text-destructive hover:text-destructive"
                                                        aria-label="Hapus"
                                                        disabled={processing}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </Form>
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

CalendarDays.layout = {
    breadcrumbs: [
        {
            title: 'Kalender Kerja',
            href: CompanyCalendarDayController.index(),
        },
    ],
};
