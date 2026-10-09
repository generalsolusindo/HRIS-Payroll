import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col justify-between overflow-hidden bg-primary p-10 text-primary-foreground lg:flex">
                <div
                    className="absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0)',
                        backgroundSize: '24px 24px',
                    }}
                />

                <Link
                    href={home()}
                    className="relative z-20 flex items-center gap-2 text-lg font-medium"
                >
                    <AppLogoIcon className="h-auto w-56" />
                </Link>

                <div className="relative z-20 max-w-sm space-y-2">
                    <p className="text-2xl font-semibold text-balance">
                        Absensi otomatis, tervalidasi lokasi &amp; foto — tanpa
                        perlu verifikasi manual.
                    </p>
                    <p className="text-sm text-primary-foreground/70">
                        Karyawan absen dari mana pun mereka bertugas, HRD
                        tinggal melihat rekapnya.
                    </p>
                </div>
            </div>
            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[380px]">
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center lg:hidden"
                    >
                        <AppLogoIcon className="h-auto w-48 sm:w-56" />
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-medium">{title}</h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
