@props(['type' => 'info', 'role' => null])

@php
    $styles = [
        'info' => 'border-secondary/30 bg-secondary/10 text-secondary',
        'success' => 'border-success/30 bg-success/10 text-success',
        'warning' => 'border-warning/40 bg-warning/15 text-warning-fg',
        'danger' => 'border-danger/30 bg-danger/10 text-danger',
    ][$type];

    $role ??= $type === 'danger' ? 'alert' : 'status';
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border px-4 py-3 text-sm {$styles}"]) }} role="{{ $role }}">
    {{ $slot }}
</div>
