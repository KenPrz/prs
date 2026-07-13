<?php

namespace App\Http\Controllers;

use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\PurposeType;
use App\Http\Requests\PurchaseRequisition\IndexPurchaseRequisitionRequest;
use App\Http\Requests\PurchaseRequisition\StorePurchaseRequisitionRequest;
use App\Http\Requests\PurchaseRequisition\UpdatePurchaseRequisitionRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\ItemUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Services\PurchaseRequisitionService;
use App\Services\Workflow\WorkflowManager;
use App\Support\AttachmentRequestFiles;
use App\Support\AttachmentResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequisitionController extends Controller
{
    public function __construct(
        private PurchaseRequisitionService $purchaseRequisitionService,
        private WorkflowManager $workflow,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexPurchaseRequisitionRequest $request): Response
    {
        $this->authorize('viewAny', PurchaseRequisition::class);

        return Inertia::render('purchase-requisition/index', [
            'purchaseRequisitions' => $this->purchaseRequisitionService->list(
                $request->validated(),
            ),
            'canCreate' => $request->user()?->can('create', PurchaseRequisition::class) ?? false,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', PurchaseRequisition::class);

        return Inertia::render('purchase-requisition/create', [
            'departments' => Department::all(['id', 'name', 'code']),
            'itemUnits' => ItemUnit::all(['id', 'name', 'code']),
            'priceTypes' => array_column(PriceType::cases(), 'value'),
            ...$this->sharedFormOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePurchaseRequisitionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $data = Arr::except($validated, ['attachments', 'removed_attachment_ids']);
        $data['requestor_id'] = $request->user()->id;

        $requisition = $this->purchaseRequisitionService->create(
            $data,
            AttachmentRequestFiles::normalize($request),
        );

        return to_route('purchase-requisitions.show', $requisition);
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseRequisition $purchaseRequisition, Request $request): Response
    {
        $this->authorize('view', $purchaseRequisition);

        $user = $request->user();

        $purchaseRequisition->load([
            'requestor',
            'toBeOrderedBy',
            'departments',
            'lineItems',
            'notes',
            'purchaseOrders.supplier',
        ]);

        return Inertia::render('purchase-requisition/show', [
            'purchaseRequisition' => $purchaseRequisition,
            'attachments' => AttachmentResource::toArray($purchaseRequisition),
            'departments' => Department::all(['id', 'name', 'code']),
            'itemUnits' => ItemUnit::all(['id', 'name', 'code']),
            'priceTypes' => array_column(PriceType::cases(), 'value'),
            ...$this->sharedFormOptions(),
            'canUpdate' => ($user?->can('update', $purchaseRequisition) ?? false) && $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT,
            'canDelete' => ($user?->can('delete', $purchaseRequisition) ?? false) && $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT,
            'approvalSummary' => $this->workflow->summary($purchaseRequisition),
            'canSubmit' => ($user?->can('submit', $purchaseRequisition) ?? false) && $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT,
            'canActOnCurrentStep' => $this->workflow->canAct($purchaseRequisition, $user),
            // Status conditions repeated so the props stay honest for Super
            // Admins, whose Gate::before bypasses the policy checks.
            'canMarkReadyForPo' => ($user?->can('markReadyForPo', $purchaseRequisition) ?? false) && $purchaseRequisition->status === PurchaseRequisitionStatus::APPROVED,
            'canCancel' => ($user?->can('cancel', $purchaseRequisition) ?? false)
                && \in_array($purchaseRequisition->status, [PurchaseRequisitionStatus::APPROVED, PurchaseRequisitionStatus::READY_FOR_PO], true)
                && ! $purchaseRequisition->purchaseOrders()->active()->exists(),
            'canCancelWorkflow' => ($user?->can('cancelWorkflow', $purchaseRequisition) ?? false) && $purchaseRequisition->status === PurchaseRequisitionStatus::REVIEWING,
            'canCreatePurchaseOrder' => ($user?->can('create', PurchaseOrder::class) ?? false)
                && $this->canCreatePurchaseOrder($purchaseRequisition),
            'canPreviewPrDocument' => $purchaseRequisition->requisition_document_id !== null
                || Document::defaultPurchaseRequisitionTemplate() !== null,
            'canOverride' => ($user?->can('workflow.override') ?? false) && $purchaseRequisition->workflow_instance_id !== null,
            'workflowInstanceId' => $purchaseRequisition->workflow_instance_id,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchaseRequisition $purchaseRequisition, Request $request): Response
    {
        $this->authorize('update', $purchaseRequisition);
        abort_if(
            $purchaseRequisition->status !== PurchaseRequisitionStatus::DRAFT,
            403,
            'Only draft requisitions can be edited.'
        );

        $user = $request->user();

        $purchaseRequisition->load(['requestor', 'toBeOrderedBy', 'departments', 'lineItems', 'notes']);

        return Inertia::render('purchase-requisition/edit', [
            'purchaseRequisition' => $purchaseRequisition,
            'attachments' => AttachmentResource::toArray($purchaseRequisition),
            'departments' => Department::all(['id', 'name', 'code']),
            'itemUnits' => ItemUnit::all(['id', 'name', 'code']),
            'priceTypes' => array_column(PriceType::cases(), 'value'),
            ...$this->sharedFormOptions(),
            'statuses' => array_map(
                static fn (PurchaseRequisitionStatus $status) => $status->value,
                PurchaseRequisitionStatus::cases(),
            ),
            'approvalSummary' => $this->workflow->summary($purchaseRequisition),
            'canSubmit' => ($user?->can('submit', $purchaseRequisition) ?? false) && $purchaseRequisition->status === PurchaseRequisitionStatus::DRAFT,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdatePurchaseRequisitionRequest $request,
        PurchaseRequisition $purchaseRequisition,
    ): RedirectResponse {
        $this->authorize('update', $purchaseRequisition);
        abort_if(
            $purchaseRequisition->status !== PurchaseRequisitionStatus::DRAFT,
            403,
            'Only draft requisitions can be edited.'
        );

        $validated = $request->validated();
        $data = Arr::except($validated, ['attachments', 'removed_attachment_ids']);
        $data['requestor_id'] = $request->user()->id;

        $this->purchaseRequisitionService->update(
            $purchaseRequisition,
            $data,
            AttachmentRequestFiles::normalize($request),
            $request->input('removed_attachment_ids'),
        );

        return redirect()
            ->route('purchase-requisitions.show', $purchaseRequisition)
            ->with('success', 'Purchase requisition updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        $this->authorize('delete', $purchaseRequisition);
        abort_if(
            $purchaseRequisition->status !== PurchaseRequisitionStatus::DRAFT,
            403,
            'Only draft requisitions can be deleted.'
        );

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'A deletion reason is required.'],
        );

        $this->purchaseRequisitionService->delete($purchaseRequisition, $validated['reason']);

        return to_route('purchase-requisitions.index');
    }

    /**
     * Build a normalized, JSON-safe approval summary for the frontend panel.
     *
     * @return array{
     *     workflow_label: string,
     *     workflow_key: string,
     *     workflow_version: int,
     *     instance_status: string,
     *     current_step_order: int,
     *     steps: list<array{step_order: int, name: string, completion_strategy: string, status: string, assignments: list<array{user_id: int, user_name: string, status: string}>}>,
     *     decisions: list<array{actor_name: string, action: string, comment: string|null, acted_at: string|null}>,
     * }|null
     */
    /**
     * Option lists shared by the create/edit/show requisition forms.
     *
     * @return array{
     *     purposeTypes: list<array{value: string, label: string}>,
     *     accountingDetails: list<array{value: string, label: string}>,
     *     users: Collection<int, User>,
     * }
     */
    private function sharedFormOptions(): array
    {
        return [
            'purposeTypes' => array_map(
                static fn (PurposeType $type) => ['value' => $type->value, 'label' => $type->label()],
                PurposeType::cases(),
            ),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    private function canCreatePurchaseOrder(PurchaseRequisition $purchaseRequisition): bool
    {
        if (! \in_array($purchaseRequisition->status, [
            PurchaseRequisitionStatus::READY_FOR_PO,
            PurchaseRequisitionStatus::PARTIALLY_ORDERED,
            PurchaseRequisitionStatus::FULLY_ALLOCATED,
            PurchaseRequisitionStatus::FULLY_ORDERED,
        ], true)) {
            return false;
        }

        $purchaseRequisition->loadMissing('lineItems');

        return $purchaseRequisition->lineItems->contains(
            fn ($lineItem) => ! $lineItem->is_omitted && $lineItem->quantity_unallocated > 0,
        );
    }
}
