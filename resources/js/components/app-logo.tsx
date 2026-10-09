import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

export default function AppLogo({ className }: { className?: string }) {
    return (
        <AppLogoIcon
            className={cn(
                'h-10 w-36 shrink-0 object-left group-data-[collapsible=icon]:size-8 group-data-[collapsible=icon]:object-center',
                className,
            )}
        />
    );
}
