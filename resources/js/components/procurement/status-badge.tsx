import { Badge } from '@/components/ui/badge';

type StatusVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const statusConfig: Record<
    string,
    { label: string; variant: StatusVariant; className?: string }
> = {
    // PR statuses
    DRAFT: { label: 'Draft', variant: 'secondary' },
    REVIEWING: {
        label: 'Reviewing',
        variant: 'outline',
        className:
            'border-amber-200 text-amber-700 dark:border-amber-800 dark:text-amber-400',
    },
    APPROVED: {
        label: 'Approved',
        variant: 'outline',
        className:
            'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-400',
    },
    READY_FOR_PO: {
        label: 'Ready for PO',
        variant: 'outline',
        className:
            'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-400',
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
        className:
            'border-blue-200 text-blue-700 dark:border-blue-800 dark:text-blue-400',
    },
    FULLY_ALLOCATED: {
        label: 'Fully Allocated',
        variant: 'outline',
        className:
            'border-indigo-200 text-indigo-700 dark:border-indigo-800 dark:text-indigo-400',
    },
    FULLY_ORDERED: {
        label: 'Fully Ordered',
        variant: 'outline',
        className:
            'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-400',
    },
    CLOSED: { label: 'Closed', variant: 'secondary' },

    // PO statuses
    PENDING_APPROVAL: {
        label: 'Pending Approval',
        variant: 'outline',
        className:
            'border-amber-200 text-amber-700 dark:border-amber-800 dark:text-amber-400',
    },
    RELEASED: {
        label: 'Ordered',
        variant: 'outline',
        className:
            'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-400',
    },
    PARTIALLY_RECEIVED: {
        label: 'Partially Received',
        variant: 'outline',
        className:
            'border-blue-200 text-blue-700 dark:border-blue-800 dark:text-blue-400',
    },
    FULLY_RECEIVED: {
        label: 'Fully Received',
        variant: 'outline',
        className:
            'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-400',
    },

    // RR statuses
    PENDING: {
        label: 'Pending',
        variant: 'outline',
        className:
            'border-amber-200 text-amber-700 dark:border-amber-800 dark:text-amber-400',
    },
    VERIFIED: {
        label: 'Verified',
        variant: 'outline',
        className:
            'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-400',
    },
    SUBMITTED_TO_ACCOUNTING: {
        label: 'Submitted to Accounting',
        variant: 'outline',
        className:
            'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-400',
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
