import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardCheck,
    Landmark,
    Settings2,
} from 'lucide-react';
import PayrollComponentController from '@/actions/App/Http/Controllers/Hrd/PayrollComponentController';
import PayrollPeriodController from '@/actions/App/Http/Controllers/Hrd/PayrollPeriodController';
import PayrollPolicyController from '@/actions/App/Http/Controllers/Hrd/PayrollPolicyController';
import PayrollProfileController from '@/actions/App/Http/Controllers/Hrd/PayrollProfileController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Policy = {
    id: number;
    late_deduction_per_minute: string;
    absence_deduction_per_day: string;
    perfect_attendance_reward: string;
    overtime_rate_per_hour: string;
};

type RateKey =
    | 'late_deduction_per_minute'
    | 'absence_deduction_per_day'
    | 'perfect_attendance_reward'
    | 'overtime_rate_per_hour';
const rateFields: {
    key: RateKey;
    label: string;
    description: string;
    unit: string;
}[] = [
    {
        key: 'late_deduction_per_minute',
        label: 'Potongan terlambat',
        description: 'Dihitung dari total menit terlambat pada rekap absensi.',
        unit: 'Rp / menit',
    },
    {
        key: 'absence_deduction_per_day',
        label: 'Potongan alpha',
        description:
            'Nominal tetap untuk setiap hari yang ditetapkan alpha oleh HRD.',
        unit: 'Rp / hari',
    },
    {
        key: 'perfect_attendance_reward',
        label: 'Reward kehadiran sempurna',
        description:
            'Diberikan jika seluruh tanggal periode sudah direkap, tanpa telat, alpha, atau hari belum jelas.',
        unit: 'Rp / periode',
    },
    {
        key: 'overtime_rate_per_hour',
        label: 'Tarif lembur',
        description:
            'Dikalikan durasi lembur yang disetujui, dihitung proporsional dari menit.',
        unit: 'Rp / jam',
    },
];

function PendingRule({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-2 py-4">
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{title}</p>
                <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                    {description}
                </p>
            </div>
            <span className="rounded-md bg-muted px-2 py-1 text-[11px] text-muted-foreground">
                Belum tersedia
            </span>
        </div>
    );
}

