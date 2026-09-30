import { type LucideIcon } from 'lucide-react';

export function ComingSoon({
    title,
    description,
    icon: Icon,
}: {
    title: string;
    description: string;
    icon: LucideIcon;
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4">
            <div>
                <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                    {title}
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            </div>

            <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-3xl bg-muted/40 py-24 text-center">
                <span className="flex size-14 items-center justify-center rounded-full bg-brand-navy/8 dark:border dark:border-brand-navy-foreground/10 dark:bg-brand-navy/35">
                    <Icon className="size-6 text-brand-navy dark:text-brand-navy-foreground" />
                </span>
                <p className="text-sm text-muted-foreground">
                    Modul ini sedang dalam pengembangan.
                </p>
            </div>
        </div>
    );
}
