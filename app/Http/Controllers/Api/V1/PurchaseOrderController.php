<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\PurchaseOrder\IndexRequest;
use App\Http\Requests\Api\V1\PurchaseOrder\StoreRequest;
use App\Http\Requests\Api\V1\PurchaseOrder\UpdateRequest;
use App\Http\Resources\Api\V1\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends ApiController
{
    public function __construct(private PurchaseOrderService $service) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $paginator = $this->service->list($request->validated());

        return response()->json(
            PurchaseOrderResource::collection($paginator)->response()->getData(true)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $pr = PurchaseRequisition::findOrFail($request->validated()['purchase_requisition_id']);
        $po = $this->service->create($pr, $request->validated(), $request->file('attachments'));

        return $this->created(new PurchaseOrderResource($po));
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->loadMissing([
            'purchaseRequisition',
            'supplier',
            'items.lineItem.unit',
            'billToAddress',
            'shipToAddress',
            'workflowInstance.steps.assignments.user',
            'workflowInstance.actions.actor',
        ]);

        return $this->success(new PurchaseOrderResource($purchaseOrder));
    }

    public function update(UpdateRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        $po = $this->service->update(
            $purchaseOrder,
            $request->validated(),
            $request->file('attachments'),
            $request->input('removed_attachment_ids'),
        );

        return $this->success(new PurchaseOrderResource($po));
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('delete', $purchaseOrder);

        $this->service->delete($purchaseOrder, request('reason'));

        return $this->noContent();
    }
}
