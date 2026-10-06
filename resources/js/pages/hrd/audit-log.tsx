import { Head, router } from '@inertiajs/react';
import { History } from 'lucide-react';
import { useState } from 'react';
import PayrollAuditLogController from '@/actions/App/Http/Controllers/Hrd/PayrollAuditLogController';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type AuditLogRow = {
    id: number;
    company_name: string;
    actor_name: string;
    entity_type: string;
    entity_id: number;
    action: string;
    before_values: Record<string, unknown> | null;
    after_values: Record<string, unknown> | null;
    reason: string;
    created_at: string;
};

type PaginatedLogs = {
    data: AuditLogRow[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Company = { id: number; name: string };

type Props = {
    logs: PaginatedLogs;
    entityTypes: string[];
    companies: Company[];
    filters: { entity_type?: string; company_id?: string };
};

const ENTITY_TYPE_LABELS: Record<string, string> = {
    AttendanceReview: 'Koreksi Absensi',
    EmployeePayrollProfile: 'Profil Gaji',
    PayrollPolicy: 'Kebijakan Absensi',
};

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDiff(value: Record<string, unknown> | null): string {
    if (!value) {
        return '—';
    }

    return JSON.stringify(value, null, 2);
}

export default function AuditLog({
    logs,
    entityTypes,
    companies,
    filters,
}: Props) {
    const [entityType, setEntityType] = useState(filters.entity_type ?? '');
    const [companyId, setCompanyId] = useState(filters.company_id ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);

    function applyFilters(next: { entity_type?: string; company_id?: string }) {
        router.get(
            PayrollAuditLogController.index().url,
            {
                entity_type: next.entity_type ?? entityType,
                company_id: next.company_id ?? companyId,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function goToPage(url: string | null) {
        if (!url) {
            return;
        }
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Audit Log" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Audit Log
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Riwayat koreksi data yang berpengaruh ke payroll —
                        koreksi absensi, profil gaji, dan tarif kebijakan.
                        Transisi status (ajukan/setujui/kunci) ada di riwayat
                        persetujuan pada halaman hasil payroll.
                    </p>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Select
                        value={entityType || 'all'}
                        onValueChange={(value) => {
                            const next = value === 'all' ? '' : value;
                            setEntityType(next);
                            applyFilters({ entity_type: next });
                        }}
                    >
                        <SelectTrigger className="w-56">
                            <SelectValue placeholder="Semua jenis data" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Semua jenis data
                            </SelectItem>
                            {entityTypes.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {ENTITY_TYPE_LABELS[type] ?? type}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={companyId || 'all'}
                        onValueChange={(value) => {
                            const next = value === 'all' ? '' : value;
                            setCompanyId(next);
                            applyFilters({ company_id: next });
                        }}
                    >
                        <SelectTrigger className="w-56">
                            <SelectValue placeholder="Semua instansi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua instansi</SelectItem>
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
                </div>

                {logs.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                        <History className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Belum ada riwayat koreksi.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {logs.data.map((log) => (
                            <div
                                key={log.id}
                                className="flex flex-col gap-2 rounded-2xl border bg-card p-4"
                            >
                                <button
                                    type="button"
                                    className="flex items-center justify-between gap-4 text-left"
                                    onClick={() =>
                                        setExpanded((current) =>
                                            current === log.id ? null : log.id,
                                        )
                                    }
                                >
                                    <div>
                                        <div className="font-semibold">
                                            {ENTITY_TYPE_LABELS[
                                                log.entity_type
                                            ] ?? log.entity_type}{' '}
                                            #{log.entity_id} — {log.action}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {log.company_name} ·{' '}
                                            {log.actor_name} ·{' '}
                                            {formatDateTime(log.created_at)}
                                        </div>
                                    </div>
                                </button>
                                <p className="text-sm text-muted-foreground">
                                    {log.reason}
                                </p>
                                {expanded === log.id && (
                                    <div className="grid gap-3 border-t pt-3 text-xs sm:grid-cols-2">
                                        <div>
                                            <div className="mb-1 font-medium text-muted-foreground uppercase">
                                                Sebelum
                                            </div>
                                            <pre className="overflow-x-auto rounded-xl bg-muted/40 p-2">
                                                {formatDiff(log.before_values)}
                                            </pre>
                                        </div>
                                        <div>
                                            <div className="mb-1 font-medium text-muted-foreground uppercase">
                                                Sesudah
                                            </div>
                                            <pre className="overflow-x-auto rounded-xl bg-muted/40 p-2">
                                                {formatDiff(log.after_values)}
                                            </pre>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Halaman {logs.current_page} dari {logs.last_page} ·{' '}
                            {logs.total} catatan
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!logs.prev_page_url}
                                onClick={() => goToPage(logs.prev_page_url)}
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!logs.next_page_url}
                                onClick={() => goToPage(logs.next_page_url)}
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

AuditLog.layout = {
    breadcrumbs: [
        {
            title: 'Audit Log',
            href: PayrollAuditLogController.index(),
        },
    ],
};
