import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { FileText, Plus } from 'lucide-react';
import { useState } from 'react';
import { ApprovalStatusPanel } from '@/components/approval/approval-status-panel';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import type { DocumentAttachmentRecord } from '@/components/document-attachments-field';
import { AdditionalNotesCard } from '@/components/procurement/additional-notes-card';
import { DocumentPageLayout } from '@/components/procurement/document-page-layout';
import { ReasonActionDialog } from '@/components/procurement/reason-action-dialog';
import { StatusBadge } from '@/components/procurement/status-badge';
import { WorkflowActionDialog } from '@/components/procurement/workflow-action-dialog';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { priceBreakdown } from '@/lib/price-type';
import * as purchaseOrders from '@/routes/purchase-orders';
import * as purchaseRequisitions from '@/routes/purchase-requisitions';
import * as workflows from '@/routes/workflows';
import type {
    ApprovalSummary,
    BreadcrumbItem,
    PurchaseOrderRow,
} from '@/types';

import { LineItemsCard } from './partials/line-items-card';
import { RequestDetailsCard } from './partials/request-details-card';
import { SummaryCard } from './partials/summary-card';

type Requestor = {
    id: number;
    name: string;
};

type Department = { id: number; name: string; code: string };
type ItemUnit = { id: number; name: string; code: string };
type LabeledOption = { value: string; label: string };
type UserOption = { id: number; name: string; email?: string };

type PurchaseRequisitionModel = {
    id: number;
    pr_number: string;
    title: string;
    description: string | null;
    status: string;
    delivery_date: string | null;
    price_type: string;
    purpose_type: string | null;
    expected_useful_life: string | null;
    to_be_ordered_by_id: number | null;
    requestor?: Requestor | null;
    departments?: Department[];
    line_items?: {
        id: number;
        name: string;
        quantity: number;
        unit_id: number;
        price: string;
        is_omitted?: boolean;
        omit_reason?: string | null;
    }[];
    notes?: { id: number; content: string }[];
    purchase_orders?: PurchaseOrderRow[];
};

