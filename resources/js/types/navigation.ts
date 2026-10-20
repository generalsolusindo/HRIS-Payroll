import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    activePaths?: string[];
    // Key into the shared `navBadges` page prop — shows a small dot on
    // this item while true (see NavBadgeService on the backend).
    badgeKey?: string;
};
