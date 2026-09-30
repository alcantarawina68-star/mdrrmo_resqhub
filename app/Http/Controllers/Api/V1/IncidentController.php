<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CallerIncidentRequest;
use App\Http\Requests\ProcessVerificationRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Http\Requests\UpdateIncidentStatusRequest;
use App\Http\Resources\IncidentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents) {}

    public function index(Request $request): JsonResponse
    {
        $query = Incident::query()
            ->publiclyVisible()
            ->with('reporter:id,name,contact_number')
            ->latest('reported_at');

        $this->applyFilters($query, $request);

        $limit = $request->integer('limit');

        if ($limit > 0) {
            return ApiResponse::success(
                IncidentResource::collection($query->limit(min($limit, 200))->get()),
            );
        }

        return ApiResponse::paginated(
            $query->paginate($request->integer('per_page', 20))->withQueryString(),
        );
    }

    public function show(Request $request, Incident $incident): JsonResponse
    {
        if (! in_array($incident->status, IncidentStatus::publiclyVisible(), true)) {
            return ApiResponse::error('Incident not found.', 404);
        }

        $incident->load('reporter:id,name,contact_number', 'evidence');

        return ApiResponse::success(new IncidentResource($incident));
    }

    public function my(Request $request): JsonResponse
    {
        $incidents = $request->user()
            ->incidents()
            ->latest('reported_at')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return ApiResponse::paginated($incidents);
    }

    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $incident = $this->incidents->createOnline($request->user(), $request->validated());

        return ApiResponse::success(
            new IncidentResource($incident->load('reporter:id,name,contact_number')),
            201,
        );
    }

    public function callerBased(CallerIncidentRequest $request): JsonResponse
    {
        $incident = $this->incidents->createCallerBased($request->user(), $request->validated());

        return ApiResponse::success(
            new IncidentResource($incident->load('reporter:id,name,contact_number')),
            201,
        );
    }

    public function verify(ProcessVerificationRequest $request, Incident $incident): JsonResponse
    {
        $approved = $request->input('action') === 'approve';

        $incident = $this->incidents->processVerification(
            $request->user(),
            $incident,
            $approved,
            $request->input('notes'),
            $request->input('assigned_unit'),
        );

        return ApiResponse::success(new IncidentResource($incident->load('reporter:id,name,contact_number')));
    }

    public function updateStatus(UpdateIncidentStatusRequest $request, Incident $incident): JsonResponse
    {
        $status = IncidentStatus::tryFrom($request->input('status'));

        if ($status === IncidentStatus::Rejected) {
            return ApiResponse::error('Rejection must go through the verification flow.', 422);
        }

        $incident = $this->incidents->updateStatus(
            $request->user(),
            $incident,
            $status,
            $request->input('note'),
        );

        $incident->load('reporter:id,name,contact_number');

        if ($request->filled('assigned_unit')) {
            $incident = $this->incidents->assignUnit($request->user(), $incident, $request->input('assigned_unit'));
        }

        return ApiResponse::success(new IncidentResource($incident));
    }

    public function update(UpdateIncidentRequest $request, Incident $incident): JsonResponse
    {
        $incident = $this->incidents->updateDetails($request->user(), $incident, $request->validated());

        return ApiResponse::success(new IncidentResource($incident->load('reporter:id,name,contact_number')));
    }

    public function destroy(Request $request, Incident $incident): JsonResponse
    {
        $incident->delete();

        return ApiResponse::success(['message' => 'Incident deleted.']);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('incident_type') && in_array($request->input('incident_type'), IncidentType::values(), true)) {
            $query->where('incident_type', $request->input('incident_type'));
        }

        if ($request->filled('status') && in_array($request->input('status'), IncidentStatus::values(), true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('barangay')) {
            $query->where('location_label', $request->input('barangay'));
        }
    }
}
