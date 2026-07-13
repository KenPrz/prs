<?php

use App\Enums\WorkflowActionType;
use App\Enums\WorkflowAssigneeType;
use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowStepType;
use App\Services\CarboneClient;
use App\Services\Workflow\WorkflowManager;
use Database\Seeders\DocumentSeeder;

/*
|--------------------------------------------------------------------------
| Signature stamping
|--------------------------------------------------------------------------
| Rendered documents carry the signers' uploaded signature images: the app
| swaps the templates' sig_{slot}.png placeholder bytes before the template
| is sent to Carbone. Slots without a signer keep the transparent
| placeholder (which renders as nothing).
*/

function tinyPngBytes(): string
{
    $img = imagecreatetruecolor(80, 30);
    imagefill($img, 0, 0, imagecolorallocate($img, 10, 10, 10));
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}

test('approved signatures are stamped into the rendered template and empty slots stay blank', function () {
    $this->seed(DocumentSeeder::class);

    $finance = roleApprover('finance_manager');
    $finance->activeSignature->addMediaFromString(tinyPngBytes())
        ->usingFileName('sig.png')
        ->toMediaCollection('attachments');

    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager = app(WorkflowManager::class);
    $manager->start($pr, null, $pr->requestor);
    $manager->act($pr, $finance, WorkflowActionType::Approve); // PR → APPROVED

    $captured = null;
    $this->mock(CarboneClient::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('render')->andReturnUsing(function ($template) use (&$captured) {
            $captured = $template;

            return '%PDF-1.4 fake';
        });
    });

    $this->actingAs(userWithPermission('pr.view'))
        ->get(route('purchase-requisitions.render', $pr))
        ->assertOk();

    expect($captured)->not->toBeNull();

    $tmp = tempnam(sys_get_temp_dir(), 'sig-test-');
    file_put_contents($tmp, $captured);
    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue();

    // The approver signed → the approved_by slot carries their signature bytes.
    $approved = $zip->getFromName('word/media/sig_approved_by.png');
    // The requestor never uploaded a signature and the PR is not yet ready
    // for PO → these slots keep the small transparent placeholder.
    $requested = $zip->getFromName('word/media/sig_requested_by.png');
    $received = $zip->getFromName('word/media/sig_received_by.png');
    $zip->close();
    @unlink($tmp);

    expect(strlen($approved))->toBeGreaterThan(500)
        ->and(strlen($requested))->toBeLessThan(500)
        ->and(strlen($received))->toBeLessThan(500);
});
