import { EMPLOYMENT_TYPE_LABELS } from '@/lib/employment-types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import EmployeeDocumentController from '@/actions/App/Http/Controllers/Hrd/EmployeeDocumentController';
import PayrollProfileController from '@/actions/App/Http/Controllers/Hrd/PayrollProfileController';
import UserManagementController from '@/actions/App/Http/Controllers/Hrd/UserManagementController';
import { ConfirmDialog } from '@/components/confirm-dialog';
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

type Employee = {
    id: number;
    employee_number: string | null;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    status: string;
    company: { id: number; name: string };
    division: { id: number; name: string } | null;
    employment_type: string | null;
    employment_started_on: string | null;
    probation_ends_on: string | null;
    contract_ends_on: string | null;
    employment_ended_on: string | null;
    is_payroll_eligible: boolean;
    is_allowance_eligible: boolean;
    bank_name: string | null;
    bank_account_holder: string | null;
};

type SalaryProfile = {
    division_name: string | null;
    components: { name: string; type: string; amount: string }[];
    monthly_total: string;
};

type Document = {
    id: number;
    type: string;
    label: string | null;
    file_name: string;
    file_size: number;
    uploaded_by_name: string | null;
    created_at: string;
};

type Props = {
    employee: Employee;
    salaryProfile: SalaryProfile | null;
    documents: Document[];
    documentTypes: string[];
};

const ROLE_LABELS: Record<string, string> = {
    karyawan: 'Karyawan',
    hrd: 'HRD',
    admin: 'Admin (Manager)',
    finance: 'Finance',
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Menunggu',
    active: 'Aktif',
    rejected: 'Nonaktif',
};

