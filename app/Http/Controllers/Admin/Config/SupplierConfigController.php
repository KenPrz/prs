<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequestForm;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierConfigController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('admin/config/suppliers/index', [
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create(): Response
    {
        return Inertia::render('admin/config/suppliers/create');
    }

    /**
     * Store a newly created supplier.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_person_1' => ['nullable', 'string', 'max:255'],
            'contact_person_2' => ['nullable', 'string', 'max:255'],
            'contact_person_3' => ['nullable', 'string', 'max:255'],
        ]);

        Supplier::create($validated);

        return to_route('admin.config.suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    /**
     * Show the form for editing a supplier.
     */
    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('admin/config/suppliers/edit', [
            'supplier' => $supplier,
        ]);
    }

    /**
     * Update the specified supplier.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_person_1' => ['nullable', 'string', 'max:255'],
            'contact_person_2' => ['nullable', 'string', 'max:255'],
            'contact_person_3' => ['nullable', 'string', 'max:255'],
        ]);

        $supplier->update($validated);

        return to_route('admin.config.suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        // Restrict FKs on purchase_orders and payment_request_forms would 500.
        if ($supplier->purchaseOrders()->exists()
            || PaymentRequestForm::query()->where('supplier_id', $supplier->id)->exists()) {
            return back()->with('error', 'This supplier is referenced by purchase orders or payment request forms and cannot be deleted.');
        }

        $supplier->delete();

        return to_route('admin.config.suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }
}
