<?php

use App\Enums\PaymentRequestFormStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\ReceivingReportStatus;
use App\Models\PaymentRequestForm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\ReceivingReport;
use App\Models\User;
use App\Policies\PaymentRequestFormPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequisitionPolicy;
use App\Policies\ReceivingReportPolicy;

// X-01 PR: only the owner of a DRAFT may update/delete
test('requisition update and delete require ownership and draft status', function () {
    $owner = userWithPermission('pr.prepare');
    $other = User::factory()->create();
    $policy = new PurchaseRequisitionPolicy;

    $draft = PurchaseRequisition::factory()->for($owner, 'requestor')->create(['status' => PurchaseRequisitionStatus::DRAFT]);
    $approved = PurchaseRequisition::factory()->for($owner, 'requestor')->create(['status' => PurchaseRequisitionStatus::APPROVED]);

    expect($policy->update($owner, $draft))->toBeTrue()
        ->and($policy->delete($owner, $draft))->toBeTrue()
        ->and($policy->update($other, $draft))->toBeFalse()
        ->and($policy->update($owner, $approved))->toBeFalse();
});

// X-01 PR: only the designated orderer of an APPROVED PR may mark it ready
test('marking ready for PO requires the designated orderer and an approved status', function () {
    $orderer = userWithPermission('pr.finalize');
    $policy = new PurchaseRequisitionPolicy;

    $approved = PurchaseRequisition::factory()->for(User::factory()->create(), 'requestor')->create([
        'status' => PurchaseRequisitionStatus::APPROVED,
        'to_be_ordered_by_id' => $orderer->id,
    ]);

    expect($policy->markReadyForPo($orderer, $approved))->toBeTrue()
        ->and($policy->markReadyForPo(User::factory()->create(), $approved))->toBeFalse();
});

// X-01 PO: update/delete gated on the po.prepare permission and DRAFT status
test('purchase order update and delete require the preparer permission and draft status', function () {
    $preparer = userWithPermission('po.prepare');
    $policy = new PurchaseOrderPolicy;

    $draft = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::DRAFT]);
    $approved = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::APPROVED]);

    expect($policy->update($preparer, $draft))->toBeTrue()
        ->and($policy->delete($preparer, $draft))->toBeTrue()
        ->and($policy->update($preparer, $approved))->toBeFalse()
        ->and($policy->create(User::factory()->create()))->toBeFalse()
        ->and($policy->update(User::factory()->create(), $draft))->toBeFalse();
});

// X-01 PO: sealing requires the preparer permission and an APPROVED order
test('marking a purchase order as ordered requires a preparer and approved status', function () {
    $preparer = userWithPermission('po.finalize', 'po.cancel');
    $policy = new PurchaseOrderPolicy;

    $draft = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::DRAFT]);
    $approved = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::APPROVED]);

    expect($policy->markAsOrdered($preparer, $approved))->toBeTrue()
        ->and($policy->markAsOrdered($preparer, $draft))->toBeFalse()
        ->and($policy->markAsOrdered(User::factory()->create(), $approved))->toBeFalse()
        ->and($policy->cancel($preparer, $approved))->toBeTrue()
        ->and($policy->cancel($preparer, $draft))->toBeFalse();
});

// X-01 PRF: mutating a draft requires the prf.prepare permission AND ownership
test('payment request form update and delete require the preparer permission, ownership and draft status', function () {
    $owner = userWithPermission('prf.prepare');
    $ownerWithoutPermission = User::factory()->create();
    $policy = new PaymentRequestFormPolicy;

    $draft = PaymentRequestForm::factory()->for($owner, 'requestor')->create(['status' => PaymentRequestFormStatus::DRAFT]);
    $reviewing = PaymentRequestForm::factory()->for($owner, 'requestor')->create(['status' => PaymentRequestFormStatus::REVIEWING]);
    $plainDraft = PaymentRequestForm::factory()->for($ownerWithoutPermission, 'requestor')->create(['status' => PaymentRequestFormStatus::DRAFT]);

    expect($policy->update($owner, $draft))->toBeTrue()
        ->and($policy->delete($owner, $draft))->toBeTrue()
        ->and($policy->update(User::factory()->create(), $draft))->toBeFalse()
        ->and($policy->update($owner, $reviewing))->toBeFalse()
        // Owner of the draft, but missing the prf.prepare permission → denied.
        ->and($policy->update($ownerWithoutPermission, $plainDraft))->toBeFalse();
});

// X-01 PRF: cancellation abilities are permission-gated, owner-scoped and status-specific
test('payment request form cancellation is limited to the preparer-owner at the right stage', function () {
    $owner = userWithPermission('prf.cancel');
    $ownerWithoutPermission = User::factory()->create();
    $policy = new PaymentRequestFormPolicy;

    $reviewing = PaymentRequestForm::factory()->for($owner, 'requestor')->create(['status' => PaymentRequestFormStatus::REVIEWING]);
    $approved = PaymentRequestForm::factory()->for($owner, 'requestor')->create(['status' => PaymentRequestFormStatus::APPROVED]);
    $plainApproved = PaymentRequestForm::factory()->for($ownerWithoutPermission, 'requestor')->create(['status' => PaymentRequestFormStatus::APPROVED]);

    // Mid-flow cancel: preparer-owner, while REVIEWING only.
    expect($policy->cancelWorkflow($owner, $reviewing))->toBeTrue()
        ->and($policy->cancelWorkflow(User::factory()->create(), $reviewing))->toBeFalse()
        ->and($policy->cancelWorkflow($owner, $approved))->toBeFalse()
        // Post-approval cancel: preparer-owner, while APPROVED only.
        ->and($policy->cancel($owner, $approved))->toBeTrue()
        ->and($policy->cancel(User::factory()->create(), $approved))->toBeFalse()
        ->and($policy->cancel($owner, $reviewing))->toBeFalse()
        // Owner without the prf.prepare permission → denied.
        ->and($policy->cancel($ownerWithoutPermission, $plainApproved))->toBeFalse();
});

// X-01 RR: update/delete gated on the rr.prepare permission and DRAFT status
test('receiving report update and delete require the preparer permission and draft status', function () {
    $preparer = userWithPermission('rr.prepare');
    $policy = new ReceivingReportPolicy;

    $draft = ReceivingReport::factory()->create(['status' => ReceivingReportStatus::DRAFT]);
    $verified = ReceivingReport::factory()->create(['status' => ReceivingReportStatus::VERIFIED]);

    expect($policy->update($preparer, $draft))->toBeTrue()
        ->and($policy->delete($preparer, $draft))->toBeTrue()
        ->and($policy->update($preparer, $verified))->toBeFalse()
        ->and($policy->update(User::factory()->create(), $draft))->toBeFalse();
});
