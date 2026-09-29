@props([
    'name' => 'priority',
    'id' => null,
    'value' => null,
    'placeholder' => null,
    'required' => true,
])

@php
    $current = old($name, $value ?? \App\Enums\IncidentClassification::Yellow->value);
@endphp

<select id="{{ $id ?? $name }}" name="{{ $name }}" class="select" @required($required)>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach (\App\Enums\IncidentClassification::labels() as $classificationValue => $classificationLabel)
        <option value="{{ $classificationValue }}" @selected($current === $classificationValue)>{{ $classificationLabel }}</option>
    @endforeach
</select>
