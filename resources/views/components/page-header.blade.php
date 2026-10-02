@props(['description' => null])

<div {{ $attributes->merge(['class' => 'mb-5 flex flex-wrap items-end justify-between gap-3']) }}>
    @if ($description)
        <p class="max-w-2xl text-sm text-muted">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
