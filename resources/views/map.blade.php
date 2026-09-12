<x-layouts.app title="Live Incident Map">
    <div
        class="flex h-[calc(100dvh-4rem)] flex-col lg:flex-row"
        x-data="mapPage()"
        @keydown.escape.window="selected = null; $store.bottomSheet.close()"
    >
        <div class="relative isolate min-h-[45vh] flex-1 sm:min-h-[55vh] lg:min-h-0">
            <div id="incident-map" class="absolute inset-0" role="application" aria-label="Incident map"></div>

            <div x-show="loading" x-cloak class="absolute inset-0 z-[500] flex items-center justify-center bg-bg text-muted">
                <div class="flex flex-col items-center gap-3">
                    <div class="skeleton-line h-4 w-32"></div>
                    <div class="skeleton-line h-3 w-48"></div>
                </div>
            </div>

            <div x-show="!loading && !mapReady" x-cloak class="absolute inset-0 z-[500] flex items-center justify-center bg-bg">
                <div class="text-center">
                    <p class="text-sm text-muted">Map unavailable. Coordinates are still captured.</p>
                    <button type="button" class="btn btn-tertiary mt-2" @click="boot()">Reload Map</button>
                </div>
            </div>

            <div x-show="selected" x-cloak
                class="absolute bottom-20 left-4 z-[600] w-[calc(100%-2rem)] max-w-sm border border-border bg-surface p-3 shadow-lg sm:left-4 sm:p-4 lg:bottom-4 relative">
                <button type="button" class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center text-muted hover:text-fg"
                    @click="selected = null" aria-label="Close details">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
                <template x-if="selected">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="mono text-muted" x-text="selected.incident_number"></span>
                            <span class="chip" :class="'chip-' + selected.status" x-text="selected.status_label"></span>
                        </div>
                        <h3 class="mt-2" x-text="selected.incident_type_label"></h3>
                        <p class="mt-1 text-sm text-muted" x-text="selected.location_label"></p>
                        <p class="mt-2 line-clamp-3 text-sm" x-text="selected.description"></p>
                        <a :href="'/incidents/' + selected.id" class="btn btn-tertiary mt-3 !px-1">View details</a>
                    </div>
                </template>
            </div>

            <button type="button"
                class="absolute right-4 top-4 z-[600] flex h-12 items-center gap-2 border border-border bg-surface px-4 text-sm font-medium shadow-lg lg:hidden"
                x-data @click="$store.bottomSheet.toggle()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <span>Filters</span>
                <span class="mono text-xs text-muted" x-text="'(' + filtered().length + ')'"></span>
            </button>
        </div>

        <aside class="hidden flex-col border-l border-border bg-surface lg:flex lg:w-80">
            <div class="border-b border-border p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-fg">Incidents</h2>
                    <span class="mono text-xs text-muted" x-text="filtered().length + ' shown'"></span>
                </div>
                @auth
                    <a href="{{ route('report.create') }}" class="btn btn-primary mt-3 w-full">Submit Report</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-secondary mt-3 w-full">Log in to submit a report</a>
                @endauth
            </div>
            <div class="space-y-4 border-b border-border p-4">
                <div class="field">
                    <label class="label" for="map-type">Incident type</label>
                    <select id="map-type" class="select" x-model="activeType" @change="render()">
                        <option value="">All types</option>
                        <template x-for="(label, value) in types" :key="value">
                            <option :value="value" x-text="label"></option>
                        </template>
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="map-location">Location</label>
                    <input id="map-location" type="search" class="input" placeholder="Barangay or incident no." x-model="search" @input="render()">
                </div>
                <fieldset>
                    <legend class="label mb-2">Status</legend>
                    <div class="space-y-1.5">
                        <template x-for="(label, value) in statuses" :key="value">
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" :value="value" x-model="activeStatuses" @change="render()"
                                    class="h-4 w-4 accent-[var(--color-primary)]">
                                <span x-text="label"></span>
                                <span class="mono ml-auto text-xs text-muted"
                                    x-text="incidents.filter(i => i.status === value).length"></span>
                            </label>
                        </template>
                    </div>
                </fieldset>
                <div>
                    <p class="label mb-2">Legend</p>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full bg-success"></span><span>Verified</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-0 w-0 border-x-4 border-x-transparent border-b-8 border-warning"></span><span>Ongoing</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 bg-success opacity-85"></span><span>Resolved</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3.5 w-3.5 rounded-full border-2 border-danger"></span><span>Urgent priority</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-if="filtered().length === 0">
                    <div class="p-4">
                        <p class="text-sm text-muted">No verified incidents in this area.</p>
                        <button type="button" class="btn btn-tertiary mt-1 !px-0" @click="clearFilters()">Adjust filters</button>
                    </div>
                </template>
                <ul class="divide-y divide-border" role="list">
                    <template x-for="incident in filtered()" :key="incident.id">
                        <li>
                            <button type="button" class="flex w-full flex-col gap-1 px-4 py-3 text-left transition-colors duration-150 hover:bg-bg"
                                @click="focusIncident(incident)">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-fg" x-text="incident.incident_type_label"></span>
                                    <span class="chip" :class="'chip-' + incident.status" x-text="incident.status_label"></span>
                                </span>
                                <span class="mono text-xs text-muted" x-text="incident.incident_number + ' · ' + (incident.location_label ?? 'Location pending')"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </aside>

        <div x-show="$store.bottomSheet.open" x-cloak
            class="fixed inset-0 z-40 lg:hidden"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">
            <div class="absolute inset-0 bg-black/50" @click="$store.bottomSheet.close()"></div>
            <div class="absolute bottom-0 left-0 right-0 max-h-[85vh] overflow-y-auto border-t border-border bg-surface pb-20"
                x-transition:enter="transition duration-300 ease-out"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition duration-200 ease-in"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="translate-y-full">
                <div class="mx-auto mt-3 h-1 w-10 rounded-full bg-border"></div>
                <div class="p-4">
                    <div class="border-b border-border p-4 -mx-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold text-fg">Incidents</h2>
                            <span class="mono text-xs text-muted" x-text="filtered().length + ' shown'"></span>
                        </div>
                        @auth
                            <a href="{{ route('report.create') }}" class="btn btn-primary mt-3 w-full">Submit Report</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-secondary mt-3 w-full">Log in to submit a report</a>
                        @endauth
                    </div>
                    <div class="space-y-4 border-b border-border p-4 -mx-4">
                        <div class="field">
                            <label class="label" for="map-type-mobile">Incident type</label>
                            <select id="map-type-mobile" class="select" x-model="activeType" @change="render()">
                                <option value="">All types</option>
                                <template x-for="(label, value) in types" :key="value">
                                    <option :value="value" x-text="label"></option>
                                </template>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="map-location-mobile">Location</label>
                            <input id="map-location-mobile" type="search" class="input" placeholder="Barangay or incident no." x-model="search" @input="render()">
                        </div>
                        <fieldset>
                            <legend class="label mb-2">Status</legend>
                            <div class="space-y-1.5">
                                <template x-for="(label, value) in statuses" :key="value">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                                        <input type="checkbox" :value="value" x-model="activeStatuses" @change="render()"
                                            class="h-4 w-4 accent-[var(--color-primary)]">
                                        <span x-text="label"></span>
                                        <span class="mono ml-auto text-xs text-muted"
                                            x-text="incidents.filter(i => i.status === value).length"></span>
                                    </label>
                                </template>
                            </div>
                        </fieldset>
                        <div>
                            <p class="label mb-2">Legend</p>
                            <div class="space-y-1.5 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-3 w-3 rounded-full bg-success"></span><span>Verified</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-0 w-0 border-x-4 border-x-transparent border-b-8 border-warning"></span><span>Ongoing</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-3 w-3 bg-success opacity-85"></span><span>Resolved</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-3.5 w-3.5 rounded-full border-2 border-danger"></span><span>Urgent priority</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="min-h-0 max-h-[40vh] overflow-y-auto">
                        <template x-if="filtered().length === 0">
                            <div class="p-4">
                                <p class="text-sm text-muted">No verified incidents in this area.</p>
                                <button type="button" class="btn btn-tertiary mt-1 !px-0" @click="clearFilters()">Adjust filters</button>
                            </div>
                        </template>
                        <ul class="divide-y divide-border" role="list">
                            <template x-for="incident in filtered()" :key="incident.id">
                                <li>
                                    <button type="button" class="flex w-full flex-col gap-1 px-4 py-3 text-left transition-colors duration-150 hover:bg-bg"
                                        @click="focusIncident(incident); $store.bottomSheet.close()">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-medium text-fg" x-text="incident.incident_type_label"></span>
                                            <span class="chip" :class="'chip-' + incident.status" x-text="incident.status_label"></span>
                                        </span>
                                        <span class="mono text-xs text-muted" x-text="incident.incident_number + ' · ' + (incident.location_label ?? 'Location pending')"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('mapPage', () => ({
                incidents: @js($incidents),
                types: @js($types),
                statuses: @js($statuses),
                activeStatuses: ['verified', 'ongoing', 'resolved'],
                activeType: '',
                search: '',
                map: null,
                loading: true,
                mapReady: false,
                selected: null,
                init() {
                    this.boot();
                },
                boot() {
                    this.loading = true;
                    const el = document.getElementById('incident-map');
                    if (!el) return;
                    this.map = ResqHub.createIncidentMap(el, {
                        onSelect: (incident) => { this.selected = incident; },
                    });
                    this.render();
                    this.loading = false;
                    this.mapReady = true;
                },
                filtered() {
                    return this.incidents.filter((incident) => {
                        if (!this.activeStatuses.includes(incident.status)) return false;
                        if (this.activeType && incident.incident_type !== this.activeType) return false;
                        if (this.search) {
                            const needle = this.search.toLowerCase();
                            const haystack = ((incident.location_label ?? '') + ' ' + incident.incident_number).toLowerCase();
                            if (!haystack.includes(needle)) return false;
                        }
                        return true;
                    });
                },
                render() {
                    if (!this.map) return;
                    const list = this.filtered();
                    this.map.setIncidents(list);
                    if (list.length) this.map.fitIncidents(list);
                },
                focusIncident(incident) {
                    this.selected = incident;
                    this.map.map.setView([incident.latitude, incident.longitude], 16);
                    const marker = this.map.markers.getLayers().find((layer) => layer.feature?.id === incident.id);
                    marker?.openPopup();
                },
                clearFilters() {
                    this.activeStatuses = ['verified', 'ongoing', 'resolved'];
                    this.activeType = '';
                    this.search = '';
                    this.render();
                },
            }));
        });
    </script>
</x-layouts.app>
