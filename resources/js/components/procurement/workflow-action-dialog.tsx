import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

type WorkflowAction = 'approve' | 'reject';

type Props = {
    action: WorkflowAction;
    documentLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    processing: boolean;
    comment: string;
    onCommentChange: (value: string) => void;
    onConfirm: () => void;
    onCancel: () => void;
    error?: string | null;
    canOverride?: boolean;
    confirmLabel?: string;
    title?: string;
    description?: string;
};

const labels: Record<
    WorkflowAction,
    { commentId: string; placeholder: string; confirmLabel: string }
> = {
    approve: {
        commentId: 'workflow-approve-comment',
        placeholder: 'Add a comment...',
        confirmLabel: 'Confirm Approval',
    },
    reject: {
        commentId: 'workflow-reject-comment',
        placeholder: 'Reason for rejection...',
        confirmLabel: 'Confirm Rejection',
    },
};

function getTitle(
    action: WorkflowAction,
    documentLabel: string,
    canOverride?: boolean,
): string {
    if (canOverride) {
        return action === 'approve'
            ? 'Override approval?'
            : 'Override rejection?';
    }

    return action === 'approve'
        ? `Approve this ${documentLabel}?`
        : `Reject this ${documentLabel}?`;
}

function getDescription(
    action: WorkflowAction,
    documentLabel: string,
    canOverride?: boolean,
): string {
    if (canOverride) {
        return action === 'approve'
            ? 'You are overriding this approval step as a Super Admin.'
            : `You are overriding this step to reject the ${documentLabel} as a Super Admin.`;
    }

    return action === 'approve'
        ? `You are approving this ${documentLabel} for the current workflow step.`
        : `This will end the approval workflow and mark the ${documentLabel} as rejected.`;
}

export function WorkflowActionDialog({
    action,
    documentLabel,
    open,
    onOpenChange,
    processing,
    comment,
    onCommentChange,
    onConfirm,
    onCancel,
    error,
    canOverride,
    confirmLabel: confirmLabelOverride,
    title: titleOverride,
    description: descriptionOverride,
}: Props) {
    const {
        commentId,
        placeholder,
        confirmLabel: defaultConfirmLabel,
    } = labels[action];
    const confirmLabel = confirmLabelOverride ?? defaultConfirmLabel;

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        {titleOverride ??
                            getTitle(action, documentLabel, canOverride)}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {descriptionOverride ??
                            getDescription(action, documentLabel, canOverride)}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div className="px-1">
                    <label
                        htmlFor={commentId}
                        className="mb-1 block text-sm font-medium text-foreground"
                    >
                        Comment{' '}
                        <span className="text-muted-foreground">
                            (optional)
                        </span>
                    </label>
                    <textarea
                        id={commentId}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        rows={3}
                        maxLength={1000}
                        placeholder={placeholder}
                        value={comment}
                        onChange={(e) => onCommentChange(e.target.value)}
                    />
                </div>
                {error && (
                    <p className="text-sm text-destructive" role="alert">
                        {error}
                    </p>
                )}
                <AlertDialogFooter>
                    <AlertDialogCancel onClick={onCancel}>
                        Cancel
                    </AlertDialogCancel>
                    <Button
                        onClick={onConfirm}
                        disabled={processing}
                        variant={
                            action === 'approve' ? 'success' : 'destructive'
                        }
                    >
                        {confirmLabel}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
