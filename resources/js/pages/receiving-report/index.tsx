import { Head, Link, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import PaginatedTable from '@/components/paginated-table';
import type { Paginator } from '@/components/paginated-table';
import { StatusBadge } from '@/components/procurement/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import * as receivingReports from '@/routes/receiving-reports';
import type { BreadcrumbItem, ReceivingReportRow } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Receiving reports',
        href: receivingReports.index.url(),
    },
];

export default function ReceivingReportIndex({
    receivingReports: reports,
}: {
    receivingReports: Paginator<ReceivingReportRow>;
}) {
    const { url } = usePage();

    const initialQuery = useMemo(() => {
        const searchParams = new URL(url, window.location.origin).searchParams;

        return {
            search: searchParams.get('search') ?? '',
            perPage: searchParams.get('per_page') ?? '10',
        };
    }, [url]);

    const [search, setSearch] = useState(initialQuery.search);
    const [perPage, setPerPage] = useState(initialQuery.perPage);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            // Unchanged filters mean this is the mount after a pagination
            // visit — navigating now would drop the page param and bounce
            // the user back to page 1.
            if (
                search === initialQuery.search &&
                perPage === initialQuery.perPage
            ) {
                return;
            }

            router.get(
                receivingReports.index.url({
                    query: {
                        search: search || undefined,
                        per_page: perPage !== '10' ? perPage : undefined,
                    },
                }),
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => {
            window.clearTimeout(handle);
        };
    }, [perPage, search, initialQuery]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Receiving reports" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Receiving Reports"
                    description="Track and manage receiving reports for purchased goods."
                />

                <div className="flex flex-wrap items-center gap-3">
                    <div className="w-full max-w-sm">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search reports…"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Per page
                        </span>
                        <Select value={perPage} onValueChange={setPerPage}>
                            <SelectTrigger size="sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="10">10</SelectItem>
                                <SelectItem value="25">25</SelectItem>
                                <SelectItem value="50">50</SelectItem>
                                <SelectItem value="100">100</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <PaginatedTable
                    paginator={reports}
                    rowKey={(row) => row.id}
                    columns={[
                        {
                            header: 'RR No.',
                            cell: (row) => (
                                <span className="font-medium">
                                    {row.rr_number}
                                </span>
                            ),
                        },
                        {
                            header: 'PO No.',
                            cell: (row) => row.purchase_order?.po_number ?? '—',
                        },
                        {
                            header: 'Received Date',
                            cell: (row) =>
                                row.received_date
                                    ? format(new Date(row.received_date), 'PP')
                                    : '—',
                        },
                        {
                            header: 'Status',
                            cell: (row) => <StatusBadge status={row.status} />,
                        },
                        {
                            header: 'Received By',
                            cell: (row) => row.received_by?.name ?? '—',
                        },
                        {
                            header: '',
                            cell: (row) => (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={receivingReports.show.url(row.id)}
                                    >
                                        View
                                    </Link>
                                </Button>
                            ),
                            className: 'text-right',
                        },
                    ]}
                />
            </div>
        </AppLayout>
    );
}
