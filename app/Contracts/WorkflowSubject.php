<?php

namespace App\Contracts;

use App\Enums\WorkflowInstanceStatus;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Implemented by every document that can flow through the workflow engine
 * (PurchaseRequisition, PurchaseOrder, ReceivingReport, PaymentRequestForm).
 *
 * This contract is what makes the engine document-agnostic: all per-document
 * behaviour (status mapping, dynamic participant resolution) lives on the model,
 * never inside the engine.
 */
interface WorkflowSubject
{
    /** Stable document-type key used to find the active workflow definition (e.g. 'PR'). */
    public function workflowDocumentType(): string;

    /** Default workflow key when none is supplied (e.g. 'pr.default'). */
    public function defaultWorkflowKey(): string;

    /**
     * Users who are the heads of this subject's tagged departments.
     *
     * @return Collection<int, User>
     */
    public function resolveDepartmentHeads(): Collection;

    /**
     * Users designated to receive/acknowledge this subject.
     *
     * @return Collection<int, User>
     */
    public function resolveReceivers(): Collection;

    /**
     * Validation errors that must block starting the given workflow (e.g. a
     * tagged department has no head for a Department-Head step). Empty = OK.
     *
     * @return array<int, string>
     */
    public function workflowStartErrors(WorkflowDefinition $definition): array;

    /** Apply the document's own "in review" status when its workflow starts. */
    public function applyWorkflowStarted(): void;

    /** Map a terminal workflow status onto the document's own status. */
    public function applyWorkflowStatus(WorkflowInstanceStatus $status): void;

    /** Persist the workflow pointers onto the document. */
    public function setWorkflowPointers(WorkflowDefinition $definition, WorkflowInstance $instance): void;

    /** @return BelongsTo<WorkflowInstance, covariant self> */
    public function workflowInstance(): BelongsTo;
}
