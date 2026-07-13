<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseRequisitionStatus;
use App\Events\Procurement\PurchaseRequisitionReadyForPo;
use App\Models\PurchaseRequisition;
use App\Pdf\BuildPurchaseRequisitionPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MarkPurchaseRequisitionReadyForPoController extends Controller
{
    public function __invoke(
        PurchaseRequisition $purchaseRequisition,
        BuildPurchaseRequisitionPdf $builder,
    ): RedirectResponse {
        $this->authorize('markReadyForPo', $purchaseRequisition);

        // Hard state guard: the policy's status check is bypassed by Super
        // Admin god-mode, so enforce the transition here too.
        if ($purchaseRequisition->status !== PurchaseRequisitionStatus::APPROVED) {
            return back()->with('error', 'Only an approved requisition can be marked ready for PO.');
        }

        // Atomic seal: the media binding and status flip commit together
        // (file I/O stays best-effort; DB state cannot desync).
        DB::transaction(function () use ($purchaseRequisition, $builder) {
            // Clear before building so the builder renders fresh instead of
            // returning a previously bound document.
            $purchaseRequisition->clearMediaCollection('final_document');

            $pdf = $builder->build($purchaseRequisition);

            abort_unless($pdf !== null, 404);

            $filename = $purchaseRequisition->pr_number
                ? sprintf('purchase-requisition-%s.pdf', $purchaseRequisition->pr_number)
                : sprintf('purchase-requisition-%d.pdf', $purchaseRequisition->id);

            $purchaseRequisition->addMediaFromString($pdf)
                ->usingFileName($filename)
                ->toMediaCollection('final_document');

            $purchaseRequisition->forceFill([
                'status' => PurchaseRequisitionStatus::READY_FOR_PO,
            ])->save();
        });

        PurchaseRequisitionReadyForPo::dispatch($purchaseRequisition);

        return to_route('purchase-requisitions.show', $purchaseRequisition);
    }
}
