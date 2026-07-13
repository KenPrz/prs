<?php

namespace App\Http\Controllers\Api\V1\Lookups;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $departments = Department::orderBy('name')->get();

        return response()->json(['success' => true, 'data' => DepartmentResource::collection($departments)]);
    }
}
