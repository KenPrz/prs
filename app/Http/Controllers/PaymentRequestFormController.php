<?php

namespace App\Http\Controllers;

use App\Enums\PaymentRequestFormStatus;
use App\Http\Requests\PaymentRequestForm\IndexPaymentRequestFormRequest;
use App\Http\Requests\PaymentRequestForm\StorePaymentRequestFormRequest;
use App\Http\Requests\PaymentRequestForm\UpdatePaymentRequestFormRequest;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\Document;
use App\Models\PaymentRequestForm;
use App\Models\Supplier;
use App\Services\PaymentRequestFormService;
use App\Services\Workflow\WorkflowManager;
use App\Support\AttachmentRequestFiles;
use App\Support\AttachmentResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class PaymentRequestFormController extends Controller
{
    public function __construct(
        private PaymentRequestFormService $paymentRequestFormService,
        private WorkflowManager $workflow,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexPaymentRequestFormRequest $request): Response
    {
        $this->authorize('viewAny', PaymentRequestForm::class);

        return Inertia::render('payment-request-form/index', [
            'paymentRequestForms' => $this->paymentRequestFormService->list(
                $request->validated(),
            ),
            'canCreate' => $request->user()?->can('create', PaymentRequestForm::class) ?? false,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', PaymentRequestForm::class);

        return Inertia::render('payment-request-form/create', [
            'departments' => Department::all(['id', 'name', 'code']),
            'suppliers' => Supplier::all(['id', 'name']),
            'companyProfiles' => CompanyProfile::all(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentRequestFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $data = Arr::except($validated, ['attachments', 'removed_attachment_ids']);
        $data['requestor_id'] = $request->user()->id;

        $prf = $this->paymentRequestFormService->create(
            $data,
            AttachmentRequestFiles::normalize($request),
        );

        return to_route('payment-request-forms.show', $prf);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentRequestForm $paymentRequestForm, Request $request): Response
    {
        $this->authorize('view', $paymentRequestForm);

        $user = $request->user();

        $paymentRequestForm->load([
            'requestor',
            'supplier',
            'companyProfile',
            'departments',
            'notes',
        ]);

        return Inertia::render('payment-request-form/show', [
            'paymentRequestForm' => $paymentRequestForm,
            'attachments' => AttachmentResource::toArray($paymentRequestForm),
            'departments' => Department::all(['id', 'name', 'code']),
            'suppliers' => Supplier::all(['id', 'name']),
            'companyProfiles' => CompanyProfile::all(['id', 'name']),
            'canUpdate' => ($user?->can('update', $paymentRequestForm) ?? false) && $paymentRequestForm->status === PaymentRequestFormStatus::DRAFT,
            'canDelete' => ($user?->can('delete', $paymentRequestForm) ?? false) && $paymentRequestForm->status === PaymentRequestFormStatus::DRAFT,
            'approvalSummary' => $this->workflow->summary($paymentRequestForm),
            'canSubmit' => ($user?->can('submit', $paymentRequestForm) ?? false) && $paymentRequestForm->status === PaymentRequestFormStatus::DRAFT,
            'canActOnCurrentStep' => $this->workflow->canAct($paymentRequestForm, $user),
            // Status conditions repeated so the props stay honest for Super
            // Admins, whose Gate::before bypasses the policy checks.
            'canCancel' => ($user?->can('cancel', $paymentRequestForm) ?? false) && $paymentRequestForm->status === PaymentRequestFormStatus::APPROVED,
            'canCancelWorkflow' => ($user?->can('cancelWorkflow', $paymentRequestForm) ?? false) && $paymentRequestForm->status === PaymentRequestFormStatus::REVIEWING,
            'canPreviewDocument' => $paymentRequestForm->document_id !== null
                || Document::defaultPaymentRequestFormTemplate() !== null,
            'canOverride' => ($user?->can('workflow.override') ?? false) && $paymentRequestForm->workflow_instance_id !== null,
            'workflowInstanceId' => $paymentRequestForm->workflow_instance_id,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentRequestForm $paymentRequestForm, Request $request): Response
    {
        $this->authorize('update', $paymentRequestForm);
        abort_if(
            $paymentRequestForm->status !== PaymentRequestFormStatus::DRAFT,
            403,
            'Only draft payment request forms can be edited.'
        );

        $user = $request->user();

        $paymentRequestForm->load(['requestor', 'supplier', 'companyProfile', 'departments', 'notes']);

        return Inertia::render('payment-request-form/edit', [
            'paymentRequestForm' => $paymentRequestForm,
            'attachments' => AttachmentResource::toArray($paymentRequestForm),
            'departments' => Department::all(['id', 'name', 'code']),
            'suppliers' => Supplier::all(['id', 'name']),
            'companyProfiles' => CompanyProfile::all(['id', 'name']),
            'statuses' => array_map(
                static fn (PaymentRequestFormStatus $status) => $status->value,
                PaymentRequestFormStatus::cases(),
            ),
            'approvalSummary' => $this->workflow->summary($paymentRequestForm),
            'canSubmit' => ($user?->can('submit', $paymentRequestForm) ?? false) && $paymentRequestForm->status === PaymentRequestFormStatus::DRAFT,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdatePaymentRequestFormRequest $request,
        PaymentRequestForm $paymentRequestForm,
    ): RedirectResponse {
        $this->authorize('update', $paymentRequestForm);
        abort_if(
            $paymentRequestForm->status !== PaymentRequestFormStatus::DRAFT,
            403,
            'Only draft payment request forms can be edited.'
        );

        $validated = $request->validated();
        $data = Arr::except($validated, ['attachments', 'removed_attachment_ids']);
        $data['requestor_id'] = $request->user()->id;

        $this->paymentRequestFormService->update(
            $paymentRequestForm,
            $data,
            AttachmentRequestFiles::normalize($request),
            $request->input('removed_attachment_ids'),
        );

        return redirect()
            ->route('payment-request-forms.show', $paymentRequestForm)
            ->with('success', 'Payment request form updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, PaymentRequestForm $paymentRequestForm): RedirectResponse
    {
        $this->authorize('delete', $paymentRequestForm);
        abort_if(
            $paymentRequestForm->status !== PaymentRequestFormStatus::DRAFT,
            403,
            'Only draft payment request forms can be deleted.'
        );

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'A deletion reason is required.'],
        );

        $this->paymentRequestFormService->delete($paymentRequestForm, $validated['reason']);

        return to_route('payment-request-forms.index');
    }
}