const DOCUMENT_TYPE_LABELS: Record<string, string> = {
    ktp: 'KTP',
    kk: 'Kartu Keluarga (KK)',
    ktm: 'KTM / Kartu Siswa',
    ijazah: 'Fotokopi Ijazah',
    lainnya: 'Lainnya',
};

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatRupiah(value: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function Field({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-medium">{value}</div>
        </div>
    );
}

function DocumentUploadForm({
    employeeId,
    documentTypes,
}: {
    employeeId: number;
    documentTypes: string[];
}) {
    const [type, setType] = useState(documentTypes[0] ?? 'lainnya');
    const [label, setLabel] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (!file || submitting) {
            return;
        }

        setSubmitting(true);
        setErrors({});

        const formData = new FormData();
        formData.append('type', type);
        if (label.trim()) {
            formData.append('label', label.trim());
        }
        formData.append('file', file);

        router.post(
            EmployeeDocumentController.store.url(employeeId),
            formData,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setLabel('');
                    setFile(null);
                },
                onError: (pageErrors) => {
                    setErrors(pageErrors as Record<string, string>);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="grid gap-3 rounded-xl border p-3 sm:grid-cols-[10rem_1fr_1fr_auto] sm:items-end"
        >
            <div className="grid gap-1.5">
                <Label htmlFor="doc-type">Jenis Dokumen</Label>
                <Select value={type} onValueChange={setType}>
                    <SelectTrigger id="doc-type" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {documentTypes.map((value) => (
                            <SelectItem key={value} value={value}>
                                {DOCUMENT_TYPE_LABELS[value] ?? value}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.type} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="doc-label">
                    Keterangan{' '}
                    <span className="font-normal text-muted-foreground">
                        (opsional)
                    </span>
                </Label>
                <Input
                    id="doc-label"
                    value={label}
                    onChange={(e) => setLabel(e.target.value)}
                    placeholder="Contoh: Ijazah S1"
                />
                <InputError message={errors.label} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="doc-file">File</Label>
                <input
                    id="doc-file"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                    className="text-sm"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                />
                <InputError message={errors.file} />
            </div>
            <Button type="submit" disabled={!file || submitting}>
                <Upload className="size-4" />
                Unggah
            </Button>
        </form>
    );
}

export default function UserDetail({
    employee,
    salaryProfile,
    documents,
    documentTypes,
}: Props) {
    const [deletingDocument, setDeletingDocument] = useState<Document | null>(
        null,
    );
    const [deletingDocumentBusy, setDeletingDocumentBusy] = useState(false);

    function confirmDeleteDocument() {
        if (!deletingDocument) return;

        setDeletingDocumentBusy(true);
        router.delete(
            EmployeeDocumentController.destroy.url(deletingDocument.id),
            {
                preserveScroll: true,
                onSuccess: () => setDeletingDocument(null),
                onFinish: () => setDeletingDocumentBusy(false),
            },
        );
    }

    return (
        <>
            <Head title={`Detail — ${employee.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <Link href={UserManagementController.index()}>
                        <Button variant="ghost" size="sm" className="w-fit">
                            <ArrowLeft className="size-3.5" />
                            Kembali ke Manajemen User
                        </Button>
                    </Link>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                            {employee.name}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {employee.employee_number ?? '—'} ·{' '}
                            {ROLE_LABELS[employee.role] ?? employee.role} ·{' '}
                            {STATUS_LABELS[employee.status] ?? employee.status}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {employee.probation_ends_on && (
                            <span className="rounded-full border border-amber-400/50 bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-600 dark:border-amber-400/40 dark:text-amber-400">
                                Masa percobaan hingga{' '}
                                {formatDate(employee.probation_ends_on)}
                            </span>
                        )}
                        {!employee.is_payroll_eligible && (
                            <span className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                Tidak ikut payroll
                            </span>
                        )}
                    </div>
                </div>

                <div className="grid items-start gap-5 xl:grid-cols-2">
                    <section className="rounded-2xl border bg-card p-5">
                        <h2 className="border-b pb-3 font-semibold">
                            Data Pribadi
                        </h2>
                        <div className="grid grid-cols-2 gap-4 pt-4">
                            <Field label="Nama Lengkap" value={employee.name} />
                            <Field label="Email" value={employee.email} />
                            <Field
                                label="Nomor WhatsApp"
                                value={employee.phone ?? '-'}
                            />
                            <Field
                                label="Nomor Karyawan"
                                value={employee.employee_number ?? '-'}
                            />
                        </div>
                    </section>

                    <section className="rounded-2xl border bg-card p-5">
                        <h2 className="border-b pb-3 font-semibold">
                            Data Pekerjaan &amp; Status
                        </h2>
                        <div className="grid grid-cols-2 gap-4 pt-4">
                            <Field
                                label="Instansi"
                                value={employee.company.name}
                            />
                            <Field
                                label="Jabatan / Divisi"
                                value={employee.division?.name ?? '-'}
                            />
                            <Field
                                label="Jenis Karyawan"
                                value={
                                    EMPLOYMENT_TYPE_LABELS[
                                        employee.employment_type ?? ''
                                    ] ?? 'Belum ditentukan'
                                }
                            />
                            <Field
                                label="Role"
                                value={
                                    ROLE_LABELS[employee.role] ?? employee.role
                                }
                            />
                            <Field
                                label="Status Akun"
                                value={
                                    STATUS_LABELS[employee.status] ??
                                    employee.status
                                }
                            />
                            <Field
                                label="Ikut Payroll"
                                value={
                                    employee.is_payroll_eligible
                                        ? 'Ya'
                                        : 'Tidak'
                                }
                            />
                            <Field
                                label="Dapat Tunjangan"
                                value={
                                    employee.is_allowance_eligible
                                        ? 'Ya'
                                        : 'Tidak'
                                }
                            />
                        </div>
                    </section>

                    <section className="rounded-2xl border bg-card p-5">
                        <h2 className="border-b pb-3 font-semibold">
                            Tanggal &amp; Kontrak
                        </h2>
                        <div className="grid grid-cols-2 gap-4 pt-4">
                            <Field
                                label="Tanggal Mulai Kerja"
                                value={formatDate(
                                    employee.employment_started_on,
                                )}
                            />
                            <Field
                                label="Tanggal Berakhir Masa Percobaan"
                                value={formatDate(
                                    employee.probation_ends_on,
                                )}
                            />
                            <Field
                                label="Tanggal Berakhir Kontrak"
                                value={formatDate(employee.contract_ends_on)}
                            />
                            <Field
                                label="Tanggal Keluar"
                                value={formatDate(
                                    employee.employment_ended_on,
                                )}
                            />
                        </div>
                    </section>

                    <section className="rounded-2xl border bg-card p-5">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h2 className="font-semibold">Gaji &amp; Tunjangan</h2>
                            <Link
                                href={PayrollProfileController.edit(
                                    employee.id,
                                )}
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="rounded-full"
                                >
                                    Atur di Profil Gaji
                                </Button>
                            </Link>
                        </div>
                        {salaryProfile ? (
                            <div className="pt-4">
                                <div className="flex flex-col gap-1.5 text-sm">
                                    {salaryProfile.components.map(
                                        (component, index) => (
                                            <div
                                                key={index}
                                                className="flex items-center justify-between"
                                            >
                                                <span>{component.name}</span>
                                                <span
                                                    className={
                                                        component.type ===
                                                        'deduction'
                                                            ? 'text-red-600 dark:text-red-400'
                                                            : ''
                                                    }
                                                >
                                                    {component.type ===
                                                    'deduction'
                                                        ? '− '
                                                        : ''}
                                                    {formatRupiah(
                                                        component.amount,
                                                    )}
                                                </span>
                                            </div>
                                        ),
                                    )}
                                </div>
                                <div className="mt-3 flex items-center justify-between border-t pt-3 font-semibold">
                                    <span>Total / Bulan</span>
                                    <span>
                                        {formatRupiah(
                                            salaryProfile.monthly_total,
                                        )}
                                    </span>
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Ini komponen manual saja — belum termasuk
                                    tunjangan kehadiran/transport otomatis
                                    atau potongan dari absensi.
                                </p>
                            </div>
                        ) : (
                            <p className="pt-4 text-sm text-muted-foreground">
                                Belum ada profil gaji untuk karyawan ini.
                            </p>
                        )}
                    </section>

                    {(employee.bank_name || employee.bank_account_holder) && (
                        <section className="rounded-2xl border bg-card p-5">
                            <h2 className="border-b pb-3 font-semibold">
                                Rekening Bank
                            </h2>
                            <div className="grid grid-cols-2 gap-4 pt-4">
                                <Field
                                    label="Nama Bank"
                                    value={employee.bank_name ?? '-'}
                                />
                                <Field
                                    label="Nama Pemilik Rekening"
                                    value={
                                        employee.bank_account_holder ?? '-'
                                    }
                                />
                            </div>
                        </section>
                    )}

                    <section className="rounded-2xl border bg-card p-5 xl:col-span-2">
                        <h2 className="border-b pb-3 font-semibold">
                            Dokumen
                        </h2>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Semua dokumen di sini opsional — unggah yang ada
                            saja, tidak ada yang wajib.
                        </p>

                        <div className="mt-4">
                            <DocumentUploadForm
                                employeeId={employee.id}
                                documentTypes={documentTypes}
                            />
                        </div>

                        {documents.length === 0 ? (
                            <p className="mt-4 text-sm text-muted-foreground">
                                Belum ada dokumen diunggah.
                            </p>
                        ) : (
                            <div className="mt-4 flex flex-col gap-2">
                                {documents.map((document) => (
                                    <div
                                        key={document.id}
                                        className="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3 text-sm"
                                    >
                                        <div className="flex min-w-0 items-center gap-2">
                                            <FileText className="size-4 shrink-0 text-muted-foreground" />
                                            <div className="min-w-0">
                                                <div className="font-medium">
                                                    {DOCUMENT_TYPE_LABELS[
                                                        document.type
                                                    ] ?? document.type}
                                                    {document.label
                                                        ? ` — ${document.label}`
                                                        : ''}
                                                </div>
                                                <div className="truncate text-xs text-muted-foreground">
                                                    {document.file_name} ·{' '}
                                                    {formatFileSize(
                                                        document.file_size,
                                                    )}
                                                    {document.uploaded_by_name
                                                        ? ` · diunggah oleh ${document.uploaded_by_name}`
                                                        : ''}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            <a
                                                href={
                                                    EmployeeDocumentController.show.url(
                                                        document.id,
                                                    )
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="rounded-full"
                                                    aria-label={`Unduh ${document.file_name}`}
                                                >
                                                    <Download className="size-4" />
                                                </Button>
                                            </a>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="rounded-full text-destructive hover:text-destructive"
                                                aria-label={`Hapus ${document.file_name}`}
                                                onClick={() =>
                                                    setDeletingDocument(
                                                        document,
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>

            <ConfirmDialog
                open={deletingDocument !== null}
                onOpenChange={(open) => {
                    if (!open) setDeletingDocument(null);
                }}
                title="Hapus Dokumen"
                description={
                    deletingDocument
                        ? `Hapus dokumen "${DOCUMENT_TYPE_LABELS[deletingDocument.type] ?? deletingDocument.type}"? Tindakan ini tidak bisa dibatalkan.`
                        : ''
                }
                confirmLabel="Hapus"
                destructive
                confirming={deletingDocumentBusy}
                onConfirm={confirmDeleteDocument}
            />
        </>
    );
}

UserDetail.layout = {
    breadcrumbs: [
        {
            title: 'Manajemen User',
            href: UserManagementController.index(),
        },
        {
            title: 'Detail Karyawan',
            href: '#',
        },
    ],
};
