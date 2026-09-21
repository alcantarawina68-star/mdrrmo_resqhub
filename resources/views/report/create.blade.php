<x-layouts.app title="Submit a Report">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6" x-data="reportForm()">
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
                    <div id="report-map" class="h-64 border border-border bg-bg sm:h-96" role="application" aria-label="Map to pin incident location"></div>
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
                <div class="border border-border bg-surface p-6">
                    <h2 class="mb-4 text-base font-semibold text-fg">Incident details</h2>

                    <div class="space-y-4">
                        <div class="field">
                            <label class="label" for="incident_type">Incident type</label>
                            <select id="incident_type" name="incident_type" class="select" required>
                                <option value="">Select type</option>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected(old('incident_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label class="label" for="priority">Priority</label>
                            <select id="priority" name="priority" class="select" required>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
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
                            <select id="location_label" name="location_label" class="select">
                                <option value="">Select barangay</option>
                                @foreach ($barangays as $barangay)
                                    <option value="{{ $barangay }}" @selected(old('location_label') === $barangay)>{{ $barangay }}</option>
                                @endforeach
                            </select>
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
                map: null,
                marker: null,
                locating: false,
                submitting: false,
                geoError: '',
                init() {
                    const el = document.getElementById('report-map');
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
</x-layouts.app>
