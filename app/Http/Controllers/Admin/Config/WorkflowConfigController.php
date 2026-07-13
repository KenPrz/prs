<?php

namespace App\Http\Controllers\Admin\Config;

use App\Enums\WorkflowAssigneeType;
use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowStepType;
use App\Http\Controllers\Controller;
use App\Models\PaymentRequestForm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStepDefinition;
use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class WorkflowConfigController extends Controller
{
    /** The document types the workflow engine understands. */
    private const DOCUMENT_TYPES = ['PR', 'PO', 'RR', 'PRF'];

    public function index(Request $request): Response
    {
        $workflows = WorkflowDefinition::query()
            ->withCount('steps')
            ->when($request->input('search'), function ($query, $search) {
                $query->whereLike('name', "%{$search}%")
                    ->orWhereLike('document_type', "%{$search}%");
            })
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', app(GeneralSettings::class)->records_per_page))
            ->withQueryString();

        return Inertia::render('admin/config/workflows/index', [
            'workflows' => $workflows,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/config/workflows/create', [
            'users' => User::all(['id', 'name', 'email']),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated) {
            $workflow = WorkflowDefinition::create([
                'name' => $validated['name'],
                'key' => $validated['key'],
                'description' => $validated['description'] ?? null,
                'document_type' => $validated['document_type'],
                'version' => 1,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->syncSteps($workflow, $validated['steps']);
        });

        return to_route('admin.config.workflows.index')
            ->with('success', 'Workflow created successfully.');
    }

    public function edit(WorkflowDefinition $workflow): Response
    {
        $workflow->load(['steps.assignees.user']);

        return Inertia::render('admin/config/workflows/edit', [
            'workflow' => $workflow,
            'users' => User::all(['id', 'name', 'email']),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, WorkflowDefinition $workflow): RedirectResponse
    {
        // key and document_type are code contracts — immutable after creation.
        $validated = $this->validatePayload($request, $workflow);

        if (($validated['is_active'] ?? true) === false && $this->isProtectedDefault($workflow)) {
            return back()->with('error', 'This is the active default workflow for its document type — deactivating it would block all submissions. Activate a replacement with the same key first.');
        }

        DB::transaction(function () use ($validated, $workflow) {
            $workflow->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                // Steps are replaced, so this is a new revision of the template.
                'version' => $workflow->version + 1,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Steps are fully replaced on each save — simpler and avoids drift.
            $workflow->steps()->delete();
            $this->syncSteps($workflow, $validated['steps']);
        });

        return to_route('admin.config.workflows.index')
            ->with('success', 'Workflow updated successfully.');
    }

    public function destroy(WorkflowDefinition $workflow): RedirectResponse
    {
        if ($workflow->instances()->where('status', 'PENDING')->exists()) {
            return back()->with('error', 'Cannot delete a workflow with active instances.');
        }

        if ($this->isProtectedDefault($workflow)) {
            return back()->with('error', 'This is the active default workflow for its document type — deleting it would block all submissions. Activate a replacement with the same key first.');
        }

        $workflow->steps()->delete();
        $workflow->delete();

        return to_route('admin.config.workflows.index')
            ->with('success', 'Workflow deleted successfully.');
    }

    /**
     * True when this workflow is the only active definition serving one of the
     * model default keys — the definition every submission resolves to.
     */
    private function isProtectedDefault(WorkflowDefinition $workflow): bool
    {
        if (! $workflow->is_active || ! \in_array($workflow->key, $this->defaultKeys(), true)) {
            return false;
        }

        return WorkflowDefinition::query()
            ->where('key', $workflow->key)
            ->where('document_type', $workflow->document_type)
            ->where('is_active', true)
            ->whereKeyNot($workflow->id)
            ->doesntExist();
    }

    /**
     * The workflow keys the models resolve by default — derived from code so
     * the guard can never drift from it.
     *
     * @return array<int, string>
     */
    private function defaultKeys(): array
    {
        return array_map(
            fn (string $class) => (new $class)->defaultWorkflowKey(),
            [PurchaseRequisition::class, PurchaseOrder::class, ReceivingReport::class, PaymentRequestForm::class],
        );
    }

    /**
     * Selectable options for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'documentTypes' => self::DOCUMENT_TYPES,
            'roleOptions' => Role::query()->orderBy('name')->pluck('name'),
            'permissionOptions' => Permission::query()->orderBy('name')->pluck('name'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?WorkflowDefinition $existing = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Immutable on update: the key is how code resolves the workflow.
            'key' => $existing
                ? ['sometimes']
                : [
                    'required', 'string', 'max:255',
                    Rule::unique('workflow_definitions', 'key')->where('document_type', $request->input('document_type')),
                ],
            'description' => ['nullable', 'string', 'max:255'],
            'document_type' => $existing
                ? ['sometimes']
                : ['required', 'string', Rule::in(self::DOCUMENT_TYPES)],
            'is_active' => ['boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.step_type' => ['required', Rule::enum(WorkflowStepType::class)],
            'steps.*.completion_strategy' => ['required', Rule::enum(WorkflowCompletionStrategy::class)],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1'],
            'steps.*.condition' => ['nullable', 'array'],
            'steps.*.assignees' => ['present', 'array'],
            'steps.*.assignees.*.assignee_type' => ['required', Rule::enum(WorkflowAssigneeType::class)],
            'steps.*.assignees.*.assignee_identifier' => ['nullable', 'string'],
            'steps.*.assignees.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->assertAssigneeIdentifiersResolve($validated['steps']);

        return $validated;
    }

    /**
     * A role/permission assignee naming something that doesn't exist would
     * resolve to zero assignees and silently skip the step — reject it here.
     *
     * @param  array<int, array<string, mixed>>  $steps
     *
     * @throws ValidationException
     */
    private function assertAssigneeIdentifiersResolve(array $steps): void
    {
        $errors = [];

        foreach ($steps as $stepIndex => $step) {
            foreach ($step['assignees'] ?? [] as $assigneeIndex => $assignee) {
                $type = WorkflowAssigneeType::from($assignee['assignee_type']);
                $identifier = $assignee['assignee_identifier'] ?? null;
                $field = "steps.{$stepIndex}.assignees.{$assigneeIndex}";

                if ($type === WorkflowAssigneeType::Role
                    && ! Role::query()->where('name', $identifier)->exists()) {
                    $errors["{$field}.assignee_identifier"] = "No role named \"{$identifier}\" exists.";
                }

                if ($type === WorkflowAssigneeType::Permission
                    && ! Permission::query()->where('name', $identifier)->exists()) {
                    $errors["{$field}.assignee_identifier"] = "No permission named \"{$identifier}\" exists.";
                }

                if ($type === WorkflowAssigneeType::User && empty($assignee['user_id'])) {
                    $errors["{$field}.user_id"] = 'A user must be selected for a user assignee.';
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function syncSteps(WorkflowDefinition $workflow, array $steps): void
    {
        foreach ($steps as $order => $stepData) {
            /** @var WorkflowStepDefinition $step */
            $step = $workflow->steps()->create([
                'step_order' => $order + 1,
                'name' => $stepData['name'],
                'step_type' => $stepData['step_type'],
                'completion_strategy' => $stepData['completion_strategy'],
                'sla_hours' => $stepData['sla_hours'] ?? null,
                'condition' => $stepData['condition'] ?? null,
                'is_active' => true,
            ]);

            foreach ($stepData['assignees'] ?? [] as $assignee) {
                $identifier = $assignee['assignee_identifier'] ?? null;

                if ($assignee['assignee_type'] === 'USER' && empty($identifier)) {
                    $identifier = isset($assignee['user_id']) ? (string) $assignee['user_id'] : null;
                }

                $step->assignees()->create([
                    'assignee_type' => $assignee['assignee_type'],
                    'assignee_identifier' => $identifier,
                    'user_id' => $assignee['user_id'] ?? null,
                ]);
            }
        }
    }
}
