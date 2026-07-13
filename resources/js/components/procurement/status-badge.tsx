import { Badge } from '@/components/ui/badge';

type StatusVariant = 'default' | 'secondary' | 'destructive' | 'outline';

// Carbon tag tints built from semantic tokens: warning (in review),
// success (approved/complete), chart-1 blue (in progress),
// chart-2 purple (allocated). Chart vars flip per theme automatically.
const TINT = {
    warning: 'border-warning/60 bg-warning/15 text-foreground',
    success: 'border-success/40 bg-success/10 text-success',
    progress: 'border-chart-1/40 bg-chart-1/10 text-chart-1',
    allocated: 'border-chart-2/40 bg-chart-2/10 text-chart-2',
};

const statusConfig: Record<
    string,
    { label: string; variant: StatusVariant; className?: string }
> = {
    // PR statuses
    DRAFT: { label: 'Draft', variant: 'secondary' },
    REVIEWING: {
        label: 'Reviewing',
        variant: 'outline',
        className: TINT.warning,
    },
    APPROVED: {
        label: 'Approved',
        variant: 'outline',
        className: TINT.success,
    },
    READY_FOR_PO: {
        label: 'Ready for PO',
        variant: 'outline',
        className: TINT.success,
    },
    REJECTED: { label: 'Rejected', variant: 'destructive' },
    CANCELLED: {
        label: 'Cancelled',
        variant: 'secondary',
        className: 'text-muted-foreground',
    },
    PARTIALLY_ORDERED: {
        label: 'Partially Ordered',
        variant: 'outline',
        className: TINT.progress,
    },
    FULLY_ALLOCATED: {
        label: 'Fully Allocated',
        variant: 'outline',
        className: TINT.allocated,
    },
    FULLY_ORDERED: {
        label: 'Fully Ordered',
        variant: 'outline',
        className: TINT.success,
    },
    CLOSED: { label: 'Closed', variant: 'secondary' },

    // PO statuses
    PENDING_APPROVAL: {
        label: 'Pending Approval',
        variant: 'outline',
        className: TINT.warning,
    },
    RELEASED: {
        label: 'Ordered',
        variant: 'outline',
        className: TINT.success,
    },
    PARTIALLY_RECEIVED: {
        label: 'Partially Received',
        variant: 'outline',
        className: TINT.progress,
    },
    FULLY_RECEIVED: {
        label: 'Fully Received',
        variant: 'outline',
        className: TINT.success,
    },

    // RR statuses
    PENDING: {
        label: 'Pending',
        variant: 'outline',
        className: TINT.warning,
    },
    VERIFIED: {
        label: 'Verified',
        variant: 'outline',
        className: TINT.success,
    },
    SUBMITTED_TO_ACCOUNTING: {
        label: 'Submitted to Accounting',
        variant: 'outline',
        className: TINT.success,
    },
};

export function StatusBadge({ status }: { status: string }) {
    const config = statusConfig[status] ?? {
        label: status,
        variant: 'secondary' as StatusVariant,
    };

    return (
        <Badge variant={config.variant} className={config.className}>
            {config.label}
        </Badge>
    );
}
