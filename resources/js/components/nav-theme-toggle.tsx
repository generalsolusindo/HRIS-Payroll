import { Moon, Sun } from 'lucide-react';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';

export function NavThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                    tooltip={{
                        children: isDark ? 'Mode Terang' : 'Mode Gelap',
                    }}
                >
                    {isDark ? <Sun /> : <Moon />}
                    <span>{isDark ? 'Mode Terang' : 'Mode Gelap'}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
