import { Form, Head } from '@inertiajs/react';
import { Send } from 'lucide-react';
import CompanySettingsController from '@/actions/App/Http/Controllers/Hrd/CompanySettingsController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Company = {
    id: number;
    name: string;
    telegram_chat_id: string | null;
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
