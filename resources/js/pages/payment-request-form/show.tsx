import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import { ApprovalStatusPanel } from '@/components/approval/approval-status-panel';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import type { DocumentAttachmentRecord } from '@/components/document-attachments-field';
import { DocumentPageLayout } from '@/components/procurement/document-page-layout';
import { ReasonActionDialog } from '@/components/procurement/reason-action-dialog';
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
import * as paymentRequestForms from '@/routes/payment-request-forms';
import * as workflows from '@/routes/workflows';
import type { ApprovalSummary, BreadcrumbItem } from '@/types';

type Requestor = {
    id: number;
    name: string;
};

type Supplier = {
    id: number;
    name: string;
};

type CompanyProfile = {
    id: number;
    name: string;
};

type Department = { id: number; name: string; code: string };

type PaymentRequestFormModel = {
    id: number;
    prf_number: string;
    description: string | null;
    amount: string;
    invoice_number: string | null;
    due_date: string | null;
    stamp_date: string | null;
    status: string;
    created_at: string;
    requestor?: Requestor | null;
    supplier?: Supplier | null;
    company_profile?: CompanyProfile | null;
    departments?: Department[];
    notes?: { id: number; content: string }[];
};

const formatCurrency = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(num || 0);
};

export default function PaymentRequestFormShow({
    paymentRequestForm,
    canUpdate,
    canDelete,
    approvalSummary,
    canSubmit,
    canActOnCurrentStep,
    canCancel = false,
    canCancelWorkflow = false,
    canPreviewDocument,
    canOverride,
    attachments = [],
}: {
    paymentRequestForm: PaymentRequestFormModel;
    departments: Department[];
    suppliers: Supplier[];
    companyProfiles: CompanyProfile[];
    canUpdate: boolean;
    canDelete: boolean;
    approvalSummary: ApprovalSummary | null;
    canSubmit: boolean;
    canActOnCurrentStep: boolean;
    canCancel?: boolean;
    canCancelWorkflow?: boolean;
    canPreviewDocument: boolean;
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
            title: 'Payment request forms',
            href: paymentRequestForms.index.url(),
        },
        {
            title: paymentRequestForm.prf_number,
            href: paymentRequestForms.show.url(paymentRequestForm.id),
        },
    ];

    const formattedDueDate = paymentRequestForm.due_date
        ? format(new Date(paymentRequestForm.due_date), 'PPP')
        : '—';

    const formattedStampDate = paymentRequestForm.stamp_date
        ? format(new Date(paymentRequestForm.stamp_date), 'PPP')
        : '—';

    const selectedDepartmentNames = paymentRequestForm.departments?.length
        ? paymentRequestForm.departments.map((d) => d.name).join(', ')
        : '—';

    const handleSubmitForApproval = () => {
        setSubmitting(true);
        router.post(
            workflows.submit.url({ type: 'prf', id: paymentRequestForm.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setSubmitting(false),
                onError: () => setSubmitting(false),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const handleDecide = (action: 'APPROVE' | 'REJECT') => {
        setDeciding(true);
        setDecideError(null);
        router.post(
            workflows.act.url({ type: 'prf', id: paymentRequestForm.id }),
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
                onFinish: () => setDeciding(false),
            },
        );
    };

    const isDraft = paymentRequestForm.status === 'DRAFT';
    const isReviewing = paymentRequestForm.status === 'REVIEWING';

    const currentApprovalStepName =
        approvalSummary?.steps.find(
            (s) => s.step_order === approvalSummary.current_step_order,
        )?.name ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={paymentRequestForm.prf_number} />

            <DocumentPageLayout
                header={
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                {paymentRequestForm.prf_number}
                                {paymentRequestForm.status && (
                                    <span className="ml-2 inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-foreground">
                                        {paymentRequestForm.status}
                                    </span>
                                )}
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Payment Request Form
                            </h1>
                        </div>
                        {canPreviewDocument ? (
                            <div className="flex shrink-0 gap-2">
                                <Button
                                    variant="outline"
                                    className="gap-2"
                                    asChild
                                >
                                    <a
                                        href={`${paymentRequestForms.render.url(paymentRequestForm.id)}?standalone=1`}
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
                                        href={paymentRequestForms.render.url(
                                            paymentRequestForm.id,
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
                        <Card>
                            <CardHeader>
                                <CardTitle>Payment Details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Company
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {paymentRequestForm.company_profile
                                                ?.name ?? '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Payee / Supplier
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {paymentRequestForm.supplier
                                                ?.name ?? '—'}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Department
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {selectedDepartmentNames}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Prepared by
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {paymentRequestForm.requestor
                                                ?.name ?? '—'}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Amount
                                        </p>
                                        <p className="mt-1 text-lg font-semibold">
                                            {formatCurrency(
                                                paymentRequestForm.amount,
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Invoice #
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {paymentRequestForm.invoice_number ??
                                                '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Date Created
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {paymentRequestForm.created_at
                                                ? format(
                                                      new Date(
                                                          paymentRequestForm.created_at,
                                                      ),
                                                      'PPP',
                                                  )
                                                : '—'}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Due Date
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {formattedDueDate}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Date Received
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {formattedStampDate}
                                        </p>
                                    </div>
                                </div>

                                {paymentRequestForm.description && (
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Description
                                        </p>
                                        <p className="mt-1 text-sm whitespace-pre-wrap">
                                            {paymentRequestForm.description}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {(paymentRequestForm.notes?.length ?? 0) > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Notes</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {paymentRequestForm.notes?.map((note) => (
                                        <div
                                            key={note.id}
                                            className="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm"
                                        >
                                            {note.content}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

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
                                <CardTitle>Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Department
                                    </span>
                                    <span className="font-medium">
                                        {selectedDepartmentNames}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Due Date
                                    </span>
                                    <span className="font-medium">
                                        {formattedDueDate}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Invoice #
                                    </span>
                                    <span className="font-medium">
                                        {paymentRequestForm.invoice_number ??
                                            '—'}
                                    </span>
                                </div>
                                <div className="border-t pt-3">
                                    <div className="flex items-center justify-between text-base font-semibold">
                                        <span>Amount</span>
                                        <span>
                                            {formatCurrency(
                                                paymentRequestForm.amount,
                                            )}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex flex-col gap-2 pt-2">
                                    {/* Submit for Approval */}
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
                                                        payment request form
                                                        through the approval
                                                        workflow. It can no
                                                        longer be edited as a
                                                        draft.
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

                                    {/* Approve / Reject */}
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
                                                documentLabel="payment request"
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
                                                documentLabel="payment request"
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

                                    {isReviewing &&
                                        !canActOnCurrentStep &&
                                        approvalSummary &&
                                        approvalSummary.instance_status ===
                                            'PENDING' && (
                                            <p className="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-center text-xs text-muted-foreground">
                                                {currentApprovalStepName
                                                    ? `This payment request is awaiting approval at the ${currentApprovalStepName} step. You are not assigned to act on this step.`
                                                    : 'This payment request is awaiting approval. You are not assigned to act on the current step.'}
                                            </p>
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
                                                    type: 'prf',
                                                    id: paymentRequestForm.id,
                                                })}
                                                field="comment"
                                                title="Cancel approval?"
                                                description="This stops the approval workflow and cancels the payment request. A reason is required."
                                                confirmLabel="Cancel Approval"
                                            />
                                        </>
                                    )}

                                    {/* Cancel an approved payment request (reason required) */}
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
                                                Cancel Payment Request
                                            </Button>
                                            <ReasonActionDialog
                                                open={cancelDialogOpen}
                                                onOpenChange={
                                                    setCancelDialogOpen
                                                }
                                                url={paymentRequestForms.cancel.url(
                                                    paymentRequestForm.id,
                                                )}
                                                title="Cancel Payment Request"
                                                description="This cancels the approved payment request before it is paid out. A reason is required."
                                                confirmLabel="Cancel Payment Request"
                                            />
                                        </>
                                    )}

                                    {canUpdate ? (
                                        <Button
                                            variant="outline"
                                            asChild
                                            className="w-full"
                                        >
                                            <Link
                                                href={paymentRequestForms.edit.url(
                                                    paymentRequestForm.id,
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
                                                url={paymentRequestForms.destroy.url(
                                                    paymentRequestForm.id,
                                                )}
                                                method="delete"
                                                title="Delete Payment Request"
                                                description="This permanently deletes the draft payment request form. A reason is required."
                                                confirmLabel="Confirm Delete"
                                            />
                                        </>
                                    ) : null}

                                    <Button
                                        variant="ghost"
                                        asChild
                                        className="mt-2 w-full"
                                    >
                                        <Link
                                            href={paymentRequestForms.index.url()}
                                        >
                                            ← All payment requests
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
