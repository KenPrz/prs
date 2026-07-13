import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import ConfigPageHeader from '@/components/admin-config/config-page-header';
import PaginatedTable from '@/components/paginated-table';
import type { Paginator } from '@/components/paginated-table';
import { Badge } from '@/components/ui/badge';
import { Combobox } from '@/components/ui/combobox';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Access Logs', href: '/admin/config/access-logs' },
];

type AccessLog = {
    id: number;
    event: string | null;
    method: string | null;
    path: string;
    route_name: string | null;
    status: number | null;
    ip: string | null;
    created_at: string | null;
    user: { id: number; name: string; email: string } | null;
};

type Filters = {
    user_id?: string;
    event?: string;
    from?: string;
    to?: string;
};

const EVENT_OPTIONS = [
    { value: 'all', label: 'All events' },
    { value: 'route', label: 'Route actions' },
    { value: 'login', label: 'Login' },
    { value: 'logout', label: 'Logout' },
    { value: 'failed_login', label: 'Failed login' },
];

function eventBadge(log: AccessLog) {
    if (log.event === 'failed_login') {
        return <Badge variant="destructive">failed login</Badge>;
    }

    if (log.event) {
        return <Badge variant="secondary">{log.event}</Badge>;
    }

    return (
        <Badge variant="outline" className="font-mono">
            {log.method}
        </Badge>
    );
}

export default function AccessLogsIndex({
    logs,
    filters,
    users,
}: {
    logs: Paginator<AccessLog>;
    filters: Filters;
    users: { id: number; name: string }[];
}) {
    const { url } = usePage();

    const initial = useMemo(
        () => ({
            event: filters.event ?? 'all',
            user_id: filters.user_id ?? 'all',
            from: filters.from ?? '',
            to: filters.to ?? '',
        }),
        [filters],
    );

    const [event, setEvent] = useState(initial.event);
    const [userId, setUserId] = useState(initial.user_id);
    const [from, setFrom] = useState(initial.from);
    const [to, setTo] = useState(initial.to);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            // Unchanged filters mean this is the mount after a pagination
            // visit — re-navigating would drop the page param.
            if (
                event === initial.event &&
                userId === initial.user_id &&
                from === initial.from &&
                to === initial.to
            ) {
                return;
            }

            router.get(
                '/admin/config/access-logs',
                {
                    event: event !== 'all' ? event : undefined,
                    user_id: userId !== 'all' ? userId : undefined,
                    from: from || undefined,
                    to: to || undefined,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => window.clearTimeout(handle);
    }, [event, userId, from, to, initial, url]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Access Logs — Configuration" />
            <AdminConfigLayout>
                <ConfigPageHeader
                    title="Access Logs"
                    description="Mutating requests and authentication events, with IP and user agent."
                />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <span className="text-xs text-muted-foreground">
                            Event
                        </span>
                        <Select value={event} onValueChange={setEvent}>
                            <SelectTrigger size="sm" className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {EVENT_OPTIONS.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <span className="text-xs text-muted-foreground">
                            User
                        </span>
                        <Combobox
                            className="w-48"
                            placeholder="All users"
                            searchPlaceholder="Search users…"
                            emptyText="No users found."
                            value={userId === 'all' ? '' : userId}
                            onChange={(v) => setUserId(v || 'all')}
                            options={[
                                { value: 'all', label: 'All users' },
                                ...users.map((u) => ({
                                    value: String(u.id),
                                    label: u.name,
                                })),
                            ]}
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <span className="text-xs text-muted-foreground">
                            From
                        </span>
                        <DatePicker
                            date={from}
                            onChange={setFrom}
                            placeholder="Start date"
                            className="w-40"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <span className="text-xs text-muted-foreground">
                            To
                        </span>
                        <DatePicker
                            date={to}
                            onChange={setTo}
                            placeholder="End date"
                            className="w-40"
                        />
                    </div>
                </div>

                <PaginatedTable
                    paginator={logs}
                    rowKey={(row) => row.id}
                    columns={[
                        {
                            header: 'Time',
                            cell: (row) => (
                                <span className="text-xs whitespace-nowrap text-muted-foreground">
                                    {row.created_at
                                        ? new Date(
                                              row.created_at,
                                          ).toLocaleString()
                                        : '—'}
                                </span>
                            ),
                        },
                        {
                            header: 'User',
                            cell: (row) =>
                                row.user ? (
                                    <div className="flex flex-col">
                                        <span className="font-medium">
                                            {row.user.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {row.user.email}
                                        </span>
                                    </div>
                                ) : (
                                    <span className="text-xs text-muted-foreground italic">
                                        —
                                    </span>
                                ),
                        },
                        {
                            header: 'Event',
                            cell: (row) => eventBadge(row),
                        },
                        {
                            header: 'Path',
                            cell: (row) => (
                                <div className="flex flex-col">
                                    <span className="font-mono text-xs">
                                        {row.path}
                                    </span>
                                    {row.route_name && (
                                        <span className="text-[10px] text-muted-foreground">
                                            {row.route_name}
                                        </span>
                                    )}
                                </div>
                            ),
                        },
                        {
                            header: 'IP',
                            cell: (row) => (
                                <span className="font-mono text-xs text-muted-foreground">
                                    {row.ip ?? '—'}
                                </span>
                            ),
                        },
                        {
                            header: 'Status',
                            cell: (row) =>
                                row.status ? (
                                    <span className="font-mono text-xs">
                                        {row.status}
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground">
                                        —
                                    </span>
                                ),
                        },
                    ]}
                />
            </AdminConfigLayout>
        </AppLayout>
    );
}
