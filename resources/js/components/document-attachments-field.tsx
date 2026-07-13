import { Paperclip, Trash2, Download } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type DocumentAttachmentRecord = {
    id: number;
    name: string;
    size: number;
    mime_type: string | null;
    download_url: string;
};

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export type DocumentAttachmentsFieldProps = {
    /** Existing files from the server (edit / show). */
    existingAttachments?: DocumentAttachmentRecord[];
    /** New files staged for upload (create / edit). */
    attachments: File[];
    onAttachmentsChange: (files: File[]) => void;
    /** IDs of existing attachments marked for removal (edit only). */
    removedAttachmentIds: number[];
    onRemovedAttachmentIdsChange: (ids: number[]) => void;
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
    /** Hide file input and removal actions; show list with downloads only. */
    readOnly?: boolean;
    label?: string;
    description?: string;
    className?: string;
};

export function DocumentAttachmentsField({
    existingAttachments = [],
    attachments,
    onAttachmentsChange,
    removedAttachmentIds,
    onRemovedAttachmentIdsChange,
    errors,
    disabled = false,
    readOnly = false,
    label = 'Attachments',
    description = 'PDF or images only (JPEG, PNG, GIF, WebP, BMP). Max 20 files, 10 MB each.',
    className,
}: DocumentAttachmentsFieldProps) {
    const inputId = useId();

    const visibleExisting = existingAttachments.filter(
        (a) => !removedAttachmentIds.includes(a.id),
    );

    const handleFileChange = (
        event: React.ChangeEvent<HTMLInputElement>,
    ): void => {
        const list = event.target.files ? Array.from(event.target.files) : [];

        if (list.length === 0) {
            return;
        }

        onAttachmentsChange([...attachments, ...list]);
        event.target.value = '';
    };

    const removeStagedAt = (index: number): void => {
        onAttachmentsChange(attachments.filter((_, i) => i !== index));
    };

    const markExistingRemoved = (id: number): void => {
        onRemovedAttachmentIdsChange([...removedAttachmentIds, id]);
    };

    const unmarkExistingRemoved = (id: number): void => {
        onRemovedAttachmentIdsChange(
            removedAttachmentIds.filter((x) => x !== id),
        );
    };

    const attachmentErrorMessages = Object.entries(errors ?? {})
        .filter(
            ([key]) => key === 'attachments' || key.startsWith('attachments.'),
        )
        .map(([, v]) => v)
        .filter(Boolean);

    return (
        <Card className={cn(className)}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Paperclip className="h-4 w-4" />
                    {label}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {!readOnly && (
                    <div>
                        <input
                            id={inputId}
                            type="file"
                            multiple
                            className="sr-only"
                            disabled={disabled}
                            onChange={handleFileChange}
                            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,application/pdf,image/jpeg,image/png,image/gif,image/webp,image/bmp"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={disabled}
                            onClick={() =>
                                document.getElementById(inputId)?.click()
                            }
                        >
                            Add files
                        </Button>
                    </div>
                )}

                {visibleExisting.length > 0 && (
                    <ul className="space-y-2 text-sm">
                        {visibleExisting.map((file) => (
                            <li
                                key={file.id}
                                className="flex items-center justify-between gap-2 rounded-md border border-border bg-muted/30 px-3 py-2"
                            >
                                <span className="min-w-0 flex-1 truncate font-medium">
                                    {file.name}
                                </span>
                                <span className="shrink-0 text-muted-foreground">
                                    {formatBytes(file.size)}
                                </span>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button variant="ghost" size="icon" asChild>
                                        <a
                                            href={file.download_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            title="Download"
                                        >
                                            <Download className="h-4 w-4" />
                                            <span className="sr-only">
                                                Download
                                            </span>
                                        </a>
                                    </Button>
                                    {!readOnly && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={disabled}
                                            onClick={() =>
                                                markExistingRemoved(file.id)
                                            }
                                            title="Remove"
                                        >
                                            <Trash2 className="h-4 w-4 text-destructive" />
                                            <span className="sr-only">
                                                Remove
                                            </span>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {!readOnly &&
                    removedAttachmentIds.length > 0 &&
                    existingAttachments.some((a) =>
                        removedAttachmentIds.includes(a.id),
                    ) && (
                        <div className="rounded-md border border-dashed border-muted-foreground/30 p-3 text-xs text-muted-foreground">
                            <p className="font-medium text-foreground">
                                Pending removal
                            </p>
                            <ul className="mt-1 space-y-1">
                                {existingAttachments
                                    .filter((a) =>
                                        removedAttachmentIds.includes(a.id),
                                    )
                                    .map((a) => (
                                        <li
                                            key={a.id}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <span className="truncate">
                                                {a.name}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="link"
                                                className="h-auto shrink-0 p-0 text-xs"
                                                onClick={() =>
                                                    unmarkExistingRemoved(a.id)
                                                }
                                            >
                                                Undo
                                            </Button>
                                        </li>
                                    ))}
                            </ul>
                        </div>
                    )}

                {attachments.length > 0 && (
                    <ul className="space-y-2 text-sm">
                        {attachments.map((file, index) => (
                            <li
                                key={`${file.name}-${index}-${file.size}`}
                                className="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2"
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {file.name}
                                </span>
                                <span className="shrink-0 text-muted-foreground">
                                    {formatBytes(file.size)}
                                </span>
                                {!readOnly && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={disabled}
                                        onClick={() => removeStagedAt(index)}
                                        title="Remove"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span className="sr-only">Remove</span>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {readOnly &&
                    visibleExisting.length === 0 &&
                    attachments.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No attachments.
                        </p>
                    )}

                {!readOnly && attachmentErrorMessages.length > 0 && (
                    <ul className="list-inside list-disc text-sm text-destructive">
                        {attachmentErrorMessages.map((msg, i) => (
                            <li key={i}>{msg}</li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
