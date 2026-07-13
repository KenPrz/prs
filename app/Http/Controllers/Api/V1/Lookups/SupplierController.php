<?php

namespace App\Http\Controllers\Api\V1\Lookups;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->search, fn ($q, $s) => $q->whereLike('name', "%{$s}%"))
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => SupplierResource::collection($suppliers)]);
    }
}
