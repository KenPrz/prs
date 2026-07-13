import { Link } from '@inertiajs/react';
import { CheckCircle, ShieldAlert, UserCircle2, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface ActivityItem {
    actor_name: string;
    action: string;
    subject_label: string;
    href: string | null;
    comment: string | null;
    acted_at: string;
}

const ACTION_LABELS: Record<string, string> = {
    SUBMIT: 'Submitted',
    APPROVE: 'Approved',
    REJECT: 'Rejected',
    RECEIVE: 'Received',
    CANCEL: 'Cancelled',
    REASSIGN: 'Reassigned',
    OVERRIDE: 'Overridden',
    ESCALATE: 'Escalated',
};

function getActionIcon(action: string) {
    switch (action) {
        case 'APPROVE':
        case 'RECEIVE':
            return (
                <CheckCircle className="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" />
            );
        case 'REJECT':
        case 'CANCEL':
            return (
                <XCircle className="h-3.5 w-3.5 text-red-600 dark:text-red-400" />
            );
        case 'REASSIGN':
            return (
                <ShieldAlert className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
            );
        default:
            return (
                <UserCircle2 className="h-3.5 w-3.5 text-muted-foreground" />
            );
    }
}

function getActionColor(action: string) {
    switch (action) {
        case 'APPROVE':
        case 'RECEIVE':
            return 'bg-teal-50 text-teal-700 border-teal-200/60 dark:bg-teal-950/30 dark:text-teal-400 dark:border-teal-800/50';
        case 'REJECT':
        case 'CANCEL':
            return 'bg-red-50 text-red-700 border-red-200/60 dark:bg-red-950/30 dark:text-red-400 dark:border-red-800/50';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

export function RecentActivity({ activities }: { activities: ActivityItem[] }) {
    return (
        <Card className="h-full">
            <CardHeader className="border-b border-border/40">
                <CardTitle className="text-base font-semibold">
                    Recent Activity
                </CardTitle>
            </CardHeader>
            <CardContent className="pt-6">
                <div className="space-y-6">
                    {activities.length === 0 ? (
                        <p className="py-4 text-center text-sm text-muted-foreground">
                            No recent activity.
                        </p>
                    ) : (
                        activities.map((activity, i) => (
                            <div key={i} className="group flex gap-4">
                                <div className="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted/60 transition-colors group-hover:bg-accent">
                                    <UserCircle2 className="h-4 w-4 text-muted-foreground/60" />
                                    {i !== activities.length - 1 && (
                                        <div className="absolute top-8 h-8 w-px bg-border/50" />
                                    )}
                                </div>
                                <div className="space-y-1.5 pb-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-semibold">
                                            {activity.actor_name}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className={`h-5 px-1.5 text-[10px] font-bold tracking-tight uppercase ${getActionColor(activity.action)}`}
                                        >
                                            <span className="mr-1">
                                                {getActionIcon(activity.action)}
                                            </span>
                                            {ACTION_LABELS[activity.action] ??
                                                activity.action}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground/50">
                                            {activity.acted_at}
                                        </span>
                                    </div>
                                    <p className="text-sm leading-tight">
                                        {activity.href ? (
                                            <Link
                                                href={activity.href}
                                                className="font-semibold text-primary/80 hover:underline"
                                            >
                                                {activity.subject_label}
                                            </Link>
                                        ) : (
                                            <span className="font-semibold text-primary/80">
                                                {activity.subject_label}
                                            </span>
                                        )}
                                    </p>
                                    {activity.comment && (
                                        <div className="rounded-md border border-border/40 bg-muted/30 px-3 py-2 text-[12px] leading-snug text-muted-foreground/80 italic">
                                            "{activity.comment}"
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
