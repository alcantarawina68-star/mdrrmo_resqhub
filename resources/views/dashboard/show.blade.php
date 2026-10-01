<x-layouts.dashboard title="Incident {{ $incident->incident_number }}">
    @php
        $canEditDetails = auth()->user()->hasRole(...\App\Enums\UserRole::incidentEditorRoles());
    @endphp

    <a href="{{ route('dashboard.incidents') }}" class="btn btn-tertiary mb-4 !px-0">&larr; Back to incidents</a>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <div class="card p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="mono text-lg">{{ $incident->incident_number }}</h2>
                    <x-status-chip :status="$incident->status" />
                    <span class="mono ml-auto text-xs text-muted">reported {{ $incident->reported_at?->format('M j, Y g:i A') }}</span>
                </div>
                <h3 class="mt-3 text-xl font-semibold text-fg">{{ $incident->incident_type->label() }}</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-fg/90">{{ $incident->description }}</p>
            </div>

            <div class="card relative overflow-hidden">
                <div id="detail-map" class="h-64 bg-bg" role="application" aria-label="Incident location map"></div>
                <div class="absolute right-3 top-3 z-10">
                    <x-map-type-switch label="Map imagery" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="card p-5">
                    <p class="panel-title mb-3">Details</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Barangay</dt><dd class="font-medium">{{ $incident->location_label ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Coordinates</dt><dd class="mono">{{ number_format($incident->latitude, 5) }}, {{ number_format($incident->longitude, 5) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Source</dt><dd class="font-medium">{{ $incident->source->label() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Assigned unit</dt><dd class="mono">{{ $incident->assigned_unit ?? '—' }}</dd></div>
                    </dl>
                </div>

                <div class="card p-5">
                    <p class="panel-title mb-3">Reporter</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Name</dt><dd class="font-medium">{{ $incident->is_anonymous ? 'Anonymous' : ($incident->reporter?->name ?? '—') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Contact</dt><dd class="mono">{{ $incident->is_anonymous ? '—' : ($incident->reporter?->contact_number ?? '—') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Verified</dt><dd class="mono">{{ $incident->verified_at?->format('M j, g:i A') ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Resolved</dt><dd class="mono">{{ $incident->resolved_at?->format('M j, g:i A') ?? '—' }}</dd></div>
                    </dl>
                </div>
            </div>

            @if ($incident->evidence->isNotEmpty())
                <div class="card">
                    <div class="flex items-center justify-between border-b border-border px-5 py-4">
                        <p class="panel-title">Attached evidence</p>
                        <span class="mono text-xs text-muted">{{ $incident->evidence->count() }} item{{ $incident->evidence->count() === 1 ? '' : 's' }}</span>
                    </div>
                    <ul class="divide-y divide-border">
                        @foreach ($incident->evidence as $item)
                            <li class="px-5 py-4">
                                <div class="flex items-start gap-4">
                                    <img src="{{ asset('storage/'.$item->file_path) }}" alt="{{ $item->original_name }}" class="h-24 w-24 shrink-0 rounded border border-border bg-bg object-cover">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ asset('storage/'.$item->file_path) }}" download="{{ $item->original_name }}" class="block truncate text-sm font-medium no-underline hover:underline" aria-label="Download {{ $item->original_name }}">
                                            {{ $item->original_name }}
                                        </a>
                                        <p class="mono mt-0.5 text-xs text-muted">{{ number_format($item->file_size / 1024, 1) }} KB &middot; {{ $item->file_type }}</p>
                                        <div class="mt-2 text-xs">
                                            @if ($item->ai_error)
                                                <span class="text-muted">AI check failed</span>
                                            @elseif ($item->ai_analyzed_at)
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="{{ $item->ai_is_generated ? 'text-danger' : 'text-success' }} font-medium">
                                                        {{ $item->ai_is_generated ? 'AI-generated image' : 'Likely real photo' }}
                                                    </span>
                                                    <span class="mono text-muted">{{ number_format(($item->ai_score ?? 0) * 100, 1) }}% confidence</span>
                                                    <span class="text-muted">Analyzed {{ $item->ai_analyzed_at->format('M j, g:i A') }}</span>
                                                </span>
                                            @else
                                                <span class="text-muted">AI check pending</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($canEditDetails)
                <div
                    class="card p-5"
                    x-data="incidentEditForm(@js(['lat' => old('latitude', $incident->latitude), 'lng' => old('longitude', $incident->longitude)]))"
                >
                    <p class="panel-title mb-3">Edit details</p>
                    <form method="POST" action="{{ route('dashboard.incidents.update', $incident) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div class="field">
                            <label class="label" for="edit-type">Incident type</label>
                            <x-incident-type-select name="incident_type" id="edit-type" :value="$incident->incident_type->value" />
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
                            <div class="flex items-center justify-between gap-2">
                                <label class="label mb-0" for="incident-edit-map">Location</label>
                                <button type="button" class="btn btn-tertiary !px-1 !text-xs" :disabled="locating" @click="useCurrentLocation()">
                                    Use current location
                                </button>
                            </div>
                            <div id="incident-edit-map" class="mt-2 h-64 w-full rounded-lg border border-border bg-bg" role="application" aria-label="Map to correct the incident location"></div>
                            <p class="mt-2 text-xs text-muted">Click the map to move the pin, drag it to fine&#8209;tune, or type the coordinates below.</p>
                            <p class="mt-1 text-xs text-danger" x-show="geoError" x-text="geoError"></p>
                        </div>
                        <div class="field">
                            <label class="label {{ $errors->has('latitude') ? 'text-danger' : '' }}" for="edit-latitude">Latitude</label>
                            <input id="edit-latitude" type="number" name="latitude" class="input mono {{ $errors->has('latitude') ? 'border-danger focus:border-danger' : '' }}" step="any" min="-90" max="90" value="{{ old('latitude', $incident->latitude) }}" x-model="lat" @change="syncMarker" @error('latitude') aria-invalid="true" aria-describedby="edit-latitude-error" @enderror>
                            @error('latitude')
                                <p id="edit-latitude-error" class="text-xs font-medium text-danger" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="field">
                            <label class="label {{ $errors->has('longitude') ? 'text-danger' : '' }}" for="edit-longitude">Longitude</label>
                            <input id="edit-longitude" type="number" name="longitude" class="input mono {{ $errors->has('longitude') ? 'border-danger focus:border-danger' : '' }}" step="any" min="-180" max="180" value="{{ old('longitude', $incident->longitude) }}" x-model="lng" @change="syncMarker" @error('longitude') aria-invalid="true" aria-describedby="edit-longitude-error" @enderror>
                            @error('longitude')
                                <p id="edit-longitude-error" class="text-xs font-medium text-danger" role="alert">{{ $message }}</p>
                            @enderror
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
                <div class="card p-5">
                    <p class="panel-title mb-3">Verification</p>
                    <form method="POST" action="{{ route('dashboard.incidents.verify', $incident) }}" class="space-y-4">
                        @csrf
                        <div class="field">
                            <label class="label" for="verify-notes">Notes</label>
                            <textarea id="verify-notes" name="notes" rows="3" class="textarea" placeholder="How was this verified?"></textarea>
                        </div>
                        <div class="field">
                            <label class="label {{ $errors->has('assigned_unit') ? 'text-danger' : '' }}" for="verify-unit">Assigned unit <span class="normal-case">(required to approve)</span></label>
                            <input id="verify-unit" type="text" name="assigned_unit" class="input {{ $errors->has('assigned_unit') ? 'border-danger focus:border-danger' : '' }}" placeholder="Rescue 117" value="{{ old('assigned_unit') }}" @error('assigned_unit') aria-invalid="true" aria-describedby="verify-unit-error" autofocus @enderror>
                            @error('assigned_unit')
                                <p id="verify-unit-error" class="text-xs font-medium text-danger" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
            @endif

            @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder) && $incident->emergencyContactPhone() && ! $incident->hasSentContactSms())
                <div class="card p-5">
                    <p class="panel-title mb-3">Emergency contact</p>
                    <p class="text-sm text-muted">No SMS has reached the contact on file (<span class="mono">{{ $incident->emergencyContactPhone() }}</span>) yet.</p>
                    <form method="POST" action="{{ route('dashboard.incidents.notify', $incident) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-primary">Notify emergency contact</button>
                    </form>
                </div>
            @endif

            @if ($incident->status !== \App\Enums\IncidentStatus::Rejected && auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder))
                <div class="card p-5">
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

            <div class="card p-5">
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
                        layers: @js(\App\Support\MapLayers::all()),
                        zoom: 15,
                        zoomControl: false,
                        showDetailsLink: false,
                    });
                    map.addIncident(@js([
                        'latitude' => $incident->latitude,
                        'longitude' => $incident->longitude,
                        'status' => $incident->status->value,
                        'status_label' => $incident->status->label(),
                    ]));
                    setTimeout(() => map.map.invalidateSize(), 100);
                },
            }));

            Alpine.data('incidentEditForm', (initial) => ({
                lat: initial.lat ?? '',
                lng: initial.lng ?? '',
                picker: null,
                locating: false,
                geoError: '',
                get hasPoint() {
                    return this.lat !== '' && this.lng !== '' && this.lat !== null && this.lng !== null;
                },
                init() {
                    const el = document.getElementById('incident-edit-map');
                    if (!el) return;

                    this.picker = ResqHub.createLocationPicker(el, {
                        layers: @js(\App\Support\MapLayers::all()),
                        center: this.hasPoint ? [Number(this.lat), Number(this.lng)] : undefined,
                        zoom: 16,
                        onChange: (latlng) => this.setPoint(latlng),
                    });

                    if (this.hasPoint) {
                        this.picker.setPoint({ lat: Number(this.lat), lng: Number(this.lng) });
                    }

                    setTimeout(() => this.picker.map.invalidateSize(), 100);
                },
                setPoint(latlng) {
                    this.lat = Number(latlng.lat.toFixed(7));
                    this.lng = Number(latlng.lng.toFixed(7));
                    this.geoError = '';
                },
                syncMarker() {
                    if (!this.picker || !this.hasPoint) return;

                    this.picker.setPoint({ lat: Number(this.lat), lng: Number(this.lng) });
                },
                useCurrentLocation() {
                    if (!navigator.geolocation) {
                        this.geoError = 'Geolocation is not supported by this browser. Please pin the map manually.';
                        return;
                    }

                    this.locating = true;
                    this.geoError = '';

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.setPoint({ lat: position.coords.latitude, lng: position.coords.longitude });
                            this.picker?.setPoint({ lat: this.lat, lng: this.lng }, 16);
                            this.locating = false;
                        },
                        () => {
                            this.geoError = 'Unable to determine your location. Please pin the map manually.';
                            this.locating = false;
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
                    );
                },
            }));
        });
    </script>
    <div x-data="detailMap()"></div>
</x-layouts.dashboard>
