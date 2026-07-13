<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceivingReportStatus;
use App\Http\Requests\ReceivingReport\StoreReceivingReportRequest;
use App\Http\Requests\ReceivingReport\UpdateReceivingReportRequest;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Services\ReceivingReportService;
use App\Services\Workflow\WorkflowManager;
use App\Support\AttachmentRequestFiles;
use App\Support\AttachmentResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ReceivingReportController extends Controller
{
    public function __construct(
        private ReceivingReportService $receivingReportService,
        private WorkflowManager $workflow,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', ReceivingReport::class);

        return Inertia::render('receiving-report/index', [
            'receivingReports' => $this->receivingReportService->list(
                request()->only(['search', 'status', 'per_page', 'purchase_order_id']),
            ),
        ]);
    }

    /**
     * Show the form for creating a new resource. Without a purchase order in the
     * query string, offers the list of ordered POs to pick from.
     */
    public function create(): Response
    {
        $this->authorize('create', ReceivingReport::class);

        $purchaseOrderId = request()->query('purchase_order_id');

        return Inertia::render('receiving-report/create', [
            'purchaseOrder' => $purchaseOrderId
                ? PurchaseOrder::with(['items.lineItem.unit', 'supplier'])->findOrFail($purchaseOrderId)
                : null,
            'orderedPurchaseOrders' => PurchaseOrder::query()
                ->whereIn('status', [
                    PurchaseOrderStatus::RELEASED,
                    PurchaseOrderStatus::PARTIALLY_RECEIVED,
                ])
                ->with('supplier:id,name')
                ->orderByDesc('created_at')
                ->get(['id', 'po_number', 'status', 'supplier_id']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReceivingReportRequest $request): RedirectResponse
    {
        $this->authorize('create', ReceivingReport::class);

        $validated = $request->validated();
        $data = Arr::except($validated, ['attachments', 'removed_attachment_ids']);
        $data['received_by_id'] = $request->user()->id;

        $purchaseOrder = PurchaseOrder::findOrFail($data['purchase_order_id']);

        $receivingReport = $this->receivingReportService->create(
            $purchaseOrder,
            $data,
            AttachmentRequestFiles::normalize($request),
        );

        return to_route('receiving-reports.show', $receivingReport);
    }

    /**
     * Display the specified resource.
     */
    public function show(ReceivingReport $receivingReport, Request $request): Response
    {
        $this->authorize('view', $receivingReport);

        $user = $request->user();

        $receivingReport->load([
            'purchaseOrder.supplier',
            'purchaseOrder.purchaseRequisition',
            'receivedBy',
            'items.purchaseOrderItem.lineItem',
            'notes.user',
        ]);

        return Inertia::render('receiving-report/show', [
            'receivingReport' => $receivingReport,
            'attachments' => AttachmentResource::toArray($receivingReport),
            'approvalSummary' => $this->workflow->summary($receivingReport),
            // Status conditions repeated so the props stay honest for Super
            // Admins, whose Gate::before bypasses the policy checks.
            'canSubmit' => ($user?->can('submit', $receivingReport) ?? false) && $receivingReport->status === ReceivingReportStatus::DRAFT,
            'canActOnCurrentStep' => $this->workflow->canAct($receivingReport, $user),
            'canOverride' => ($user?->can('workflow.override') ?? false) && $receivingReport->workflow_instance_id !== null,
            'workflowInstanceId' => $receivingReport->workflow_instance_id,
            'canPreviewRrDocument' => Document::defaultReceivingReportTemplate() !== null,
            'canUpdate' => ($user?->can('update', $receivingReport) ?? false) && $receivingReport->status === ReceivingReportStatus::DRAFT,
            'canDelete' => ($user?->can('delete', $receivingReport) ?? false) && $receivingReport->status === ReceivingReportStatus::DRAFT,
            'canCancelWorkflow' => ($user?->can('cancelWorkflow', $receivingReport) ?? false) && $receivingReport->status === ReceivingReportStatus::PENDING,
        ]);
    }

    /**
     * Show the form for editing a draft receiving report.
     */
    public function edit(ReceivingReport $receivingReport): Response
    {
        $this->authorize('update', $receivingReport);

        $receivingReport->load(['items.purchaseOrderItem', 'notes.user', 'receivedBy']);
        $purchaseOrder = $receivingReport->purchaseOrder->load(['items.lineItem.unit', 'supplier']);

        return Inertia::render('receiving-report/edit', [
            'receivingReport' => $receivingReport,
            'purchaseOrder' => $purchaseOrder,
            'attachments' => AttachmentResource::toArray($receivingReport),
        ]);
    }

    /**
     * Update a draft receiving report in storage.
     */
    public function update(UpdateReceivingReportRequest $request, ReceivingReport $receivingReport): RedirectResponse
    {
        $validated = $request->validated();
        $data = Arr::except($validated, ['attachments', 'removed_attachment_ids']);

        $this->receivingReportService->update(
            $receivingReport,
            $data,
            AttachmentRequestFiles::normalize($request),
            $request->input('removed_attachment_ids'),
        );

        return redirect()
            ->route('receiving-reports.show', $receivingReport)
            ->with('success', 'Receiving report updated successfully.');
    }

    /**
     * Remove a draft receiving report, returning its quantities to the pool.
     */
    public function destroy(Request $request, ReceivingReport $receivingReport): RedirectResponse
    {
        $this->authorize('delete', $receivingReport);

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'A deletion reason is required.'],
        );

        $this->receivingReportService->delete($receivingReport, $validated['reason']);

        return to_route('receiving-reports.index');
    }
}
