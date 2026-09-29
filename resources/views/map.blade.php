<x-layouts.app title="Live Incident Map" :footer-spacing="false">
    <div
        class="flex h-[calc(100dvh-4rem)] flex-col lg:flex-row"
        x-data="mapPage()"
        @keydown.escape.window="$store.bottomSheet.close()"
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

            <button type="button"
                class="absolute right-4 top-4 z-[600] flex h-12 items-center gap-2 border border-border bg-surface px-4 text-sm font-medium shadow-lg lg:hidden"
                x-data @click="$store.bottomSheet.toggle()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <span>Filters</span>
                <span class="mono text-xs text-muted" x-text="'(' + filtered().length + ')'"></span>
            </button>
        </div>

        <aside class="hidden flex-col overflow-y-auto border-l border-border bg-surface lg:flex lg:w-80">
            <div class="border-b border-border p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-fg">Map</h2>
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
                        <template x-for="(group, category) in types" :key="category">
                            <optgroup :label="group.label">
                                <template x-for="(label, value) in group.types" :key="value">
                                    <option :value="value" x-text="label"></option>
                                </template>
                            </optgroup>
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
                    <p class="text-xs text-muted">Shape &amp; fill show status</p>
                    <div class="mt-1.5 space-y-1.5 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full bg-success"></span><span>Verified</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-0 w-0 border-x-4 border-x-transparent border-b-8 border-warning"></span><span>Ongoing</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 bg-success opacity-85"></span><span>Closed</span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted">Ring shows classification</p>
                    <div class="mt-1.5 grid grid-cols-2 gap-1.5 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full bg-muted ring-2 ring-danger"></span><span>🔴 Red</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full bg-muted ring-2 ring-success"></span><span>🟢 Green</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full bg-muted ring-2 ring-warning"></span><span>🟡 Yellow</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full bg-muted ring-2 ring-fg"></span><span>⚫ Black</span>
                        </div>
                    </div>
                </div>
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
                            <h2 class="text-base font-semibold text-fg">Map</h2>
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
                                <template x-for="(group, category) in types" :key="category">
                                    <optgroup :label="group.label">
                                        <template x-for="(label, value) in group.types" :key="value">
                                            <option :value="value" x-text="label"></option>
                                        </template>
                                    </optgroup>
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
                                <p class="text-xs text-muted">Shape &amp; fill show status</p>
                                <div class="mt-1.5 space-y-1.5 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full bg-success"></span><span>Verified</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-0 w-0 border-x-4 border-x-transparent border-b-8 border-warning"></span><span>Ongoing</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 bg-success opacity-85"></span><span>Closed</span>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-muted">Ring shows classification</p>
                                <div class="mt-1.5 grid grid-cols-2 gap-1.5 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full bg-muted ring-2 ring-danger"></span><span>🔴 Red</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full bg-muted ring-2 ring-success"></span><span>🟢 Green</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full bg-muted ring-2 ring-warning"></span><span>🟡 Yellow</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full bg-muted ring-2 ring-fg"></span><span>⚫ Black</span>
                                    </div>
                                </div>
                            </div>
                    </div>
                    <template x-if="filtered().length === 0">
                        <div class="border-t border-border p-4 -mx-4">
                            <p class="text-sm text-muted">No verified incidents in this area.</p>
                            <button type="button" class="btn btn-tertiary mt-1 !px-0" @click="clearFilters()">Adjust filters</button>
                        </div>
                    </template>
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
                activeStatuses: ['verified', 'ongoing', 'closed'],
                activeType: '',
                search: '',
                map: null,
                loading: true,
                mapReady: false,
                init() {
                    this.boot();
                },
                boot() {
                    this.loading = true;
                    const el = document.getElementById('incident-map');
                    if (!el) return;
                    this.map = ResqHub.createIncidentMap(el);
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
                clearFilters() {
                    this.activeStatuses = ['verified', 'ongoing', 'closed'];
                    this.activeType = '';
                    this.search = '';
                    this.render();
                },
            }));
        });
    </script>
</x-layouts.app>
