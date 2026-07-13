<?php

namespace App\Http\Controllers\Api\V1\Lookups;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkflowResource;
use App\Models\WorkflowDefinition;
use Illuminate\Http\JsonResponse;

class WorkflowController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $workflows = WorkflowDefinition::where('is_active', true)->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => WorkflowResource::collection($workflows)]);
    }
}
