import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { FileText, Pencil } from 'lucide-react';
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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import * as purchaseOrders from '@/routes/purchase-orders';
import * as receivingReports from '@/routes/receiving-reports';
import * as workflows from '@/routes/workflows';
import type {
    ApprovalSummary,
    BreadcrumbItem,
    ReceivingReportItemModel,
    ReceivingReportModel,
} from '@/types';

export default function ReceivingReportShow({
    receivingReport,
    approvalSummary,
    canSubmit,
    canActOnCurrentStep,
    canOverride,
    workflowInstanceId,
    canPreviewRrDocument,
    canUpdate = false,
    canDelete = false,
    canCancelWorkflow = false,
    attachments = [],
}: {
    receivingReport: ReceivingReportModel;
    approvalSummary: ApprovalSummary | null;
    canSubmit: boolean;
    canActOnCurrentStep: boolean;
    canOverride: boolean;
    workflowInstanceId: number | null;
    canPreviewRrDocument: boolean;
    canUpdate?: boolean;
    canDelete?: boolean;
    canCancelWorkflow?: boolean;
    attachments?: DocumentAttachmentRecord[];
}) {
    const [submitting, setSubmitting] = useState(false);
    const [deciding, setDeciding] = useState(false);
    const [decideComment, setDecideComment] = useState('');
    const [decideError, setDecideError] = useState<string | null>(null);
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [submitDialogOpen, setSubmitDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [cancelWorkflowDialogOpen, setCancelWorkflowDialogOpen] =
        useState(false);
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Receiving reports',
            href: receivingReports.index.url(),
        },
        {
            title: receivingReport.rr_number,
            href: receivingReports.show.url(receivingReport.id),
        },
    ];

    const items = receivingReport.items ?? [];
    const po = receivingReport.purchase_order;

    const totalReceived = items.reduce(
        (sum, item) => sum + item.quantity_received,
        0,
    );
    const totalRejected = items.reduce(
        (sum, item) => sum + item.quantity_rejected,
        0,
    );

    const isPending = receivingReport.status === 'PENDING';

    const handleSubmitForVerification = () => {
        setSubmitting(true);
        router.post(
            workflows.submit.url({ type: 'rr', id: receivingReport.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSubmitting(false);
                    setSubmitDialogOpen(false);
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
            workflows.act.url({ type: 'rr', id: receivingReport.id }),
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
            <Head title={receivingReport.rr_number} />

            <DocumentPageLayout
                header={
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                {receivingReport.rr_number}
                                <span className="ml-2">
                                    <StatusBadge
                                        status={receivingReport.status}
                                    />
                                </span>
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Receiving Report
                            </h1>
                        </div>

                        <div className="flex shrink-0 gap-2">
                            {canUpdate && (
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    asChild
                                >
                                    <Link
                                        href={receivingReports.edit.url(
                                            receivingReport.id,
                                        )}
                                    >
                                        <Pencil
                                            className="size-4"
                                            aria-hidden
                                        />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                            {canPreviewRrDocument && (
                                <>
                                    <Button
                                        variant="outline"
                                        className="gap-2"
                                        asChild
                                    >
                                        <a
                                            href={`${receivingReports.render.url(receivingReport.id)}?standalone=1`}
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
                                            href={receivingReports.render.url(
                                                receivingReport.id,
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
                                </>
                            )}
                        </div>
                    </div>
                }
                main={
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Report Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Received Date
                                    </span>
                                    <span className="font-medium">
                                        {receivingReport.received_date
                                            ? format(
                                                  new Date(
                                                      receivingReport.received_date,
                                                  ),
                                                  'PPP',
                                              )
                                            : '—'}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Purchase Order
                                    </span>
                                    {po ? (
                                        <Link
                                            href={purchaseOrders.show.url(
                                                po.id,
                                            )}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {po.po_number}
                                        </Link>
                                    ) : (
                                        <span className="font-medium">—</span>
                                    )}
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Supplier
                                    </span>
                                    <span className="font-medium">
                                        {po?.supplier?.name ?? '—'}
                                    </span>
                                </div>
                                <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                    <span className="text-muted-foreground">
                                        Received By
                                    </span>
                                    <span className="font-medium">
                                        {receivingReport.received_by?.name ??
                                            '—'}
                                    </span>
                                </div>
                                {receivingReport.remarks && (
                                    <div className="pt-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Remarks
                                        </span>
                                        <p className="mt-1 text-sm whitespace-pre-wrap">
                                            {receivingReport.remarks}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Received Items
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
                                                <th className="w-[80px] pb-3 text-center font-medium">
                                                    PO Qty
                                                </th>
                                                <th className="w-[90px] pb-3 text-center font-medium">
                                                    Received
                                                </th>
                                                <th className="w-[90px] pb-3 text-center font-medium">
                                                    Rejected
                                                </th>
                                                <th className="pb-3 text-left font-medium">
                                                    Remarks
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {items.map(
                                                (
                                                    item: ReceivingReportItemModel,
                                                ) => (
                                                    <tr
                                                        key={item.id}
                                                        className="border-b"
                                                    >
                                                        <td className="py-3">
                                                            {item
                                                                .purchase_order_item
                                                                ?.line_item
                                                                ?.name ?? '—'}
                                                        </td>
                                                        <td className="py-3 text-center">
                                                            {item
                                                                .purchase_order_item
                                                                ?.quantity ?? 0}
                                                        </td>
                                                        <td className="py-3 text-center font-medium text-emerald-600">
                                                            {
                                                                item.quantity_received
                                                            }
                                                        </td>
                                                        <td className="py-3 text-center font-medium text-destructive">
                                                            {
                                                                item.quantity_rejected
                                                            }
                                                        </td>
                                                        <td className="py-3 text-muted-foreground">
                                                            {item.remarks ??
                                                                '—'}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
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
                                            status={receivingReport.status}
                                        />
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Items Received
                                        </span>
                                        <span className="font-medium">
                                            {items.length}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Total Qty Received
                                        </span>
                                        <span className="font-medium text-emerald-600">
                                            {totalReceived}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Total Qty Rejected
                                        </span>
                                        <span className="font-medium text-destructive">
                                            {totalRejected}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Received By
                                        </span>
                                        <span className="font-medium">
                                            {receivingReport.received_by
                                                ?.name ?? '—'}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3">
                                    {/* Submit for Verification (drafts only; gated by policy) */}
                                    {canSubmit && (
                                        <AlertDialog
                                            open={submitDialogOpen}
                                            onOpenChange={setSubmitDialogOpen}
                                        >
                                            <AlertDialogTrigger asChild>
                                                <Button
                                                    type="button"
                                                    className="w-full gap-2"
                                                    disabled={submitting}
                                                >
                                                    Submit for Verification
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>
                                                        Submit for Verification?
                                                    </AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        This will route the
                                                        receiving report through
                                                        the verification
                                                        workflow. It can no
                                                        longer be deleted as a
                                                        draft.
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>
                                                        Cancel
                                                    </AlertDialogCancel>
                                                    <Button
                                                        onClick={
                                                            handleSubmitForVerification
                                                        }
                                                        disabled={submitting}
                                                    >
                                                        Confirm Submission
                                                    </Button>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    )}

                                    {/* Verify / Reject (assigned to current step only) */}
                                    {canActOnCurrentStep &&
                                        isPending &&
                                        workflowInstanceId && (
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
                                                    Verify
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
                                                    documentLabel="report"
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
                                                    confirmLabel="Confirm Verification"
                                                    title={
                                                        canOverride
                                                            ? 'Override verification?'
                                                            : 'Verify this report?'
                                                    }
                                                    description={
                                                        canOverride
                                                            ? 'You are overriding this verification step as a Super Admin.'
                                                            : 'You are verifying this receiving report for the current workflow step.'
                                                    }
                                                />
                                                <WorkflowActionDialog
                                                    action="reject"
                                                    documentLabel="report"
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
                                                    description={
                                                        canOverride
                                                            ? 'You are overriding this step to reject the report as a Super Admin.'
                                                            : 'This will end the verification workflow and mark the receiving report as rejected.'
                                                    }
                                                />
                                            </div>
                                        )}

                                    {workflowInstanceId &&
                                        !canActOnCurrentStep &&
                                        approvalSummary &&
                                        approvalSummary.instance_status ===
                                            'PENDING' && (
                                            <p className="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-center text-xs text-muted-foreground">
                                                {currentApprovalStepName
                                                    ? `This report is awaiting verification at the ${currentApprovalStepName} step. You are not assigned to act on this step.`
                                                    : 'This report is awaiting verification. You are not assigned to act on the current step.'}
                                            </p>
                                        )}

                                    {canCancelWorkflow &&
                                        workflowInstanceId &&
                                        approvalSummary?.instance_status ===
                                            'PENDING' && (
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
                                                    Cancel Verification
                                                </Button>
                                                <ReasonActionDialog
                                                    open={
                                                        cancelWorkflowDialogOpen
                                                    }
                                                    onOpenChange={
                                                        setCancelWorkflowDialogOpen
                                                    }
                                                    url={workflows.cancel.url({
                                                        type: 'rr',
                                                        id: receivingReport.id,
                                                    })}
                                                    field="comment"
                                                    title="Cancel verification?"
                                                    description="This stops the verification workflow and returns the recorded quantities to the pool. A reason is required."
                                                    confirmLabel="Cancel Verification"
                                                />
                                            </>
                                        )}

                                    {canDelete && (
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
                                                url={receivingReports.destroy.url(
                                                    receivingReport.id,
                                                )}
                                                method="delete"
                                                title="Delete Receiving Report"
                                                description="Deleting this receiving report reverses its received quantities and returns them to the pool. A reason is required."
                                                confirmLabel="Confirm Delete"
                                            />
                                        </>
                                    )}
                                    <Button
                                        variant="ghost"
                                        asChild
                                        className="mt-2 w-full"
                                    >
                                        <Link
                                            href={receivingReports.index.url()}
                                        >
                                            ← All receiving reports
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
