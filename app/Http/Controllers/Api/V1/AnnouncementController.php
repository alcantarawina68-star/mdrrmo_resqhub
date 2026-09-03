<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Http\Responses\ApiResponse;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::query()
            ->currentlyActive()
            ->with('user:id,name')
            ->latest('published_at');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        return ApiResponse::paginated(
            $query->paginate($request->integer('per_page', 20))->withQueryString(),
        );
    }

    public function show(Announcement $announcement): JsonResponse
    {
        if ($announcement->expires_at && $announcement->expires_at->isPast()) {
            return ApiResponse::error('Announcement not found.', 404);
        }

        return ApiResponse::success(new AnnouncementResource($announcement->load('user:id,name')));
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $announcement = $request->user()->announcements()->create($request->validated());

        return ApiResponse::success(
            new AnnouncementResource($announcement->load('user:id,name')),
            201,
        );
    }

    public function update(StoreAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $announcement->update($request->validated());

        return ApiResponse::success(new AnnouncementResource($announcement->load('user:id,name')));
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return ApiResponse::success(['message' => 'Announcement deleted.']);
    }
}
