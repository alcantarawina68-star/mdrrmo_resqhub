<x-layouts.dashboard title="Incident {{ $incident->incident_number }}">
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <div class="border border-border bg-surface p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="mono text-lg">{{ $incident->incident_number }}</h2>
                    <x-status-chip :status="$incident->status" />
                    <x-priority-badge :priority="$incident->priority" />
                    <span class="mono ml-auto text-xs text-muted">reported {{ $incident->reported_at?->format('M j, Y g:i A') }}</span>
                </div>
                <h3 class="mt-3 text-xl font-semibold text-fg">{{ $incident->incident_type->label() }}</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-fg/90">{{ $incident->description }}</p>
            </div>

            <div class="border border-border bg-surface">
                <div id="detail-map" class="h-64 bg-bg" role="application" aria-label="Incident location map"></div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="border border-border bg-surface p-5">
                    <p class="panel-title mb-3">Details</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Barangay</dt><dd class="font-medium">{{ $incident->location_label ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Coordinates</dt><dd class="mono">{{ number_format($incident->latitude, 5) }}, {{ number_format($incident->longitude, 5) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Source</dt><dd class="font-medium">{{ $incident->source->label() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Assigned unit</dt><dd class="mono">{{ $incident->assigned_unit ?? '—' }}</dd></div>
                    </dl>
                </div>

                <div class="border border-border bg-surface p-5">
                    <p class="panel-title mb-3">Reporter</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Name</dt><dd class="font-medium">{{ $incident->is_anonymous ? 'Anonymous' : ($incident->reporter?->name ?? '—') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Contact</dt><dd class="mono">{{ $incident->is_anonymous ? '—' : ($incident->reporter?->contact_number ?? '—') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Verified</dt><dd class="mono">{{ $incident->verified_at?->format('M j, g:i A') ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Resolved</dt><dd class="mono">{{ $incident->resolved_at?->format('M j, g:i A') ?? '—' }}</dd></div>
                    </dl>
                </div>
            </div>

            @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder))
                <div class="border border-border bg-surface p-5">
                    <p class="panel-title mb-3">Edit details</p>
                    <form method="POST" action="{{ route('dashboard.incidents.update', $incident) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div class="field">
                            <label class="label" for="edit-type">Incident type</label>
                            <select id="edit-type" name="incident_type" class="select">
                                @foreach (\App\Enums\IncidentType::labels() as $value => $label)
                                    <option value="{{ $value }}" @selected($incident->incident_type->value === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="edit-priority">Priority</label>
                            <select id="edit-priority" name="priority" class="select">
                                @foreach (\App\Enums\Priority::labels() as $value => $label)
                                    <option value="{{ $value }}" @selected($incident->priority->value === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field sm:col-span-2">
                            <label class="label" for="edit-location">Barangay / landmark</label>
                            <input id="edit-location" type="text" name="location_label" class="input" value="{{ $incident->location_label }}">
                        </div>
                        <div class="field sm:col-span-2">
                            <label class="label" for="edit-description">Description</label>
                            <textarea id="edit-description" name="description" rows="4" class="textarea" minlength="20" required>{{ $incident->description }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-secondary">Save changes</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            @if ($incident->status === \App\Enums\IncidentStatus::UnderVerification && auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder))
                <div class="border border-border bg-surface p-5">
                    <p class="panel-title mb-3">Verification</p>
                    <form method="POST" action="{{ route('dashboard.incidents.verify', $incident) }}" class="space-y-4">
                        @csrf
                        <div class="field">
                            <label class="label" for="verify-notes">Notes</label>
                            <textarea id="verify-notes" name="notes" rows="3" class="textarea" placeholder="How was this verified?"></textarea>
                        </div>
                        <div class="field">
                            <label class="label" for="verify-unit">Assigned unit <span class="normal-case">(required to approve)</span></label>
                            <input id="verify-unit" type="text" name="assigned_unit" class="input" placeholder="Rescue 117">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($incident->status !== \App\Enums\IncidentStatus::Rejected && auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder))
                <div class="border border-border bg-surface p-5">
                    <p class="panel-title mb-3">Update status</p>
                    <form method="POST" action="{{ route('dashboard.incidents.status', $incident) }}" class="space-y-4">
                        @csrf
                        <div class="field">
                            <label class="label" for="status-select">New status</label>
                            <select id="status-select" name="status" class="select">
                                @foreach (\App\Enums\IncidentStatus::labels() as $value => $label)
                                    @if ($value !== 'rejected' && $value !== 'under_verification')
                                        <option value="{{ $value }}" @selected($incident->status->value === $value)>{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="status-note">Note</label>
                            <textarea id="status-note" name="note" rows="2" class="textarea" placeholder="Optional note for the log"></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary">Update status</button>
                    </form>
                </div>
            @endif

            <div class="border border-border bg-surface p-5">
                <p class="panel-title mb-3">Status history</p>
                @if ($incident->statusLogs->isEmpty())
                    <p class="text-sm text-muted">No status changes logged yet.</p>
                @else
                    <ol class="space-y-4">
                        @foreach ($incident->statusLogs as $log)
                            <li class="relative border-l border-border pl-4">
                                <p class="text-sm">
                                    <span class="mono text-xs text-muted">{{ $log->created_at->format('M j, g:i A') }}</span>
                                    <span class="ml-2 font-medium">{{ $log->user?->name }}</span>
                                </p>
                                <p class="mt-0.5 text-sm">
                                    <span class="mono">{{ $log->old_status }}</span>
                                    &rarr;
                                    <span class="mono font-semibold">{{ $log->new_status }}</span>
                                </p>
                                @if ($log->note)
                                    <p class="mt-1 text-xs text-muted">{{ $log->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('detailMap', () => ({
                init() {
                    const map = ResqHub.createIncidentMap(document.getElementById('detail-map'), {
                        zoom: 15,
                        zoomControl: false,
                        attributionControl: false,
                        showDetailsLink: false,
                    });
                    map.addIncident(@js([
                        'latitude' => $incident->latitude,
                        'longitude' => $incident->longitude,
                        'status' => $incident->status->value,
                        'status_label' => $incident->status->label(),
                        'priority' => $incident->priority->value,
                    ]));
                    setTimeout(() => map.map.invalidateSize(), 100);
                },
            }));
        });
    </script>
    <div x-data="detailMap()"></div>
</x-layouts.dashboard>
