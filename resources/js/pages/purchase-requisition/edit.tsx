import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import PurchaseRequisitionController from '@/actions/App/Http/Controllers/PurchaseRequisitionController';
import { ApprovalStatusPanel } from '@/components/approval/approval-status-panel';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import type { DocumentAttachmentRecord } from '@/components/document-attachments-field';
import { AdditionalNotesCard } from '@/components/procurement/additional-notes-card';
import { DocumentPageLayout } from '@/components/procurement/document-page-layout';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { priceBreakdown } from '@/lib/price-type';
import * as purchaseRequisitions from '@/routes/purchase-requisitions';
import * as workflows from '@/routes/workflows';
import type { ApprovalSummary, BreadcrumbItem } from '@/types';
import type { LineItemData, NoteData } from './create';
import { LineItemsCard } from './partials/line-items-card';
import { RequestDetailsCard } from './partials/request-details-card';
import { SummaryCard } from './partials/summary-card';

interface Department {
    id: number;
    name: string;
    code: string;
}

interface ItemUnit {
    id: number;
    name: string;
    code: string;
}

interface Requestor {
    id: number;
    name: string;
}

interface LabeledOption {
    value: string;
    label: string;
}

interface UserOption {
    id: number;
    name: string;
    email?: string;
}

interface PurchaseRequisitionModel {
    id: number;
    pr_number: string;
    title: string;
    description: string | null;
    delivery_date: string | null;
    status: string;
    price_type: string;
    purpose_type: string | null;
    expected_useful_life: string | null;
    to_be_ordered_by_id: number | null;
    requestor?: Requestor | null;
    departments: Department[];
    line_items: LineItemData[];
    notes: NoteData[];
}

