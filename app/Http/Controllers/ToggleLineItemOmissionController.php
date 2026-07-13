<?php

namespace App\Http\Controllers;

use App\Events\Procurement\ItemShortClosed;
use App\Models\LineItem;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ToggleLineItemOmissionController extends Controller
{
    public function __invoke(Request $request, LineItem $lineItem, PurchaseOrderService $service): RedirectResponse
    {
        $purchaseRequisition = $lineItem->purchaseRequisition;

        $this->authorize('omitLineItem', $purchaseRequisition);

        $validated = $request->validate([
            'omit' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Omitting an allocated line is a partial short-close: quantities on
        // active purchase orders remain ordered/receivable — only the
        // unallocated remainder is waived (see the PR fulfillment sync).
        $lineItem->forceFill($validated['omit']
            ? ['omitted_at' => now(), 'omit_reason' => $validated['reason'] ?? null]
            : ['omitted_at' => null, 'omit_reason' => null],
        )->save();

        // Re-close the requisition: omitting the last unordered line can complete it (and vice-versa).
        $service->syncPurchaseRequisitionFulfillmentStatus($purchaseRequisition);

        if ($validated['omit']) {
            ItemShortClosed::dispatch($purchaseRequisition, $lineItem->name, $validated['reason'] ?? null);
        }

        return back();
    }
}
