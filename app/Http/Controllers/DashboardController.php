<?php

namespace App\Http\Controllers;

use App\Enums\PaymentRequestFormStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\ReceivingReportStatus;
use App\Enums\WorkflowAssignmentStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\PaymentRequestForm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowAssignment;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Per-subject-type presentation: badge, index/show path, and number column.
     *
     * @var array<class-string, array{type: string, path: string, number: string}>
     */
    private const SUBJECT_MAP = [
        PurchaseRequisition::class => ['type' => 'PR', 'path' => 'purchase-requisitions', 'number' => 'pr_number'],
        PurchaseOrder::class => ['type' => 'PO', 'path' => 'purchase-orders', 'number' => 'po_number'],
        ReceivingReport::class => ['type' => 'RR', 'path' => 'receiving-reports', 'number' => 'rr_number'],
        PaymentRequestForm::class => ['type' => 'PRF', 'path' => 'payment-request-forms', 'number' => 'prf_number'],
    ];

    public function index(): Response
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        // "Sees all" = the org-wide overview capability (Super Admin passes via Gate::before).
        $isAdmin = $user->can('workflow.override');

        return Inertia::render('dashboard', [
            'queue' => $this->actionQueue($user->id),
            'recentActivity' => $this->recentActivity($user->id, $isAdmin),
            'overview' => $this->overview($user, $isAdmin),
            'signature' => [
                'uploaded' => $user->signatures()->exists(),
                'active' => $user->hasActiveSignature(),
            ],
        ]);
    }

    /**
     * Assignments genuinely actionable by the user right now: the assignment is
     * pending, its step is the instance's active current step, and the workflow
     * itself is still running. Oldest first (FIFO).
     *
     * @return array<int, array<string, mixed>>
     */
    private function actionQueue(int $userId): array
    {
        return WorkflowAssignment::query()
            ->with(['stepInstance.instance.subject'])
            ->where('user_id', $userId)
            ->where('status', WorkflowAssignmentStatus::Pending)
            ->whereHas('stepInstance', function ($query) {
                $query->where('status', WorkflowStepStatus::Active)
                    ->whereHas('instance', function ($iq) {
                        $iq->where('status', WorkflowInstanceStatus::Pending)
                            ->whereColumn('workflow_instances.current_step_order', 'workflow_step_instances.step_order');
                    });
            })
            ->oldest()
            ->get()
            ->map(function (WorkflowAssignment $assignment) {
                $instance = $assignment->stepInstance->instance;
                $subject = $instance->subject;
                $map = self::SUBJECT_MAP[$instance->subject_type] ?? null;

                if (! $subject || ! $map) {
                    return null;
                }

                $dueAt = $assignment->stepInstance->due_at;

                return [
                    'id' => $assignment->id,
                    'doc_type' => $map['type'],
                    'href' => "/{$map['path']}/{$subject->getKey()}",
                    'number' => $subject->{$map['number']},
                    'title' => $subject->title ?? $subject->description ?? null,
                    'step_name' => $assignment->stepInstance->name,
                    'waiting_since' => $assignment->created_at->diffForHumans(),
                    'due_at' => $dueAt?->diffForHumans(),
                    'is_overdue' => $dueAt !== null && $dueAt->isPast(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Latest workflow decisions — org-wide for admins, scoped to the user's own
     * activity (as actor or as document owner) otherwise.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(int $userId, bool $isAdmin): array
    {
        return WorkflowAction::query()
            ->with(['actor', 'instance.subject'])
            ->when(! $isAdmin, function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('actor_user_id', $userId)
                        ->orWhereHas('instance', function ($iq) use ($userId) {
                            $iq->whereHasMorph(
                                'subject',
                                [PurchaseRequisition::class, PaymentRequestForm::class],
                                fn ($sq) => $sq->where('requestor_id', $userId),
                            );
                        });
                });
            })
            ->latest('acted_at')
            ->take(8)
            ->get()
            ->map(function (WorkflowAction $action) {
                $subject = $action->instance?->subject;
                $map = $subject ? (self::SUBJECT_MAP[$action->instance->subject_type] ?? null) : null;

                return [
                    'actor_name' => $action->actor?->name ?? 'System',
                    'action' => $action->action->value,
                    'subject_label' => $map ? "{$map['type']} {$subject->{$map['number']}}" : 'Document',
                    'href' => $map ? "/{$map['path']}/{$subject->getKey()}" : null,
                    'comment' => $action->comment,
                    'acted_at' => $action->acted_at->diffForHumans(),
                ];
            })
            ->all();
    }

    /**
     * Per-module pipeline counts (documents currently awaiting workflow review)
     * for the modules the user may view. No spend figures by design.
     *
     * @return array<int, array<string, mixed>>
     */
    private function overview(User $user, bool $isAdmin): array
    {
        $modules = [];
        $userId = $user->id;

        if ($user->can('pr.view')) {
            $modules[] = [
                'key' => 'pr',
                'label' => 'Purchase Requisitions',
                'href' => '/purchase-requisitions',
                'in_review' => PurchaseRequisition::query()
                    ->where('status', PurchaseRequisitionStatus::REVIEWING)
                    ->when(! $isAdmin, fn ($q) => $q->where('requestor_id', $userId))
                    ->count(),
            ];
        }

        if ($user->can('po.view')) {
            $modules[] = [
                'key' => 'po',
                'label' => 'Purchase Orders',
                'href' => '/purchase-orders',
                'in_review' => PurchaseOrder::query()
                    ->where('status', PurchaseOrderStatus::PENDING_APPROVAL)
                    ->count(),
            ];
        }

        if ($user->can('rr.view')) {
            $modules[] = [
                'key' => 'rr',
                'label' => 'Receiving Reports',
                'href' => '/receiving-reports',
                'in_review' => ReceivingReport::query()
                    ->where('status', ReceivingReportStatus::PENDING)
                    ->count(),
            ];
        }

        if ($user->can('prf.view')) {
            $modules[] = [
                'key' => 'prf',
                'label' => 'Payment Request Forms',
                'href' => '/payment-request-forms',
                'in_review' => PaymentRequestForm::query()
                    ->where('status', PaymentRequestFormStatus::REVIEWING)
                    ->when(! $isAdmin, fn ($q) => $q->where('requestor_id', $userId))
                    ->count(),
            ];
        }

        return $modules;
    }
}
