<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
}
