import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import ConfigDeleteDialog from '@/components/admin-config/config-delete-dialog';
import ConfigPageHeader from '@/components/admin-config/config-page-header';
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
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DepartmentConfig } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Departments', href: '/admin/config/departments' },
];

export default function DepartmentsIndex({
    departments,
}: {
    departments: Paginator<DepartmentConfig>;
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
                '/admin/config/departments',
                {
                    search: search || undefined,
                    per_page: perPage !== '10' ? perPage : undefined,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => window.clearTimeout(handle);
    }, [search, perPage, initialQuery]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Departments — Configuration" />
            <AdminConfigLayout>
                <ConfigPageHeader
                    title="Departments"
                    description="Manage organizational departments."
                    createHref="/admin/config/departments/create"
                    createLabel="Add department"
                />

                <div className="flex flex-wrap items-center gap-3">
                    <div className="w-full max-w-sm">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or code…"
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
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <PaginatedTable
                    paginator={departments}
                    rowKey={(row) => row.id}
                    columns={[
                        {
                            header: 'Code',
                            cell: (row) => (
                                <span className="font-mono text-xs font-medium">
                                    {row.code}
                                </span>
                            ),
                            className: 'w-[120px]',
                        },
                        {
                            header: 'Name',
                            cell: (row) => row.name,
                        },
                        {
                            header: '',
                            cell: (row) => (
                                <div className="flex items-center justify-end gap-1.5">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={`/admin/config/departments/${row.id}/edit`}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <ConfigDeleteDialog
                                        deleteUrl={`/admin/config/departments/${row.id}`}
                                        label={`department "${row.name}"`}
                                    />
                                </div>
                            ),
                            className: 'text-right w-[120px]',
                        },
                    ]}
                />
            </AdminConfigLayout>
        </AppLayout>
    );
}
