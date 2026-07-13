import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { FileText, Plus } from 'lucide-react';
import { useState } from 'react';

import { ApprovalStatusPanel } from '@/components/approval/approval-status-panel';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import type { DocumentAttachmentRecord } from '@/components/document-attachments-field';
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
import { formatPHP } from '@/lib/format-currency';
import { priceBreakdown } from '@/lib/price-type';
import * as purchaseOrders from '@/routes/purchase-orders';
import * as purchaseRequisitions from '@/routes/purchase-requisitions';
import * as receivingReports from '@/routes/receiving-reports';
import * as workflows from '@/routes/workflows';
import type {
    ApprovalSummary,
    BreadcrumbItem,
    PurchaseOrderItemModel,
    PurchaseOrderModel,
    ReceivingReportRow,
} from '@/types';

export default function PurchaseOrderShow({
    purchaseOrder,
    approvalSummary,
    canSubmit,
    canActOnCurrentStep,
    canOverride,
    canCreateReceivingReport = false,
    canUpdate = false,
    canDelete = false,
    canMarkAsOrdered = false,
    canCancel = false,
    canCancelWorkflow = false,
    attachments = [],
}: {
    purchaseOrder: PurchaseOrderModel;
    approvalSummary: ApprovalSummary | null;
    canSubmit: boolean;
    canActOnCurrentStep: boolean;
    canOverride: boolean;
    workflowInstanceId: number | null;
    canCreateReceivingReport?: boolean;
    canUpdate?: boolean;
    canDelete?: boolean;
    canMarkAsOrdered?: boolean;
    canCancel?: boolean;
    canCancelWorkflow?: boolean;
    attachments?: DocumentAttachmentRecord[];
}) {
    const [submitting, setSubmitting] = useState(false);
    const [deciding, setDeciding] = useState(false);
    const [decideComment, setDecideComment] = useState('');
    const [decideError, setDecideError] = useState<string | null>(null);
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [marking, setMarking] = useState(false);
    const [markOrderedDialogOpen, setMarkOrderedDialogOpen] = useState(false);
    const [cancelOrderDialogOpen, setCancelOrderDialogOpen] = useState(false);
    const [cancelWorkflowDialogOpen, setCancelWorkflowDialogOpen] =
        useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Purchase orders',
            href: purchaseOrders.index.url(),
        },
        {
            title: purchaseOrder.po_number,
            href: purchaseOrders.show.url(purchaseOrder.id),
        },
    ];

    const items = purchaseOrder.items ?? [];
    const rrs = purchaseOrder.receiving_reports ?? [];

    const rawTotal = items.reduce((sum, item) => {
        if (item.is_omitted) {
            return sum;
        }

        return sum + item.quantity * Number(item.price || 0);
    }, 0);

    const { netTotal, grossTotal, vatTotal } = priceBreakdown(
        rawTotal,
        purchaseOrder.price_type,
    );

    const formattedDeliveryDate = purchaseOrder.expected_delivery_date
        ? format(new Date(purchaseOrder.expected_delivery_date), 'PPP')
        : '—';

    const isDraft = purchaseOrder.status === 'DRAFT';
    const isPendingApproval = purchaseOrder.status === 'PENDING_APPROVAL';
    // Receiving requires the seal: only ordered (RELEASED) POs are receivable.
    const isReceivable = ['RELEASED', 'PARTIALLY_RECEIVED'].includes(
        purchaseOrder.status,
    );

    const handleMarkAsOrdered = () => {
        setMarking(true);
        router.post(
            purchaseOrders.markAsOrdered.url(purchaseOrder.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setMarking(false);
                    setMarkOrderedDialogOpen(false);
                },
            },
        );
    };

    const handleSubmitForApproval = () => {
        setSubmitting(true);
        router.post(
            workflows.submit.url({ type: 'po', id: purchaseOrder.id }),
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
            workflows.act.url({ type: 'po', id: purchaseOrder.id }),
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

    const currentApprovalStepName =
        approvalSummary?.steps.find(
            (s) => s.step_order === approvalSummary.current_step_order,
        )?.name ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={purchaseOrder.po_number} />

            <DocumentPageLayout
                header={
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                {purchaseOrder.po_number}
                                <span className="ml-2">
                                    <StatusBadge
                                        status={purchaseOrder.status}
                                    />
                                </span>
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Purchase Order
                            </h1>
                        </div>
                        <div className="flex shrink-0 gap-2">
                            <Button variant="outline" className="gap-2" asChild>
                                <a
                                    href={`${purchaseOrders.render.url(purchaseOrder.id)}?standalone=1`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <FileText className="size-4" aria-hidden />
                                    Standalone PDF
                                </a>
                            </Button>
                            <Button variant="outline" className="gap-2" asChild>
                                <a
                                    href={purchaseOrders.render.url(
                                        purchaseOrder.id,
                                    )}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <FileText className="size-4" aria-hidden />
                                    PDF with attachments
                                </a>
                            </Button>
                        </div>
                    </div>
                }
                main={
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Order Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Supplier
                                    </span>
                                    <span className="font-medium">
                                        {purchaseOrder.supplier?.name ?? '—'}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Purchase Requisition
                                    </span>
                                    {purchaseOrder.purchase_requisition ? (
                                        <Link
                                            href={purchaseRequisitions.show.url(
                                                purchaseOrder
                                                    .purchase_requisition.id,
                                            )}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {
                                                purchaseOrder
                                                    .purchase_requisition
                                                    .pr_number
                                            }
                                        </Link>
                                    ) : (
                                        <span className="font-medium">—</span>
                                    )}
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Expected Delivery Date
                                    </span>
                                    <span className="font-medium">
                                        {formattedDeliveryDate}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Payment Terms
                                    </span>
                                    <span className="font-medium">
                                        {purchaseOrder.payment_terms || '—'}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Currency
                                    </span>
                                    <span className="font-medium">
                                        {purchaseOrder.currency || 'PHP'}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Bill To
                                    </span>
                                    <span className="max-w-[60%] text-right font-medium">
                                        {purchaseOrder.bill_to_address
                                            ? `${purchaseOrder.bill_to_address.recipient_name} - ${purchaseOrder.bill_to_address.street}, ${purchaseOrder.bill_to_address.city}`
                                            : '—'}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Ship To
                                    </span>
                                    <span className="max-w-[60%] text-right font-medium">
                                        {purchaseOrder.ship_to_address
                                            ? `${purchaseOrder.ship_to_address.recipient_name} - ${purchaseOrder.ship_to_address.street}, ${purchaseOrder.ship_to_address.city}`
                                            : '—'}
                                    </span>
                                </div>
                                {purchaseOrder.terms_and_conditions && (
                                    <div className="pt-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Terms & Conditions
                                        </span>
                                        <p className="mt-1 text-sm whitespace-pre-wrap">
                                            {purchaseOrder.terms_and_conditions}
                                        </p>
                                    </div>
                                )}
                                {purchaseOrder.remarks && (
                                    <div className="pt-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Remarks
                                        </span>
                                        <p className="mt-1 text-sm whitespace-pre-wrap">
                                            {purchaseOrder.remarks}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Order Items
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-muted-foreground">
                                                <th className="pb-3 text-left font-medium">
                                                    Description
                                                </th>
                                                <th className="w-[80px] pb-3 text-left font-medium">
                                                    Qty
                                                </th>
                                                <th className="w-[80px] pb-3 text-left font-medium">
                                                    Unit
                                                </th>
                                                <th className="w-[100px] pb-3 text-right font-medium">
                                                    Price
                                                </th>
                                                <th className="w-[100px] pb-3 text-right font-medium">
                                                    Total
                                                </th>
                                                <th className="w-[90px] pb-3 text-right font-medium">
                                                    Received
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {items.map(
                                                (
                                                    item: PurchaseOrderItemModel,
                                                ) => {
                                                    const rowTotal =
                                                        item.quantity *
                                                        Number(item.price || 0);

                                                    return (
                                                        <tr
                                                            key={item.id}
                                                            className={
                                                                item.is_omitted
                                                                    ? 'border-b text-muted-foreground'
                                                                    : 'border-b'
                                                            }
                                                        >
                                                            <td className="py-3">
                                                                <span
                                                                    className={
                                                                        item.is_omitted
                                                                            ? 'line-through'
                                                                            : undefined
                                                                    }
                                                                >
                                                                    {item
                                                                        .line_item
                                                                        ?.name ??
                                                                        '—'}
                                                                </span>
                                                                {item.is_omitted && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="ml-2 border-amber-200 text-amber-700 dark:border-amber-800 dark:text-amber-400"
                                                                    >
                                                                        Omitted
                                                                    </Badge>
                                                                )}
                                                            </td>
                                                            <td className="py-3">
                                                                {item.quantity}
                                                            </td>
                                                            <td className="py-3">
                                                                {item.line_item
                                                                    ?.unit
                                                                    ?.code ??
                                                                    '—'}
                                                            </td>
                                                            <td className="py-3 text-right">
                                                                ₱
                                                                {Number(
                                                                    item.price,
                                                                ).toLocaleString(
                                                                    'en-PH',
                                                                    {
                                                                        minimumFractionDigits: 2,
                                                                    },
                                                                )}
                                                            </td>
                                                            <td className="py-3 text-right font-medium">
                                                                ₱
                                                                {rowTotal.toLocaleString(
                                                                    'en-PH',
                                                                    {
                                                                        minimumFractionDigits: 2,
                                                                    },
                                                                )}
                                                            </td>
                                                            <td className="py-3 text-right">
                                                                {item.quantity_received ??
                                                                    0}{' '}
                                                                /{' '}
                                                                {item.quantity}
                                                            </td>
                                                        </tr>
                                                    );
                                                },
                                            )}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="py-4 pr-4 text-right font-medium"
                                                >
                                                    Grand Total:
                                                </td>
                                                <td className="py-4 text-right text-base font-bold">
                                                    ₱
                                                    {grossTotal.toLocaleString(
                                                        'en-PH',
                                                        {
                                                            minimumFractionDigits: 2,
                                                        },
                                                    )}
                                                </td>
                                                <td />
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        <DocumentAttachmentsField
                            readOnly
                            existingAttachments={attachments}
                            attachments={[]}
                            onAttachmentsChange={() => {}}
                            removedAttachmentIds={[]}
                            onRemovedAttachmentIdsChange={() => {}}
                        />

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                <CardTitle className="text-lg">
                                    Receiving Reports
                                </CardTitle>
                                {isReceivable && canCreateReceivingReport && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-8 gap-1.5"
                                        asChild
                                    >
                                        <Link
                                            href={receivingReports.create.url({
                                                query: {
                                                    purchase_order_id:
                                                        purchaseOrder.id,
                                                },
                                            })}
                                        >
                                            <Plus className="h-3.5 w-3.5" />
                                            New RR
                                        </Link>
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {rrs.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        No receiving reports yet.
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-muted-foreground">
                                                    <th className="pb-3 text-left font-medium">
                                                        RR No.
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Date
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Status
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Received By
                                                    </th>
                                                    <th className="pb-3 text-right font-medium" />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {rrs.map(
                                                    (
                                                        rr: ReceivingReportRow,
                                                    ) => (
                                                        <tr
                                                            key={rr.id}
                                                            className="border-b"
                                                        >
                                                            <td className="py-3 font-medium">
                                                                {rr.rr_number}
                                                            </td>
                                                            <td className="py-3">
                                                                {rr.received_date
                                                                    ? format(
                                                                          new Date(
                                                                              rr.received_date,
                                                                          ),
                                                                          'PP',
                                                                      )
                                                                    : '—'}
                                                            </td>
                                                            <td className="py-3">
                                                                <StatusBadge
                                                                    status={
                                                                        rr.status
                                                                    }
                                                                />
                                                            </td>
                                                            <td className="py-3">
                                                                {rr.received_by
                                                                    ?.name ??
                                                                    '—'}
                                                            </td>
                                                            <td className="py-3 text-right">
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={receivingReports.show.url(
                                                                            rr.id,
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
                    </>
                }
                sidebar={
                    <>
                        <Card className="bg-card/50">
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-6">
                                <div className="grid gap-3">
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Status
                                        </span>
                                        <StatusBadge
                                            status={purchaseOrder.status}
                                        />
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Item Count
                                        </span>
                                        <span className="font-medium">
                                            {items.length}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Supplier
                                        </span>
                                        <span className="max-w-[60%] truncate text-right font-medium">
                                            {purchaseOrder.supplier?.name ??
                                                '—'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Expected Delivery
                                        </span>
                                        <span className="font-medium">
                                            {formattedDeliveryDate}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Net Amount
                                        </span>
                                        <span className="font-medium">
                                            {formatPHP(netTotal)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            VAT Amount
                                        </span>
                                        <span className="font-medium">
                                            {formatPHP(vatTotal)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between pt-2">
                                        <span className="font-semibold">
                                            Total Amount
                                        </span>
                                        <span className="text-lg font-bold">
                                            {formatPHP(grossTotal)}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3">
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
                                                        purchase order through
                                                        the approval workflow.
                                                        It can no longer be
                                                        edited as a draft.
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

                                    {/* Approve / Reject (PENDING_APPROVAL, assigned to current step only) */}
                                    {canActOnCurrentStep &&
                                        isPendingApproval && (
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    variant="success"
                                                    className="flex-1"
                                                    disabled={deciding}
                                                    onClick={() =>
                                                        setApproveDialogOpen(
                                                            true,
                                                        )
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
                                                        setRejectDialogOpen(
                                                            true,
                                                        )
                                                    }
                                                >
                                                    Reject
                                                </Button>
                                                <WorkflowActionDialog
                                                    action="approve"
                                                    documentLabel="order"
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
                                                    documentLabel="order"
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

                                    {isPendingApproval &&
                                        !canActOnCurrentStep &&
                                        approvalSummary &&
                                        approvalSummary.instance_status ===
                                            'PENDING' && (
                                            <p className="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-center text-xs text-muted-foreground">
                                                {currentApprovalStepName
                                                    ? `This order is awaiting approval at the ${currentApprovalStepName} step. You are not assigned to act on this step.`
                                                    : 'This order is awaiting approval. You are not assigned to act on the current step.'}
                                            </p>
                                        )}

                                    {/* Cancel mid approval flow (reason required) */}
                                    {canCancelWorkflow && isPendingApproval && (
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
                                                    type: 'po',
                                                    id: purchaseOrder.id,
                                                })}
                                                field="comment"
                                                title="Cancel approval?"
                                                description="This stops the approval workflow, cancels the purchase order, and returns its allocations to the requisition pool. A reason is required."
                                                confirmLabel="Cancel Approval"
                                            />
                                        </>
                                    )}

                                    {/* Seal an approved order (final PDF is bound) */}
                                    {canMarkAsOrdered && (
                                        <AlertDialog
                                            open={markOrderedDialogOpen}
                                            onOpenChange={
                                                setMarkOrderedDialogOpen
                                            }
                                        >
                                            <AlertDialogTrigger asChild>
                                                <Button
                                                    type="button"
                                                    className="w-full gap-2"
                                                    disabled={marking}
                                                >
                                                    Mark as Ordered
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>
                                                        Mark as Ordered?
                                                    </AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        This seals the purchase
                                                        order: its final PDF is
                                                        generated and bound, and
                                                        receiving reports can be
                                                        created against it.
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>
                                                        Cancel
                                                    </AlertDialogCancel>
                                                    <Button
                                                        onClick={
                                                            handleMarkAsOrdered
                                                        }
                                                        disabled={marking}
                                                    >
                                                        Confirm
                                                    </Button>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    )}

                                    {/* Cancel an approved order instead of sealing it */}
                                    {canCancel && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full text-destructive"
                                                onClick={() =>
                                                    setCancelOrderDialogOpen(
                                                        true,
                                                    )
                                                }
                                            >
                                                Cancel Order
                                            </Button>
                                            <ReasonActionDialog
                                                open={cancelOrderDialogOpen}
                                                onOpenChange={
                                                    setCancelOrderDialogOpen
                                                }
                                                url={purchaseOrders.cancel.url(
                                                    purchaseOrder.id,
                                                )}
                                                title="Cancel Purchase Order"
                                                description="This cancels the approved order and returns its allocated quantities to the requisition pool. A reason is required."
                                                confirmLabel="Cancel Order"
                                            />
                                        </>
                                    )}

                                    {isDraft && (
                                        <>
                                            {canUpdate && (
                                                <Button
                                                    variant="outline"
                                                    asChild
                                                    className="w-full"
                                                >
                                                    <Link
                                                        href={purchaseOrders.edit.url(
                                                            purchaseOrder.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            )}
                                            {canDelete && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        className="w-full"
                                                        onClick={() =>
                                                            setDeleteDialogOpen(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                    <ReasonActionDialog
                                                        open={deleteDialogOpen}
                                                        onOpenChange={
                                                            setDeleteDialogOpen
                                                        }
                                                        url={purchaseOrders.destroy.url(
                                                            purchaseOrder.id,
                                                        )}
                                                        method="delete"
                                                        title="Delete Purchase Order"
                                                        description="Deleting this draft returns its allocated quantities to the requisition pool. A reason is required."
                                                        confirmLabel="Confirm Delete"
                                                    />
                                                </>
                                            )}
                                        </>
                                    )}
                                    <Button
                                        variant="ghost"
                                        asChild
                                        className="mt-2 w-full"
                                    >
                                        <Link href={purchaseOrders.index.url()}>
                                            ← All purchase orders
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <ApprovalStatusPanel
                            approvalSummary={approvalSummary}
                        />
                    </>
                }
            />
        </AppLayout>
    );
}
