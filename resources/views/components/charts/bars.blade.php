@props([
    'label',
    'items' => [],
    'valueLabel' => 'incidents',
    'empty' => 'Nothing to show for this period.',
    'footNote' => null,
])

@php
    $max = max(1, collect($items)->max('total'));
@endphp

<div class="card p-5">
    <div class="mb-4 flex items-baseline justify-between gap-3">
        <p class="panel-title">{{ $label }}</p>
        {{ $slot }}
    </div>

    @if (empty($items))
        <p class="py-8 text-center text-sm text-muted">{{ $empty }}</p>
    @else
        <div class="divide-y divide-border" role="img" aria-label="{{ $label }}">
            @foreach ($items as $item)
                <div class="flex items-center justify-between gap-2 py-2 text-sm sm:gap-4"
                    title="{{ $item['label'] }}: {{ $item['total'] }} {{ $valueLabel }}">
                    <span class="truncate {{ ($item['tone'] ?? null) === 'danger' ? 'font-medium text-danger' : '' }}">{{ $item['label'] }}</span>
                    <div class="flex shrink-0 items-center gap-3">
                        <div class="h-1.5 w-20 rounded-full bg-bg sm:w-32" aria-hidden="true">
                            <div class="h-full rounded-full bg-{{ $item['tone'] ?? 'secondary' }}"
                                style="width: {{ max(1, round(($item['total'] / $max) * 100)) }}%"></div>
                        </div>
                        <span class="mono w-8 text-right font-semibold text-fg">{{ $item['total'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($footNote)
            <p class="mt-3 text-xs text-muted">{{ $footNote }}</p>
        @endif
    @endif
</div>
