import { router } from '@inertiajs/react';
import { EyeOff, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/hooks/use-permissions';

/**
 * Uniform control to omit (short-close) or restore a procurement line item.
 * Omitted items stay visible on the show page but are dropped from the document
 * PDF and from the close/fulfillment calculations. `endpoint` is the toggle route
 * for the specific item (line item or purchase order item). When `permission` is
 * given, the control is hidden from users who lack it (the backend enforces the
 * same permission).
 */
export function OmitItemButton({
    endpoint,
    isOmitted,
    disabled = false,
    permission,
}: {
    endpoint: string;
    isOmitted: boolean;
    disabled?: boolean;
    permission?: string;
}) {
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    if (permission && !can(permission)) {
        return null;
    }

    const post = (omit: boolean) => {
        setSubmitting(true);
        setOpen(false); // close before the request so Radix restores <body> pointer-events before the row re-renders/moves
        router.post(
            endpoint,
            { omit, reason: omit ? reason : '' },
            {
                preserveScroll: true,
                onSuccess: () => setReason(''),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    if (isOmitted) {
        return (
            <Button
                type="button"
                variant="ghost"
                size="sm"
                disabled={disabled || submitting}
                className="h-8 gap-1.5 text-muted-foreground hover:text-foreground"
                onClick={() => post(false)}
            >
                <RotateCcw className="h-3.5 w-3.5" />
                Restore
            </Button>
        );
    }

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={disabled}
                    className="h-8 gap-1.5 text-muted-foreground hover:text-destructive"
                >
                    <EyeOff className="h-3.5 w-3.5" />
                    Omit
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Omit this item?</AlertDialogTitle>
                    <AlertDialogDescription>
                        It stays visible here but is removed from the document
                        PDF and no longer counts toward closing. You can restore
                        it later.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Textarea
                    placeholder="Reason (optional)"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    maxLength={255}
                />
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <Button
                        type="button"
                        onClick={() => post(true)}
                        disabled={submitting}
                    >
                        Omit
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
