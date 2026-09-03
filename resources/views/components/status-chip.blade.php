@props(['status'])

@php
    $value = $status instanceof \App\Enums\IncidentStatus ? $status->value : $status;
    $label = $status instanceof \App\Enums\IncidentStatus
        ? $status->label()
        : (\App\Enums\IncidentStatus::tryFrom($status)?->label() ?? $status);
@endphp

<span class="chip chip-{{ $value }}" role="status">{{ $label }}</span>
