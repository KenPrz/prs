import { useForm } from '@inertiajs/react';

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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Endpoint the reason is submitted to. */
    url: string;
    method?: 'post' | 'delete';
    /** Request field name the backend validates (`reason` or `comment`). */
    field?: 'reason' | 'comment';
    title: string;
    description: string;
    confirmLabel: string;
    confirmVariant?: 'destructive' | 'default';
    placeholder?: string;
};

/**
 * Confirmation dialog for destructive/cancelling actions that require a reason
 * (deletions, document cancellations, mid-flow workflow cancellations).
 */
export function ReasonActionDialog({
    open,
    onOpenChange,
    url,
    method = 'post',
    field = 'reason',
    title,
    description,
    confirmLabel,
    confirmVariant = 'destructive',
    placeholder = 'Provide a reason...',
}: Props) {
    const form = useForm({ [field]: '' } as Record<string, string>);
    const value = form.data[field] ?? '';
    const inputId = `reason-action-${field}`;

    const submit = () => {
        form.submit(method, url, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div className="px-1">
                    <label
                        htmlFor={inputId}
                        className="mb-1 block text-sm font-medium text-foreground"
                    >
                        Reason{' '}
                        <span className="text-muted-foreground">
                            (required)
                        </span>
                    </label>
                    <textarea
                        id={inputId}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        rows={3}
                        maxLength={1000}
                        placeholder={placeholder}
                        value={value}
                        onChange={(e) => form.setData(field, e.target.value)}
                    />
                    {form.errors[field] && (
                        <p
                            className="mt-1 text-sm text-destructive"
                            role="alert"
                        >
                            {form.errors[field]}
                        </p>
                    )}
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel onClick={() => form.reset()}>
                        Cancel
                    </AlertDialogCancel>
                    <Button
                        onClick={submit}
                        disabled={form.processing || value.trim() === ''}
                        variant={confirmVariant}
                    >
                        {confirmLabel}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