export default function PurchaseRequisitionEdit({
    purchaseRequisition,
    departments = [],
    itemUnits = [],
    priceTypes = [],
    purposeTypes = [],
    users = [],
    approvalSummary = null,
    attachments = [],
}: {
    purchaseRequisition: PurchaseRequisitionModel;
    departments: Department[];
    itemUnits: ItemUnit[];
    priceTypes: string[];
    purposeTypes: LabeledOption[];
    users: UserOption[];
    approvalSummary?: ApprovalSummary | null;
    attachments?: DocumentAttachmentRecord[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Purchase requisitions',
            href: purchaseRequisitions.index.url(),
        },
        {
            title: purchaseRequisition.pr_number,
            href: purchaseRequisitions.show.url(purchaseRequisition.id),
        },
        {
            title: 'Edit',
            href: purchaseRequisitions.edit.url(purchaseRequisition.id),
        },
    ];

    const form = useForm<{
        title: string;
        description: string;
        delivery_date: string;
        department_ids: number[];
        price_type: string;
        purpose_type: string;
        expected_useful_life: string;
        to_be_ordered_by_id: number | null;
        line_items: LineItemData[];
        notes: NoteData[];
        status: string;
        attachments: File[];
        removed_attachment_ids: number[];
    }>({
        title: purchaseRequisition.title,
        description: purchaseRequisition.description ?? '',
        delivery_date: purchaseRequisition.delivery_date ?? '',
        department_ids: purchaseRequisition.departments.map((d) => d.id),
        price_type: purchaseRequisition.price_type,
        purpose_type: purchaseRequisition.purpose_type ?? '',
        expected_useful_life: purchaseRequisition.expected_useful_life ?? '',
        to_be_ordered_by_id: purchaseRequisition.to_be_ordered_by_id ?? null,
        line_items: purchaseRequisition.line_items.map((item) => ({
            id: item.id,
            name: item.name,
            quantity: item.quantity,
            unit_id: item.unit_id,
            price: item.price,
        })),
        notes:
            purchaseRequisition.notes.length > 0
                ? purchaseRequisition.notes.map((note) => ({
                      id: note.id,
                      content: note.content,
                  }))
                : [{ content: '' }],
        status: purchaseRequisition.status,
        attachments: [],
        removed_attachment_ids: [],
    });

    const [submittingWorkflow, setSubmittingWorkflow] = useState(false);

    const handleAction = (status: 'DRAFT' | 'REVIEWING') => {
        const submitOpts = {
            preserveScroll: true,
            forceFormData: form.data.attachments.length > 0,
        } as const;

        if (status === 'DRAFT') {
            form.transform((data) => ({
                ...data,
                status: 'DRAFT',
            }));
            form.put(
                PurchaseRequisitionController.update.url(
                    purchaseRequisition.id,
                ),
                submitOpts,
            );

            return;
        }

        setSubmittingWorkflow(true);
        form.transform((data) => ({
            ...data,
            status: 'DRAFT',
        }));
        form.put(
            PurchaseRequisitionController.update.url(purchaseRequisition.id),
            {
                ...submitOpts,
                onSuccess: () => {
                    router.post(
                        workflows.submit.url({
                            type: 'pr',
                            id: purchaseRequisition.id,
                        }),
                        {},
                        {
                            preserveScroll: true,
                            onSuccess: () => {
                                setSubmittingWorkflow(false);
                            },
                            onError: () => {
                                setSubmittingWorkflow(false);
                            },
                            onFinish: () => {
                                setSubmittingWorkflow(false);
                            },
                        },
                    );
                },
                onError: () => {
                    setSubmittingWorkflow(false);
                },
            },
        );
    };

    const rawTotal = form.data.line_items.reduce((sum, item) => {
        const qty = Number(item.quantity) || 0;
        const price = Number(item.price) || 0;

        return sum + qty * price;
    }, 0);

    const { netTotal, grossTotal, vatTotal } = priceBreakdown(
        rawTotal,
        form.data.price_type,
    );

    const selectedDepartmentNames = form.data.department_ids
        .map((id) => departments.find((d) => d.id === id)?.name)
        .filter(Boolean)
        .join(', ');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${purchaseRequisition.pr_number}`} />

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
                                    href={purchaseRequisitions.show.url(
                                        purchaseRequisition.id,
                                    )}
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                    <span className="sr-only">Back</span>
                                </Link>
                            </Button>
                            <div className="flex flex-col gap-1">
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    Edit Requisition:{' '}
                                    {purchaseRequisition.pr_number}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Update the details for this purchase
                                    requisition
                                </p>
                            </div>
                        </div>
                    }
                    main={
                        <>
                            <RequestDetailsCard
                                departments={departments}
                                departmentIds={form.data.department_ids}
                                onDepartmentIdsChange={(ids) =>
                                    form.setData('department_ids', ids)
                                }
                                deliveryDate={form.data.delivery_date}
                                onDeliveryDateChange={(date) =>
                                    form.setData('delivery_date', date)
                                }
                                title={form.data.title}
                                onTitleChange={(val) =>
                                    form.setData('title', val)
                                }
                                description={form.data.description}
                                onDescriptionChange={(val) =>
                                    form.setData('description', val)
                                }
                                priceType={form.data.price_type}
                                onPriceTypeChange={(val) =>
                                    form.setData('price_type', val)
                                }
                                priceTypes={priceTypes}
                                purposeType={form.data.purpose_type}
                                onPurposeTypeChange={(val) =>
                                    form.setData('purpose_type', val)
                                }
                                purposeTypes={purposeTypes}
                                expectedUsefulLife={
                                    form.data.expected_useful_life
                                }
                                onExpectedUsefulLifeChange={(val) =>
                                    form.setData('expected_useful_life', val)
                                }
                                toBeOrderedById={form.data.to_be_ordered_by_id}
                                onToBeOrderedByIdChange={(val) =>
                                    form.setData('to_be_ordered_by_id', val)
                                }
                                users={users}
                                errors={form.errors}
                            />

                            <LineItemsCard
                                lineItems={form.data.line_items}
                                onLineItemsChange={(items) =>
                                    form.setData('line_items', items)
                                }
                                itemUnits={itemUnits}
                                errors={form.errors}
                            />
                            <AdditionalNotesCard
                                notes={form.data.notes}
                                onNotesChange={(notes) =>
                                    form.setData('notes', notes)
                                }
                                errors={form.errors}
                            />
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
                                    form.setData('removed_attachment_ids', ids)
                                }
                                errors={form.errors}
                                disabled={form.processing || submittingWorkflow}
                            />
                        </>
                    }
                    sidebar={
                        <>
                            <SummaryCard
                                processing={
                                    form.processing || submittingWorkflow
                                }
                                itemCount={form.data.line_items.length}
                                departmentNames={selectedDepartmentNames || '—'}
                                deliveryDate={form.data.delivery_date || '—'}
                                totalAmount={grossTotal}
                                netAmount={netTotal}
                                vatAmount={vatTotal}
                                onAction={handleAction}
                            />

                            <ApprovalStatusPanel
                                approvalSummary={approvalSummary}
                            />
                        </>
                    }
                />
            </form>
        </AppLayout>
    );
}
