<x-layouts.app title="{{ $incident->incident_number }}">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <a href="{{ route('home') }}" class="btn btn-tertiary mb-6 !px-0">&larr; Back to map</a>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="border border-border bg-surface">
                <div id="detail-map" class="h-56 bg-bg sm:h-72" role="application" aria-label="Incident location map"></div>
            </div>

            <div class="flex flex-col gap-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="mono text-lg sm:text-xl">{{ $incident->incident_number }}</h1>
                        <x-status-chip :status="$incident->status" />
                        <x-priority-badge :priority="$incident->priority" />
                    </div>
                    <h2 class="mt-2 text-xl font-semibold text-fg sm:text-2xl">{{ $incident->incident_type->label() }}</h2>
                </div>

                <div class="divide-y divide-border border border-border bg-surface">
                    <div class="flex justify-between gap-4 px-4 py-3 text-sm">
                        <span class="text-muted">Location</span>
                        <span class="text-right font-medium">{{ $incident->location_label ?? 'Location pending' }}</span>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3 text-sm">
                        <span class="text-muted">Coordinates</span>
                        <span class="mono text-right">{{ number_format($incident->latitude, 5) }}, {{ number_format($incident->longitude, 5) }}</span>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3 text-sm">
                        <span class="text-muted">Reported</span>
                        <span class="text-right font-medium">{{ $incident->reported_at?->format('M j, Y g:i A') }}</span>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3 text-sm">
                        <span class="text-muted">Reporter</span>
                        <span class="text-right font-medium">{{ $incident->is_anonymous ? 'Anonymous' : ($incident->reporter?->name ?? '—') }}</span>
                    </div>
                    @if ($incident->assigned_unit)
                        <div class="flex justify-between gap-4 px-4 py-3 text-sm">
                            <span class="text-muted">Assigned unit</span>
                            <span class="mono text-right">{{ $incident->assigned_unit }}</span>
                        </div>
                    @endif
                </div>

                <div>
                    <h3 class="mb-2">Description</h3>
                    <p class="text-sm leading-relaxed text-fg/90">{{ $incident->description }}</p>
                </div>

                @if ($incident->evidence->isNotEmpty())
                    <div>
                        <h3 class="mb-2">Attached evidence</h3>
                        <ul class="divide-y divide-border border border-border bg-surface">
                            @foreach ($incident->evidence as $item)
                                <li class="text-sm">
                                    <div class="flex items-start gap-4 px-4 py-3">
                                        <img src="{{ asset('storage/'.$item->file_path) }}" alt="{{ $item->original_name }}" class="h-16 w-16 shrink-0 rounded border border-border bg-bg object-cover">
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ asset('storage/'.$item->file_path) }}" download="{{ $item->original_name }}"
                                                class="flex items-center justify-between gap-4 no-underline transition-colors duration-150 hover:text-fg"
                                                aria-label="Download {{ $item->original_name }}">
                                                <span class="truncate">{{ $item->original_name }}</span>
                                                <span class="flex shrink-0 items-center gap-2">
                                                    <span class="mono text-xs text-muted">{{ number_format($item->file_size / 1024, 1) }} KB</span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                                </span>
                                            </a>
                                            @if ($item->ai_analyzed_at)
                                                <div class="flex items-center justify-between gap-4 py-2 text-xs">
                                                    @if ($item->ai_error)
                                                        <span class="text-muted">AI check failed</span>
                                                    @else
                                                        <span class="inline-flex items-center gap-2">
                                                            <span class="{{ $item->ai_is_generated ? 'text-danger' : 'text-success' }}">
                                                                {{ $item->ai_is_generated ? 'AI-generated image' : 'Likely real photo' }}
                                                            </span>
                                                            <span class="mono text-muted">{{ number_format(($item->ai_score ?? 0) * 100, 1) }}% confidence</span>
                                                        </span>
                                                    @endif
                                                    <span class="text-muted">Analyzed {{ $item->ai_analyzed_at->format('M j, g:i A') }}</span>
                                                </div>
                                            @else
                                                <div class="flex items-center justify-between gap-4 py-2 text-xs">
                                                    <span class="text-muted">AI check pending</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            const incident = @js([
                'latitude' => $incident->latitude,
                'longitude' => $incident->longitude,
                'status' => $incident->status->value,
                'priority' => $incident->priority->value,
            ]);

            Alpine.data('detailMap', () => ({
                init() {
                    const map = ResqHub.createIncidentMap(document.getElementById('detail-map'), {
                        zoom: 15,
                        zoomControl: false,
                        attributionControl: false,
                        showDetailsLink: false,
                    });
                    const marker = map.addIncident({ ...incident, status_label: '{{ $incident->status->label() }}' });
                    marker.openPopup();
                    setTimeout(() => map.map.invalidateSize(), 100);
                },
            }));
        });
    </script>
    <div x-data="detailMap()"></div>
</x-layouts.app>
