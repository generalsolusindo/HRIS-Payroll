import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PayrollComponentController from '@/actions/App/Http/Controllers/Hrd/PayrollComponentController';
import PayrollPeriodController from '@/actions/App/Http/Controllers/Hrd/PayrollPeriodController';
import PayrollPolicyController from '@/actions/App/Http/Controllers/Hrd/PayrollPolicyController';
import PayrollProfileController from '@/actions/App/Http/Controllers/Hrd/PayrollProfileController';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

const sections = [
    {
        title: 'Proses Gaji',
        href: PayrollPeriodController.index(),
        paths: ['/hrd/periode-gaji', '/hrd/hasil-payroll'],
    },
    {
        title: 'Aturan Gaji',
        href: PayrollPolicyController.index(),
        paths: ['/hrd/kebijakan-absensi'],
    },
    {
        title: 'Komponen',
        href: PayrollComponentController.index(),
        paths: ['/hrd/komponen-gaji'],
    },
    {
        title: 'Profil Gaji',
        href: PayrollProfileController.index(),
        paths: ['/hrd/profil-gaji'],
    },
];

/** Shared presentation only; each page keeps its existing data and actions. */
export function PayrollWorkspace({ children }: { children: ReactNode }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { currentUrl } = useCurrentUrl();
    const matches = (path: string) =>
        currentUrl === path || currentUrl.startsWith(`${path}/`);
    const isHrdPayroll =
        auth.user.role === 'hrd' &&
        sections.some((section) => section.paths.some(matches));
    const isPayroll =
        isHrdPayroll ||
        ['/manager/payroll', '/finance/payroll', '/penggajian'].some(matches);

    if (!isPayroll) return children;

    return (
        <div className="flex min-w-0 flex-1 flex-col bg-muted/30">
            {isHrdPayroll && (
                <nav
                    aria-label="Navigasi penggajian HRD"
                    className="border-b bg-background px-4 sm:px-6"
                >
                    <div className="mx-auto flex max-w-screen-2xl flex-wrap gap-x-4 gap-y-1">
                        {sections.map((section) => {
                            const active = section.paths.some(matches);
                            return (
                                <Link
                                    key={section.title}
                                    href={section.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'inline-flex min-h-11 items-center border-b-2 px-1 py-2 text-sm font-medium transition-colors outline-none focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring',
                                        active
                                            ? 'border-primary text-primary'
                                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                                    )}
                                >
                                    {section.title}
                                </Link>
                            );
                        })}
                    </div>
                </nav>
            )}
            <div className="mx-auto flex w-full max-w-screen-2xl min-w-0 flex-1 flex-col py-2 sm:px-2 sm:py-4 lg:px-4">
                {children}
            </div>
        </div>
    );
}
