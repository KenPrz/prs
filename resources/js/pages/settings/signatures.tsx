import { Form, Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ImageOff,
    PenLine,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SignatureController from '@/actions/App/Http/Controllers/Settings/SignatureController';
import InputError from '@/components/input-error';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { index } from '@/routes/signatures';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Signatures',
        href: index(),
    },
];

type Signature = {
    id: number;
    is_active: boolean;
    url: string | null;
    name: string | null;
    created_at: string;
};

function formatSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default function Signatures({
    signatures,
}: {
    signatures: Signature[];
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [deciding, setDeciding] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Signature | null>(null);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const chooseFile = (file: File | null) => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        setSelectedFile(file);
        setPreviewUrl(file ? URL.createObjectURL(file) : null);
    };

    const clearFile = () => {
        chooseFile(null);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const setActive = (id: number) => {
        setDeciding(true);
        router.put(
            SignatureController.update.url(id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setDeciding(false),
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeciding(true);
        router.delete(SignatureController.destroy.url(deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeciding(false);
                setDeleteTarget(null);
            },
        });
    };

    const uploadCard = (
        <Card>
            <CardHeader>
                <CardTitle>Upload a Signature</CardTitle>
                <CardDescription>
                    Transparent PNG up to 2 MB — it is stamped onto the
                    documents you approve.
                </CardDescription>
            </CardHeader>

            <Form
                {...SignatureController.store.form()}
                options={{ preserveScroll: true }}
                transform={(data) => ({ ...data, signature: selectedFile })}
                onSuccess={clearFile}
            >
                {({ processing, errors }) => (
                    <CardContent className="space-y-4">
                        {/* Hidden real input; the dropzone drives it. */}
                        <input
                            id="signature"
                            name="signature"
                            type="file"
                            accept="image/png"
                            ref={fileInputRef}
                            className="hidden"
                            onChange={(e) =>
                                chooseFile(e.target.files?.[0] ?? null)
                            }
                            disabled={processing}
                        />

                        {selectedFile && previewUrl ? (
                            <div className="rounded-xl border bg-muted/20 p-4">
                                <div className="flex items-center justify-center rounded-lg bg-white p-4 dark:bg-muted/40">
                                    <img
                                        src={previewUrl}
                                        alt="Signature preview"
                                        className="max-h-28 object-contain"
                                    />
                                </div>
                                <div className="mt-3 flex items-center justify-between gap-3">
                                    <p className="truncate text-sm text-muted-foreground">
                                        {selectedFile.name}
                                        <span className="ml-2 text-xs text-muted-foreground/60">
                                            {formatSize(selectedFile.size)}
                                        </span>
                                    </p>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={clearFile}
                                            disabled={processing}
                                        >
                                            <X className="mr-1 h-4 w-4" />
                                            Remove
                                        </Button>
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            <Upload className="mr-1 h-4 w-4" />
                                            {processing
                                                ? 'Uploading…'
                                                : 'Upload signature'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setIsDragging(true);
                                }}
                                onDragLeave={() => setIsDragging(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setIsDragging(false);
                                    const file = e.dataTransfer.files?.[0];

                                    if (file && file.type === 'image/png') {
                                        chooseFile(file);
                                    }
                                }}
                                className={cn(
                                    'flex w-full flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-10 text-center transition-colors',
                                    isDragging
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-muted-foreground/40 hover:bg-muted/30',
                                )}
                            >
                                <div className="rounded-full bg-muted/60 p-3">
                                    <PenLine className="h-6 w-6 text-muted-foreground" />
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">
                                        Drag and drop your signature here
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        or click to browse — PNG only, max 2 MB
                                    </p>
                                </div>
                            </button>
                        )}

                        <InputError message={errors.signature} />
                    </CardContent>
                )}
            </Form>
        </Card>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Signatures" />

            <h1 className="sr-only">Signatures</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    {signatures.length === 0 ? (
                        <>
                            <Card className="border-warning/40 bg-warning/5">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        A signature is required for approvals
                                    </CardTitle>
                                    <CardDescription>
                                        Your active signature is applied when
                                        you approve or receive documents. Until
                                        you upload one, you cannot act on
                                        approval requests.
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                            {uploadCard}
                        </>
                    ) : (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Your Signatures</CardTitle>
                                    <CardDescription>
                                        The active signature is applied to
                                        documents you approve. Only one can be
                                        active at a time.
                                    </CardDescription>
                                </CardHeader>

                                <CardContent>
                                    <div className="grid gap-6 sm:grid-cols-2">
                                        {signatures.map((sig) => (
                                            <div
                                                key={sig.id}
                                                className={cn(
                                                    'group relative flex flex-col overflow-hidden rounded-xl border transition-all',
                                                    sig.is_active
                                                        ? 'border-success ring-1 ring-success'
                                                        : 'border-border bg-card hover:border-muted-foreground/30',
                                                )}
                                            >
                                                <div className="relative flex aspect-3/1 w-full items-center justify-center bg-white p-6 dark:bg-muted/30">
                                                    {sig.url ? (
                                                        <img
                                                            src={sig.url}
                                                            alt={
                                                                sig.name ||
                                                                'Signature'
                                                            }
                                                            className="h-full w-full object-contain"
                                                        />
                                                    ) : (
                                                        <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                                            <ImageOff className="h-6 w-6 opacity-30" />
                                                            <span className="text-xs">
                                                                Image
                                                                unavailable
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="flex items-center justify-between border-t border-border/60 p-3">
                                                    <div className="flex items-center gap-2">
                                                        {sig.is_active ? (
                                                            <Badge className="gap-1 bg-success/10 text-success hover:bg-success/10">
                                                                <CheckCircle2 className="h-3 w-3" />
                                                                Active
                                                            </Badge>
                                                        ) : (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-7 text-xs"
                                                                onClick={() =>
                                                                    setActive(
                                                                        sig.id,
                                                                    )
                                                                }
                                                                disabled={
                                                                    deciding
                                                                }
                                                            >
                                                                Set as Active
                                                            </Button>
                                                        )}
                                                        <span className="text-[11px] text-muted-foreground/60">
                                                            {new Date(
                                                                sig.created_at,
                                                            ).toLocaleDateString()}
                                                        </span>
                                                    </div>

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7 rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                        onClick={() =>
                                                            setDeleteTarget(sig)
                                                        }
                                                        disabled={deciding}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Delete
                                                        </span>
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            {uploadCard}
                        </>
                    )}
                </div>
            </SettingsLayout>

            <AlertDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete this signature?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {deleteTarget?.is_active
                                ? 'This is your active signature. Deleting it means you cannot approve documents until you activate or upload another one.'
                                : 'This signature will be permanently removed.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            className="bg-destructive text-white hover:bg-destructive/90"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
