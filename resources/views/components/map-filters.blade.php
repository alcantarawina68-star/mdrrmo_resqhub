@props(['prefix' => ''])

<div {{ $attributes->merge(['class' => 'space-y-4 p-4']) }}>
    <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-fg">Map</h2>
        <span class="mono text-xs text-muted" aria-live="polite" x-text="filtered().length + ' shown'"></span>
    </div>

    @auth
        <a href="{{ route('report.create') }}" class="btn btn-primary w-full">Submit Report</a>
    @else
        <a href="{{ route('login') }}" class="btn btn-secondary w-full">Log in to submit a report</a>
    @endauth

    <div>
        <p class="label mb-2">Map imagery</p>
        <x-map-type-switch label="Map imagery" />
    </div>

    <div class="field">
        <label class="label" for="map-type{{ $prefix }}">Incident type</label>
        <select id="map-type{{ $prefix }}" class="select" x-model="activeType" @change="render()">
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
        <label class="label" for="map-location{{ $prefix }}">Location</label>
        <input id="map-location{{ $prefix }}" type="search" class="input" placeholder="Barangay or incident no."
            x-model="search" @input="render()">
    </div>

    <div>
        <div class="mb-2 flex items-center justify-between">
            <p class="label mb-0">Heatmap</p>
            <label class="heat-switch">
                <input type="checkbox" class="sr-only" x-model="heat" @change="render()"
                    aria-label="Toggle heatmap overlay">
                <span class="heat-switch-track" aria-hidden="true">
                    <span class="heat-switch-knob"></span>
                </span>
            </label>
        </div>

        <div x-show="heat" x-cloak class="mt-3">
            <div class="heat-legend" aria-hidden="true"></div>
            <div class="mt-1 flex justify-between text-[10px] leading-none text-muted">
                <span>Few</span>
                <span>Many</span>
            </div>
            <p class="mt-2 text-xs text-muted">Density of the incidents currently shown. Hot spots show the dominant
                incident type.</p>
        </div>
    </div>

    <div x-show="heat" x-cloak>
        <p class="label mb-2">Hot spots</p>

        <template x-if="hotSpots().length">
            <ol class="heat-spots">
                <template x-for="(spot, index) in hotSpots()" :key="version + '-' + index">
                    <li>
                        <button type="button" class="heat-spot" @click="focusSpot(spot)">
                            <span class="mono mt-0.5 text-xs text-muted" x-text="String(index + 1).padStart(2, '0')"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-fg" x-text="spot.label"></span>
                                <span class="block text-xs text-muted" x-text="spot.count + ' · ' + spot.dominantLabel"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ol>
        </template>

        <p x-show="filtered().length && !hotSpots().length" class="text-xs text-muted">No clusters in the visible area.
            Pan or zoom to reveal more.</p>
        <p x-show="!filtered().length" class="text-xs text-muted">No incidents to plot.</p>
    </div>

    <fieldset>
        <legend class="label mb-2">Status</legend>
        <div class="space-y-1.5">
            <template x-for="(label, value) in statuses" :key="value">
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" :value="value" x-model="activeStatuses" @change="render()"
                        class="h-4 w-4 accent-[var(--color-primary)]">
                    <span x-text="label"></span>
                    <span class="mono ml-auto text-xs text-muted" x-text="incidents.filter(i => i.status === value).length"></span>
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
                <span class="inline-block h-3 w-3 rounded-sm bg-success opacity-85"></span><span>Closed</span>
            </div>
        </div>
    </div>

    <template x-if="filtered().length === 0">
        <div class="border-t border-border pt-3">
            <p class="text-sm text-muted">No incidents match your filters.</p>
            <button type="button" class="btn btn-tertiary mt-1 !px-0" @click="clearFilters()">Adjust filters</button>
        </div>
    </template>
</div>
