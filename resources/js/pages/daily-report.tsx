import { Head } from '@inertiajs/react';
import { NotebookPen } from 'lucide-react';
import { ComingSoon } from '@/components/coming-soon';
import { dailyReport } from '@/routes';

export default function DailyReport() {
    return (
        <>
            <Head title="Daily Report" />
            <ComingSoon
                title="Daily Report"
                description="Laporan aktivitas harian karyawan."
                icon={NotebookPen}
            />
        </>
    );
}

DailyReport.layout = {
    breadcrumbs: [
        {
            title: 'Daily Report',
            href: dailyReport(),
        },
    ],
};
