import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import * as purchaseRequisitions from '@/routes/purchase-requisitions';
import type { Auth, BreadcrumbItem } from '@/types';
import type { QueueItem } from './dashboard/partials/active-queue';
import { ActiveQueue } from './dashboard/partials/active-queue';
import type { ModuleOverview } from './dashboard/partials/module-overview';
import { ModuleOverviewStrip } from './dashboard/partials/module-overview';
import type { ActivityItem } from './dashboard/partials/recent-activity';
import { RecentActivity } from './dashboard/partials/recent-activity';
import type { SignatureStatus } from './dashboard/partials/signature-status';
import { SignatureStatusBanner } from './dashboard/partials/signature-status';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

interface DashboardProps {
    queue: QueueItem[];
    recentActivity: ActivityItem[];
    overview: ModuleOverview[];
    signature: SignatureStatus;
}

export default function Dashboard({
    queue,
    recentActivity,
    overview,
    signature,
}: DashboardProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { can } = usePermissions();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            Welcome back, {auth.user.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Documents awaiting your action and recent workflow
                            activity.
                        </p>
                    </div>
                    {can('pr.prepare') && (
                        <Button asChild>
                            <Link href={purchaseRequisitions.create.url()}>
                                <Plus className="mr-2 h-4 w-4" /> New
                                Requisition
                            </Link>
                        </Button>
                    )}
                </div>

                {/* Signature readiness */}
                <SignatureStatusBanner status={signature} />

                {/* Pipeline overview */}
                <ModuleOverviewStrip modules={overview} />

                {/* Action queue + activity feed */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <div className="min-h-80 lg:col-span-7">
                        <ActiveQueue items={queue} />
                    </div>
                    <div className="lg:col-span-5">
                        <RecentActivity activities={recentActivity} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
