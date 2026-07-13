<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Events\Procurement\PurchaseOrderReleased;
use App\Models\PurchaseOrder;
use App\Pdf\BuildPurchaseOrderPdf;
use App\Services\ReceivingReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MarkPurchaseOrderAsOrderedController extends Controller
{
    /**
     * Seal an approved purchase order as ordered: snapshot its final PDF and
     * open it for receiving.
     */
    public function __invoke(
        PurchaseOrder $purchaseOrder,
        BuildPurchaseOrderPdf $builder,
        ReceivingReportService $receivingReportService,
    ): RedirectResponse {
        $this->authorize('markAsOrdered', $purchaseOrder);

        // Hard state guard: the policy's status check is bypassed by Super
        // Admin god-mode, so enforce the transition here too.
        if ($purchaseOrder->status !== PurchaseOrderStatus::APPROVED) {
            return back()->with('error', 'Only an approved purchase order can be marked as ordered.');
        }

        // Atomic seal: the media binding, status flip and receiving re-sync
        // commit together (file I/O stays best-effort; DB state cannot desync).
        DB::transaction(function () use ($purchaseOrder, $builder, $receivingReportService) {
            // Clear before building so the builder renders fresh instead of
            // returning a previously bound document.
            $purchaseOrder->clearMediaCollection('final_document');

            $pdf = $builder->build($purchaseOrder);

            abort_unless($pdf !== null, 404);

            $filename = $purchaseOrder->po_number
                ? sprintf('purchase-order-%s.pdf', $purchaseOrder->po_number)
                : sprintf('purchase-order-%d.pdf', $purchaseOrder->id);

            $purchaseOrder->addMediaFromString($pdf)
                ->usingFileName($filename)
                ->toMediaCollection('final_document');

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::RELEASED,
            ])->save();

            // Re-evaluate receiving state (e.g. every item already short-closed).
            $receivingReportService->syncPurchaseOrderReceivingStatus($purchaseOrder);
        });

        PurchaseOrderReleased::dispatch($purchaseOrder);

        return to_route('purchase-orders.show', $purchaseOrder);
    }
}
