<x-layouts.dashboard title="Encode Caller Report">
    <x-page-header description="Turn a phone call into an incident. Reports are verified automatically once saved." />

    <div class="grid gap-6 lg:grid-cols-2" x-data="callerForm()">
        <form method="POST" action="{{ route('dashboard.caller.store') }}" class="card flex flex-col gap-4 p-6">
            @csrf

            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-fg">Caller information</h2>
                <span class="mono text-xs text-muted">target &lt; 90s</span>
            </div>

            <div class="field">
                <label class="label" for="caller_name">Caller name <span class="normal-case">(optional)</span></label>
                <input id="caller_name" type="text" name="caller_name" class="input" value="{{ old('caller_name') }}">
            </div>

            <div class="field">
                <label class="label" for="caller_contact">Contact number <span class="normal-case">(optional)</span></label>
                <input id="caller_contact" type="tel" name="caller_contact" class="input" value="{{ old('caller_contact') }}" placeholder="0917 123 4567">
            </div>

            <div class="field">
                <label class="label" for="emergency_contact">Emergency contact <span class="normal-case">(optional)</span></label>
                <input id="emergency_contact" type="tel" name="emergency_contact" class="input" value="{{ old('emergency_contact') }}" placeholder="0917 123 4567">
                <p class="mt-1 text-xs text-muted">This number receives SMS status updates for this report.</p>
            </div>

            <div class="field">
                <label class="label" for="incident_type">Incident type</label>
                <x-incident-type-select name="incident_type" id="incident_type" />
            </div>

            <div class="field">
                <label class="label" for="location_label">Barangay</label>
                <select id="location_label" name="location_label" class="select" x-model="locationLabel">
                    <option value="">Select barangay</option>
                    @foreach (\App\Support\CamalBarangays::all() as $barangay)
                        <option value="{{ $barangay }}" @selected(old('location_label') === $barangay)>{{ $barangay }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted" x-show="locationLabel" x-text="locationHint"></p>
            </div>

            <div class="field">
                <label class="label" for="description">Narrative</label>
                <textarea id="description" name="description" rows="5" class="textarea" minlength="20" required placeholder="What happened, where, who needs help.">{{ old('description') }}</textarea>
            </div>

            <div class="field">
                <label class="label" for="assigned_unit">Assigned unit <span class="normal-case">(optional)</span></label>
                <input id="assigned_unit" type="text" name="assigned_unit" class="input" value="{{ old('assigned_unit') }}" placeholder="Rescue 117">
                <p class="mt-1 text-xs text-muted">This report will be verified automatically.</p>
            </div>

            <div class="field">
                <label class="label">Coordinates</label>
                <p class="mono text-sm text-muted" x-text="lat && lng ? lat + ', ' + lng : 'Click the map to pin the location'"></p>
            </div>
            <input type="hidden" name="latitude" :value="lat" required>
            <input type="hidden" name="longitude" :value="lng" required>
            @error('latitude') <p class="text-xs text-danger">{{ $message }}</p> @enderror
            @error('longitude') <p class="text-xs text-danger">{{ $message }}</p> @enderror

            <button type="submit" class="btn btn-primary w-full">Save Report</button>
        </form>

        <div class="card sticky top-20 self-start p-4">
            <div class="mb-2 flex items-center justify-between">
                <label class="label" for="caller-map">Tag location</label>
                <button type="button" class="btn btn-tertiary !px-1 !text-xs" :disabled="locating" @click="useMyLocation()">
                    Use my location
                </button>
            </div>
            <div id="caller-map" class="h-72 w-full rounded-lg border border-border bg-bg lg:h-[calc(100dvh-14rem)]" role="application" aria-label="Map to tag caller location"></div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="label mb-0">Map imagery</span>
                <x-map-type-switch label="Map imagery" />
            </div>
            <p class="mt-1 text-xs text-muted" x-show="lat && lng">
                <span class="mono" x-text="lat + ', ' + lng"></span>
            </p>
            <p class="mt-1 text-xs text-danger" x-show="geoError" x-text="geoError"></p>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('callerForm', () => ({
                lat: '',
                lng: '',
                locationLabel: @js(old('location_label', '')),
                barangayCentroids: @js(\App\Support\BarangayLocations::centroids()),
                maxSuggestionMetres: @js(\App\Support\BarangayLocations::MAX_SUGGESTION_METRES),
                suggestedBarangay: '',
                barangayDistanceMetres: 0,
                picker: null,
                locating: false,
                geoError: '',
                init() {
                    const el = document.getElementById('caller-map');
                    if (!el) return;

                    this.picker = ResqHub.createLocationPicker(el, {
                        layers: @js(\App\Support\MapLayers::all()),
                        onChange: (latlng) => this.setPoint(latlng),
                    });
                },
                setPoint(latlng) {
                    this.lat = latlng.lat.toFixed(7);
                    this.lng = latlng.lng.toFixed(7);
                    this.suggestBarangay();
                },
                suggestBarangay() {
                    const match = ResqHub.nearestBarangay(
                        this.barangayCentroids,
                        { lat: Number(this.lat), lng: Number(this.lng) },
                        this.maxSuggestionMetres,
                    );

                    if (!match) {
                        this.suggestedBarangay = '';
                        return;
                    }

                    this.suggestedBarangay = match.name;
                    this.barangayDistanceMetres = match.distance_metres;
                    this.locationLabel = match.name;
                },
                get locationHint() {
                    if (!this.locationLabel) return '';
                    if (!this.suggestedBarangay) {
                        return 'No barangay matched this pin — please pick one yourself.';
                    }

                    const distance = this.barangayDistanceMetres < 1000
                        ? `${Math.round(this.barangayDistanceMetres)} m`
                        : `${(this.barangayDistanceMetres / 1000).toFixed(1)} km`;

                    if (this.suggestedBarangay !== this.locationLabel) {
                        return `Pin is nearest to ${this.suggestedBarangay} (${distance}). Kept your choice: ${this.locationLabel}.`;
                    }

                    return `Filled in from the pin, ${distance} from the centre of ${this.locationLabel}. Change it if the pin looks wrong.`;
                },
                useMyLocation() {
                    if (!navigator.geolocation) {
                        this.geoError = 'Geolocation is not supported by this browser.';
                        return;
                    }

                    this.locating = true;
                    this.geoError = '';

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.picker.setPoint({ lat: position.coords.latitude, lng: position.coords.longitude }, 16);
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
</x-layouts.dashboard>
