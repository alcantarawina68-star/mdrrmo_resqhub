<x-layouts.dashboard title="Encode Caller Report">
    <div class="grid gap-6 lg:grid-cols-2" x-data="callerForm()">
        <form method="POST" action="{{ route('dashboard.caller.store') }}" class="flex flex-col gap-4 border border-border bg-surface p-6">
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
                <label class="label" for="incident_type">Incident type</label>
                <select id="incident_type" name="incident_type" class="select" required>
                    <option value="">Select type</option>
                    @foreach (\App\Enums\IncidentType::labels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('incident_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="field">
                    <label class="label" for="priority">Priority</label>
                    <select id="priority" name="priority" class="select" required>
                        @foreach (\App\Enums\Priority::labels() as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="location_label">Barangay</label>
                    <select id="location_label" name="location_label" class="select">
                        <option value="">Select barangay</option>
                        @foreach (\App\Support\CamalBarangays::all() as $barangay)
                            <option value="{{ $barangay }}" @selected(old('location_label') === $barangay)>{{ $barangay }}</option>
                        @endforeach
                    </select>
                </div>
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

        <div class="border border-border bg-surface p-4">
            <div class="mb-2 flex items-center justify-between">
                <label class="label" for="caller-map">Tag location</label>
                <button type="button" class="btn btn-tertiary !px-1 !text-xs" :disabled="locating" @click="useMyLocation()">
                    Use my location
                </button>
            </div>
            <div id="caller-map" class="h-72 w-full border border-border bg-bg lg:h-[28rem]" role="application" aria-label="Map to tag caller location"></div>
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
                map: null,
                marker: null,
                locating: false,
                geoError: '',
                init() {
                    const el = document.getElementById('caller-map');
                    if (!el) return;

                    const map = L.map(el, {
                        center: [18.275, 121.675],
                        zoom: 14,
                        scrollWheelZoom: false,
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    }).addTo(map);

                    this.map = map;
                    map.on('click', (event) => this.setPoint(event.latlng));
                },
                setPoint(latlng) {
                    this.lat = latlng.lat.toFixed(7);
                    this.lng = latlng.lng.toFixed(7);

                    if (this.marker) {
                        this.marker.setLatLng(latlng);
                    } else {
                        this.marker = L.marker(latlng, { draggable: true }).addTo(this.map);
                        this.marker.on('dragend', (event) => this.setPoint(event.target.getLatLng()));
                    }
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
                            const latlng = { lat: position.coords.latitude, lng: position.coords.longitude };
                            this.map.setView(latlng, 16);
                            this.setPoint(latlng);
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
