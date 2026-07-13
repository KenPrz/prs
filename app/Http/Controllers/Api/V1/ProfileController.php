<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load('departments', 'activeSignature');

        return $this->success(new UserResource($user));
    }
}
