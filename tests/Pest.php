<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Models\ItemUnit;
use App\Models\LineItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use App\Models\Signature;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\PurchaseOrderService;
use App\Services\ReceivingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Every Feature test hits a real (refreshed) database — no mocks for DB.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Shared builders
|--------------------------------------------------------------------------
| Reused across the suite so individual tests stay focused on the scenario
| under test rather than re-deriving setup.
*/

/**
 * Give each user an active signature so they are allowed to approve documents
 * (the workflow engine blocks approvals from users without an active signature).
 */
function withActiveSignature(User ...$users): void
{
    foreach ($users as $user) {
        Signature::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Signature',
            'is_active' => true,
        ]);
    }
}

/** A user holding the given role with an active signature, ready to approve. */
function roleApprover(string $role): User
{
    Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);
    withActiveSignature($user);

    return $user;
}

/** A user granted the given permissions directly (e.g. po.prepare, rr.prepare). */
function userWithPermission(string ...$permissions): User
{
    $user = User::factory()->create();
    grantPermissions($user, ...$permissions);

    return $user;
}

/** Grant permissions (created on the fly) directly to an existing user. */
function grantPermissions(User $user, string ...$permissions): void
{
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($permissions);
}

/**
 * Build a workflow definition with steps.
 * Each step: [name, WorkflowStepType, WorkflowCompletionStrategy, assignees[], condition?, sla_hours?].
 * Each assignee: [WorkflowAssigneeType, identifier?, user_id?].
 *
 * @param  array<int, array{0: string, 1: mixed, 2: mixed, 3: array<int, array<int, mixed>>}>  $steps
 */
function defineWorkflow(string $documentType, array $steps, string $key): WorkflowDefinition
{
    $definition = WorkflowDefinition::query()->create([
        'key' => $key,
        'name' => $documentType.' Test Workflow',
        'document_type' => $documentType,
        'version' => 1,
        'is_active' => true,
    ]);

    foreach ($steps as $order => $step) {
        [$name, $type, $strategy, $assignees] = $step;
        $stepDef = $definition->steps()->create([
            'step_order' => $order + 1,
            'name' => $name,
            'step_type' => $type,
            'completion_strategy' => $strategy,
            'condition' => $step[4] ?? null,
            'sla_hours' => $step[5] ?? null,
            'is_active' => true,
        ]);

        foreach ($assignees as $assignee) {
            $stepDef->assignees()->create([
                'assignee_type' => $assignee[0],
                'assignee_identifier' => $assignee[1] ?? null,
                'user_id' => $assignee[2] ?? null,
            ]);
        }
    }

    return $definition;
}

/** Shorthand for a PR-type workflow (the engine's primary exercised path). */
function definePrWorkflow(array $steps, string $key = 'pr.default'): WorkflowDefinition
{
    return defineWorkflow('PR', $steps, $key);
}

/** A DRAFT purchase requisition owned by $requestor, optionally with a receiver. */
function makePr(?User $requestor = null, ?User $receiver = null): PurchaseRequisition
{
    // An internally-created requestor is a full PR preparer so it can drive the
    // HTTP endpoints (view/prepare/submit/cancel/finalize).
    $requestor ??= userWithPermission('pr.view', 'pr.prepare', 'pr.cancel', 'pr.finalize', 'pr.omit');

    return PurchaseRequisition::factory()->for($requestor, 'requestor')->create([
        'status' => PurchaseRequisitionStatus::DRAFT,
        'to_be_ordered_by_id' => $receiver?->id,
    ]);
}

/**
 * A READY_FOR_PO requisition with two line items (Alpha: 10, Bravo: 5), plus a
 * supplier and unit — the standard fixture for PO/RR accounting scenarios.
 * The fixture user holds the po/rr preparer permissions so it can drive the
 * whole chain through the HTTP endpoints.
 *
 * @return array{pr: PurchaseRequisition, a: LineItem, b: LineItem, unit: ItemUnit, supplier: Supplier, user: User}
 */
function prReadyForPo(): array
{
    // A power procurement user: can view + act on the whole PR→PO→RR chain.
    $user = userWithPermission(
        'pr.view', 'pr.prepare', 'pr.finalize', 'pr.cancel', 'pr.omit',
        'po.view', 'po.prepare', 'po.finalize', 'po.cancel', 'po.omit',
        'rr.view', 'rr.prepare', 'rr.cancel',
    );
    $supplier = Supplier::factory()->create();
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);

    $pr = PurchaseRequisition::factory()->for($user, 'requestor')->create([
        'status' => PurchaseRequisitionStatus::READY_FOR_PO,
        'to_be_ordered_by_id' => $user->id,
    ]);

    $a = $pr->lineItems()->create(['name' => 'Alpha', 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]);
    $b = $pr->lineItems()->create(['name' => 'Bravo', 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 50]);

    return ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user];
}

/**
 * Create a purchase order from a requisition via the real service.
 *
 * @param  array<int, array{line_item_id: int, quantity: int, unit_id: int, price: int|float}>  $items
 */
function makeOrder(PurchaseRequisition $pr, Supplier $supplier, array $items): PurchaseOrder
{
    return app(PurchaseOrderService::class)->create($pr, [
        'supplier_id' => $supplier->id,
        'price_type' => 'VAT_INCLUSIVE',
        'items' => $items,
    ]);
}

/** Seal an approved purchase order as ordered (→ RELEASED), skipping the PDF snapshot. */
function sealOrder(PurchaseOrder $po): void
{
    $po->forceFill(['status' => PurchaseOrderStatus::RELEASED])->save();
}

/**
 * An ordered (sealed, RELEASED) purchase order built from prReadyForPo()
 * (Alpha: 10, Bravo: 5), ready to receive against.
 *
 * @return array{pr: PurchaseRequisition, po: PurchaseOrder, itemA: PurchaseOrderItem, itemB: PurchaseOrderItem, user: User}
 */
function orderedOrder(): array
{
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 50],
    ]);
    $po->forceFill(['status' => PurchaseOrderStatus::APPROVED])->save();
    sealOrder($po);

    return [
        'pr' => $pr, 'po' => $po, 'user' => $user,
        'itemA' => $po->items->firstWhere('line_item_id', $a->id),
        'itemB' => $po->items->firstWhere('line_item_id', $b->id),
    ];
}

/**
 * Record a receiving report against a purchase order via the real service.
 *
 * @param  array<int, array{purchase_order_item_id: int, quantity_received: int, quantity_rejected?: int}>  $items
 */
function receive(PurchaseOrder $po, User $user, array $items): ReceivingReport
{
    return app(ReceivingReportService::class)->create($po, [
        'received_by_id' => $user->id,
        'received_date' => now()->toDateString(),
        'items' => $items,
    ]);
}

/** Push a receiving report through verification (→ VERIFIED) and re-sync its PO. */
function verifyReport(ReceivingReport $rr): void
{
    $rr->applyWorkflowStatus(WorkflowInstanceStatus::Approved);
}
