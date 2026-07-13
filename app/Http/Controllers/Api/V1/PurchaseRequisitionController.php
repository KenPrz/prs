<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\PurchaseRequisition\IndexRequest;
use App\Http\Requests\Api\V1\PurchaseRequisition\StoreRequest;
use App\Http\Requests\Api\V1\PurchaseRequisition\UpdateRequest;
use App\Http\Resources\Api\V1\PurchaseRequisitionResource;
use App\Models\PurchaseRequisition;
use App\Services\PurchaseRequisitionService;
use Illuminate\Http\JsonResponse;

class PurchaseRequisitionController extends ApiController
{
    public function __construct(private PurchaseRequisitionService $service) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequisition::class);

        $paginator = $this->service->list($request->validated());

        return response()->json(
            PurchaseRequisitionResource::collection($paginator)->response()->getData(true)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseRequisition::class);

        $pr = $this->service->create($request->validated(), $request->file('attachments'));

        return $this->created(new PurchaseRequisitionResource($pr));
    }

    public function show(PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        $this->authorize('view', $purchaseRequisition);

        $purchaseRequisition->loadMissing([
            'requestor',
            'departments',
            'lineItems.unit',
            'workflowInstance.steps.assignments.user',
            'workflowInstance.actions.actor',
        ]);

        return $this->success(new PurchaseRequisitionResource($purchaseRequisition));
    }

    public function update(UpdateRequest $request, PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        $this->authorize('update', $purchaseRequisition);

        $pr = $this->service->update(
            $purchaseRequisition,
            $request->validated(),
            $request->file('attachments'),
            $request->input('removed_attachment_ids'),
        );

        return $this->success(new PurchaseRequisitionResource($pr));
    }

    public function destroy(PurchaseRequisition $purchaseRequisition): JsonResponse
    {
        $this->authorize('delete', $purchaseRequisition);

        $this->service->delete($purchaseRequisition);

        return $this->noContent();
    }
}
