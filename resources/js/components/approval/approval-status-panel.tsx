import {
    Check,
    Circle,
    CircleDot,
    CircleOff,
    Clock,
    ShieldAlert,
    X,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ApprovalSummary } from '@/types';

interface ApprovalStatusPanelProps {
    approvalSummary: ApprovalSummary | null;
}

function instanceStatusLabel(status: string): string {
    const map: Record<string, string> = {
        PENDING: 'In Review',
        APPROVED: 'Approved',
        REJECTED: 'Rejected',
        CANCELLED: 'Cancelled',
    };

    return map[status] ?? status;
}

function instanceStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'APPROVED':
            return 'default';
        case 'REJECTED':
            return 'destructive';
        case 'CANCELLED':
            return 'outline';
        default:
            return 'secondary';
    }
}

function StepIcon({
    status,
    isCurrent,
}: {
    status: string;
    isCurrent: boolean;
}) {
    const base = 'h-5 w-5 shrink-0';

    switch (status) {
        case 'APPROVED':
        case 'RECEIVED':
            return (
                <Check className={`${base} text-success`} aria-hidden="true" />
            );
        case 'REJECTED':
            return (
                <X className={`${base} text-destructive`} aria-hidden="true" />
            );
        case 'OVERRIDDEN':
            return (
                <ShieldAlert
                    className={`${base} text-warning`}
                    aria-hidden="true"
                />
            );
        case 'SKIPPED':
            return (
                <Circle
                    className={`${base} text-muted-foreground/40`}
                    aria-hidden="true"
                />
            );
        case 'STOPPED':
            return (
                <CircleOff
                    className={`${base} text-muted-foreground/30`}
                    aria-hidden="true"
                />
            );
        default:
            if (isCurrent) {
                return (
                    <CircleDot
                        className={`${base} text-primary`}
                        aria-hidden="true"
                    />
                );
            }

            return (
                <Clock
                    className={`${base} text-muted-foreground/40`}
                    aria-hidden="true"
                />
            );
    }
}

function assignmentBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'APPROVED':
        case 'RECEIVED':
            return 'default';
        case 'REJECTED':
            return 'destructive';
        case 'OVERRIDDEN':
        case 'REASSIGNED':
            return 'secondary';
        case 'STOPPED':
            return 'outline';
        default:
            return 'outline';
    }
}

function assignmentStatusLabel(status: string): string {
    const map: Record<string, string> = {
        PENDING: 'Pending',
        APPROVED: 'Approved',
        REJECTED: 'Rejected',
        RECEIVED: 'Received',
        REASSIGNED: 'Reassigned',
        OVERRIDDEN: 'Overridden',
        STOPPED: 'Stopped',
    };

    return map[status] ?? status;
}

function decisionActionLabel(action: string): string {
    const map: Record<string, string> = {
        APPROVE: 'Approved',
        REJECT: 'Rejected',
        RECEIVE: 'Received',
        REASSIGN: 'Reassigned',
        OVERRIDE: 'Overridden',
        CANCEL: 'Cancelled',
    };

    return map[action] ?? action;
}

function decisionBadgeVariant(
    action: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (action) {
        case 'APPROVE':
        case 'RECEIVE':
            return 'default';
        case 'REJECT':
            return 'destructive';
        case 'OVERRIDE':
        case 'REASSIGN':
            return 'secondary';
        default:
            return 'outline';
    }
}

