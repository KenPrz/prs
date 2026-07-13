<?php

namespace App\Http\Controllers\Api\V1\Lookups;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ItemUnitResource;
use App\Models\ItemUnit;
use Illuminate\Http\JsonResponse;

class ItemUnitController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $units = ItemUnit::orderBy('name')->get();

        return response()->json(['success' => true, 'data' => ItemUnitResource::collection($units)]);
    }
}
