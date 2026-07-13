<?php

namespace App\Http\Controllers;

use App\Contracts\WorkflowSubject;
use App\Http\Requests\Workflow\WorkflowActionRequest;
use App\Http\Requests\Workflow\WorkflowCancelRequest;
use App\Http\Requests\Workflow\WorkflowReassignRequest;
use App\Http\Requests\Workflow\WorkflowSubmitRequest;
use App\Models\PaymentRequestForm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use App\Models\User;
use App\Models\WorkflowAssignment;
use App\Services\Workflow\WorkflowManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single, document-agnostic entry point for every workflow action. Resolves the
 * subject from {type}/{id}, authorizes via the subject's existing policy, and
 * delegates to the WorkflowManager.
 */
class WorkflowController extends Controller
{
    /** @var array<string, array{model: class-string<Model>, route: string}> */
    private const SUBJECTS = [
        'pr' => ['model' => PurchaseRequisition::class, 'route' => 'purchase-requisitions.show'],
        'po' => ['model' => PurchaseOrder::class, 'route' => 'purchase-orders.show'],
        'rr' => ['model' => ReceivingReport::class, 'route' => 'receiving-reports.show'],
        'prf' => ['model' => PaymentRequestForm::class, 'route' => 'payment-request-forms.show'],
    ];

    public function __construct(
        private WorkflowManager $manager,
    ) {}

    public function submit(WorkflowSubmitRequest $request, string $type, int $id): JsonResponse|RedirectResponse
    {
        $subject = $this->resolveSubject($type, $id);
        $this->authorize('submit', $subject);

        $this->manager->start($subject, $request->validated()['workflow_key'] ?? null, $request->user());

        return $this->respond($request, $type, $subject, 'Submitted for approval.');
    }

    public function act(WorkflowActionRequest $request, string $type, int $id): JsonResponse|RedirectResponse
    {
        $subject = $this->resolveSubject($type, $id);
        $this->authorize('decide', $subject);

        $this->manager->act($subject, $request->user(), $request->action(), $request->validated()['comment'] ?? null);

        return $this->respond($request, $type, $subject, 'Your decision has been recorded.');
    }

    public function reassign(WorkflowReassignRequest $request, string $type, int $id): JsonResponse|RedirectResponse
    {
        $subject = $this->resolveSubject($type, $id);
        abort_unless($request->user()?->can('workflow.reassign') ?? false, 403);

        $data = $request->validated();
        $assignment = WorkflowAssignment::query()->findOrFail($data['assignment_id']);
        $toUser = User::query()->findOrFail($data['to_user_id']);

        $this->manager->reassign($subject, $assignment, $toUser, $request->user());

        return $this->respond($request, $type, $subject, 'The assignment has been reassigned.');
    }

    public function cancel(WorkflowCancelRequest $request, string $type, int $id): JsonResponse|RedirectResponse
    {
        $subject = $this->resolveSubject($type, $id);
        $this->authorize('cancelWorkflow', $subject);

        $this->manager->cancel($subject, $request->user(), $request->validated()['comment']);

        return $this->respond($request, $type, $subject, 'The workflow has been cancelled.');
    }

    /**
     * @return WorkflowSubject&Model
     */
    private function resolveSubject(string $type, int $id): WorkflowSubject
    {
        $config = self::SUBJECTS[$type] ?? abort(404);

        /** @var WorkflowSubject&Model $subject */
        $subject = $config['model']::query()->findOrFail($id);

        return $subject;
    }

    /**
     * @param  WorkflowSubject&Model  $subject
     */
    private function respond(Request $request, string $type, WorkflowSubject $subject, string $message): JsonResponse|RedirectResponse
    {
        $subject = $subject->fresh();
        $instance = $subject->workflowInstance;

        if ($request->header('X-Inertia')) {
            return redirect()
                ->route(self::SUBJECTS[$type]['route'], $subject)
                ->with('success', $message);
        }

        return response()->json([
            'workflow_instance_id' => $instance?->id,
            'instance_status' => $instance?->status->value,
            'status' => $subject->status->value ?? null,
            'current_step_order' => $instance?->current_step_order,
        ]);
    }
}
