import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Calculator,
    CalendarClock,
    CalendarDays,
    CalendarOff,
    CalendarRange,
    ClipboardList,
    Clock,
    History,
    LayoutGrid,
    Settings,
    ShieldCheck,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import AttendanceHistoryController from '@/actions/App/Http/Controllers/AttendanceHistoryController';
import PayrollPaymentController from '@/actions/App/Http/Controllers/Finance/PayrollPaymentController';
import AttendanceDashboardController from '@/actions/App/Http/Controllers/Hrd/AttendanceDashboardController';
import AttendanceRangeSummaryController from '@/actions/App/Http/Controllers/Hrd/AttendanceRangeSummaryController';
import CompanyCalendarDayController from '@/actions/App/Http/Controllers/Hrd/CompanyCalendarDayController';
import CompanySettingsController from '@/actions/App/Http/Controllers/Hrd/CompanySettingsController';
import DivisionController from '@/actions/App/Http/Controllers/Hrd/DivisionController';
import HrdLeaveRequestApprovalController from '@/actions/App/Http/Controllers/Hrd/LeaveRequestApprovalController';
import OvertimeApprovalController from '@/actions/App/Http/Controllers/Hrd/OvertimeApprovalController';
import PayrollAuditLogController from '@/actions/App/Http/Controllers/Hrd/PayrollAuditLogController';
import PayrollComponentController from '@/actions/App/Http/Controllers/Hrd/PayrollComponentController';
import PayrollPeriodController from '@/actions/App/Http/Controllers/Hrd/PayrollPeriodController';
import PayrollPolicyController from '@/actions/App/Http/Controllers/Hrd/PayrollPolicyController';
import PayrollProfileController from '@/actions/App/Http/Controllers/Hrd/PayrollProfileController';
import UserManagementController from '@/actions/App/Http/Controllers/Hrd/UserManagementController';
import OvertimeRequestController from '@/actions/App/Http/Controllers/OvertimeRequestController';
import ManagerLeaveRequestApprovalController from '@/actions/App/Http/Controllers/Manager/LeaveRequestApprovalController';
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
import { dashboard, izin, penggajian } from '@/routes';
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

// Self-service items an employee uses for their own data. Each one is
// only shown to roles it can actually apply to — see the filtering in
// AppSidebar() below for why HRD/Manager are excluded from some of these.
const selfServicePenggajianItem: NavItem = {
    title: 'Penggajian',
    href: penggajian(),
    icon: Wallet,
    badgeKey: 'karyawan_penggajian',
};

const selfServiceIzinItem: NavItem = {
    title: 'Izin',
    href: izin(),
    icon: CalendarOff,
    badgeKey: 'karyawan_izin',
};

const selfServiceLemburItem: NavItem = {
    title: 'Lembur',
    href: OvertimeRequestController.index(),
    icon: Clock,
    badgeKey: 'karyawan_lembur',
};

const payrollNavItems: NavItem[] = [
    {
        title: 'Proses Gaji',
        href: PayrollPeriodController.index(),
        icon: Calculator,
        activePaths: ['/hrd/periode-gaji', '/hrd/hasil-payroll'],
        badgeKey: 'hrd_payroll_complaints',
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
    {
        // Logs attendance-review corrections, salary-profile edits, and
        // policy-rate changes (see PayrollAuditLogController) — all
        // payroll-relevant data, not general HRD activity, so it belongs
        // here rather than in the generic HRD group.
        title: 'Audit Log',
        href: PayrollAuditLogController.index(),
        icon: History,
    },
];

// Org structure & company-wide setup — things configured occasionally,
// not day-to-day operational screens.
const hrdNavItems: NavItem[] = [
    {
        title: 'Manajemen User',
        href: UserManagementController.index(),
        icon: Users,
        badgeKey: 'hrd_pending_users',
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
        title: 'Pengaturan Perusahaan',
        href: CompanySettingsController.index(),
        icon: Settings,
    },
    {
        title: 'Persetujuan Lembur',
        href: OvertimeApprovalController.index(),
        icon: Clock,
        badgeKey: 'hrd_lembur',
    },
];

// Day-to-day attendance monitoring/reporting.
const attendanceNavItems: NavItem[] = [
    {
        title: 'Rekap Absensi',
        href: AttendanceDashboardController.index(),
        icon: ClipboardList,
    },
    {
        title: 'Rekap Periode',
        href: AttendanceRangeSummaryController.index(),
        icon: CalendarRange,
    },
];

// Izin approval workflow.
const leaveNavItems: NavItem[] = [
    {
        title: 'Persetujuan Izin',
        href: HrdLeaveRequestApprovalController.index(),
        icon: CalendarOff,
        badgeKey: 'hrd_izin',
    },
    {
        title: 'Riwayat Izin',
        href: HrdLeaveRequestApprovalController.riwayat(),
        icon: History,
    },
];

const managerNavItems: NavItem[] = [
    {
        title: 'Persetujuan Izin',
        href: ManagerLeaveRequestApprovalController.index(),
        icon: CalendarOff,
        badgeKey: 'manager_izin',
    },
    {
        title: 'Riwayat Izin',
        href: ManagerLeaveRequestApprovalController.riwayat(),
        icon: History,
    },
    {
        title: 'Persetujuan Payroll',
        href: PayrollRunApprovalController.index(),
        icon: Wallet,
        badgeKey: 'manager_payroll',
    },
];

const financeNavItems: NavItem[] = [
    {
        title: 'Pembayaran Payroll',
        href: PayrollPaymentController.index(),
        icon: Wallet,
        badgeKey: 'finance_payroll',
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isHrd = auth.user.role === 'hrd';
    const isManager = auth.user.role === 'admin';
    const isFinance = auth.user.role === 'finance';
    const isKaryawan = auth.user.role === 'karyawan';

    // An HRD account is for monitoring/management only — it never clocks
    // in itself, so its own attendance history is never meaningful.
    const visibleMainNavItems = isHrd
        ? mainNavItems.filter((item) => item.title !== 'Riwayat Absensi')
        : mainNavItems;

    // Each self-service item is shown only to roles it can actually
    // apply to — never just gated on being logged in:
    // - Penggajian: payroll runs only ever produce entries for
    //   role=karyawan, so this page is permanently empty for every
    //   other role.
    // - Izin: HRD and Manager are the two approval stages for Izin —
    //   letting either submit their own would let that stage approve
    //   its own request, so the backend rejects the submission outright
    //   and the button is hidden to match.
    // - Lembur: HRD is Lembur's only approval stage, same reasoning.
    const selfServiceNavItems: NavItem[] = [
        ...(isKaryawan ? [selfServicePenggajianItem] : []),
        ...(!isHrd && !isManager ? [selfServiceIzinItem] : []),
        ...(!isHrd ? [selfServiceLemburItem] : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="border-b border-sidebar-border/60 p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            className="h-20 justify-center p-0"
                            asChild
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo className="h-auto w-full max-w-40 object-center" />
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
                {isHrd && (
                    <NavMain items={attendanceNavItems} groupLabel="Absensi" />
                )}
                {isHrd && (
                    <NavMain items={leaveNavItems} groupLabel="Perizinan" />
                )}
                {isManager && (
                    <NavMain items={managerNavItems} groupLabel="Manager" />
                )}
                {isFinance && (
                    <NavMain items={financeNavItems} groupLabel="Finance" />
                )}
                {selfServiceNavItems.length > 0 && (
                    <NavMain
                        items={selfServiceNavItems}
                        groupLabel="Menu Pribadi"
                    />
                )}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border/60">
                <NavThemeToggle />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
