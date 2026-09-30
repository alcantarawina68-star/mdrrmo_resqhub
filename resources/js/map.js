const LAYER_STORAGE_KEY = 'resqhub:map-layer';
const DEFAULT_BASE_LAYER = 'standard';
const maps = new Map();

function storedLayer() {
    try {
        return localStorage.getItem(LAYER_STORAGE_KEY);
    } catch (e) {
        return null;
    }
}

export function preferredBaseLayer() {
    return storedLayer() ?? DEFAULT_BASE_LAYER;
}

export function rememberBaseLayer(key) {
    try {
        localStorage.setItem(LAYER_STORAGE_KEY, key);
    } catch (e) {
        /* storage unavailable — the choice only lasts for this page */
    }
}

function createBaseLayers(map, definitions = []) {
    const layers = new Map();
    let currentKey = null;

    definitions.forEach((definition) => {
        layers.set(
            definition.key,
            L.tileLayer(definition.url, {
                maxZoom: definition.max_zoom ?? 19,
                subdomains: definition.subdomains ?? 'abc',
                attribution: definition.attribution,
            }),
        );
    });

    function apply(key) {
        const next = layers.get(key);

        if (!next) {
            return;
        }

        if (currentKey) {
            layers.get(currentKey)?.remove();
        }

        next.addTo(map);
        currentKey = key;

        const definition = definitions.find((item) => item.key === key);
        map.getContainer().classList.toggle('map-tiles-light', definition?.light ?? false);
    }

    return {
        apply,
        applyPreferred() {
            const preferred = preferredBaseLayer();
            apply(layers.has(preferred) ? preferred : definitions[0]?.key);
        },
        has(key) {
            return layers.has(key);
        },
    };
}

export function setMapBaseLayer(key) {
    maps.forEach((controller) => {
        if (controller?.layers?.has(key)) {
            controller.layers.apply(key);
        }
    });
}

export function createLocationPicker(element, options = {}) {
    const map = L.map(element, {
        center: options.center ?? [18.275, 121.675],
        zoom: options.zoom ?? 14,
        scrollWheelZoom: false,
    });

    const controller = { map, layers: null, marker: null };

    controller.layers = createBaseLayers(map, options.layers ?? []);
    controller.layers.applyPreferred();
    maps.set(element, controller);

    function render(latlng) {
        if (controller.marker) {
            controller.marker.setLatLng(latlng);
            return;
        }

        controller.marker = L.marker(latlng, {
            draggable: true,
            icon: L.divIcon({
                className: '',
                html: '<span class="location-pin" aria-hidden="true"></span>',
                iconSize: [28, 36],
                iconAnchor: [14, 34],
            }),
            keyboard: true,
            alt: 'Selected report location',
        }).addTo(map);

        controller.marker.on('dragend', (event) => options.onChange?.(event.target.getLatLng()));
    }

    map.on('click', (event) => {
        render(event.latlng);
        options.onChange?.(event.latlng);
    });

    return {
        map,
        setPoint(latlng, zoom = null) {
            zoom ? map.setView(latlng, zoom) : map.panTo(latlng);
            render(latlng);
        },
    };
}

export function createIncidentMap(element, options = {}) {
    const showDetailsLink = options.showDetailsLink ?? true;

    const map = L.map(element, {
        center: options.center ?? [18.275, 121.675],
        zoom: options.zoom ?? 13,
        zoomControl: options.zoomControl ?? true,
        attributionControl: options.attributionControl ?? true,
    });

    const baseLayers = createBaseLayers(map, options.layers ?? []);
    baseLayers.applyPreferred();

    const controller = { map, layers: baseLayers };
    maps.set(element, controller);

    const markers = L.layerGroup().addTo(map);

    function markerClass(incident) {
        return ['incident-marker', `is-${incident.status}`].join(' ');
    }

    function shapeFor(incident) {
        const label = incident.status_label ?? incident.status;
        const ariaLabel = `Incident ${incident.incident_number}, ${incident.incident_type_label ?? incident.incident_type}, ${label}`;

        const el = document.createElement('div');
        el.className = markerClass(incident);
        el.innerHTML = `<span class="shape" aria-hidden="true"></span>`;
        el.setAttribute('role', 'button');
        el.setAttribute('aria-label', ariaLabel);

        const icon = L.divIcon({
            className: '',
            html: el.outerHTML,
            iconSize: [30, 30],
            iconAnchor: [15, 15],
        });

        return icon;
    }

    function addIncident(incident) {
        const marker = L.marker([incident.latitude, incident.longitude], {
            icon: shapeFor(incident),
            keyboard: true,
        });

        marker.feature = incident;

        marker.bindPopup(() => {
            const el = document.createElement('div');

            const meta = document.createElement('div');
            meta.style.display = 'flex';
            meta.style.gap = '8px';
            meta.style.alignItems = 'center';
            meta.style.marginBottom = '8px';

            const id = document.createElement('span');
            id.className = 'mono';
            id.textContent = incident.incident_number;

            const chip = document.createElement('span');
            chip.className = `chip chip-${incident.status}`;
            chip.textContent = incident.status_label ?? incident.status;

            meta.append(id, chip);

            const title = document.createElement('div');
            title.style.fontWeight = '600';
            title.textContent = incident.incident_type_label ?? incident.incident_type;
            title.style.marginBottom = '4px';

            const desc = document.createElement('div');
            desc.style.color = 'var(--color-muted)';
            desc.textContent = incident.location_label ?? `${incident.latitude.toFixed(5)}, ${incident.longitude.toFixed(5)}`;

            if (incident.description) {
                const summary = document.createElement('div');
                summary.style.color = 'var(--color-fg)';
                summary.style.marginTop = '4px';
                summary.style.fontSize = '13px';
                summary.textContent = incident.description;
                el.append(summary);
            }

            const actions = document.createElement('div');
            actions.style.marginTop = '8px';

            if (showDetailsLink && incident.id) {
                const link = document.createElement('a');
                link.href = `/incidents/${incident.id}`;
                link.textContent = 'View details';
                link.style.color = 'var(--color-primary)';
                link.style.fontWeight = '500';

                actions.append(link);
            }

            el.append(meta, title, desc, actions);

            return el;
        }, {
            className: 'incident-popup',
        });

        markers.addLayer(marker);

        return marker;
    }

    function setIncidents(incidents) {
        markers.clearLayers();
        incidents.forEach(addIncident);
    }

    function fitIncidents(incidents) {
        if (!incidents.length) {
            return;
        }

        const bounds = L.latLngBounds(incidents.map((i) => [i.latitude, i.longitude]));
        map.fitBounds(bounds, { padding: [32, 32], maxZoom: 15 });
    }

    function clear() {
        markers.clearLayers();
    }

    return {
        map,
        markers,
        addIncident,
        setIncidents,
        fitIncidents,
        clear,
        setBaseLayer(key) {
            baseLayers.apply(key);
        },
        get center() {
            return map.getCenter();
        },
        get zoom() {
            return map.getZoom();
        },
    };
}
