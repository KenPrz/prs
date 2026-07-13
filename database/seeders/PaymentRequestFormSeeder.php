<?php

namespace Database\Seeders;

use App\Contracts\WorkflowSubject;
use App\Enums\PaymentRequestFormStatus;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowAssignmentStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\Document;
use App\Models\PaymentRequestForm;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Workflow\WorkflowManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentRequestFormSeeder extends Seeder
{
    private WorkflowManager $manager;

    private ?int $documentId;

    private ?int $companyProfileId;

    /** Requestor — a PRF preparer (only they may create payment request forms). */
    private User $requestor;

    /** Approvers resolved by role name. */
    private User $financeManager;

    private User $generalManager;

    private User $director;

    private User $president;

    /**
     * Run the database seeds.
     *
     * Creates 6 realistic payment request scenarios that cover every workflow state:
     *
     * 1. Draft PRF — freight invoice, untouched
     * 2. PRF submitted, currently being reviewed (step 2 of 4)
     * 3. PRF rejected by the director at step 3
     * 4. Fully approved PRF, stamped by accounts payable
     * 5. Fully approved recurring/monthly supply invoice, stamped
     * 6. Cancelled PRF — duplicate invoice caught before submission
     */
    public function run(): void
    {
        $this->manager = app(WorkflowManager::class);

        $this->resolveSharedLookups();
        $this->resolveApprovers();

        DB::transaction(function () {
            $this->seedScenario1_DraftFreightInvoice();
            $this->seedScenario2_ReviewingPackagingInvoice();
            $this->seedScenario3_RejectedToolsInvoice();
            $this->seedScenario4_ApprovedRawMaterialsInvoice();
            $this->seedScenario5_ApprovedRecurringChemicalSupply();
            $this->seedScenario6_CancelledDuplicateInvoice();
        });
    }

    // ──────────────────────────────────────────────────────────────────
    //  Shared helpers
    // ──────────────────────────────────────────────────────────────────

    private function resolveSharedLookups(): void
    {
        $document = Document::defaultPaymentRequestFormTemplate();
        $this->documentId = $document?->id;
        $this->companyProfileId = CompanyProfile::query()->value('id');
    }

    private function resolveApprovers(): void
    {
        $this->requestor = User::permission('prf.prepare')->firstOrFail();
        $this->financeManager = User::whereHas('roles', fn ($q) => $q->where('name', 'finance_manager'))->firstOrFail();
        $this->generalManager = User::whereHas('roles', fn ($q) => $q->where('name', 'general_manager'))->firstOrFail();
        $this->director = User::whereHas('roles', fn ($q) => $q->where('name', 'director'))->firstOrFail();
        $this->president = User::whereHas('roles', fn ($q) => $q->where('name', 'president'))->firstOrFail();
    }

    /**
     * Create a PRF for the given supplier/department with the given column overrides.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createPrf(string $supplierName, string $departmentCode, array $overrides): PaymentRequestForm
    {
        $supplier = Supplier::query()->where('name', $supplierName)->firstOrFail();
        $department = Department::query()->where('code', $departmentCode)->first();

        /** @var PaymentRequestForm $prf */
        $prf = PaymentRequestForm::query()->create(array_merge([
            'requestor_id' => $this->requestor->id,
            'supplier_id' => $supplier->id,
            'company_profile_id' => $this->companyProfileId,
            'document_id' => $this->documentId,
            'status' => PaymentRequestFormStatus::DRAFT,
        ], $overrides));

        if ($department) {
            $prf->departments()->sync([$department->id]);
        }

        return $prf;
    }

    /** Start a PRF workflow then complete the first $steps active steps. */
    private function approveSteps(PaymentRequestForm $prf, int $steps): void
    {
        $this->manager->start($prf, 'prf.default', $this->requestor);

        for ($i = 0; $i < $steps; $i++) {
            if (! $this->completeActiveStep($prf)) {
                break;
            }
        }
    }

    /** Start a PRF workflow, advance to a step, then reject it. */
    private function rejectAtStep(PaymentRequestForm $prf, int $rejectAtStep, string $reason): void
    {
        $this->manager->start($prf, 'prf.default', $this->requestor);

        for ($i = 0; $i < $rejectAtStep - 1; $i++) {
            $this->completeActiveStep($prf);
        }

        $actor = $this->firstPendingActor($prf);
        if ($actor !== null) {
            $this->manager->act($prf, $actor, WorkflowActionType::Reject, $reason);
        }
    }

    /**
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

        foreach ($step->assignments->where('status', WorkflowAssignmentStatus::Pending) as $assignment) {
            if ($assignment->user === null) {
                continue;
            }

            $this->manager->act($subject, $assignment->user, WorkflowActionType::Approve, 'Approved by seeder.');

            if (($subject->workflowInstance?->refresh()->current_step_order) !== $order) {
                break;
            }
        }

        return true;
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

    // ──────────────────────────────────────────────────────────────────
    //  Scenario implementations
    // ──────────────────────────────────────────────────────────────────

    /** Scenario 1: Draft PRF — freight invoice, just created, not submitted. */
    private function seedScenario1_DraftFreightInvoice(): void
    {
        $this->createPrf('Veridian Logistics Partners', 'LOG', [
            'description' => 'Freight and delivery charges for Job Order #2026-0456 (Calamba to Cavite warehouse transfer).',
            'amount' => 45320.00,
            'invoice_number' => 'INV-88213',
            'due_date' => now()->addDays(15)->toDateString(),
        ]);
    }

    /** Scenario 2: PRF submitted, currently in review (step 2 approved, waiting on step 3). */
    private function seedScenario2_ReviewingPackagingInvoice(): void
    {
        $prf = $this->createPrf('Orchid Paper & Packaging', 'OPS', [
            'description' => 'Substrate paper and corrugated packaging delivery for March production run.',
            'amount' => 128540.75,
            'invoice_number' => 'INV-OPP-30219',
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->approveSteps($prf, 2); // Finance + GM approved, waiting on Director
    }

    /** Scenario 3: PRF rejected by the director at step 3 with a comment. */
    private function seedScenario3_RejectedToolsInvoice(): void
    {
        $prf = $this->createPrf('Summit Tools & Equipment', 'OPS', [
            'description' => 'Replacement power tools for maintenance team — invoice billed against PO #2026-014.',
            'amount' => 76200.00,
            'invoice_number' => 'INV-STE-55102',
            'due_date' => now()->addDays(20)->toDateString(),
        ]);

        $this->rejectAtStep(
            $prf,
            3,
            'Invoice amount does not match the approved purchase order total. Please reconcile with Purchasing before resubmission.',
        );
    }

    /** Scenario 4: Fully approved PRF, stamped by accounts payable for payment. */
    private function seedScenario4_ApprovedRawMaterialsInvoice(): void
    {
        $prf = $this->createPrf('Cedar & Co. Manufacturing', 'FIN', [
            'description' => 'Raw material delivery (steel hex bolts, industrial lubricant) — three-month supply agreement, batch 2.',
            'amount' => 310750.50,
            'invoice_number' => 'INV-CCM-91044',
            'due_date' => now()->addDays(10)->toDateString(),
            'stamp_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->approveSteps($prf, 4);
    }

    /** Scenario 5: Fully approved recurring monthly chemical supply invoice, stamped. */
    private function seedScenario5_ApprovedRecurringChemicalSupply(): void
    {
        $prf = $this->createPrf('Westlake Chemicals', 'OPS', [
            'description' => 'Recurring monthly billing — industrial solvent and dispersant supply, account #WC-4471.',
            'amount' => 89215.25,
            'invoice_number' => 'INV-WC-04471-0626',
            'due_date' => now()->addDays(7)->toDateString(),
            'stamp_date' => now()->subDay()->toDateString(),
        ]);

        $this->approveSteps($prf, 4);
    }

    /** Scenario 6: Cancelled PRF — duplicate invoice caught by accounting before submission. */
    private function seedScenario6_CancelledDuplicateInvoice(): void
    {
        $this->createPrf('Pioneer Fasteners', 'FIN', [
            'description' => 'Duplicate submission of invoice INV-PF-22871, already paid under PRF 2026-014. Cancelled by accounting.',
            'amount' => 18450.00,
            'invoice_number' => 'INV-PF-22871',
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => PaymentRequestFormStatus::CANCELLED,
        ]);
    }
}
