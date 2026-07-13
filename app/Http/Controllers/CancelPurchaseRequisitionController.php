<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseRequisitionStatus;
use App\Events\Procurement\DocumentCancelled;
use App\Models\PurchaseRequisition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelPurchaseRequisitionController extends Controller
{
    /**
     * Cancel an approved requisition (before or after it was marked ready for PO)
     * that has no active purchase orders.
     */
    public function __invoke(Request $request, PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        $this->authorize('cancel', $purchaseRequisition);

        // Hard state guard (Super Admin bypasses the policy's status checks).
        if (! \in_array($purchaseRequisition->status, [PurchaseRequisitionStatus::APPROVED, PurchaseRequisitionStatus::READY_FOR_PO], true)) {
            return back()->with('error', 'Only an approved or ready-for-PO requisition can be cancelled here.');
        }

        if ($purchaseRequisition->purchaseOrders()->active()->exists()) {
            return back()->with('error', 'Cancel or complete the active purchase orders first.');
        }

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'A cancellation reason is required.'],
        );

        $purchaseRequisition->forceFill(['status' => PurchaseRequisitionStatus::CANCELLED])->save();

        activity()
            ->performedOn($purchaseRequisition)
            ->withProperties(['reason' => $validated['reason']])
            ->log('cancelled');

        DocumentCancelled::dispatch($purchaseRequisition, $validated['reason']);

        return to_route('purchase-requisitions.show', $purchaseRequisition);
    }
}
