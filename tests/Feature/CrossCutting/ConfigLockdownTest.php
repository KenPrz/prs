<?php

use App\Enums\WorkflowAssigneeType;
use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowStepType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\Workflow\WorkflowManager;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Config lockdown
|--------------------------------------------------------------------------
| Code-coupled config values must not be editable into a broken state:
| workflow keys/document types are immutable, assignee identifiers must
| resolve, protected defaults can't be deleted, reserved role names are
| locked, document type rows are system-managed, and the last Super Admin
| can't be demoted or deleted.
*/

function workflowAdmin(): User
{
    return userWithPermission('config.workflows.manage');
}

/**
 * @return array<string, mixed>
 */
function workflowPayload(array $overrides = []): array
{
    Role::firstOrCreate(['name' => 'finance_manager', 'guard_name' => 'web']);

    return array_merge([
        'name' => 'Test Workflow',
        'key' => 'pr.custom',
        'document_type' => 'PR',
        'is_active' => true,
        'steps' => [
            [
                'name' => 'Finance',
                'step_type' => WorkflowStepType::Approval->value,
                'completion_strategy' => WorkflowCompletionStrategy::All->value,
                'sla_hours' => null,
                'condition' => null,
                'assignees' => [
                    ['assignee_type' => WorkflowAssigneeType::Role->value, 'assignee_identifier' => 'finance_manager', 'user_id' => null],
                ],
            ],
        ],
    ], $overrides);
}

// ── Workflow config ────────────────────────────────────────────────────────

test('a workflow document type must be one of the known types', function () {
    $this->actingAs(workflowAdmin())
        ->post(route('admin.config.workflows.store'), workflowPayload(['document_type' => 'PurchaseRequisition']))
        ->assertSessionHasErrors('document_type');
});

test('an assignee naming an unknown role is rejected', function () {
    $payload = workflowPayload();
    $payload['steps'][0]['assignees'][0]['assignee_identifier'] = 'ghost_role';

    $this->actingAs(workflowAdmin())
        ->post(route('admin.config.workflows.store'), $payload)
        ->assertSessionHasErrors('steps.0.assignees.0.assignee_identifier');
});

test('the workflow key and document type are immutable on update', function () {
    roleApprover('finance_manager');
    $workflow = definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ], 'pr.custom');

    $this->actingAs(workflowAdmin())
        ->put(route('admin.config.workflows.update', $workflow), workflowPayload([
            'key' => 'pr.renamed',
            'document_type' => 'PO',
        ]))
        ->assertRedirect(route('admin.config.workflows.index'));

    $workflow->refresh();
    expect($workflow->key)->toBe('pr.custom')
        ->and($workflow->document_type)->toBe('PR')
        ->and($workflow->version)->toBe(2); // auto-incremented, not user-supplied
});

test('the active default workflow cannot be deleted or deactivated', function () {
    roleApprover('finance_manager');
    $workflow = definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]); // key defaults to pr.default

    $admin = workflowAdmin();

    $this->actingAs($admin)
        ->from(route('admin.config.workflows.index'))
        ->delete(route('admin.config.workflows.destroy', $workflow))
        ->assertRedirect(route('admin.config.workflows.index'))
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->from(route('admin.config.workflows.index'))
        ->put(route('admin.config.workflows.update', $workflow), workflowPayload(['is_active' => false]))
        ->assertSessionHas('error');

    expect(WorkflowDefinition::find($workflow->id))->not->toBeNull()
        ->and($workflow->fresh()->is_active)->toBeTrue();
});

test('a non-default workflow can still be deleted', function () {
    roleApprover('finance_manager');
    $workflow = definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ], 'pr.custom');

    $this->actingAs(workflowAdmin())
        ->delete(route('admin.config.workflows.destroy', $workflow))
        ->assertRedirect(route('admin.config.workflows.index'));

    expect(WorkflowDefinition::find($workflow->id))->toBeNull();
});

// ── Submit-time assignee check ─────────────────────────────────────────────

test('submitting fails loudly when a step resolves no assignees', function () {
    // The role exists as an assignee identifier, but nobody holds it.
    definePrWorkflow([
        ['Ghost Step', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'ghost_role']]],
    ]);
    $pr = makePr();

    expect(fn () => app(WorkflowManager::class)->start($pr, null, $pr->requestor))
        ->toThrow(ValidationException::class);

    expect($pr->fresh()->workflow_instance_id)->toBeNull();
});

// ── Role guards ────────────────────────────────────────────────────────────

test('the super admin role cannot be renamed', function () {
    $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->actingAs(userWithPermission('access.roles.manage'))
        ->from(route('admin.config.roles.index'))
        ->put(route('admin.config.roles.update', $role), ['name' => 'God Mode', 'permissions' => []])
        ->assertSessionHas('error');

    expect($role->fresh()->name)->toBe('Super Admin');
});

test('a role referenced by workflow steps cannot be renamed or deleted', function () {
    $role = Role::firstOrCreate(['name' => 'finance_manager', 'guard_name' => 'web']);
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);

    $admin = userWithPermission('access.roles.manage');

    $this->actingAs($admin)
        ->from(route('admin.config.roles.index'))
        ->put(route('admin.config.roles.update', $role), ['name' => 'finance_lead', 'permissions' => []])
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->from(route('admin.config.roles.index'))
        ->delete(route('admin.config.roles.destroy', $role))
        ->assertSessionHas('error');

    expect($role->fresh()->name)->toBe('finance_manager');
});

// ── Documents are system-managed ───────────────────────────────────────────

test('document type rows cannot be created or deleted and the index self-heals', function () {
    $admin = userWithPermission('config.documents.manage');

    $this->actingAs($admin)
        ->get(route('admin.config.documents.index'))
        ->assertOk();

    // One row per code-defined type, created automatically.
    expect(Document::count())->toBe(count(Document::types()));

    $document = Document::first();

    // No store/destroy routes exist; "create" only collides with the PUT-only
    // {document} URI — every mutation path is method-not-allowed.
    $this->actingAs($admin)->post('/admin/config/documents', [])->assertStatus(405);
    $this->actingAs($admin)->delete("/admin/config/documents/{$document->id}")->assertStatus(405);
    $this->actingAs($admin)->get('/admin/config/documents/create')->assertStatus(405);
});

// ── Last Super Admin guards ────────────────────────────────────────────────

test('the last super admin cannot be demoted or deleted', function () {
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $admin = userWithPermission('access.users.manage');

    $this->actingAs($admin)
        ->from(route('admin.config.users.index'))
        ->put(route('admin.config.users.update', $superAdmin), ['name' => $superAdmin->name, 'email' => $superAdmin->email, 'roles' => [], 'departments' => []])
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->from(route('admin.config.users.index'))
        ->delete(route('admin.config.users.destroy', $superAdmin))
        ->assertSessionHas('error');

    expect($superAdmin->fresh())->not->toBeNull()
        ->and($superAdmin->fresh()->hasRole('Super Admin'))->toBeTrue();

    // With a second super admin, demotion is allowed.
    User::factory()->create()->assignRole('Super Admin');

    $this->actingAs($admin)
        ->put(route('admin.config.users.update', $superAdmin), ['name' => $superAdmin->name, 'email' => $superAdmin->email, 'roles' => [], 'departments' => []])
        ->assertRedirect(route('admin.config.users.index'));

    expect($superAdmin->fresh()->hasRole('Super Admin'))->toBeFalse();
});
