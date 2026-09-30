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
                class="absolute right-4 top-4 z-[600] flex h-12 items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-medium shadow-lg lg:hidden"
                x-data @click="$store.bottomSheet.toggle()" :aria-expanded="$store.bottomSheet.open" aria-controls="map-filter-sheet">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <span>Filters</span>
                <span class="mono text-xs text-muted" x-text="'(' + filtered().length + ')'"></span>
            </button>
        </div>

        <aside class="hidden flex-col overflow-y-auto border-l border-border bg-surface lg:flex lg:w-80" aria-label="Map filters">
            <x-map-filters class="border-b border-border" />
        </aside>

        <div x-show="$store.bottomSheet.open" x-cloak class="bottom-sheet-overlay lg:hidden"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click.self="$store.bottomSheet.close()">
            <div id="map-filter-sheet" class="bottom-sheet pb-20" tabindex="-1" role="dialog" aria-modal="true"
                aria-label="Map filters" @keydown.tab="ResqHub.trapFocus($event, $refs.sheet)"
                x-ref="sheet"
                x-transition:enter="transition duration-300 ease-out"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition duration-200 ease-in"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="translate-y-full">
                <div class="bottom-sheet-handle"></div>
                <x-map-filters prefix="-mobile" />
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('mapPage', () => ({
                incidents: @js($incidents),
                types: @js($types),
                statuses: @js($statuses),
                layers: @js(\App\Support\MapLayers::all()),
                activeStatuses: ['verified', 'ongoing', 'closed'],
                activeType: '',
                search: '',
                heat: true,
                version: 0,
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
                    this.map = ResqHub.createIncidentMap(el, { layers: this.layers });
                    this.map.map.on('moveend zoomend', () => {
                        this.version++;
                    });
                    this.render();
                    this.loading = false;
                    this.mapReady = true;
                },
                hotSpots() {
                    if (!this.map) return [];
                    return this.map.hotSpots(this.filtered());
                },
                focusSpot(spot) {
                    this.map.map.setView(spot.center, Math.max(this.map.zoom, 14));
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
                    this.map.setHeatPoints(list);
                    this.map.setHeatVisible(this.heat);
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
