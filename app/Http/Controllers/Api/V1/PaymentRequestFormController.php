<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\PaymentRequestForm\IndexRequest;
use App\Http\Requests\Api\V1\PaymentRequestForm\StoreRequest;
use App\Http\Requests\Api\V1\PaymentRequestForm\UpdateRequest;
use App\Http\Resources\Api\V1\PaymentRequestFormResource;
use App\Models\PaymentRequestForm;
use App\Services\PaymentRequestFormService;
use Illuminate\Http\JsonResponse;

class PaymentRequestFormController extends ApiController
{
    public function __construct(private PaymentRequestFormService $service) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentRequestForm::class);

        $paginator = $this->service->list($request->validated());

        return response()->json(
            PaymentRequestFormResource::collection($paginator)->response()->getData(true)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->authorize('create', PaymentRequestForm::class);

        $prf = $this->service->create($request->validated(), $request->file('attachments'));

        return $this->created(new PaymentRequestFormResource($prf));
    }

    public function show(PaymentRequestForm $paymentRequestForm): JsonResponse
    {
        $this->authorize('view', $paymentRequestForm);

        $paymentRequestForm->loadMissing([
            'requestor',
            'supplier',
            'departments',
            'companyProfile',
            'workflowInstance.steps.assignments.user',
            'workflowInstance.actions.actor',
        ]);

        return $this->success(new PaymentRequestFormResource($paymentRequestForm));
    }

    public function update(UpdateRequest $request, PaymentRequestForm $paymentRequestForm): JsonResponse
    {
        $this->authorize('update', $paymentRequestForm);

        $prf = $this->service->update(
            $paymentRequestForm,
            $request->validated(),
            $request->file('attachments'),
            $request->input('removed_attachment_ids'),
        );

        return $this->success(new PaymentRequestFormResource($prf));
    }

    public function destroy(PaymentRequestForm $paymentRequestForm): JsonResponse
    {
        $this->authorize('delete', $paymentRequestForm);

        $this->service->delete($paymentRequestForm);

        return $this->noContent();
    }
}
