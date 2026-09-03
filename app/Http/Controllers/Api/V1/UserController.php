<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role') && in_array($request->input('role'), UserRole::values(), true)) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status') && in_array($request->input('status'), UserStatus::values(), true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return ApiResponse::paginated(
            $query->orderBy('name')->paginate($request->integer('per_page', 20))->withQueryString(),
        );
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($user));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return ApiResponse::success(new UserResource($user), 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if ($user->is($request->user()) && $request->hasAny(['role', 'status'])) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role or status.',
            ]);
        }

        $user->update($request->validated());

        return ApiResponse::success(new UserResource($user->fresh()));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $isLastActiveAdmin = $user->hasRole(UserRole::Admin)
            && User::where('role', UserRole::Admin->value)->where('status', UserStatus::Active->value)->count() <= 1;

        if ($isLastActiveAdmin) {
            throw ValidationException::withMessages([
                'user' => 'The last active administrator cannot be deleted.',
            ]);
        }

        $user->delete();

        return ApiResponse::success(['message' => 'User deleted.']);
    }
}
