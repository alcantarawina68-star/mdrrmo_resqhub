<x-layouts.app title="Submit a Report">
    <div class="mx-auto w-full max-w-[1600px] px-4 py-8 sm:px-6" x-data="reportForm()">
        <div class="mb-6">
            <h1>Submit a Report</h1>
            <p class="mt-1 text-sm text-muted">Drop a pin on the map first, then describe what happened.</p>
        </div>

        <form method="POST" action="{{ route('report.store') }}" class="grid gap-6 lg:grid-cols-5" enctype="multipart/form-data" @submit="submitting = true">
            @csrf

            <div class="lg:col-span-3">
                <div class="field">
                    <div class="flex items-center justify-between">
                        <label class="label" for="report-map">Pin the location</label>
                        <button type="button" class="btn btn-tertiary !px-1 !text-xs" :disabled="locating" @click="useMyLocation()">
                            Use my location
                        </button>
                    </div>
                    <div id="report-map" class="h-64 rounded-lg border border-border bg-bg sm:h-96" role="application" aria-label="Map to pin incident location"></div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="label mb-0">Map imagery</span>
                        <x-map-type-switch label="Map imagery" />
                    </div>
                    <p class="text-xs text-muted" x-show="lat && lng">
                        Selected coordinates:
                        <span class="mono" x-text="lat + ', ' + lng"></span>
                    </p>
                    <p class="text-xs text-danger" x-show="geoError" x-text="geoError"></p>
                </div>
                <input type="hidden" name="latitude" :value="lat" required>
                <input type="hidden" name="longitude" :value="lng" required>
                @error('latitude') <span class="mt-1 block text-xs text-danger">{{ $message }}</span> @enderror
                @error('longitude') <span class="mt-1 block text-xs text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="lg:col-span-2">
                <div class="card p-6 lg:sticky lg:top-20">
                    <h2 class="mb-4 text-base font-semibold text-fg">Incident details</h2>

                    <div class="space-y-4">
                        <div class="field">
                            <label class="label" for="incident_type">Incident type</label>
                            <x-incident-type-select name="incident_type" id="incident_type" />
                        </div>

                        @if ($canAssignUnit)
                            <div class="field">
                                <label class="label" for="assigned_unit">Assigned unit <span class="normal-case">(optional)</span></label>
                                <input id="assigned_unit" type="text" name="assigned_unit" class="input" value="{{ old('assigned_unit') }}" placeholder="Rescue 117">
                                <p class="mt-1 text-xs text-muted">This report will be verified immediately.</p>
                            </div>
                        @endif

                        <div class="field">
                            <label class="label" for="location_label">Nearest barangay or landmark</label>
                            <select id="location_label" name="location_label" class="select" x-model="locationLabel">
                                <option value="">Select barangay</option>
                                @foreach ($barangays as $barangay)
                                    <option value="{{ $barangay }}" @selected(old('location_label') === $barangay)>{{ $barangay }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-muted" x-show="locationLabel" x-text="locationHint"></p>
                        </div>

                        <div class="field">
                            <label class="label" for="description">Description</label>
                            <textarea id="description" name="description" rows="6" class="textarea" minlength="20" required placeholder="Describe the incident, what you saw, and anyone who needs help.">{{ old('description') }}</textarea>
                        </div>

                        <div class="field">
                            <label class="label" for="evidence">Image evidence <span class="normal-case">(optional)</span></label>
                            <input id="evidence" type="file" name="evidence" accept="image/jpeg,image/png" class="input">
                            <p class="mt-1 text-xs text-muted">A photo will be checked automatically to confirm it is not AI-generated. JPG or PNG, up to 5 MB.</p>
                            @error('evidence') <span class="mt-1 block text-xs text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="field">
                            <label class="label" for="contact_number">Contact number <span class="normal-case">(optional)</span></label>
                            <input id="contact_number" type="tel" name="contact_number" class="input" value="{{ old('contact_number', auth()->user()->contact_number) }}" placeholder="0917 123 4567">
                        </div>

                        <div class="field">
                            <label class="label" for="emergency_contact">Emergency contact <span class="normal-case">(optional)</span></label>
                            <input id="emergency_contact" type="tel" name="emergency_contact" class="input" value="{{ old('emergency_contact') }}" placeholder="0917 123 4567">
                            <p class="mt-1 text-xs text-muted">This number receives SMS status updates for this report.</p>
                            @error('emergency_contact') <span class="mt-1 block text-xs text-danger">{{ $message }}</span> @enderror
                        </div>

                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" name="is_anonymous" value="1" class="h-4 w-4 accent-[var(--color-primary)]" @checked(old('is_anonymous'))>
                            Report anonymously
                        </label>

                        <button type="submit" class="btn btn-primary w-full" :disabled="submitting">
                            <span x-show="!submitting">Submit Report</span>
                            <span x-show="submitting">Analyzing image...</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('reportForm', () => ({
                lat: '',
                lng: '',
                locationLabel: @js(old('location_label', '')),
                barangayCentroids: @js(\App\Support\BarangayLocations::centroids()),
                maxSuggestionMetres: @js(\App\Support\BarangayLocations::MAX_SUGGESTION_METRES),
                suggestedBarangay: '',
                barangayDistanceMetres: 0,
                picker: null,
                locating: false,
                submitting: false,
                geoError: '',
                init() {
                    const el = document.getElementById('report-map');
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
</x-layouts.app>
