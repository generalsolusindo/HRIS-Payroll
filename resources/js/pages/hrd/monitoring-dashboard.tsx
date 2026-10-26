import { Head } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import MonitoringDashboardController from '@/actions/App/Http/Controllers/Hrd/MonitoringDashboardController';

type Stats = {
    karyawan_aktif: number;
    kandidat_aktif: number | null;
    pkl_aktif: number | null;
    dokumen_lengkap_percent: number;
};

type AttentionItem = {
    name: string;
    monitoring: string;
    deadline: string | null;
    days_left: number | null;
    severity: 'red' | 'amber' | 'green';
};

type Props = {
    stats: Stats;
    attention: AttentionItem[];
};

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
}

const SEVERITY_DOT_CLASS: Record<AttentionItem['severity'], string> = {
    red: 'bg-red-500',
    amber: 'bg-amber-500',
    green: 'bg-emerald-500',
};

function StatCard({
    label,
    value,
    caption,
}: {
    label: string;
    value: string;
    caption: string;
}) {
    return (
        <div className="rounded-xl border p-4 text-center">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-bold tabular-nums">
                {value}
            </div>
            <div className="text-xs text-muted-foreground">{caption}</div>
        </div>
    );
}

function StatusBadge({ item }: { item: AttentionItem }) {
    const label =
        item.days_left === null
            ? 'Belum'
            : item.days_left < 0
              ? `Lewat ${Math.abs(item.days_left)} hari`
              : `${item.days_left} hari`;

    return (
        <span className="inline-flex items-center gap-1.5 text-sm">
            <span
                className={`size-2.5 rounded-full ${SEVERITY_DOT_CLASS[item.severity]}`}
            />
            {label}
        </span>
    );
}

export default function MonitoringDashboard({ stats, attention }: Props) {
    return (
        <>
            <Head title="Monitoring HR" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        Dashboard Monitoring HR
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Ringkasan status karyawan dan hal-hal yang mendekati
                        tenggat waktu.
                    </p>
                </div>

                <div className="rounded-2xl border bg-card p-5">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Monitoring HR
                    </h2>
                    <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <StatCard
                            label="Karyawan"
                            value={String(stats.karyawan_aktif)}
                            caption="Aktif"
                        />
                        <StatCard
                            label="Kandidat"
                            value={
                                stats.kandidat_aktif === null
                                    ? '—'
                                    : String(stats.kandidat_aktif)
                            }
                            caption={
                                stats.kandidat_aktif === null
                                    ? 'Belum tersedia'
                                    : 'Aktif'
                            }
                        />
                        <StatCard
                            label="PKL"
                            value={
                                stats.pkl_aktif === null
                                    ? '—'
                                    : String(stats.pkl_aktif)
                            }
                            caption={
                                stats.pkl_aktif === null
                                    ? 'Belum tersedia'
                                    : 'Aktif'
                            }
                        />
                        <StatCard
                            label="Dokumen"
                            value={`${stats.dokumen_lengkap_percent}%`}
                            caption="Lengkap (KTP + KK)"
                        />
                    </div>
                    {(stats.kandidat_aktif === null ||
                        stats.pkl_aktif === null) && (
                        <p className="mt-3 text-xs text-muted-foreground">
                            Modul Kandidat dan PKL/Internship belum dibangun
                            — angkanya baru muncul setelah modul itu ada.
                        </p>
                    )}
                </div>

                <div>
                    <h2 className="mb-3 text-lg font-semibold">
                        Perlu Perhatian
                    </h2>

                    {attention.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 rounded-3xl bg-muted/40 py-16 text-center">
                            <ListChecks className="size-8 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                Tidak ada yang perlu perhatian saat ini.
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
                                            Monitoring
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Deadline
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {attention.map((item, index) => (
                                        <tr key={index}>
                                            <td className="px-4 py-3 font-medium">
                                                {item.name}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.monitoring}
                                            </td>
                                            <td className="px-4 py-3">
                                                {formatDate(item.deadline)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge item={item} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

MonitoringDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Monitoring HR',
            href: MonitoringDashboardController.index(),
        },
    ],
};
