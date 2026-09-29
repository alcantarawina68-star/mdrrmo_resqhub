@props(['classification'])

@php
    $value = $classification instanceof \App\Enums\IncidentClassification ? $classification->value : $classification;
    $label = $classification instanceof \App\Enums\IncidentClassification
        ? $classification->label()
        : (\App\Enums\IncidentClassification::tryFrom($classification)?->label() ?? $classification);
@endphp

<span class="chip chip-{{ $value }} normal-case">{{ $label }}</span>
