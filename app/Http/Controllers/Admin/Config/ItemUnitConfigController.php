<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\ItemUnit;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemUnitConfigController extends Controller
{
    /**
     * Display a listing of item units.
     */
    public function index(Request $request): Response
    {
        $itemUnits = ItemUnit::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->whereLike('name', "%{$search}%")
                    ->orWhereLike('code', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('admin/config/item-units/index', [
            'itemUnits' => $itemUnits,
        ]);
    }

    /**
     * Show the form for creating a new item unit.
     */
    public function create(): Response
    {
        return Inertia::render('admin/config/item-units/create');
    }

    /**
     * Store a newly created item unit.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:item_units,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        ItemUnit::create($validated);

        return to_route('admin.config.item-units.index')
            ->with('success', 'Item unit created successfully.');
    }

    /**
     * Show the form for editing an item unit.
     */
    public function edit(ItemUnit $itemUnit): Response
    {
        return Inertia::render('admin/config/item-units/edit', [
            'itemUnit' => $itemUnit,
        ]);
    }

    /**
     * Update the specified item unit.
     */
    public function update(Request $request, ItemUnit $itemUnit): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', "unique:item_units,code,{$itemUnit->id}"],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $itemUnit->update($validated);

        return to_route('admin.config.item-units.index')
            ->with('success', 'Item unit updated successfully.');
    }

    /**
     * Remove the specified item unit.
     */
    public function destroy(ItemUnit $itemUnit): RedirectResponse
    {
        // Restrict FKs on line_items and purchase_order_items would 500.
        if ($itemUnit->lineItems()->exists()
            || PurchaseOrderItem::query()->where('unit_id', $itemUnit->id)->exists()) {
            return back()->with('error', 'This unit is used by requisition or purchase order items and cannot be deleted.');
        }

        $itemUnit->delete();

        return to_route('admin.config.item-units.index')
            ->with('success', 'Item unit deleted successfully.');
    }
}
