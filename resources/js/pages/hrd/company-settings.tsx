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
    code: string;
    telegram_chat_id: string | null;
};

export default function CompanySettings({ company }: { company: Company }) {
    return (
        <>
            <Head title="Pengaturan Perusahaan" />
            <div className="flex min-w-0 flex-1 flex-col gap-5 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Pengaturan Perusahaan
                    </h1>
                    <p className="mt-2 max-w-3xl text-sm leading-relaxed text-muted-foreground">
                        Pengaturan ini khusus untuk instansi Anda sendiri (
                        {company.name}) dan tidak memengaruhi instansi lain.
                    </p>
                </div>

                <Form
                    {...CompanySettingsController.update.form()}
                    disableWhileProcessing
                    setDefaultsOnSuccess
                >
                    {({ processing, errors, recentlySuccessful, isDirty }) => (
                        <div className="space-y-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
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
                                            ? 'Perubahan tersimpan'
                                            : ''}
                                </span>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Menyimpan…'
                                        : 'Simpan perubahan'}
                                </Button>
                            </div>

                            <section className="max-w-xl rounded-2xl border bg-card p-5">
                                <h2 className="flex items-center gap-2 border-b pb-4 font-semibold">
                                    <Send className="size-4 text-primary" />
                                    Notifikasi Telegram
                                </h2>

                                <div className="space-y-3 pt-4">
                                    <Label>Instansi</Label>
                                    <Input
                                        value={`${company.name} (${company.code})`}
                                        disabled
                                    />
                                </div>

                                <div className="mt-4 space-y-1.5">
                                    <Label htmlFor="telegram_chat_id">
                                        Telegram Chat ID
                                    </Label>
                                    <Input
                                        id="telegram_chat_id"
                                        name="telegram_chat_id"
                                        placeholder="Contoh: -1001234567890"
                                        defaultValue={
                                            company.telegram_chat_id ?? ''
                                        }
                                        aria-invalid={Boolean(
                                            errors.telegram_chat_id,
                                        )}
                                    />
                                    <InputError
                                        message={errors.telegram_chat_id}
                                    />
                                    <p className="text-xs leading-relaxed text-muted-foreground">
                                        {`Notifikasi pengajuan izin untuk karyawan ${company.name} dikirim ke grup Telegram ini. Kosongkan untuk memakai chat default sistem.`}
                                    </p>
                                </div>
                            </section>
                        </div>
                    )}
                </Form>
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