export function ApprovalStatusPanel({
    approvalSummary,
}: ApprovalStatusPanelProps) {
    if (!approvalSummary) {
        return (
            <Card className="bg-card/50">
                <CardContent className="flex items-center justify-center py-12 text-center">
                    <div className="space-y-1.5">
                        <p className="text-sm font-medium text-muted-foreground">
                            No Approval Workflow
                        </p>
                        <p className="text-xs text-muted-foreground/60">
                            Submit this requisition to start the approval
                            process.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const {
        workflow_label,
        instance_status,
        current_step_order,
        steps,
        decisions,
        history,
    } = approvalSummary;

    const stepTypeLabel: Record<string, string> = {
        DEPARTMENT_HEAD: 'Dept. Head',
        RECEIVE: 'Receive',
    };

    return (
        <Card className="bg-card/50">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between gap-2">
                    <CardTitle className="text-base">Approval Status</CardTitle>
                    <Badge variant={instanceStatusVariant(instance_status)}>
                        {instanceStatusLabel(instance_status)}
                    </Badge>
                </div>
                <p className="text-xs text-muted-foreground">
                    {workflow_label}
                </p>
            </CardHeader>

            <CardContent className="grid gap-5">
                {/* Step timeline */}
                <section aria-label="Approval steps">
                    <h3 className="mb-3 text-sm font-medium text-foreground">
                        Steps
                    </h3>
                    <ol className="relative space-y-0">
                        {steps.map((step, idx) => {
                            const isCurrent =
                                step.step_order === current_step_order &&
                                instance_status === 'PENDING';
                            const isLast = idx === steps.length - 1;

                            return (
                                <li
                                    key={step.step_order}
                                    className="relative flex gap-3"
                                    aria-current={
                                        isCurrent ? 'step' : undefined
                                    }
                                >
                                    {/* Connector line + icon */}
                                    <div className="flex flex-col items-center">
                                        <div className="flex h-7 items-center">
                                            <StepIcon
                                                status={step.status}
                                                isCurrent={isCurrent}
                                            />
                                        </div>
                                        {!isLast && (
                                            <div className="my-0.5 w-px grow bg-border" />
                                        )}
                                    </div>

                                    {/* Step content */}
                                    <div
                                        className={`pb-4 ${isLast ? 'pb-0' : ''}`}
                                    >
                                        <p
                                            className={`text-sm leading-7 font-medium ${
                                                isCurrent
                                                    ? 'text-foreground'
                                                    : step.status ===
                                                            'APPROVED' ||
                                                        step.status ===
                                                            'OVERRIDDEN'
                                                      ? 'text-foreground'
                                                      : 'text-muted-foreground'
                                            }`}
                                        >
                                            {step.name}
                                            {stepTypeLabel[step.step_type] && (
                                                <span className="ml-1.5 rounded bg-muted px-1.5 py-0.5 text-[10px] font-normal text-muted-foreground">
                                                    {
                                                        stepTypeLabel[
                                                            step.step_type
                                                        ]
                                                    }
                                                </span>
                                            )}
                                            {step.completion_strategy ===
                                                'ANY' && (
                                                <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                                                    (any)
                                                </span>
                                            )}
                                            {isCurrent &&
                                                step.due_at &&
                                                new Date(step.due_at) <
                                                    new Date() && (
                                                    <span className="ml-1.5 text-xs font-normal text-destructive">
                                                        (overdue)
                                                    </span>
                                                )}
                                            {step.status === 'OVERRIDDEN' && (
                                                <span className="ml-1.5 text-xs font-normal text-warning">
                                                    (overridden)
                                                </span>
                                            )}
                                        </p>

                                        {/* Assignments */}
                                        {step.assignments.length > 0 && (
                                            <ul className="mt-1.5 space-y-1">
                                                {step.assignments.map((a) => (
                                                    <li
                                                        key={a.id}
                                                        className="space-y-0.5"
                                                    >
                                                        <div className="flex items-center gap-2 text-xs">
                                                            <span className="text-muted-foreground">
                                                                {a.user_name}
                                                            </span>
                                                            <Badge
                                                                variant={assignmentBadgeVariant(
                                                                    a.status,
                                                                )}
                                                                className="h-4 px-1.5 text-[10px]"
                                                            >
                                                                {assignmentStatusLabel(
                                                                    a.status,
                                                                )}
                                                            </Badge>
                                                        </div>
                                                        {a.status ===
                                                            'OVERRIDDEN' &&
                                                            a.overridden_by_name && (
                                                                <p className="text-[10px] text-warning">
                                                                    by{' '}
                                                                    {
                                                                        a.overridden_by_name
                                                                    }
                                                                    {a.overridden_at &&
                                                                        ` · ${new Date(a.overridden_at).toLocaleString()}`}
                                                                </p>
                                                            )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                </section>

                {/* Decision history */}
                {decisions.length > 0 && (
                    <section aria-label="Decision history">
                        <h3 className="mb-2 text-sm font-medium text-foreground">
                            History
                        </h3>
                        <ul className="space-y-2">
                            {decisions.map((d, idx) => (
                                <li
                                    key={idx}
                                    className={`rounded-md border px-3 py-2 ${
                                        d.is_override
                                            ? 'border-warning/30 bg-warning/5'
                                            : 'border-border/60 bg-muted/30'
                                    }`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-xs font-medium text-foreground">
                                            {d.actor_name}
                                        </span>
                                        <Badge
                                            variant={decisionBadgeVariant(
                                                d.action,
                                            )}
                                            className="h-4 px-1.5 text-[10px]"
                                        >
                                            {decisionActionLabel(d.action)}
                                        </Badge>
                                    </div>
                                    {d.comment && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            "{d.comment}"
                                        </p>
                                    )}
                                    {d.acted_at && (
                                        <p className="mt-0.5 text-[10px] text-muted-foreground/60">
                                            {new Date(
                                                d.acted_at,
                                            ).toLocaleString()}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {/* Lifecycle timeline */}
                {history && history.length > 0 && (
                    <section aria-label="Workflow activity">
                        <h3 className="mb-2 text-sm font-medium text-foreground">
                            Activity
                        </h3>
                        <ul className="space-y-1.5">
                            {history.map((event, idx) => (
                                <li
                                    key={idx}
                                    className="flex items-start justify-between gap-2 text-xs"
                                >
                                    <span className="text-muted-foreground">
                                        {event.label}
                                        {event.actor_name && (
                                            <span className="text-foreground">
                                                {' '}
                                                — {event.actor_name}
                                            </span>
                                        )}
                                    </span>
                                    {event.created_at && (
                                        <span className="shrink-0 text-[10px] text-muted-foreground/60">
                                            {new Date(
                                                event.created_at,
                                            ).toLocaleString()}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </CardContent>
        </Card>
    );
}
