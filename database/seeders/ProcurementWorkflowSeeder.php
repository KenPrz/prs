<?php

namespace Database\Seeders;

use App\Contracts\WorkflowSubject;
use App\Enums\PriceType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\PurposeType;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowAssignmentStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStepType;
use App\Models\Address;
use App\Models\Department;
use App\Models\Document;
use App\Models\ItemUnit;
use App\Models\LineItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\ReceivingReportService;
use App\Services\Workflow\WorkflowManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcurementWorkflowSeeder extends Seeder
{
    /** @var Collection<int, int> */
    private Collection $unitIds;

    /** @var Collection<int, int> */
    private Collection $supplierIds;

    /** @var Collection<int, int> */
    private Collection $departmentIds;

    /** @var Collection<int, int> */
    private Collection $addressIds;

    private ?int $poDocumentId;

    private ?int $prDocumentId;

    private WorkflowManager $workflow;

    private PurchaseOrderService $poService;

    private ReceivingReportService $rrService;

    /** Approvers resolved by role name. */
    private User $financeManager;

    private User $generalManager;

    private User $director;

    private User $president;

    private User $logistics;

    /** Requestor — a regular employee. */
    private User $requestor;

    /** @var list<string> */
    private array $priceTypes;

    /**
     * Realistic office / manufacturing item catalog used across all scenarios.
     *
     * @var list<array{name: string, unit: string, price: float, category: string}>
     */
    private array $itemCatalog = [
        ['name' => 'A4 Bond Paper (Ream 500 sheets)', 'unit' => 'pack', 'price' => 245.00, 'category' => 'office'],
        ['name' => 'Blue Ballpoint Pen (12 pcs)', 'unit' => 'box', 'price' => 120.00, 'category' => 'office'],
        ['name' => 'Heavy-Duty Stapler', 'unit' => 'pc', 'price' => 680.00, 'category' => 'office'],
        ['name' => 'Manila Folder (Legal Size)', 'unit' => 'pack', 'price' => 85.00, 'category' => 'office'],
        ['name' => 'Whiteboard Marker (Set of 4)', 'unit' => 'set', 'price' => 195.00, 'category' => 'office'],
        ['name' => 'Toner Cartridge HP 26A', 'unit' => 'pc', 'price' => 3450.00, 'category' => 'office'],
        ['name' => 'Correction Tape', 'unit' => 'pc', 'price' => 45.00, 'category' => 'office'],
        ['name' => 'Steel Hex Bolt M10x50', 'unit' => 'box', 'price' => 850.00, 'category' => 'manufacturing'],
        ['name' => 'Industrial Lubricant (5L)', 'unit' => 'gal', 'price' => 1200.00, 'category' => 'manufacturing'],
        ['name' => 'Safety Goggles (ANSI Z87)', 'unit' => 'pc', 'price' => 320.00, 'category' => 'safety'],
        ['name' => 'Nitrile Gloves (Box of 100)', 'unit' => 'box', 'price' => 450.00, 'category' => 'safety'],
        ['name' => 'N95 Respirator Mask', 'unit' => 'box', 'price' => 780.00, 'category' => 'safety'],
        ['name' => 'Alcohol 70% (500mL)', 'unit' => 'pc', 'price' => 95.00, 'category' => 'janitorial'],
        ['name' => 'Floor Mop with Wringer', 'unit' => 'pc', 'price' => 520.00, 'category' => 'janitorial'],
        ['name' => 'Trash Bag (XXL, 50 pcs)', 'unit' => 'pack', 'price' => 180.00, 'category' => 'janitorial'],
        ['name' => 'USB-C Hub Adapter', 'unit' => 'pc', 'price' => 1850.00, 'category' => 'IT'],
        ['name' => 'CAT6 Ethernet Cable (5m)', 'unit' => 'pc', 'price' => 250.00, 'category' => 'IT'],
        ['name' => 'Surge Protector 6-Outlet', 'unit' => 'pc', 'price' => 890.00, 'category' => 'IT'],
        ['name' => 'Solvent-Based Ink (Cyan, 1L)', 'unit' => 'L', 'price' => 4200.00, 'category' => 'production'],
        ['name' => 'Solvent-Based Ink (Magenta, 1L)', 'unit' => 'L', 'price' => 4200.00, 'category' => 'production'],
        ['name' => 'Solvent-Based Ink (Yellow, 1L)', 'unit' => 'L', 'price' => 4200.00, 'category' => 'production'],
        ['name' => 'Solvent-Based Ink (Black, 1L)', 'unit' => 'L', 'price' => 3800.00, 'category' => 'production'],
        ['name' => 'Flexographic Plate (A2)', 'unit' => 'pc', 'price' => 2500.00, 'category' => 'production'],
        ['name' => 'Pigment Dispersant Additive (1kg)', 'unit' => 'kg', 'price' => 1800.00, 'category' => 'production'],
    ];

    /**
     * Run the database seeds.
     *
     * Creates 8 realistic procurement scenarios that cover every workflow state:
     *
     * 1. Draft PR — untouched
     * 2. PR submitted, currently being reviewed (step 2 of 4)
     * 3. PR rejected by director at step 3
     * 4. Fully approved PR, no PO yet
     * 5. Approved PR → Draft PO created (not submitted)
     * 6. Approved PR → PO submitted → approved → partially received via RR
     * 7. Approved PR → PO approved → fully received → RR verified
     * 8. Approved PR → 2 POs (split order) — one fully received, one partially received
     */
    public function run(): void
    {
        $this->workflow = app(WorkflowManager::class);
        $this->poService = app(PurchaseOrderService::class);
        $this->rrService = app(ReceivingReportService::class);
        $this->priceTypes = array_map(static fn (PriceType $type) => $type->value, PriceType::cases());

        $this->resolveSharedLookups();
        $this->resolveApprovers();

        DB::transaction(function () {
            $this->seedScenario1_DraftPR();
            $this->seedScenario2_ReviewingPR();
            $this->seedScenario3_RejectedPR();
            $this->seedScenario4_ApprovedPR_NoPO();
            $this->seedScenario5_ApprovedPR_DraftPO();
            $this->seedScenario6_PO_PartiallyReceived();
            $this->seedScenario7_PO_FullyReceived_RR_Verified();
            $this->seedScenario8_SplitPO_MixedReceiving();
        });
    }

    // ──────────────────────────────────────────────────────────────────
    //  Shared helpers
    // ──────────────────────────────────────────────────────────────────

    private function resolveSharedLookups(): void
    {
        $this->unitIds = ItemUnit::query()->pluck('id', 'code');
        $this->supplierIds = Supplier::query()->pluck('id');
        // Only departments that have a head can sign the Department-Head step.
        $this->departmentIds = Department::query()->whereNotNull('department_head_id')->pluck('id');
        if ($this->departmentIds->isEmpty()) {
            $this->departmentIds = Department::query()->pluck('id');
        }
        $this->addressIds = Address::query()->pluck('id');

        $poDocument = Document::query()
            ->where('resource_class', PurchaseOrder::class)
            ->where('blade_file', 'documents.purchase-order')
            ->first();

        $prDocument = Document::query()
            ->where('resource_class', PurchaseRequisition::class)
            ->where('blade_file', 'documents.purchase-requisition')
            ->first();

        $this->poDocumentId = $poDocument?->id;
        $this->prDocumentId = $prDocument?->id;
    }

    private function resolveApprovers(): void
    {
        $this->requestor = User::whereHas('roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();
        $this->financeManager = User::whereHas('roles', fn ($q) => $q->where('name', 'finance_manager'))->firstOrFail();
        $this->generalManager = User::whereHas('roles', fn ($q) => $q->where('name', 'general_manager'))->firstOrFail();
        $this->director = User::whereHas('roles', fn ($q) => $q->where('name', 'director'))->firstOrFail();
        $this->president = User::whereHas('roles', fn ($q) => $q->where('name', 'president'))->firstOrFail();

        $this->logistics = User::whereHas('roles', fn ($q) => $q->where('name', 'logistics'))->firstOrFail();
    }

    /**
     * Create a PR with line items from the catalog.
     *
     * @param  list<int>  $catalogIndices  Indices into $this->itemCatalog
     * @param  array<string, mixed>  $overrides  Extra PR column values
     */
    private function createPR(string $title, array $catalogIndices, array $overrides = []): PurchaseRequisition
    {
        /** @var PurchaseRequisition $pr */
        $pr = PurchaseRequisition::query()->create(array_merge([
            'requestor_id' => $this->requestor->id,
            'document_id' => $this->poDocumentId,
            'requisition_document_id' => $this->prDocumentId,
            'title' => $title,
            'description' => fake()->paragraph(),
            'delivery_date' => now()->addWeeks(fake()->numberBetween(1, 4))->toDateString(),
            'status' => PurchaseRequisitionStatus::DRAFT,
            'price_type' => fake()->randomElement($this->priceTypes),
            'purpose_type' => fake()->randomElement(PurposeType::cases())->value,
            'expected_useful_life' => fake()->numerify('# years'),
            'to_be_ordered_by_id' => $this->requestor->id,
        ], $overrides));

        // Sync 1-2 random departments
        if ($this->departmentIds->isNotEmpty()) {
            $pr->departments()->sync(
                $this->departmentIds->random(min($this->departmentIds->count(), fake()->numberBetween(1, 2)))
            );
        }

        foreach ($catalogIndices as $idx) {
            $item = $this->itemCatalog[$idx];
            $unitId = $this->unitIds[$item['unit']] ?? $this->unitIds->first();

            LineItem::query()->create([
                'purchase_requisition_id' => $pr->id,
                'name' => $item['name'],
                'quantity' => fake()->numberBetween(2, 20),
                'unit_id' => $unitId,
                'price' => $item['price'],
            ]);
        }

        return $pr->load('lineItems');
    }

    /** Start a PR workflow then complete the first $steps active steps. */
    private function approvePRSteps(PurchaseRequisition $pr, int $steps): void
    {
        $this->workflow->start($pr, 'pr.default', $this->requestor);

        for ($i = 0; $i < $steps; $i++) {
            if (! $this->completeActiveStep($pr)) {
                break;
            }
        }
    }

    /** Start a PR workflow and run it all the way to APPROVED (incl. receive). */
    private function fullyApprovePR(PurchaseRequisition $pr): void
    {
        $this->workflow->start($pr, 'pr.default', $this->requestor);
        $this->fullyComplete($pr);
    }

    /** Start a PR workflow, advance to a step, then reject it. */
    private function rejectPRAtStep(PurchaseRequisition $pr, int $rejectAtStep, string $reason): void
    {
        $this->workflow->start($pr, 'pr.default', $this->requestor);

        for ($i = 0; $i < $rejectAtStep - 1; $i++) {
            $this->completeActiveStep($pr);
        }

        $actor = $this->firstPendingActor($pr);
        if ($actor !== null) {
            $this->workflow->act($pr, $actor, WorkflowActionType::Reject, $reason);
        }
    }

    private function fullyApprovePO(PurchaseOrder $po): void
    {
        $this->workflow->start($po, 'po.default', $this->requestor);
        $this->fullyComplete($po);
    }

    /**
     * Seal an approved PO as ordered (→ RELEASED) so receiving reports can be
     * created against it, mirroring the mark-as-ordered action.
     */
    private function markPOAsOrdered(PurchaseOrder $po): void
    {
        // ponytail: status only — skip the real Carbone PDF snapshot the seal action attaches, to keep seeding dependency-free.
        $po->forceFill(['status' => PurchaseOrderStatus::RELEASED])->save();
    }

    private function fullyVerifyRR(ReceivingReport $rr): void
    {
        $this->workflow->start($rr, 'rr.default', $this->logistics);
        $this->fullyComplete($rr);
    }

    /**
     * Complete the current active step by having every pending assignee act
     * (approve, or receive for a Receive step). Returns false when there is no
     * active step.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    private function completeActiveStep(WorkflowSubject $subject): bool
    {
        $instance = $subject->workflowInstance?->refresh();

        if ($instance === null || $instance->status !== WorkflowInstanceStatus::Pending || $instance->current_step_order === null) {
            return false;
        }

        $order = $instance->current_step_order;
        $step = $instance->steps()->where('step_order', $order)->with('assignments.user')->first();

        if ($step === null) {
            return false;
        }

        $action = $step->step_type === WorkflowStepType::Receive
            ? WorkflowActionType::Receive
            : WorkflowActionType::Approve;

        foreach ($step->assignments->where('status', WorkflowAssignmentStatus::Pending) as $assignment) {
            if ($assignment->user === null) {
                continue;
            }

            $this->workflow->act($subject, $assignment->user, $action, 'Processed by seeder.');

            if (($subject->workflowInstance?->refresh()->current_step_order) !== $order) {
                break;
            }
        }

        return true;
    }

    /**
     * @param  WorkflowSubject&Model  $subject
     */
    private function fullyComplete(WorkflowSubject $subject): void
    {
        for ($guard = 0; $guard < 25; $guard++) {
            $instance = $subject->workflowInstance?->refresh();
            if ($instance === null || $instance->status !== WorkflowInstanceStatus::Pending) {
                break;
            }
            if (! $this->completeActiveStep($subject)) {
                break;
            }
        }
    }

    /**
     * @param  WorkflowSubject&Model  $subject
     */
    private function firstPendingActor(WorkflowSubject $subject): ?User
    {
        $instance = $subject->workflowInstance?->refresh();
        if ($instance === null || $instance->current_step_order === null) {
            return null;
        }

        $step = $instance->steps()->where('step_order', $instance->current_step_order)->with('assignments.user')->first();

        return $step?->assignments->firstWhere('status', WorkflowAssignmentStatus::Pending)?->user;
    }

    /**
     * Create a PO via the service layer (for proper allocation validation).
     *
     * @param  array<int, array{line_item_id: int, quantity: int}>  $allocations  [{line_item_id, quantity}]
     */
    private function createPO(PurchaseRequisition $pr, int $supplierIdx, array $allocations): PurchaseOrder
    {
        // Approved PRs must be marked ready for PO before they can be ordered against.
        // ponytail: status only — skip the real Carbone PDF snapshot the mark-ready action attaches, to keep seeding dependency-free.
        if ($pr->status === PurchaseRequisitionStatus::APPROVED) {
            $pr->forceFill(['status' => PurchaseRequisitionStatus::READY_FOR_PO])->save();
        }

        $supplierId = $this->supplierIds[$supplierIdx % $this->supplierIds->count()];

        $items = [];
        foreach ($allocations as $alloc) {
            $lineItem = $pr->lineItems->firstWhere('id', $alloc['line_item_id']);
            $items[] = [
                'line_item_id' => $alloc['line_item_id'],
                'quantity' => $alloc['quantity'],
                'unit_id' => $lineItem?->unit_id ?? $this->unitIds->first(),
                'price' => $lineItem ? (float) $lineItem->price : 100.00,
            ];
        }

        // Mirrors the UI default: the PO's price type defaults to the source
        // PR's price type, occasionally overridden (e.g. a different supplier quote).
        $priceType = fake()->boolean(80)
            ? $pr->price_type->value
            : fake()->randomElement($this->priceTypes);

        return $this->poService->create($pr, [
            'supplier_id' => $supplierId,
            'bill_to_id' => $this->addressIds->random(),
            'ship_to_id' => $this->addressIds->random(),
            'payment_terms' => fake()->randomElement(['Net 30', 'Net 60', 'Cash on Delivery']),
            'currency' => fake()->randomElement(['PHP', 'USD', 'JPY']),
            'expected_delivery_date' => now()->addWeeks(fake()->numberBetween(1, 8))->toDateString(),
            'terms_and_conditions' => 'Net 30 days. FOB Destination.',
            'remarks' => null,
            'price_type' => $priceType,
            'items' => $items,
        ]);
    }

    /**
     * Create an RR via the service layer (for proper receiving validation).
     *
     * @param  array<int, array{purchase_order_item_id: int, quantity_received: int, quantity_rejected?: int}>  $receivings
     */
    private function createRR(PurchaseOrder $po, array $receivings): ReceivingReport
    {
        $items = [];
        foreach ($receivings as $r) {
            $items[] = [
                'purchase_order_item_id' => $r['purchase_order_item_id'],
                'quantity_received' => $r['quantity_received'],
                'quantity_rejected' => $r['quantity_rejected'] ?? 0,
                'remarks' => null,
            ];
        }

        return $this->rrService->create($po, [
            'received_by_id' => $this->logistics->id,
            'received_date' => now()->subDays(fake()->numberBetween(1, 14))->toDateString(),
            'remarks' => null,
            'items' => $items,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    //  Scenario implementations
    // ──────────────────────────────────────────────────────────────────

    /** Scenario 1: Draft PR — just created, not submitted. */
    private function seedScenario1_DraftPR(): void
    {
        $this->createPR('Office supplies replenishment — Q2 2026', [0, 1, 2, 3]);
    }

    /** Scenario 2: PR submitted, currently in review (step 2 approved, waiting on step 3). */
    private function seedScenario2_ReviewingPR(): void
    {
        $pr = $this->createPR('Safety equipment for production floor', [9, 10, 11]);
        $this->approvePRSteps($pr, 2); // Finance + GM approved, waiting on Director
    }

    /** Scenario 3: PR rejected by the director at step 3 with a comment. */
    private function seedScenario3_RejectedPR(): void
    {
        $pr = $this->createPR('Executive office furniture upgrade', [15, 16, 17]);
        $this->rejectPRAtStep($pr, 3, 'Budget allocation for IT equipment exceeded quarterly cap. Please revise quantities and resubmit.');
    }

    /** Scenario 4: Fully approved PR, no PO created yet. */
    private function seedScenario4_ApprovedPR_NoPO(): void
    {
        $pr = $this->createPR('Janitorial supplies — monthly replenishment', [12, 13, 14]);
        $this->fullyApprovePR($pr); // Department head + approvers + receiver
    }

    /** Scenario 5: Approved PR with a draft PO (not yet submitted for approval). */
    private function seedScenario5_ApprovedPR_DraftPO(): void
    {
        $pr = $this->createPR('Ink supply for Flexo Press Line 2', [18, 19, 20, 21]);
        $this->fullyApprovePR($pr);

        $pr->refresh()->load('lineItems');

        // Create PO allocating all line items
        $allocations = $pr->lineItems->map(fn (LineItem $item) => [
            'line_item_id' => $item->id,
            'quantity' => $item->quantity,
        ])->toArray();

        $this->createPO($pr, 0, $allocations);
    }

    /** Scenario 6: Approved PR → PO approved → partially received (2 of 3 items). */
    private function seedScenario6_PO_PartiallyReceived(): void
    {
        $pr = $this->createPR('Manufacturing consumables — bolts & lubricant', [7, 8, 22]);
        $this->fullyApprovePR($pr);

        $pr->refresh()->load('lineItems');

        $allocations = $pr->lineItems->map(fn (LineItem $item) => [
            'line_item_id' => $item->id,
            'quantity' => $item->quantity,
        ])->toArray();

        $po = $this->createPO($pr, 1, $allocations);

        // Approve the PO, then seal it as ordered so it can be received against.
        $this->fullyApprovePO($po);
        $this->markPOAsOrdered($po);
        $po->refresh()->load('items');

        // Partial delivery: receive only the first 2 items partially
        $poItems = $po->items;
        $receivings = [];
        foreach ($poItems->take(2) as $poItem) {
            $receivings[] = [
                'purchase_order_item_id' => $poItem->id,
                'quantity_received' => max(1, (int) floor($poItem->quantity * 0.6)),
                'quantity_rejected' => 0,
            ];
        }

        $this->createRR($po, $receivings);
    }

    /** Scenario 7: Full lifecycle — Approved PR → PO approved → fully received → RR verified. */
    private function seedScenario7_PO_FullyReceived_RR_Verified(): void
    {
        $pr = $this->createPR('Flexographic plate & dispersant for Job #4521', [22, 23]);
        $this->fullyApprovePR($pr);

        $pr->refresh()->load('lineItems');

        $allocations = $pr->lineItems->map(fn (LineItem $item) => [
            'line_item_id' => $item->id,
            'quantity' => $item->quantity,
        ])->toArray();

        $po = $this->createPO($pr, 2, $allocations);
        $this->fullyApprovePO($po);
        $this->markPOAsOrdered($po);
        $po->refresh()->load('items');

        // Full delivery of all items
        $receivings = $po->items->map(fn (PurchaseOrderItem $item) => [
            'purchase_order_item_id' => $item->id,
            'quantity_received' => $item->quantity,
            'quantity_rejected' => 0,
        ])->toArray();

        $rr = $this->createRR($po, $receivings);

        // Verify the RR through the full workflow
        $this->fullyVerifyRR($rr);
    }

    /** Scenario 8: Split PO — one approved PR results in 2 POs to different suppliers. */
    private function seedScenario8_SplitPO_MixedReceiving(): void
    {
        $pr = $this->createPR('Ink & office supply bulk order — split across suppliers', [0, 4, 5, 18, 19, 20, 21]);
        $this->fullyApprovePR($pr);

        $pr->refresh()->load('lineItems');
        $lineItems = $pr->lineItems;

        // PO #1: office items (first 3 line items) to supplier #3
        $officeAllocations = $lineItems->take(3)->map(fn (LineItem $item) => [
            'line_item_id' => $item->id,
            'quantity' => $item->quantity,
        ])->toArray();

        $po1 = $this->createPO($pr, 3, $officeAllocations);
        $this->fullyApprovePO($po1);
        $this->markPOAsOrdered($po1);
        $po1->refresh()->load('items');

        // Fully receive PO #1
        $rr1Receivings = $po1->items->map(fn (PurchaseOrderItem $item) => [
            'purchase_order_item_id' => $item->id,
            'quantity_received' => $item->quantity,
            'quantity_rejected' => 0,
        ])->toArray();

        $rr1 = $this->createRR($po1, $rr1Receivings);
        $this->fullyVerifyRR($rr1);

        // PO #2: ink items (remaining 4 line items) to supplier #4
        $inkAllocations = $lineItems->skip(3)->map(fn (LineItem $item) => [
            'line_item_id' => $item->id,
            'quantity' => $item->quantity,
        ])->toArray();

        $po2 = $this->createPO($pr, 4, $inkAllocations);
        $this->fullyApprovePO($po2);
        $this->markPOAsOrdered($po2);
        $po2->refresh()->load('items');

        // Partially receive PO #2 (first delivery of 2 items)
        $rr2Receivings = $po2->items->take(2)->map(fn (PurchaseOrderItem $item) => [
            'purchase_order_item_id' => $item->id,
            'quantity_received' => max(1, (int) floor($item->quantity * 0.5)),
            'quantity_rejected' => 1,
        ])->toArray();

        $this->createRR($po2, $rr2Receivings);
    }
}
