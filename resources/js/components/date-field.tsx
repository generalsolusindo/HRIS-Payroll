import { CalendarDays } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * Wraps the native date/month input instead of pulling in a calendar
 * library — keeps the OS picker (accessible, mobile-friendly) but styles
 * the field itself to match the rest of the UI instead of looking
 * browser-default.
 */
export function DateField({
    id,
    label,
    className,
    ...props
}: ComponentProps<'input'> & { id: string; label: string }) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label
                htmlFor={id}
                className="text-xs font-medium text-muted-foreground"
            >
                {label}
            </Label>
            <div className="relative w-fit">
                <CalendarDays className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id={id}
                    className="h-11 w-fit rounded-full border-transparent bg-muted/60 pl-10 font-medium [color-scheme:light] dark:[color-scheme:dark]"
                    {...props}
                />
            </div>
        </div>
    );
}
