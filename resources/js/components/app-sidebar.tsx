import { Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarOff,
    ClipboardList,
    LayoutGrid,
    NotebookPen,
    Users,
    Wallet,
} from 'lucide-react';
import AttendanceHistoryController from '@/actions/App/Http/Controllers/AttendanceHistoryController';
import AttendanceDashboardController from '@/actions/App/Http/Controllers/Hrd/AttendanceDashboardController';
import UserManagementController from '@/actions/App/Http/Controllers/Hrd/UserManagementController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavThemeToggle } from '@/components/nav-theme-toggle';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dailyReport, dashboard, izin, penggajian } from '@/routes';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Riwayat Absensi',
        href: AttendanceHistoryController.index(),
        icon: CalendarClock,
    },
];

const moduleNavItems: NavItem[] = [
    {
        title: 'Penggajian',
        href: penggajian(),
        icon: Wallet,
    },
    {
        title: 'Daily Report',
        href: dailyReport(),
        icon: NotebookPen,
    },
    {
        title: 'Izin',
        href: izin(),
        icon: CalendarOff,
    },
];

const hrdNavItems: NavItem[] = [
    {
        title: 'Manajemen User',
        href: UserManagementController.index(),
        icon: Users,
    },
    {
        title: 'Rekap Absensi',
        href: AttendanceDashboardController.index(),
        icon: ClipboardList,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isHrd = auth.user.role === 'hrd';

    // An HRD account is for monitoring/management only — it never clocks
    // in itself, so its own attendance history is never meaningful.
    const visibleMainNavItems = isHrd
        ? mainNavItems.filter((item) => item.title !== 'Riwayat Absensi')
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibleMainNavItems} />
                <NavMain items={moduleNavItems} groupLabel="Modul Lain" />
                {isHrd && <NavMain items={hrdNavItems} groupLabel="HRD" />}
            </SidebarContent>

            <SidebarFooter>
                <NavThemeToggle />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
