<?php

namespace App\Http\Controllers;

use App\Events\Procurement\ItemShortClosed;
use App\Models\PurchaseOrderItem;
use App\Services\ReceivingReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TogglePurchaseOrderItemOmissionController extends Controller
{
    public function __invoke(Request $request, PurchaseOrderItem $purchaseOrderItem, ReceivingReportService $service): RedirectResponse
    {
        $purchaseOrder = $purchaseOrderItem->purchaseOrder;

        $this->authorize('omitItem', $purchaseOrder);

        $validated = $request->validate([
            'omit' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $purchaseOrderItem, $purchaseOrder, $service) {
            if (! $validated['omit'] && $purchaseOrderItem->is_omitted) {
                $this->assertRestorable($purchaseOrderItem);
            }

            $purchaseOrderItem->forceFill($validated['omit']
                ? ['omitted_at' => now(), 'omit_reason' => $validated['reason'] ?? null]
                : ['omitted_at' => null, 'omit_reason' => null],
            )->save();

            // Re-close the order: omitting the last unreceived item can complete it
            // (and vice-versa). Cascades to the requisition's fulfillment status,
            // so the freed / re-reserved allocation is reflected immediately.
            $service->syncPurchaseOrderReceivingStatus($purchaseOrder);
        });

        if ($validated['omit']) {
            ItemShortClosed::dispatch($purchaseOrder, $purchaseOrderItem->lineItem?->name ?? 'Item', $validated['reason'] ?? null);
        }

        return back();
    }

    /**
     * Restoring an omitted item re-reserves its quantity on the PR line — reject
     * when the line was omitted meanwhile or the freed quantity was re-ordered.
     *
     * @throws ValidationException
     */
    private function assertRestorable(PurchaseOrderItem $purchaseOrderItem): void
    {
        // Lock the PR line so a concurrent PO can't claim the same quantity
        // between our availability check and the restore. No-op on SQLite.
        $lineItem = $purchaseOrderItem->lineItem()->lockForUpdate()->first();

        if (! $lineItem) {
            return;
        }

        // An omitted PR line only waives its unallocated remainder — restoring
        // an already-ordered item re-reserves quantity and stays consistent,
        // so only the capacity check below applies.

        // The omitted item itself is excluded from quantity_allocated, so this is
        // the pool available for it to re-enter.
        $available = $lineItem->quantity - $lineItem->quantity_allocated;

        if ($purchaseOrderItem->quantity > $available) {
            throw ValidationException::withMessages([
                'omit' => "Cannot restore this item. Only {$available} of '{$lineItem->name}' remain unallocated on the requisition.",
            ]);
        }
    }
}
