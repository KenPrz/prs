import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import ConfigDeleteDialog from '@/components/admin-config/config-delete-dialog';
import ConfigPageHeader from '@/components/admin-config/config-page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import { describePermission } from '@/lib/permission-descriptions';
import type { BreadcrumbItem, PermissionConfig, RoleConfig } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Roles', href: '/admin/config/roles' },
];

export default function RolesIndex({
    roles,
}: {
    roles: (RoleConfig & { permissions: PermissionConfig[] })[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles — Configuration" />
            <AdminConfigLayout>
                <ConfigPageHeader
                    title="Roles"
                    description="Manage roles and their permissions."
                    createHref="/admin/config/roles/create"
                    createLabel="Add role"
                />

                <div className="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-muted-foreground">
                            <tr className="border-b border-border/50">
                                <th className="px-4 py-3 text-left text-xs font-semibold tracking-wider uppercase">
                                    Role
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-semibold tracking-wider uppercase">
                                    Permissions
                                </th>
                                <th className="w-[100px] px-4 py-3 text-right text-xs font-semibold tracking-wider uppercase">
                                    Users
                                </th>
                                <th className="w-[120px] px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border/30">
                            {roles.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-12 text-center text-sm text-muted-foreground"
                                    >
                                        No roles found.
                                    </td>
                                </tr>
                            )}
                            {roles.map((role) => (
                                <tr
                                    key={role.id}
                                    className="transition-colors hover:bg-accent/30"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {role.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {role.permissions.length === 0 && (
                                                <span className="text-xs text-muted-foreground italic">
                                                    No permissions
                                                </span>
                                            )}
                                            {role.permissions
                                                .slice(0, 5)
                                                .map((p) => (
                                                    <Tooltip key={p.id}>
                                                        <TooltipTrigger asChild>
                                                            <Badge
                                                                variant="secondary"
                                                                className="font-mono text-[10px]"
                                                            >
                                                                {p.name}
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        {describePermission(
                                                            p.name,
                                                        ) && (
                                                            <TooltipContent side="top">
                                                                {describePermission(
                                                                    p.name,
                                                                )}
                                                            </TooltipContent>
                                                        )}
                                                    </Tooltip>
                                                ))}
                                            {role.permissions.length > 5 && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-[10px]"
                                                >
                                                    +
                                                    {role.permissions.length -
                                                        5}{' '}
                                                    more
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {role.users_count ?? 0}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1.5">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={`/admin/config/roles/${role.id}/edit`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            {role.name !== 'Super Admin' && (
                                                <ConfigDeleteDialog
                                                    deleteUrl={`/admin/config/roles/${role.id}`}
                                                    label={`role "${role.name}"`}
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </AdminConfigLayout>
        </AppLayout>
    );
}