export default function AttendancePolicy({ policy }: { policy: Policy }) {
    return (
        <>
            <Head title="Aturan & Komponen Gaji" />
            <div className="flex min-w-0 flex-1 flex-col gap-5 p-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        Pengaturan penggajian
                    </p>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
                        Aturan & Komponen Gaji
                    </h1>
                    <p className="mt-2 max-w-3xl text-sm leading-relaxed text-muted-foreground">
                        Perubahan tarif digunakan pada perhitungan berikutnya,
                        termasuk hitung ulang setelah revisi. Hasil payroll yang
                        sudah dihitung tetap memakai snapshot sebelumnya.
                    </p>
                </div>
                <>
                    <div className="flex flex-wrap items-end justify-end gap-4 rounded-2xl border bg-card p-4">
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={PayrollComponentController.index()}>
                                    Kelola komponen
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={PayrollProfileController.index()}>
                                    Profil gaji karyawan
                                </Link>
                            </Button>
                        </div>
                    </div>
                    <div>
                        <Form
                            {...PayrollPolicyController.update.form(policy.id)}
                            disableWhileProcessing
                            setDefaultsOnSuccess
                        >
                            {({
                                processing,
                                errors,
                                recentlySuccessful,
                                isDirty,
                            }) => (
                                <div className="space-y-5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex items-center gap-2 text-sm">
                                            <Landmark className="size-4 shrink-0 text-muted-foreground" />
                                            <span className="font-medium">
                                                Kebijakan Absensi & Lembur
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <span
                                                role="status"
                                                aria-live="polite"
                                                className="text-xs text-muted-foreground"
                                            >
                                                {processing
                                                    ? 'Menyimpan tarif…'
                                                    : isDirty
                                                      ? 'Ada perubahan belum disimpan'
                                                      : recentlySuccessful
                                                        ? 'Perubahan tersimpan'
                                                        : 'Tarif tersimpan'}
                                            </span>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Menyimpan…'
                                                    : 'Simpan perubahan'}
                                            </Button>
                                        </div>
                                    </div>
                                    {Object.keys(errors).length > 0 && (
                                        <p
                                            role="alert"
                                            className="rounded-xl border border-destructive/40 bg-card p-3 text-sm text-destructive"
                                        >
                                            Perubahan belum tersimpan. Periksa
                                            kolom yang ditandai dan coba
                                            kembali.
                                        </p>
                                    )}
                                    <div className="grid items-start gap-5 xl:grid-cols-2">
                                        <section className="min-w-0 rounded-2xl border bg-card p-5">
                                            <h2 className="flex items-center gap-2 border-b pb-4 font-semibold">
                                                <ClipboardCheck className="size-4 text-primary" />
                                                Komponen dari data absensi
                                            </h2>
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                Isi 0 untuk menonaktifkan tarif.
                                                Simpan perubahan hanya
                                                memengaruhi empat tarif di
                                                bawah.
                                            </p>
                                            <div className="divide-y">
                                                {rateFields.map((field) => (
                                                    <div
                                                        key={field.key}
                                                        className="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_11rem]"
                                                    >
                                                        <div>
                                                            <Label
                                                                htmlFor={`${field.key}-${policy.id}`}
                                                            >
                                                                {field.label}
                                                            </Label>
                                                            <p
                                                                id={`${field.key}-${policy.id}-hint`}
                                                                className="mt-1 text-xs leading-relaxed text-muted-foreground"
                                                            >
                                                                {
                                                                    field.description
                                                                }
                                                            </p>
                                                        </div>
                                                        <div className="space-y-1.5">
                                                            <p className="text-xs text-muted-foreground">
                                                                {field.unit}
                                                            </p>
                                                            <Input
                                                                id={`${field.key}-${policy.id}`}
                                                                name={field.key}
                                                                type="number"
                                                                inputMode="decimal"
                                                                min="0"
                                                                step="0.01"
                                                                required
                                                                defaultValue={
                                                                    policy[
                                                                        field
                                                                            .key
                                                                    ]
                                                                }
                                                                aria-describedby={`${field.key}-${policy.id}-hint${errors[field.key] ? ` ${field.key}-${policy.id}-error` : ''}`}
                                                                aria-invalid={Boolean(
                                                                    errors[
                                                                        field
                                                                            .key
                                                                    ],
                                                                )}
                                                                className="text-right tabular-nums"
                                                            />
                                                            <InputError
                                                                id={`${field.key}-${policy.id}-error`}
                                                                message={
                                                                    errors[
                                                                        field
                                                                            .key
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            <div className="space-y-2 border-t pt-4">
                                                <Label
                                                    htmlFor={`reason-${policy.id}`}
                                                >
                                                    Alasan perubahan{' '}
                                                    <span className="font-normal text-muted-foreground">
                                                        (opsional)
                                                    </span>
                                                </Label>
                                                <Input
                                                    id={`reason-${policy.id}`}
                                                    name="reason"
                                                    maxLength={2000}
                                                    placeholder="Contoh: penyesuaian tarif lembur"
                                                    aria-invalid={Boolean(
                                                        errors.reason,
                                                    )}
                                                />
                                                <InputError
                                                    message={errors.reason}
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Alasan disimpan dalam
                                                    riwayat perubahan
                                                    penggajian.
                                                </p>
                                            </div>
                                            <div className="mt-5 divide-y border-t">
                                                <PendingRule
                                                    title="Uang transport, makan & dinas luar per hari"
                                                    description="Perhitungan otomatis berdasarkan hari kehadiran belum tersedia. Komponen pendapatan tetap dapat dikelola melalui profil gaji."
                                                />
                                                <PendingRule
                                                    title="Toleransi & potongan telat per kejadian"
                                                    description="Saat ini potongan dihitung per menit. Tidak ada perubahan jam masuk atau toleransi absensi melalui halaman ini."
                                                />
                                                <PendingRule
                                                    title="Alpha proporsional & lembur bertingkat"
                                                    description="Saat ini alpha memakai tarif harian tetap dan lembur memakai tarif per jam, belum formula bertingkat."
                                                />
                                            </div>
                                        </section>
                                        <div className="space-y-5">
                                            <section className="rounded-2xl border bg-card p-5">
                                                <h2 className="flex items-center gap-2 border-b pb-4 font-semibold">
                                                    <CalendarDays className="size-4 text-primary" />
                                                    Periode & jadwal
                                                </h2>
                                                <div className="py-4">
                                                    <h3 className="text-sm font-medium">
                                                        Rentang absensi &
                                                        tanggal pembayaran
                                                    </h3>
                                                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                                        Tanggal awal dan akhir
                                                        absensi serta rencana
                                                        pembayaran diatur saat
                                                        membuat masing-masing
                                                        periode gaji.
                                                    </p>
                                                    <Button
                                                        variant="outline"
                                                        asChild
                                                        className="mt-3"
                                                    >
                                                        <Link
                                                            href={PayrollPeriodController.index()}
                                                        >
                                                            Kelola periode gaji
                                                        </Link>
                                                    </Button>
                                                </div>
                                                <div className="divide-y border-t">
                                                    <PendingRule
                                                        title="Cut-off & penarikan data otomatis"
                                                        description="Belum ada jadwal penarikan otomatis. Rekap absensi diperbarui ketika perhitungan payroll dijalankan."
                                                    />
                                                    <PendingRule
                                                        title="Penyesuaian tanggal gajian saat libur"
                                                        description="Tanggal pembayaran belum otomatis dimajukan berdasarkan kalender kerja."
                                                    />
                                                    <PendingRule
                                                        title="Aturan berlaku mulai periode tertentu"
                                                        description="Perubahan belum dapat dijadwalkan khusus untuk periode berikutnya. Tarif tersimpan dipakai pada perhitungan berikutnya."
                                                    />
                                                </div>
                                            </section>
                                            <section className="rounded-2xl border bg-card p-5">
                                                <h2 className="flex items-center gap-2 border-b pb-4 font-semibold">
                                                    <Settings2 className="size-4 text-primary" />
                                                    Potongan wajib
                                                </h2>
                                                <div className="divide-y">
                                                    <PendingRule
                                                        title="BPJS Kesehatan"
                                                        description="Tarif, dasar upah dan kepesertaan untuk perhitungan otomatis belum dikonfigurasi."
                                                    />
                                                    <PendingRule
                                                        title="BPJS Ketenagakerjaan — JHT & JP"
                                                        description="Perhitungan serta pemisahan porsi karyawan dan perusahaan belum tersedia."
                                                    />
                                                    <PendingRule
                                                        title="PPh 21"
                                                        description="Data perpajakan dan aturan perhitungan perlu dilengkapi sebelum potongan dihitung otomatis."
                                                    />
                                                </div>
                                                <p className="border-t pt-4 text-xs leading-relaxed text-muted-foreground">
                                                    Bagian bertanda belum
                                                    tersedia tidak ikut disimpan
                                                    dan tidak menghasilkan
                                                    potongan otomatis.
                                                </p>
                                            </section>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </div>
                </>
            </div>
        </>
    );
}

AttendancePolicy.layout = {
    breadcrumbs: [
        {
            title: 'Aturan & Komponen Gaji',
            href: PayrollPolicyController.index(),
        },
    ],
};
