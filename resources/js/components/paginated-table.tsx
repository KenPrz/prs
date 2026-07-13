import { Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type LaravelPaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type LaravelPaginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links?: LaravelPaginatorLink[];
};

type InertiaPaginator<T> = {
    data: T[];
    links:
        | LaravelPaginatorLink[]
        | { prev: string | null; next: string | null };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export type Paginator<T> = LaravelPaginator<T> | InertiaPaginator<T>;

type Column<T> = {
    header: ReactNode;
    cell: (row: T) => ReactNode;
    className?: string;
};

function normalizePaginator<T>(paginator: Paginator<T>) {
    if ('meta' in paginator) {
        const links = paginator.links;
        const prevUrl = Array.isArray(links)
            ? (links.find((l) => l.label.includes('Previous'))?.url ?? null)
            : links.prev;
        const nextUrl = Array.isArray(links)
            ? (links.find((l) => l.label.includes('Next'))?.url ?? null)
            : links.next;

        return {
            data: paginator.data,
            currentPage: paginator.meta.current_page,
            lastPage: paginator.meta.last_page,
            perPage: paginator.meta.per_page,
            total: paginator.meta.total,
            prevUrl,
            nextUrl,
        };
    }

    return {
        data: paginator.data,
        currentPage: paginator.current_page,
        lastPage: paginator.last_page,
        perPage: paginator.per_page,
        total: paginator.total,
        prevUrl: paginator.prev_page_url,
        nextUrl: paginator.next_page_url,
    };
}

export default function PaginatedTable<T>({
    paginator,
    columns,
    rowKey,
    emptyState = 'No results found.',
}: {
    paginator: Paginator<T>;
    columns: Column<T>[];
    rowKey: (row: T) => string | number;
    emptyState?: ReactNode;
}) {
    const normalized = normalizePaginator(paginator);

    return (
        <div className="space-y-3">
            <div className="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm">
                <table className="w-full text-sm">
                    <thead className="bg-muted/40 text-muted-foreground">
                        <tr className="border-b border-border/50">
                            {columns.map((col, idx) => (
                                <th
                                    key={idx}
                                    scope="col"
                                    className={`px-4 py-3 text-left text-xs font-semibold tracking-wider uppercase ${col.className ?? ''}`}
                                >
                                    {col.header}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-border/30">
                        {normalized.data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-14 text-center"
                                >
                                    <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                        <Inbox
                                            className="size-8 opacity-40"
                                            aria-hidden
                                        />
                                        <span className="text-sm">
                                            {emptyState}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            normalized.data.map((row) => (
                                <tr
                                    key={rowKey(row)}
                                    className="transition-colors hover:bg-accent/30"
                                >
                                    {columns.map((col, idx) => (
                                        <td
                                            key={idx}
                                            className={`px-4 py-3 align-middle ${col.className ?? ''}`}
                                        >
                                            {col.cell(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="text-sm text-muted-foreground">
                    Page {normalized.currentPage} of {normalized.lastPage} ·{' '}
                    {normalized.total} total
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!normalized.prevUrl}
                        asChild={Boolean(normalized.prevUrl)}
                    >
                        {normalized.prevUrl ? (
                            <Link
                                href={normalized.prevUrl}
                                preserveScroll
                                preserveState
                            >
                                Previous
                            </Link>
                        ) : (
                            <span>Previous</span>
                        )}
                    </Button>

                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!normalized.nextUrl}
                        asChild={Boolean(normalized.nextUrl)}
                    >
                        {normalized.nextUrl ? (
                            <Link
                                href={normalized.nextUrl}
                                preserveScroll
                                preserveState
                            >
                                Next
                            </Link>
                        ) : (
                            <span>Next</span>
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
