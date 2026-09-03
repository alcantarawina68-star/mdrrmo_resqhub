<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'role' => UserRole::CommunityUser,
            'status' => UserStatus::Active,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'Your account is suspended or inactive.',
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }
}
