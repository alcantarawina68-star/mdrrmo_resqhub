@props([
    'label',
    'items' => [],
    'valueLabel' => 'incidents',
    'empty' => 'Nothing to show for this period.',
    'height' => 'h-40 sm:h-48',
    'footNote' => null,
])

@php
    $max = max(1, collect($items)->max('total'));
    $peak = collect($items)->sortByDesc('total')->first();
@endphp

<div class="card p-5">
    <div class="mb-4 flex items-baseline justify-between gap-3">
        <p class="panel-title">{{ $label }}</p>
        {{ $slot }}
    </div>

    @if (empty($items))
        <p class="py-8 text-center text-sm text-muted">{{ $empty }}</p>
    @else
        <div class="flex {{ $height }} gap-px sm:gap-1" role="img"
            aria-label="{{ $label }}. Peak {{ $peak['label'] ?? 'n/a' }} with {{ $peak['total'] ?? 0 }} {{ $valueLabel }}.">
            @foreach ($items as $item)
                <div class="group relative flex-1 flex flex-col justify-end" title="{{ $item['label'] }}: {{ $item['total'] }} {{ $valueLabel }}">
                    <div class="rounded-sm bg-{{ $item['tone'] ?? 'primary' }}/70 transition-colors duration-150 hover:opacity-80"
                        style="height: {{ max(2, round(($item['total'] / $max) * 100)) }}%">
                        <span class="sr-only">{{ $item['label'] }}: {{ $item['total'] }} {{ $valueLabel }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($footNote)
            <p class="mt-3 text-xs text-muted">{{ $footNote }}</p>
        @endif
    @endif
</div>
