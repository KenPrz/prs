import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import PaginatedTable from '@/components/paginated-table';
import type { Paginator } from '@/components/paginated-table';
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
import * as paymentRequestForms from '@/routes/payment-request-forms';
import type { BreadcrumbItem } from '@/types';

type Requestor = {
    id: number;
    name: string;
};

type Supplier = {
    id: number;
    name: string;
};

type PaymentRequestFormRow = {
    id: number;
    prf_number: string;
    description: string | null;
    amount: string;
    status: string;
    invoice_number: string | null;
    requestor?: Requestor | null;
    supplier?: Supplier | null;
    created_at: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payment request forms',
        href: paymentRequestForms.index.url(),
    },
];

const formatCurrency = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(num || 0);
};

export default function PaymentRequestFormIndex({
    paymentRequestForms: prfs,
    canCreate = false,
}: {
    paymentRequestForms: Paginator<PaymentRequestFormRow>;
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
                paymentRequestForms.index.url({
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
            <Head title="Payment request forms" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Payment Request Forms"
                    description="Manage recurring billings and payment requests."
                    actions={
                        canCreate ? (
                            <Button asChild>
                                <Link href={paymentRequestForms.create.url()}>
                                    New payment request
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
                            placeholder="Search by description, PRF#, or invoice#…"
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
                    paginator={prfs}
                    rowKey={(row) => row.id}
                    columns={[
                        {
                            header: 'PRF No.',
                            cell: (row) => (
                                <span className="font-medium">
                                    {row.prf_number}
                                </span>
                            ),
                        },
                        {
                            header: 'Description',
                            cell: (row) => row.description ?? '—',
                            className: 'w-[30%]',
                        },
                        {
                            header: 'Payee',
                            cell: (row) => row.supplier?.name ?? '—',
                        },
                        {
                            header: 'Amount',
                            cell: (row) => formatCurrency(row.amount),
                        },
                        {
                            header: 'Status',
                            cell: (row) => row.status,
                        },
                        {
                            header: '',
                            cell: (row) => (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={paymentRequestForms.show.url(
                                            row.id,
                                        )}
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
