import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Calculator,
    CalendarClock,
    CalendarDays,
    CalendarOff,
    ClipboardCheck,
    ClipboardList,
    Clock,
    History,
    LayoutGrid,
    NotebookPen,
    ShieldCheck,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import AttendanceHistoryController from '@/actions/App/Http/Controllers/AttendanceHistoryController';
import PayrollPaymentController from '@/actions/App/Http/Controllers/Finance/PayrollPaymentController';
import AttendanceDashboardController from '@/actions/App/Http/Controllers/Hrd/AttendanceDashboardController';
import AttendanceReviewController from '@/actions/App/Http/Controllers/Hrd/AttendanceReviewController';
import CompanyCalendarDayController from '@/actions/App/Http/Controllers/Hrd/CompanyCalendarDayController';
import DivisionController from '@/actions/App/Http/Controllers/Hrd/DivisionController';
import OvertimeApprovalController from '@/actions/App/Http/Controllers/Hrd/OvertimeApprovalController';
import PayrollAuditLogController from '@/actions/App/Http/Controllers/Hrd/PayrollAuditLogController';
import PayrollComponentController from '@/actions/App/Http/Controllers/Hrd/PayrollComponentController';
import PayrollPeriodController from '@/actions/App/Http/Controllers/Hrd/PayrollPeriodController';
import PayrollPolicyController from '@/actions/App/Http/Controllers/Hrd/PayrollPolicyController';
import PayrollProfileController from '@/actions/App/Http/Controllers/Hrd/PayrollProfileController';
import UserManagementController from '@/actions/App/Http/Controllers/Hrd/UserManagementController';
import OvertimeRequestController from '@/actions/App/Http/Controllers/OvertimeRequestController';
import LeaveRequestApprovalController from '@/actions/App/Http/Controllers/Manager/LeaveRequestApprovalController';
import PayrollRunApprovalController from '@/actions/App/Http/Controllers/Manager/PayrollRunApprovalController';
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
    {
        title: 'Lembur',
        href: OvertimeRequestController.index(),
        icon: Clock,
    },
];

const payrollNavItems: NavItem[] = [
    {
        title: 'Proses Gaji',
        href: PayrollPeriodController.index(),
        icon: Calculator,
        activePaths: ['/hrd/periode-gaji', '/hrd/hasil-payroll'],
    },
    {
        title: 'Aturan Gaji',
        href: PayrollPolicyController.index(),
        icon: ShieldCheck,
    },
    {
        title: 'Komponen Gaji',
        href: PayrollComponentController.index(),
        icon: Wallet,
    },
    {
        title: 'Profil Gaji',
        href: PayrollProfileController.index(),
        icon: UserCog,
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
    {
        title: 'Divisi',
        href: DivisionController.index(),
        icon: Building2,
    },
    {
        title: 'Kalender Kerja',
        href: CompanyCalendarDayController.index(),
        icon: CalendarDays,
    },
    {
        title: 'Koreksi Absensi',
        href: AttendanceReviewController.index(),
        icon: ClipboardCheck,
    },
    {
        title: 'Persetujuan Lembur',
        href: OvertimeApprovalController.index(),
        icon: Clock,
    },
    {
        title: 'Audit Log',
        href: PayrollAuditLogController.index(),
        icon: History,
    },
];

const managerNavItems: NavItem[] = [
    {
        title: 'Persetujuan Izin',
        href: LeaveRequestApprovalController.index(),
        icon: CalendarOff,
    },
    {
        title: 'Persetujuan Payroll',
        href: PayrollRunApprovalController.index(),
        icon: Wallet,
    },
];

const financeNavItems: NavItem[] = [
    {
        title: 'Pembayaran Payroll',
        href: PayrollPaymentController.index(),
        icon: Wallet,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isHrd = auth.user.role === 'hrd';
    const isManager = auth.user.role === 'admin';
    const isFinance = auth.user.role === 'finance';

    // An HRD account is for monitoring/management only — it never clocks
    // in itself, so its own attendance history is never meaningful.
    const visibleMainNavItems = isHrd
        ? mainNavItems.filter((item) => item.title !== 'Riwayat Absensi')
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="border-b border-sidebar-border/60 p-3">
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

            <SidebarContent className="gap-4 py-3">
                <NavMain items={visibleMainNavItems} />
                {isHrd && (
                    <NavMain
                        items={payrollNavItems}
                        groupLabel="Penggajian HRD"
                    />
                )}
                {isHrd && <NavMain items={hrdNavItems} groupLabel="HRD" />}
                {isManager && (
                    <NavMain items={managerNavItems} groupLabel="Manager" />
                )}
                {isFinance && (
                    <NavMain items={financeNavItems} groupLabel="Finance" />
                )}
                <NavMain items={moduleNavItems} groupLabel="Menu Pribadi" />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/60">
                <NavThemeToggle />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
