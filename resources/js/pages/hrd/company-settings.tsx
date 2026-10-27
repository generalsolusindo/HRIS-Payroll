import { Form, Head } from '@inertiajs/react';
import { Send } from 'lucide-react';
import CompanySettingsController from '@/actions/App/Http/Controllers/Hrd/CompanySettingsController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Company = {
    id: number;
    name: string;
    telegram_chat_id: string | null;
    legal_name: string | null;
    code: string | null;
    address: string | null;
    city: string | null;
    director_name: string | null;
    director_signature_path: string | null;
};

function CompanySettingsCard({ company }: { company: Company }) {
    return (
        <Form
            {...CompanySettingsController.update.form(company.id)}
            disableWhileProcessing
            setDefaultsOnSuccess
        >
            {({ processing, errors, recentlySuccessful, isDirty }) => (
                <section className="max-w-xl rounded-2xl border bg-card p-5">
                    <div className="flex items-center justify-between gap-3 border-b pb-4">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <Send className="size-4 text-primary" />
                            {company.name}
                        </h2>
                        <span
                            role="status"
                            aria-live="polite"
                            className="text-xs text-muted-foreground"
                        >
                            {processing
                                ? 'Menyimpan…'
                                : isDirty
                                  ? 'Ada perubahan belum disimpan'
                                  : recentlySuccessful
                                    ? 'Tersimpan'
                                    : ''}
                        </span>
                    </div>

                    <div className="mt-4 space-y-1.5">
                        <Label htmlFor={`telegram_chat_id-${company.id}`}>
                            Telegram Chat ID
                        </Label>
                        <Input
                            id={`telegram_chat_id-${company.id}`}
                            name="telegram_chat_id"
                            placeholder="Contoh: -1001234567890"
                            defaultValue={company.telegram_chat_id ?? ''}
                            aria-invalid={Boolean(errors.telegram_chat_id)}
                        />
                        <InputError message={errors.telegram_chat_id} />
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {`Notifikasi pengajuan izin untuk karyawan ${company.name} dikirim ke grup Telegram ini. Kosongkan untuk memakai chat default sistem.`}
                        </p>
                    </div>

                    <div className="mt-5 space-y-4 border-t pt-4">
                        <p className="text-xs font-medium text-muted-foreground">
                            Kop surat slip gaji (opsional) — dipakai saat
                            karyawan/HRD/Finance/Manager mengunduh slip gaji
                            sebagai PDF.
                        </p>

                        <div className="space-y-1.5">
                            <Label htmlFor={`legal_name-${company.id}`}>
                                Nama Resmi Perusahaan
                            </Label>
                            <Input
                                id={`legal_name-${company.id}`}
                                name="legal_name"
                                placeholder="Contoh: CV General Solusindo"
                                defaultValue={company.legal_name ?? ''}
                                aria-invalid={Boolean(errors.legal_name)}
                            />
                            <InputError message={errors.legal_name} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor={`code-${company.id}`}>
                                Kode Perusahaan (prefix NIK)
                            </Label>
                            <Input
                                id={`code-${company.id}`}
                                name="code"
                                placeholder="Contoh: GS"
                                defaultValue={company.code ?? ''}
                                aria-invalid={Boolean(errors.code)}
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor={`address-${company.id}`}>
                                Alamat
                            </Label>
                            <Textarea
                                id={`address-${company.id}`}
                                name="address"
                                rows={2}
                                defaultValue={company.address ?? ''}
                                aria-invalid={Boolean(errors.address)}
                            />
                            <InputError message={errors.address} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor={`city-${company.id}`}>
                                Kota (untuk tanggal tanda tangan)
                            </Label>
                            <Input
                                id={`city-${company.id}`}
                                name="city"
                                placeholder="Contoh: Sidoarjo"
                                defaultValue={company.city ?? ''}
                                aria-invalid={Boolean(errors.city)}
                            />
                            <InputError message={errors.city} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor={`director_name-${company.id}`}>
                                Nama Direktur
                            </Label>
                            <Input
                                id={`director_name-${company.id}`}
                                name="director_name"
                                defaultValue={company.director_name ?? ''}
                                aria-invalid={Boolean(errors.director_name)}
                            />
                            <InputError message={errors.director_name} />
                        </div>

                        <div className="space-y-1.5">
                            <Label
                                htmlFor={`director_signature-${company.id}`}
                            >
                                Tanda Tangan / Stempel{' '}
                                <span className="font-normal text-muted-foreground">
                                    (opsional, gambar)
                                </span>
                            </Label>
                            <input
                                id={`director_signature-${company.id}`}
                                type="file"
                                name="director_signature"
                                accept=".jpg,.jpeg,.png,.webp"
                                className="text-sm"
                            />
                            {company.director_signature_path && (
                                <p className="text-xs text-muted-foreground">
                                    Sudah ada tanda tangan tersimpan — unggah
                                    file baru untuk menggantinya.
                                </p>
                            )}
                            <InputError
                                message={errors.director_signature}
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex justify-end">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing}
                        >
                            {processing ? 'Menyimpan…' : 'Simpan perubahan'}
                        </Button>
                    </div>
                </section>
            )}
        </Form>
    );
}

export default function CompanySettings({
    companies,
}: {
    companies: Company[];
}) {
    return (
        <>
            <Head title="Pengaturan Perusahaan" />
            <div className="flex min-w-0 flex-1 flex-col gap-5 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Pengaturan Perusahaan
                    </h1>
                    <p className="mt-2 max-w-3xl text-sm leading-relaxed text-muted-foreground">
                        HRD mengelola pengaturan setiap instansi di sini —
                        setiap instansi punya Telegram Chat ID sendiri-sendiri,
                        disimpan terpisah.
                    </p>
                </div>

                <div className="flex flex-col gap-5">
                    {companies.map((company) => (
                        <CompanySettingsCard
                            key={company.id}
                            company={company}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

CompanySettings.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan Perusahaan',
            href: CompanySettingsController.index(),
        },
    ],
};
