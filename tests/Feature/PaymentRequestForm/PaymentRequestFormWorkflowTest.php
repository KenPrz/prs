<?php

use App\Enums\WorkflowInstanceStatus;
use App\Events\Workflow\WorkflowCompleted;
use App\Listeners\SnapshotPaymentRequestFormFinalDocument;
use App\Models\PaymentRequestForm;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Pdf\BuildPaymentRequestFormPdf;

/** A workflow instance for the given subject in the given status. */
function prfWorkflowInstance(PaymentRequestForm $prf, WorkflowInstanceStatus $status): WorkflowInstance
{
    $definition = WorkflowDefinition::query()->create([
        'key' => 'prf.default',
        'name' => 'PRF Test',
        'document_type' => 'PRF',
        'version' => 1,
        'is_active' => true,
    ]);

    return WorkflowInstance::query()->create([
        'workflow_definition_id' => $definition->id,
        'version' => 1,
        'subject_type' => $prf->getMorphClass(),
        'subject_id' => $prf->id,
        'status' => $status,
    ]);
}

// PRF-12 the final document is bound when the approval workflow completes
test('completing the approval workflow snapshots the payment request final document', function () {
    $prf = PaymentRequestForm::factory()->for(User::factory()->create(), 'requestor')->create();
    $instance = prfWorkflowInstance($prf, WorkflowInstanceStatus::Approved);

    $builder = Mockery::mock(BuildPaymentRequestFormPdf::class);
    $builder->shouldReceive('build')->once()->andReturn('%PDF-1.4 fake');

    (new SnapshotPaymentRequestFormFinalDocument($builder))
        ->handle(new WorkflowCompleted($instance));

    expect($prf->fresh()->getMedia('final_document'))->toHaveCount(1);
});

// PRF-13 the snapshot is a no-op for non-PRF subjects
test('the payment request snapshot listener ignores other document types', function () {
    $prf = PaymentRequestForm::factory()->for(User::factory()->create(), 'requestor')->create();

    // A builder that must never be called proves the instanceof guard short-circuits.
    $builder = Mockery::mock(BuildPaymentRequestFormPdf::class);
    $builder->shouldNotReceive('build');

    $instance = prfWorkflowInstance($prf, WorkflowInstanceStatus::Approved);
    // Point the instance at a non-PRF subject type.
    $instance->forceFill(['subject_type' => User::class])->save();

    (new SnapshotPaymentRequestFormFinalDocument($builder))
        ->handle(new WorkflowCompleted($instance->fresh()));

    expect($prf->fresh()->getMedia('final_document'))->toHaveCount(0);
});
