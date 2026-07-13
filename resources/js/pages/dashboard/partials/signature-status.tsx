import { Link } from '@inertiajs/react';
import { CheckCircle2, PenLine } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

export interface SignatureStatus {
    uploaded: boolean;
    active: boolean;
}

export function SignatureStatusBanner({ status }: { status: SignatureStatus }) {
    if (status.active) {
        return (
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <CheckCircle2 className="h-3.5 w-3.5 text-success" />
                <span>
                    Signature active — you're ready to approve documents.
                </span>
                <Link
                    href="/settings/signatures"
                    className="font-medium text-primary underline-offset-4 hover:underline"
                >
                    Manage
                </Link>
            </div>
        );
    }

    return (
        <Alert className="border-warning/40 bg-warning/5">
            <PenLine className="h-4 w-4 text-warning" />
            <AlertTitle>
                {status.uploaded
                    ? 'No active signature'
                    : 'No signature uploaded'}
            </AlertTitle>
            <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <span>
                    {status.uploaded
                        ? 'None of your signatures is set as active. Activate one to approve or receive documents.'
                        : "You haven't uploaded a signature yet — you won't be able to approve or receive documents until you do."}
                </span>
                <Button
                    asChild
                    size="sm"
                    variant="outline"
                    className="shrink-0"
                >
                    <Link href="/settings/signatures">
                        {status.uploaded
                            ? 'Activate a signature'
                            : 'Upload a signature'}
                    </Link>
                </Button>
            </AlertDescription>
        </Alert>
    );
}