export default function PurchaseRequisitionShow({
    purchaseRequisition,
    departments,
    itemUnits,
    priceTypes,
    purposeTypes = [],
    users = [],
    canUpdate,
    canDelete,
    approvalSummary,
    canSubmit,
    canActOnCurrentStep,
    canMarkReadyForPo,
    canCancel = false,
    canCancelWorkflow = false,
    canCreatePurchaseOrder,
    canPreviewPrDocument,
    canOverride,
    attachments = [],
}: {
    purchaseRequisition: PurchaseRequisitionModel;
    departments: Department[];
    itemUnits: ItemUnit[];
    priceTypes: string[];
    purposeTypes?: LabeledOption[];
    users?: UserOption[];
    canUpdate: boolean;
    canDelete: boolean;
    approvalSummary: ApprovalSummary | null;
    canSubmit: boolean;
    canActOnCurrentStep: boolean;
    canMarkReadyForPo: boolean;
    canCancel?: boolean;
    canCancelWorkflow?: boolean;
    canCreatePurchaseOrder: boolean;
    canPreviewPrDocument: boolean;
    canOverride: boolean;
    workflowInstanceId: number | null;
    attachments?: DocumentAttachmentRecord[];
}) {
    const [submitting, setSubmitting] = useState(false);
    const [deciding, setDeciding] = useState(false);
    const [decideComment, setDecideComment] = useState('');
    const [decideError, setDecideError] = useState<string | null>(null);
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [cancelWorkflowDialogOpen, setCancelWorkflowDialogOpen] =
        useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Purchase requisitions',
            href: purchaseRequisitions.index.url(),
        },
        {
            title: purchaseRequisition.pr_number,
            href: purchaseRequisitions.show.url(purchaseRequisition.id),
        },
    ];

    const rawTotal = (purchaseRequisition.line_items || []).reduce(
        (sum, item) => {
            const qty = Number(item.quantity) || 0;
            const price = Number(item.price) || 0;

            return sum + qty * price;
        },
        0,
    );

    const { netTotal, grossTotal, vatTotal } = priceBreakdown(
        rawTotal,
        purchaseRequisition.price_type,
    );

    const formattedDeliveryDate = purchaseRequisition.delivery_date
        ? format(new Date(purchaseRequisition.delivery_date), 'PPP')
        : '—';

    const selectedDepartmentNames = purchaseRequisition.departments?.length
        ? purchaseRequisition.departments.map((d) => d.name).join(', ')
        : '—';

    const handleSubmitForApproval = () => {
        setSubmitting(true);
        router.post(
            workflows.submit.url({ type: 'pr', id: purchaseRequisition.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSubmitting(false);
                },
                onError: () => {
                    setSubmitting(false);
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    const handleDecide = (action: 'APPROVE' | 'REJECT') => {
        setDeciding(true);
        setDecideError(null);
        router.post(
            workflows.act.url({ type: 'pr', id: purchaseRequisition.id }),
            {
                action,
                comment: decideComment || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDeciding(false);
                    setDecideComment('');
                    setApproveDialogOpen(false);
                    setRejectDialogOpen(false);
                },
                onError: (errors) => {
                    setDeciding(false);

                    if (typeof errors === 'object' && errors !== null) {
                        const msg =
                            Object.values(errors).flat().join(', ') ||
                            'You are not authorized to perform this action.';
                        setDecideError(msg);
                    } else {
                        setDecideError(
                            'You are not authorized to perform this action.',
                        );
                    }
                },
                onFinish: () => {
                    setDeciding(false);
                },
            },
        );
    };

    const [markingReadyForPo, setMarkingReadyForPo] = useState(false);
    const [markReadyForPoDialogOpen, setMarkReadyForPoDialogOpen] =
        useState(false);

    const handleMarkReadyForPo = () => {
        setMarkingReadyForPo(true);
        router.post(
            `/purchase-requisitions/${purchaseRequisition.id}/mark-ready-for-po`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMarkingReadyForPo(false);
                    setMarkReadyForPoDialogOpen(false);
                },
                onError: () => {
                    setMarkingReadyForPo(false);
                },
                onFinish: () => {
                    setMarkingReadyForPo(false);
                },
            },
        );
    };

    const isDraft = purchaseRequisition.status === 'DRAFT';
    const isReviewing = purchaseRequisition.status === 'REVIEWING';
    const isApproved = purchaseRequisition.status === 'APPROVED';
    const hasOmittedLines = (purchaseRequisition.line_items || []).some(
        (item) => item.is_omitted,
    );

    const currentApprovalStepName =
        approvalSummary?.steps.find(
            (s) => s.step_order === approvalSummary.current_step_order,
        )?.name ?? null;

    const relatedPurchaseOrders = purchaseRequisition.purchase_orders ?? [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={purchaseRequisition.pr_number} />

            <DocumentPageLayout
                header={
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                {purchaseRequisition.pr_number}
                                {purchaseRequisition.status && (
                                    <span className="ml-2 inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground">
                                        {purchaseRequisition.status}
                                    </span>
                                )}
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {purchaseRequisition.title}
                            </h1>
                        </div>
                        {canPreviewPrDocument ? (
                            <div className="flex shrink-0 gap-2">
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    asChild
                                >
                                    <a
                                        href={`${purchaseRequisitions.render.url(purchaseRequisition.id)}?standalone=1`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <FileText
                                            className="size-4"
                                            aria-hidden
                                        />
                                        Standalone PDF
                                    </a>
                                </Button>
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    asChild
                                >
                                    <a
                                        href={purchaseRequisitions.render.url(
                                            purchaseRequisition.id,
                                        )}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <FileText
                                            className="size-4"
                                            aria-hidden
                                        />
                                        PDF with attachments
                                    </a>
                                </Button>
                            </div>
                        ) : null}
                    </div>
                }
                main={
                    <>
                        <RequestDetailsCard
                            departments={departments}
                            departmentIds={
                                purchaseRequisition.departments?.map(
                                    (d) => d.id,
                                ) || []
                            }
                            onDepartmentIdsChange={() => {}}
                            deliveryDate={
                                purchaseRequisition.delivery_date || ''
                            }
                            onDeliveryDateChange={() => {}}
                            title={purchaseRequisition.title}
                            onTitleChange={() => {}}
                            description={purchaseRequisition.description || ''}
                            onDescriptionChange={() => {}}
                            priceType={purchaseRequisition.price_type}
                            onPriceTypeChange={() => {}}
                            priceTypes={priceTypes}
                            purposeType={purchaseRequisition.purpose_type ?? ''}
                            onPurposeTypeChange={() => {}}
                            purposeTypes={purposeTypes}
                            expectedUsefulLife={
                                purchaseRequisition.expected_useful_life ?? ''
                            }
                            onExpectedUsefulLifeChange={() => {}}
                            toBeOrderedById={
                                purchaseRequisition.to_be_ordered_by_id ?? null
                            }
                            onToBeOrderedByIdChange={() => {}}
                            users={users}
                            errors={{}}
                            isReadOnly
                        />

                        <LineItemsCard
                            lineItems={(
                                purchaseRequisition.line_items || []
                            ).map((item) => ({
                                ...item,
                                price: item.price ? Number(item.price) : '',
                            }))}
                            onLineItemsChange={() => {}}
                            itemUnits={itemUnits}
                            errors={{}}
                            isReadOnly
                            rowMuted={
                                hasOmittedLines
                                    ? (i) =>
                                          !!purchaseRequisition.line_items?.[i]
                                              ?.is_omitted
                                    : undefined
                            }
                            renderRowAction={
                                hasOmittedLines
                                    ? (i) =>
                                          purchaseRequisition.line_items?.[i]
                                              ?.is_omitted ? (
                                              <Badge
                                                  variant="outline"
                                                  className="border-amber-200 text-amber-700 dark:border-amber-800 dark:text-amber-400"
                                              >
                                                  Omitted
                                              </Badge>
                                          ) : null
                                    : undefined
                            }
                        />

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                <CardTitle className="text-lg">
                                    Purchase Orders
                                </CardTitle>
                                {canCreatePurchaseOrder && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-8 gap-1.5"
                                        asChild
                                    >
                                        <Link
                                            href={purchaseOrders.create.url({
                                                query: {
                                                    purchase_requisition_id:
                                                        String(
                                                            purchaseRequisition.id,
                                                        ),
                                                },
                                            })}
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                            New PO
                                        </Link>
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {relatedPurchaseOrders.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        No purchase orders yet.
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-muted-foreground">
                                                    <th className="pb-3 text-left font-medium">
                                                        PO No.
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Supplier
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Status
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Created
                                                    </th>
                                                    <th className="pb-3 text-right font-medium" />
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border/30">
                                                {relatedPurchaseOrders.map(
                                                    (po) => (
                                                        <tr
                                                            key={po.id}
                                                            className="transition-colors hover:bg-accent/30"
                                                        >
                                                            <td className="py-3 align-middle font-medium">
                                                                {po.po_number}
                                                            </td>
                                                            <td className="px-4 py-3 align-middle">
                                                                {po.supplier
                                                                    ?.name ??
                                                                    '—'}
                                                            </td>
                                                            <td className="px-4 py-3 align-middle">
                                                                <StatusBadge
                                                                    status={
                                                                        po.status
                                                                    }
                                                                />
                                                            </td>
                                                            <td className="px-4 py-3 align-middle text-muted-foreground">
                                                                {po.created_at
                                                                    ? format(
                                                                          new Date(
                                                                              po.created_at,
                                                                          ),
                                                                          'PP',
                                                                      )
                                                                    : '—'}
                                                            </td>
                                                            <td className="px-4 py-3 text-right align-middle">
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={purchaseOrders.show.url(
                                                                            po.id,
                                                                        )}
                                                                    >
                                                                        View
                                                                    </Link>
                                                                </Button>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <AdditionalNotesCard
                            notes={
                                purchaseRequisition.notes?.length
                                    ? purchaseRequisition.notes
                                    : [{ content: '' }]
                            }
                            onNotesChange={() => {}}
                            errors={{}}
                            isReadOnly
                        />
                        <DocumentAttachmentsField
                            readOnly
                            existingAttachments={attachments}
                            attachments={[]}
                            onAttachmentsChange={() => {}}
                            removedAttachmentIds={[]}
                            onRemovedAttachmentIdsChange={() => {}}
                        />
                    </>
                }
                sidebar={
                    <>
                        <SummaryCard
                            processing={submitting || deciding}
                            itemCount={
                                purchaseRequisition.line_items?.length || 0
                            }
                            departmentNames={selectedDepartmentNames}
                            deliveryDate={formattedDeliveryDate}
                            totalAmount={grossTotal}
                            netAmount={netTotal}
                            vatAmount={vatTotal}
                            actions={
                                <>
                                    {/* Submit for Approval (DRAFT only) */}
                                    {canSubmit && isDraft && (
                                        <AlertDialog>
                                            <AlertDialogTrigger asChild>
                                                <Button
                                                    type="button"
                                                    className="w-full gap-2"
                                                    disabled={submitting}
                                                >
                                                    Submit for Approval
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>
                                                        Submit for Approval?
                                                    </AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        This will route the
                                                        requisition through the
                                                        approval workflow. It
                                                        can no longer be edited
                                                        as a draft.
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>
                                                        Cancel
                                                    </AlertDialogCancel>
                                                    <Button
                                                        onClick={
                                                            handleSubmitForApproval
                                                        }
                                                        disabled={submitting}
                                                    >
                                                        Confirm Submission
                                                    </Button>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    )}

                                    {/* Approve / Reject (REVIEWING, assigned to current step only) */}
                                    {canActOnCurrentStep && isReviewing && (
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="success"
                                                className="flex-1"
                                                disabled={deciding}
                                                onClick={() =>
                                                    setApproveDialogOpen(true)
                                                }
                                            >
                                                Approve
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                className="flex-1"
                                                disabled={deciding}
                                                onClick={() =>
                                                    setRejectDialogOpen(true)
                                                }
                                            >
                                                Reject
                                            </Button>
                                            <WorkflowActionDialog
                                                action="approve"
                                                documentLabel="requisition"
                                                open={approveDialogOpen}
                                                onOpenChange={
                                                    setApproveDialogOpen
                                                }
                                                processing={deciding}
                                                comment={decideComment}
                                                onCommentChange={
                                                    setDecideComment
                                                }
                                                onConfirm={() =>
                                                    handleDecide('APPROVE')
                                                }
                                                onCancel={() => {
                                                    setDecideComment('');
                                                    setDecideError(null);
                                                }}
                                                error={decideError}
                                                canOverride={canOverride}
                                            />
                                            <WorkflowActionDialog
                                                action="reject"
                                                documentLabel="requisition"
                                                open={rejectDialogOpen}
                                                onOpenChange={
                                                    setRejectDialogOpen
                                                }
                                                processing={deciding}
                                                comment={decideComment}
                                                onCommentChange={
                                                    setDecideComment
                                                }
                                                onConfirm={() =>
                                                    handleDecide('REJECT')
                                                }
                                                onCancel={() => {
                                                    setDecideComment('');
                                                    setDecideError(null);
                                                }}
                                                error={decideError}
                                                canOverride={canOverride}
                                            />
                                        </div>
                                    )}

                                    {/* Mark Ready for PO (APPROVED, designated orderer only) */}
                                    {canMarkReadyForPo && isApproved && (
                                        <AlertDialog
                                            open={markReadyForPoDialogOpen}
                                            onOpenChange={
                                                setMarkReadyForPoDialogOpen
                                            }
                                        >
                                            <AlertDialogTrigger asChild>
                                                <Button
                                                    type="button"
                                                    className="w-full gap-2"
                                                    disabled={markingReadyForPo}
                                                >
                                                    Mark Ready for PO
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>
                                                        Mark Ready for PO?
                                                    </AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        This finalizes the
                                                        requisition document,
                                                        attaches a PDF snapshot,
                                                        and unlocks purchase
                                                        order creation. The PR
                                                        can no longer be changed
                                                        afterwards.
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>
                                                        Cancel
                                                    </AlertDialogCancel>
                                                    <Button
                                                        onClick={
                                                            handleMarkReadyForPo
                                                        }
                                                        disabled={
                                                            markingReadyForPo
                                                        }
                                                    >
                                                        Confirm
                                                    </Button>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    )}

                                    {/* Cancel mid approval flow (reason required) */}
                                    {canCancelWorkflow && isReviewing && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full text-destructive"
                                                onClick={() =>
                                                    setCancelWorkflowDialogOpen(
                                                        true,
                                                    )
                                                }
                                            >
                                                Cancel Approval
                                            </Button>
                                            <ReasonActionDialog
                                                open={cancelWorkflowDialogOpen}
                                                onOpenChange={
                                                    setCancelWorkflowDialogOpen
                                                }
                                                url={workflows.cancel.url({
                                                    type: 'pr',
                                                    id: purchaseRequisition.id,
                                                })}
                                                field="comment"
                                                title="Cancel approval?"
                                                description="This stops the approval workflow and cancels the requisition. A reason is required."
                                                confirmLabel="Cancel Approval"
                                            />
                                        </>
                                    )}

                                    {/* Cancel an approved requisition with no active orders */}
                                    {canCancel && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full text-destructive"
                                                onClick={() =>
                                                    setCancelDialogOpen(true)
                                                }
                                            >
                                                Cancel Requisition
                                            </Button>
                                            <ReasonActionDialog
                                                open={cancelDialogOpen}
                                                onOpenChange={
                                                    setCancelDialogOpen
                                                }
                                                url={purchaseRequisitions.cancel.url(
                                                    purchaseRequisition.id,
                                                )}
                                                title="Cancel Requisition"
                                                description="This cancels the requisition; no purchase orders can be created from it afterwards. A reason is required."
                                                confirmLabel="Cancel Requisition"
                                            />
                                        </>
                                    )}

                                    {isReviewing &&
                                        !canActOnCurrentStep &&
                                        approvalSummary &&
                                        approvalSummary.instance_status ===
                                            'PENDING' && (
                                            <p className="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-center text-xs text-muted-foreground">
                                                {currentApprovalStepName
                                                    ? `This requisition is awaiting approval at the ${currentApprovalStepName} step. You are not assigned to act on this step.`
                                                    : 'This requisition is awaiting approval. You are not assigned to act on the current step.'}
                                            </p>
                                        )}

                                    {canUpdate ? (
                                        <Button
                                            variant="outline"
                                            asChild
                                            className="w-full"
                                        >
                                            <Link
                                                href={purchaseRequisitions.edit.url(
                                                    purchaseRequisition.id,
                                                )}
                                            >
                                                Edit
                                            </Link>
                                        </Button>
                                    ) : null}
                                    {canDelete ? (
                                        <>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                className="w-full"
                                                onClick={() =>
                                                    setDeleteDialogOpen(true)
                                                }
                                            >
                                                Delete
                                            </Button>
                                            <ReasonActionDialog
                                                open={deleteDialogOpen}
                                                onOpenChange={
                                                    setDeleteDialogOpen
                                                }
                                                url={purchaseRequisitions.destroy.url(
                                                    purchaseRequisition.id,
                                                )}
                                                method="delete"
                                                title="Delete Requisition"
                                                description="This permanently deletes the draft requisition. A reason is required."
                                                confirmLabel="Confirm Delete"
                                            />
                                        </>
                                    ) : null}

                                    {canCreatePurchaseOrder && (
                                        <Button
                                            variant="default"
                                            asChild
                                            className="w-full"
                                        >
                                            <Link
                                                href={purchaseOrders.create.url(
                                                    {
                                                        query: {
                                                            purchase_requisition_id:
                                                                String(
                                                                    purchaseRequisition.id,
                                                                ),
                                                        },
                                                    },
                                                )}
                                            >
                                                Create Purchase Order
                                            </Link>
                                        </Button>
                                    )}

                                    <Button
                                        variant="ghost"
                                        asChild
                                        className="mt-2 w-full"
                                    >
                                        <Link
                                            href={purchaseRequisitions.index.url()}
                                        >
                                            ← All requisitions
                                        </Link>
                                    </Button>
                                </>
                            }
                        />

                        <ApprovalStatusPanel
                            approvalSummary={approvalSummary}
                        />
                    </>
                }
            />
        </AppLayout>
    );
}
