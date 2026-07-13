import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type DocumentPageLayoutProps = {
    header: ReactNode;
    main: ReactNode;
    sidebar: ReactNode;
    /** Optional wrapper class for the outer page container */
    className?: string;
};

/**
 * T-layout for procurement document pages: full-width header, a left main
 * column, and a sticky right sidebar. Columns are independent tracks, so a
 * tall sidebar (e.g. long approval logs) no longer stretches left-column rows.
 */
export function DocumentPageLayout({
    header,
    main,
    sidebar,
    className,
}: DocumentPageLayoutProps) {
    return (
        <div className={cn('mx-5 my-2 flex flex-col gap-6 p-4', className)}>
            {header}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_min(380px,30%)] lg:items-start">
                <div className="flex min-w-0 flex-col gap-6">{main}</div>

                <aside className="flex flex-col gap-6 lg:sticky lg:top-6 lg:self-start">
                    {sidebar}
                </aside>
            </div>
        </div>
    );
}
