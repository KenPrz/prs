<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ReceivingReport\IndexRequest;
use App\Http\Requests\Api\V1\ReceivingReport\StoreRequest;
use App\Http\Resources\Api\V1\ReceivingReportResource;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Services\ReceivingReportService;
use Illuminate\Http\JsonResponse;

class ReceivingReportController extends ApiController
{
    public function __construct(private ReceivingReportService $service) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ReceivingReport::class);

        $paginator = $this->service->list($request->validated());

        return response()->json(
            ReceivingReportResource::collection($paginator)->response()->getData(true)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', ReceivingReport::class);

        $po = PurchaseOrder::findOrFail($request->validated()['purchase_order_id']);
        $rr = $this->service->create($po, $request->validated(), $request->file('attachments'));

        return $this->created(new ReceivingReportResource($rr));
    }

    public function show(ReceivingReport $receivingReport): JsonResponse
    {
        $this->authorize('view', $receivingReport);

        $receivingReport->loadMissing([
            'purchaseOrder.supplier',
            'receivedBy',
            'items.purchaseOrderItem.lineItem.unit',
            'workflowInstance.steps.assignments.user',
            'workflowInstance.actions.actor',
        ]);

        return $this->success(new ReceivingReportResource($receivingReport));
    }

    public function destroy(ReceivingReport $receivingReport): JsonResponse
    {
        $this->authorize('delete', $receivingReport);

        $this->service->delete($receivingReport, request('reason'));

        return $this->noContent();
    }
}
