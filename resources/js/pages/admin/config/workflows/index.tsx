import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import ConfigDeleteDialog from '@/components/admin-config/config-delete-dialog';
import ConfigPageHeader from '@/components/admin-config/config-page-header';
import PaginatedTable from '@/components/paginated-table';
import type { Paginator } from '@/components/paginated-table';
import { Badge } from '@/components/ui/badge';
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
import type { BreadcrumbItem, WorkflowConfig } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Workflows', href: '/admin/config/workflows' },
];

export default function WorkflowsIndex({
    workflows,
}: {
    workflows: Paginator<WorkflowConfig>;
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
                '/admin/config/workflows',
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
            <Head title="Workflows — Configuration" />
            <AdminConfigLayout>
                <ConfigPageHeader
                    title="Workflows"
                    description="Define approval workflows and their steps."
                    createHref="/admin/config/workflows/create"
                    createLabel="Add workflow"
                />

                <div className="flex flex-wrap items-center gap-3">
                    <div className="w-full max-w-sm">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or document type…"
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
                    paginator={workflows}
                    rowKey={(row) => row.id}
                    columns={[
                        {
                            header: 'Name',
                            cell: (row) => (
                                <span className="font-medium">{row.name}</span>
                            ),
                        },
                        {
                            header: 'Key',
                            cell: (row) => (
                                <span className="font-mono text-xs">
                                    {row.key}
                                </span>
                            ),
                        },
                        {
                            header: 'Document Type',
                            cell: (row) => row.document_type,
                        },
                        {
                            header: 'Version',
                            cell: (row) => `v${row.version}`,
                            className: 'text-center',
                        },
                        {
                            header: 'Steps',
                            cell: (row) => row.steps_count ?? 0,
                            className: 'text-center',
                        },
                        {
                            header: 'Status',
                            cell: (row) => (
                                <Badge
                                    variant={
                                        row.is_active ? 'default' : 'secondary'
                                    }
                                    className={
                                        row.is_active
                                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                            : ''
                                    }
                                >
                                    {row.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            ),
                        },
                        {
                            header: '',
                            cell: (row) => (
                                <div className="flex items-center justify-end gap-1.5">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={`/admin/config/workflows/${row.id}/edit`}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <ConfigDeleteDialog
                                        deleteUrl={`/admin/config/workflows/${row.id}`}
                                        label={`workflow "${row.name}"`}
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
