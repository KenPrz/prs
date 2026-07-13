import { cn } from '@/lib/utils';

type Props = {
    title: string;
    subtitle?: string;
    align?: 'center' | 'left';
};

export function SectionHeader({ title, subtitle, align = 'center' }: Props) {
    return (
        <div className={cn('space-y-3', align === 'center' && 'text-center')}>
            <h2 className="text-3xl font-semibold tracking-tight">{title}</h2>
            {subtitle && (
                <p
                    className={cn(
                        'text-base text-muted-foreground',
                        align === 'center' && 'mx-auto max-w-2xl',
                    )}
                >
                    {subtitle}
                </p>
            )}
        </div>
    );
}
