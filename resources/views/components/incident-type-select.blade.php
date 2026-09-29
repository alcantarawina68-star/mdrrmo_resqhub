@props([
    'name' => 'incident_type',
    'id' => null,
    'value' => null,
    'placeholder' => 'Select type',
    'required' => true,
])

@php
    $current = old($name, $value);
    $isLegacy = filled($current) && ! in_array($current, \App\Enums\IncidentType::values(), true);
@endphp

<select id="{{ $id ?? $name }}" name="{{ $name }}" class="select" @required($required)>
    <option value="">{{ $placeholder }}</option>

    @if ($isLegacy)
        <option value="{{ $current }}" selected disabled>
            {{ \App\Enums\IncidentType::tryFrom($current)?->label() }} (legacy — choose a current type)
        </option>
    @endif

    @foreach (\App\Enums\IncidentType::grouped() as $group)
        <optgroup label="{{ $group['label'] }}">
            @foreach ($group['types'] as $typeValue => $typeLabel)
                <option value="{{ $typeValue }}" @selected($current === $typeValue)>{{ $typeLabel }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
