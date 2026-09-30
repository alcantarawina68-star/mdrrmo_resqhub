@php
    $layers = $layers ?? \App\Support\MapLayers::all();
@endphp

<div
    class="map-layer-switch"
    role="group"
    aria-label="{{ $label ?? 'Map type' }}"
    x-data="mapLayerSwitch(@js($layers))"
>
    <template x-for="layer in layers" :key="layer.key">
        <button
            type="button"
            class="map-layer-switch-option"
            :class="layer.key === key && 'is-active'"
            :aria-pressed="(layer.key === key).toString()"
            x-text="layer.label"
            @click="$store.mapLayer.set(layer.key)"
        ></button>
    </template>
</div>
