import { Head, Link, router, usePage } from '@inertiajs/react';
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
import * as purchaseOrders from '@/routes/purchase-orders';
import type { BreadcrumbItem, PurchaseOrderRow } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Purchase orders',
        href: purchaseOrders.index.url(),
    },
];

export default function PurchaseOrderIndex({
    purchaseOrders: orders,
    canCreate = false,
}: {
    purchaseOrders: Paginator<PurchaseOrderRow>;
    canCreate?: boolean;
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
                purchaseOrders.index.url({
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
            <Head title="Purchase orders" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Purchase Orders"
                    description="Manage and track purchase orders."
                    actions={
                        canCreate ? (
                            <Button asChild>
                                <Link href={purchaseOrders.create.url()}>
                                    New purchase order
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <div className="w-full max-w-sm">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search orders…"
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
                    paginator={orders}
                    rowKey={(row) => row.id}
                    columns={[
                        {
                            header: 'PO No.',
                            cell: (row) => (
                                <span className="font-medium">
                                    {row.po_number}
                                </span>
                            ),
                        },
                        {
                            header: 'PR No.',
                            cell: (row) =>
                                row.purchase_requisition?.pr_number ?? '—',
                        },
                        {
                            header: 'Supplier',
                            cell: (row) => row.supplier?.name ?? '—',
                            className: 'w-[30%]',
                        },
                        {
                            header: 'Status',
                            cell: (row) => <StatusBadge status={row.status} />,
                        },
                        {
                            header: '',
                            cell: (row) => (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={purchaseOrders.show.url(row.id)}
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
