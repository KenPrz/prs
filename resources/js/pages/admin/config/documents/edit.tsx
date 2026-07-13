import { Head, Link, useForm } from '@inertiajs/react';
import { Download, FileText, X } from 'lucide-react';
import { useRef, useState } from 'react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ResourceClassOption } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Documents', href: '/admin/config/documents' },
    { title: 'Edit', href: '#' },
];

type DocumentProp = {
    id: number;
    resource_class: string;
    docx_template_name: string | null;
    docx_template_url: string | null;
};

export default function DocumentEdit({
    document: doc,
    resourceClassOptions,
}: {
    document: DocumentProp;
    resourceClassOptions: ResourceClassOption[];
}) {
    const { data, setData, post, processing, errors } = useForm<{
        resource_class: string;
        docx_template: File | null;
        remove_docx_template: boolean;
        _method: string;
    }>({
        resource_class: doc.resource_class,
        docx_template: null,
        remove_docx_template: false,
        _method: 'PUT',
    });

    const fileRef = useRef<HTMLInputElement>(null);
    const [newFileName, setNewFileName] = useState<string | null>(null);
    const [removing, setRemoving] = useState(false);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        setData('docx_template', file);
        setNewFileName(file?.name ?? null);
    }

    function clearNewFile() {
        setData('docx_template', null);
        setNewFileName(null);

        if (fileRef.current) {
            fileRef.current.value = '';
        }
    }

    function removeExisting() {
        setData('remove_docx_template', true);
        setRemoving(true);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(`/admin/config/documents/${doc.id}`, { forceFormData: true });
    }

    const hasExistingTemplate = doc.docx_template_name && !removing;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Document Template — Configuration" />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="Edit Document Template"
                        description="Update the model-to-template mapping."
                    >
                        <div className="grid gap-2">
                            <Label>Document Type</Label>
                            <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                {resourceClassOptions.find(
                                    (option) =>
                                        option.value === data.resource_class,
                                )?.label ?? data.resource_class}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                The document type is system-defined and cannot
                                be changed — only its template can be replaced.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label>.docx Template</Label>

                            {hasExistingTemplate && !newFileName && (
                                <div className="flex items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-2">
                                    <FileText className="h-4 w-4 shrink-0 text-primary" />
                                    <span className="flex-1 truncate text-sm">
                                        {doc.docx_template_name}
                                    </span>
                                    {doc.docx_template_url && (
                                        <a
                                            href={doc.docx_template_url}
                                            download
                                            className="text-muted-foreground hover:text-foreground"
                                        >
                                            <Download className="h-4 w-4" />
                                        </a>
                                    )}
                                    <button
                                        type="button"
                                        onClick={removeExisting}
                                        className="text-muted-foreground hover:text-destructive"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            )}

                            {newFileName ? (
                                <div className="flex items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-2">
                                    <FileText className="h-4 w-4 shrink-0 text-primary" />
                                    <span className="flex-1 truncate text-sm">
                                        {newFileName}
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            (new)
                                        </span>
                                    </span>
                                    <button
                                        type="button"
                                        onClick={clearNewFile}
                                        className="text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            ) : (
                                <Input
                                    ref={fileRef}
                                    type="file"
                                    accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    onChange={handleFileChange}
                                />
                            )}

                            <InputError message={errors.docx_template} />
                            <p className="text-xs text-muted-foreground">
                                Upload a replacement .docx file to overwrite the
                                current template.
                            </p>
                        </div>
                    </ConfigFormSection>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/config/documents">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminConfigLayout>
        </AppLayout>
    );
}
