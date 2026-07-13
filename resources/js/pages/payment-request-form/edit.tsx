import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import type { DocumentAttachmentRecord } from '@/components/document-attachments-field';
import { DocumentPageLayout } from '@/components/procurement/document-page-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import * as paymentRequestForms from '@/routes/payment-request-forms';
import type { ApprovalSummary, BreadcrumbItem } from '@/types';

interface Department {
    id: number;
    name: string;
    code: string;
}

interface Supplier {
    id: number;
    name: string;
}

interface CompanyProfile {
    id: number;
    name: string;
}

interface NoteData {
    id?: number;
    content: string;
}

type PaymentRequestFormModel = {
    id: number;
    prf_number: string;
    supplier_id: number;
    company_profile_id: number | null;
    description: string | null;
    amount: string;
    invoice_number: string | null;
    due_date: string | null;
    stamp_date: string | null;
    status: string;
    departments?: Department[];
    notes?: { id: number; content: string }[];
};

export default function PaymentRequestFormEdit({
    paymentRequestForm,
    departments = [],
    suppliers = [],
    companyProfiles = [],
    attachments = [],
}: {
    paymentRequestForm: PaymentRequestFormModel;
    departments: Department[];
    suppliers: Supplier[];
    companyProfiles: CompanyProfile[];
    attachments: DocumentAttachmentRecord[];
    statuses: string[];
    approvalSummary: ApprovalSummary | null;
    canSubmit: boolean;
}) {
    const form = useForm({
        supplier_id: paymentRequestForm.supplier_id || ('' as number | ''),
        company_profile_id:
            paymentRequestForm.company_profile_id || ('' as number | ''),
        description: paymentRequestForm.description || '',
        amount: paymentRequestForm.amount
            ? Number(paymentRequestForm.amount)
            : ('' as number | ''),
        invoice_number: paymentRequestForm.invoice_number || '',
        due_date: paymentRequestForm.due_date || ('' as string),
        stamp_date: paymentRequestForm.stamp_date || ('' as string),
        department_ids:
            paymentRequestForm.departments?.map((d) => d.id) ||
            ([] as number[]),
        notes: (paymentRequestForm.notes?.length
            ? paymentRequestForm.notes
            : [{ content: '' }]) as NoteData[],
        status: 'DRAFT',
        attachments: [] as File[],
        removed_attachment_ids: [] as number[],
        _method: 'PUT' as const,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Payment request forms',
            href: paymentRequestForms.index.url(),
        },
        {
            title: paymentRequestForm.prf_number,
            href: paymentRequestForms.show.url(paymentRequestForm.id),
        },
        {
            title: 'Edit',
            href: paymentRequestForms.edit.url(paymentRequestForm.id),
        },
    ];

    const handleSave = () => {
        form.post(paymentRequestForms.update.url(paymentRequestForm.id), {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    const selectedDepartmentNames = form.data.department_ids
        .map((id) => departments.find((d) => d.id === id)?.name)
        .filter(Boolean)
        .join(', ');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${paymentRequestForm.prf_number}`} />

            <form onSubmit={(e) => e.preventDefault()} className="w-full">
                <DocumentPageLayout
                    className="md:p-8"
                    header={
                        <div className="mb-2 flex items-start gap-4">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="mt-1 h-8 w-8 text-muted-foreground"
                                asChild
                            >
                                <Link
                                    href={paymentRequestForms.show.url(
                                        paymentRequestForm.id,
                                    )}
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                    <span className="sr-only">Back</span>
                                </Link>
                            </Button>
                            <div className="flex flex-col gap-1">
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    Edit {paymentRequestForm.prf_number}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Update payment request form details
                                </p>
                            </div>
                        </div>
                    }
                    main={
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Payment Details</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Company */}
                                    <div className="space-y-2">
                                        <Label htmlFor="company_profile_id">
                                            Company
                                        </Label>
                                        <Select
                                            value={
                                                form.data.company_profile_id
                                                    ? String(
                                                          form.data
                                                              .company_profile_id,
                                                      )
                                                    : ''
                                            }
                                            onValueChange={(val) =>
                                                form.setData(
                                                    'company_profile_id',
                                                    Number(val),
                                                )
                                            }
                                        >
                                            <SelectTrigger id="company_profile_id">
                                                <SelectValue placeholder="Select company…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {companyProfiles.map((cp) => (
                                                    <SelectItem
                                                        key={cp.id}
                                                        value={String(cp.id)}
                                                    >
                                                        {cp.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {form.errors.company_profile_id && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.company_profile_id}
                                            </p>
                                        )}
                                    </div>

                                    {/* Payee */}
                                    <div className="space-y-2">
                                        <Label htmlFor="supplier_id">
                                            Payee / Supplier{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={
                                                form.data.supplier_id
                                                    ? String(
                                                          form.data.supplier_id,
                                                      )
                                                    : ''
                                            }
                                            onValueChange={(val) =>
                                                form.setData(
                                                    'supplier_id',
                                                    Number(val),
                                                )
                                            }
                                        >
                                            <SelectTrigger id="supplier_id">
                                                <SelectValue placeholder="Select payee…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {suppliers.map((s) => (
                                                    <SelectItem
                                                        key={s.id}
                                                        value={String(s.id)}
                                                    >
                                                        {s.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {form.errors.supplier_id && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.supplier_id}
                                            </p>
                                        )}
                                    </div>

                                    {/* Department */}
                                    <div className="space-y-2">
                                        <Label>
                                            Department{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <MultiSelect
                                            options={departments.map((d) => ({
                                                label: d.name,
                                                value: String(d.id),
                                            }))}
                                            selected={form.data.department_ids.map(
                                                String,
                                            )}
                                            onChange={(vals) =>
                                                form.setData(
                                                    'department_ids',
                                                    vals.map(Number),
                                                )
                                            }
                                            placeholder="Select departments…"
                                        />
                                        {form.errors.department_ids && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.department_ids}
                                            </p>
                                        )}
                                    </div>

                                    {/* Amount and Invoice # */}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="amount">
                                                Amount{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="amount"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={form.data.amount}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'amount',
                                                        e.target.value
                                                            ? Number(
                                                                  e.target
                                                                      .value,
                                                              )
                                                            : '',
                                                    )
                                                }
                                                placeholder="0.00"
                                            />
                                            {form.errors.amount && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.amount}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="invoice_number">
                                                Invoice #
                                            </Label>
                                            <Input
                                                id="invoice_number"
                                                value={form.data.invoice_number}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'invoice_number',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g., INV-2026-001"
                                            />
                                            {form.errors.invoice_number && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.invoice_number}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Due Date and Date Received */}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="due_date">
                                                Due Date
                                            </Label>
                                            <DatePicker
                                                date={form.data.due_date || ''}
                                                onChange={(val) =>
                                                    form.setData(
                                                        'due_date',
                                                        val,
                                                    )
                                                }
                                                placeholder="Select due date"
                                            />
                                            {form.errors.due_date && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.due_date}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="stamp_date">
                                                Date Received
                                            </Label>
                                            <DatePicker
                                                date={
                                                    form.data.stamp_date || ''
                                                }
                                                onChange={(val) =>
                                                    form.setData(
                                                        'stamp_date',
                                                        val,
                                                    )
                                                }
                                                placeholder="Select date received"
                                            />
                                            {form.errors.stamp_date && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.stamp_date}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Description */}
                                    <div className="space-y-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="description"
                                            value={form.data.description}
                                            onChange={(
                                                e: ChangeEvent<HTMLTextAreaElement>,
                                            ) =>
                                                form.setData(
                                                    'description',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Describe the purpose of this payment request…"
                                            rows={3}
                                        />
                                        {form.errors.description && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.description}
                                            </p>
                                        )}
                                    </div>

                                    {/* Notes */}
                                    <div className="space-y-2">
                                        <Label>Notes</Label>
                                        {form.data.notes.map((note, i) => (
                                            <div
                                                key={note.id ?? `new-${i}`}
                                                className="flex gap-2"
                                            >
                                                <Textarea
                                                    value={note.content}
                                                    onChange={(
                                                        e: ChangeEvent<HTMLTextAreaElement>,
                                                    ) => {
                                                        const notes = [
                                                            ...form.data.notes,
                                                        ];
                                                        notes[i] = {
                                                            ...notes[i],
                                                            content:
                                                                e.target.value,
                                                        };
                                                        form.setData(
                                                            'notes',
                                                            notes,
                                                        );
                                                    }}
                                                    placeholder="Add a note…"
                                                    rows={2}
                                                    className="flex-1"
                                                />
                                                {form.data.notes.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            const notes =
                                                                form.data.notes.filter(
                                                                    (_, idx) =>
                                                                        idx !==
                                                                        i,
                                                                );
                                                            form.setData(
                                                                'notes',
                                                                notes,
                                                            );
                                                        }}
                                                    >
                                                        ✕
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                form.setData('notes', [
                                                    ...form.data.notes,
                                                    { content: '' },
                                                ])
                                            }
                                        >
                                            + Add note
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Attachments (mobile) */}
                            <div className="lg:hidden">
                                <DocumentAttachmentsField
                                    existingAttachments={attachments}
                                    attachments={form.data.attachments}
                                    onAttachmentsChange={(files) =>
                                        form.setData('attachments', files)
                                    }
                                    removedAttachmentIds={
                                        form.data.removed_attachment_ids
                                    }
                                    onRemovedAttachmentIdsChange={(ids) =>
                                        form.setData(
                                            'removed_attachment_ids',
                                            ids,
                                        )
                                    }
                                    errors={form.errors}
                                    disabled={form.processing}
                                />
                            </div>
                        </>
                    }
                    sidebar={
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Summary</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Department
                                        </span>
                                        <span className="font-medium">
                                            {selectedDepartmentNames || '—'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Due Date
                                        </span>
                                        <span className="font-medium">
                                            {form.data.due_date || '—'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Invoice #
                                        </span>
                                        <span className="font-medium">
                                            {form.data.invoice_number || '—'}
                                        </span>
                                    </div>
                                    <div className="border-t pt-3">
                                        <div className="flex items-center justify-between text-base font-semibold">
                                            <span>Amount</span>
                                            <span>
                                                {new Intl.NumberFormat(
                                                    'en-PH',
                                                    {
                                                        style: 'currency',
                                                        currency: 'PHP',
                                                    },
                                                ).format(
                                                    Number(form.data.amount) ||
                                                        0,
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-2 pt-2">
                                        <Button
                                            type="button"
                                            onClick={handleSave}
                                            disabled={form.processing}
                                            className="w-full"
                                        >
                                            Save changes
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            asChild
                                            className="w-full"
                                        >
                                            <Link
                                                href={paymentRequestForms.show.url(
                                                    paymentRequestForm.id,
                                                )}
                                            >
                                                Cancel
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Attachments (desktop) */}
                            <div className="hidden lg:block">
                                <DocumentAttachmentsField
                                    existingAttachments={attachments}
                                    attachments={form.data.attachments}
                                    onAttachmentsChange={(files) =>
                                        form.setData('attachments', files)
                                    }
                                    removedAttachmentIds={
                                        form.data.removed_attachment_ids
                                    }
                                    onRemovedAttachmentIdsChange={(ids) =>
                                        form.setData(
                                            'removed_attachment_ids',
                                            ids,
                                        )
                                    }
                                    errors={form.errors}
                                    disabled={form.processing}
                                />
                            </div>
                        </>
                    }
                />
            </form>
        </AppLayout>
    );
}
