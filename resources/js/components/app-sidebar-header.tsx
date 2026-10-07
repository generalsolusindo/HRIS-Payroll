import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex min-h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/50 bg-background px-4 py-3 sm:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger
                    className="-ml-1 shrink-0"
                    aria-label="Buka atau tutup menu navigasi"
                />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
        </header>
    );
}
