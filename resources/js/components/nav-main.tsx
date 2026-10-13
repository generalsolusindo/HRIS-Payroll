import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavMain({
    items,
    groupLabel = 'Platform',
}: {
    items: NavItem[];
    groupLabel?: string;
}) {
    const { currentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    // Only the single most specific match wins. Plain prefix-matching
    // would otherwise keep a broader item highlighted too (e.g.
    // "Persetujuan Izin" at /hrd/izin) whenever a sibling's own
    // sub-path (e.g. "Riwayat Izin" at /hrd/izin/riwayat) is open, just
    // because that sub-path happens to start with the broader one's
    // prefix.
    let activeIndex = -1;
    let bestSpecificity = -1;

    items.forEach((item, index) => {
        if (item.isActive) {
            activeIndex = index;
            bestSpecificity = Infinity;
            return;
        }

        for (const path of item.activePaths ?? [toUrl(item.href)]) {
            let specificity = -1;
            if (currentUrl === path) {
                specificity = path.length + 1;
            } else if (currentUrl.startsWith(`${path}/`)) {
                specificity = path.length;
            }

            if (specificity > bestSpecificity) {
                bestSpecificity = specificity;
                activeIndex = index;
            }
        }
    });

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{groupLabel}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item, index) => {
                    const isActive = index === activeIndex;

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive}
                                tooltip={{ children: item.title }}
                                className="h-10 rounded-lg group-data-[collapsible=icon]:p-1!"
                            >
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={isActive ? 'page' : undefined}
                                    onClick={() => {
                                        if (isMobile) setOpenMobile(false);
                                    }}
                                >
                                    {item.icon && (
                                        <span
                                            className={cn(
                                                'flex size-6 shrink-0 items-center justify-center rounded-md transition-colors',
                                                isActive &&
                                                    'bg-sidebar-primary text-sidebar-primary-foreground',
                                            )}
                                        >
                                            <item.icon className="size-4" />
                                        </span>
                                    )}
                                    <span
                                        className={cn(
                                            isActive && 'font-semibold',
                                        )}
                                    >
                                        {item.title}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
