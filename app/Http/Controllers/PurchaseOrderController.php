<?php

namespace App\Http\Controllers;

use App\Enums\PriceType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Models\Address;
use App\Models\ItemUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Services\Workflow\WorkflowManager;
use App\Support\AttachmentRequestFiles;
use App\Support\AttachmentResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $purchaseOrderService,
        private WorkflowManager $workflow,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return Inertia::render('purchase-order/index', [
            'purchaseOrders' => $this->purchaseOrderService->list(
                request()->only(['search', 'status', 'per_page', 'purchase_requisition_id']),
            ),
            'canCreate' => request()->user()?->can('create', PurchaseOrder::class) ?? false,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        $purchaseRequisitionId = request()->query('purchase_requisition_id');

        return Inertia::render('purchase-order/create', [
            'purchaseRequisition' => $purchaseRequisitionId
                ? PurchaseRequisition::with('lineItems.unit')->findOrFail($purchaseRequisitionId)
                : null,
            'approvedRequisitions' => PurchaseRequisition::query()
                ->whereIn('status', [
                    PurchaseRequisitionStatus::READY_FOR_PO,
                    PurchaseRequisitionStatus::PARTIALLY_ORDERED,
                ])
                ->orderByDesc('created_at')
                ->get(['id', 'pr_number', 'title', 'status']),
            'suppliers' => Supplier::all(['id', 'name']),
            'itemUnits' => ItemUnit::all(['id', 'name', 'code']),
            'priceTypes' => array_column(PriceType::cases(), 'value'),
            'addresses' => Address::all(['id', 'recipient_name', 'street', 'city']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $data = Arr::except($validated, ['attachments', 'removed_attachment_ids']);
        $data['notes_user_id'] = $request->user()->id;
        $purchaseRequisition = PurchaseRequisition::findOrFail($data['purchase_requisition_id']);

        $purchaseOrder = $this->purchaseOrderService->create(
            $purchaseRequisition,
            $data,
            AttachmentRequestFiles::normalize($request),
        );

        return to_route('purchase-orders.show', $purchaseOrder);
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder, Request $request): Response
    {
        $this->authorize('view', $purchaseOrder);

        $user = $request->user();

        $purchaseOrder->load([
            'purchaseRequisition',
            'supplier',
            'items.lineItem.unit',
            'items.receivingReportItems',
            'receivingReports.receivedBy',
            'billToAddress',
            'shipToAddress',
        ]);

        return Inertia::render('purchase-order/show', [
            'purchaseOrder' => $purchaseOrder,
            'attachments' => AttachmentResource::toArray($purchaseOrder),
            'approvalSummary' => $this->workflow->summary($purchaseOrder),
            'canSubmit' => ($user?->can('submit', $purchaseOrder) ?? false) && $purchaseOrder->status === PurchaseOrderStatus::DRAFT,
            'canActOnCurrentStep' => $this->workflow->canAct($purchaseOrder, $user),
            'canOverride' => ($user?->can('workflow.override') ?? false) && $purchaseOrder->workflow_instance_id !== null,
            'workflowInstanceId' => $purchaseOrder->workflow_instance_id,
            // Status conditions are repeated here so the props stay honest for
            // Super Admins, whose Gate::before bypasses the policy checks.
            'canUpdate' => ($user?->can('update', $purchaseOrder) ?? false) && $purchaseOrder->status === PurchaseOrderStatus::DRAFT,
            'canDelete' => ($user?->can('delete', $purchaseOrder) ?? false) && $purchaseOrder->status === PurchaseOrderStatus::DRAFT,
            'canMarkAsOrdered' => ($user?->can('markAsOrdered', $purchaseOrder) ?? false) && $purchaseOrder->status === PurchaseOrderStatus::APPROVED,
            'canCancel' => ($user?->can('cancel', $purchaseOrder) ?? false) && $purchaseOrder->status === PurchaseOrderStatus::APPROVED,
            'canCancelWorkflow' => ($user?->can('cancelWorkflow', $purchaseOrder) ?? false) && $purchaseOrder->status === PurchaseOrderStatus::PENDING_APPROVAL,
            'canCreateReceivingReport' => ($user?->can('create', ReceivingReport::class) ?? false)
                && \in_array($purchaseOrder->status, [
                    PurchaseOrderStatus::RELEASED,
                    PurchaseOrderStatus::PARTIALLY_RECEIVED,
                ], true),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'items.lineItem', 'notes.user']);
        $purchaseRequisition = $purchaseOrder->purchaseRequisition->load('lineItems.unit');

        return Inertia::render('purchase-order/edit', [
            'purchaseOrder' => $purchaseOrder,
            'purchaseRequisition' => $purchaseRequisition,
            'attachments' => AttachmentResource::toArray($purchaseOrder),
            'suppliers' => Supplier::all(['id', 'name']),
            'itemUnits' => ItemUnit::all(['id', 'name', 'code']),
            'priceTypes' => array_column(PriceType::cases(), 'value'),
            'addresses' => Address::all(['id', 'recipient_name', 'street', 'city']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validated();
        $data = Arr::except($validated, ['attachments', 'removed_attachment_ids']);
        $data['notes_user_id'] = $request->user()->id;

        $this->purchaseOrderService->update(
            $purchaseOrder,
            $data,
            AttachmentRequestFiles::normalize($request),
            $request->input('removed_attachment_ids'),
        );

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'purchase order updated successfully.');
    }

    /**
     * Remove the specified resource from storage, returning its allocated
     * quantities to the requisition pool.
     */
    public function destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'A deletion reason is required.'],
        );

        $this->purchaseOrderService->delete($purchaseOrder, $validated['reason']);

        return to_route('purchase-orders.index');
    }
}
