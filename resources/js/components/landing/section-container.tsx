import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    children: React.ReactNode;
};

export function SectionContainer({ className, children }: Props) {
    return (
        <div className={cn('mx-auto max-w-6xl px-4 sm:px-6', className)}>
            {children}
        </div>
    );
}
