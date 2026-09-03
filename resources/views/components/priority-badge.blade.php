@props(['priority'])

@php
    $value = $priority instanceof \App\Enums\Priority ? $priority->value : $priority;
    $label = $priority instanceof \App\Enums\Priority
        ? $priority->label()
        : (\App\Enums\Priority::tryFrom($priority)?->label() ?? $priority);
@endphp

<span class="chip chip-{{ $value }}">{{ $label }}</span>
