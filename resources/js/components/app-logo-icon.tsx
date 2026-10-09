import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function AppLogoIcon({
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/general-solusindo.png"
            alt="General Solusindo"
            width={600}
            height={280}
            className={cn('object-contain', className)}
            {...props}
        />
    );
}
