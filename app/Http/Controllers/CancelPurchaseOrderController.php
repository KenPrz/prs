<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Events\Procurement\DocumentCancelled;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CancelPurchaseOrderController extends Controller
{
    /**
     * Cancel an approved (not yet ordered) purchase order instead of sealing it,
     * returning its allocated quantities to the requisition pool.
     */
    public function __invoke(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        // Hard state guard: bypassed policies (Super Admin) must not cancel
        // orders that were never approved or are already in receiving.
        if ($purchaseOrder->status !== PurchaseOrderStatus::APPROVED) {
            return back()->with('error', 'Only an approved (not yet ordered) purchase order can be cancelled here.');
        }

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'A cancellation reason is required.'],
        );

        DB::transaction(function () use ($purchaseOrder, $service, $validated) {
            $purchaseOrder->forceFill(['status' => PurchaseOrderStatus::CANCELLED])->save();

            activity('allocation')
                ->performedOn($purchaseOrder)
                ->withProperties(['reason' => $validated['reason']])
                ->log('deallocated');

            if ($purchaseOrder->purchaseRequisition !== null) {
                $service->syncPurchaseRequisitionFulfillmentStatus($purchaseOrder->purchaseRequisition);
            }
        });

        DocumentCancelled::dispatch($purchaseOrder, $validated['reason']);

        return to_route('purchase-orders.show', $purchaseOrder);
    }
}
