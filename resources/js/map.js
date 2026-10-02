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

const DEFAULT_HEAT_GRADIENT = {
    0.0: 'rgba(43, 108, 176, 0)',
    0.35: 'rgba(43, 108, 176, 0.55)',
    0.55: 'rgba(249, 168, 37, 0.6)',
    0.75: 'rgba(226, 95, 45, 0.7)',
    1.0: 'rgba(198, 40, 40, 0.85)',
};

const HeatLayer = L.Layer.extend({
    options: {
        radius: 28,
        maxOpacity: 0.8,
        gradient: null,
    },

    initialize(points, options) {
        this._points = points ?? [];
        L.Util.setOptions(this, options);
        this._gradient = this.options.gradient ?? DEFAULT_HEAT_GRADIENT;
        this._stops = Object.keys(this._gradient).map(Number).sort((a, b) => a - b);
    },

    setPoints(points) {
        this._points = points ?? [];
        this._reset();
        return this;
    },

    setVisible(visible) {
        if (this._canvas) {
            this._canvas.classList.toggle('is-hidden', !visible);
        }
        return this;
    },

    onAdd(map) {
        this._map = map;

        if (!this._canvas) {
            this._canvas = L.DomUtil.create('canvas', 'leaflet-heat-layer');
            this._ctx = this._canvas.getContext('2d');
        }

        map.getPanes().overlayPane.appendChild(this._canvas);
        map.on('moveend resize viewreset zoomend', this._reset, this);
        map.on('move', this._move, this);
        this._reset();
        return this;
    },

    onRemove(map) {
        L.DomUtil.remove(this._canvas);
        map.off('moveend resize viewreset zoomend', this._reset, this);
        map.off('move', this._move, this);
        this._canvas = this._ctx = this._map = null;
        return this;
    },

    _move() {
        if (!this._canvas || !this._map) {
            return;
        }
        L.DomUtil.setPosition(this._canvas, this._map.containerPointToLayerPoint([0, 0]));
    },

    _reset() {
        if (!this._canvas || !this._map) {
            return;
        }

        const size = this._map.getSize();
        this._canvas.width = size.x;
        this._canvas.height = size.y;
        this._canvas.style.width = `${size.x}px`;
        this._canvas.style.height = `${size.y}px`;
        this._move();
        this._redraw();
    },

    _redraw() {
        if (!this._canvas || !this._map || !this._ctx) {
            return;
        }

        const ctx = this._ctx;
        const size = this._map.getSize();
        const topLeft = this._map.containerPointToLayerPoint([0, 0]);
        const radius = this.options.radius;
        const margin = radius + 4;

        ctx.clearRect(0, 0, size.x, size.y);
        ctx.globalCompositeOperation = 'lighter';
        ctx.globalAlpha = this.options.maxOpacity;

        this._points.forEach((incident) => {
            const point = this._map.latLngToLayerPoint([incident.latitude, incident.longitude]);
            const x = point.x - topLeft.x;
            const y = point.y - topLeft.y;

            if (x < -margin || x > size.x + margin || y < -margin || y > size.y + margin) {
                return;
            }

            const gradient = ctx.createRadialGradient(x, y, 0, x, y, radius);
            this._stops.forEach((stop) => gradient.addColorStop(stop, this._gradient[stop]));

            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            ctx.fillStyle = gradient;
            ctx.fill();
        });
    },
});

const METRES_PER_DEGREE = 111320;

/*
 * Nearest barangay centroid for a dropped pin.
 *
 * This mirrors App\Support\BarangayLocations::nearest() exactly (same
 * equirectangular approximation, same METRES_PER_DEGREE) so a pin resolves to
 * the same barangay in the browser as it does in PHP. Callers pass
 * BarangayLocations::MAX_SUGGESTION_METRES as `maxMetres` to get the behaviour
 * of `suggest()` and null for a pin too far away to guess. Centroids are points,
 * not boundaries, so callers must present the result as a suggestion.
 */
export function nearestBarangay(centroids, latlng, maxMetres = Infinity) {
    const lngScale = Math.cos((latlng.lat * Math.PI) / 180);
    let nearest = null;
    let best = Infinity;

    Object.entries(centroids).forEach(([name, centroid]) => {
        const dLat = centroid.lat - latlng.lat;
        const dLng = (centroid.lng - latlng.lng) * lngScale;
        const distance = Math.sqrt(dLat * dLat + dLng * dLng);

        if (distance < best) {
            best = distance;
            nearest = name;
        }
    });

    if (nearest === null || best * METRES_PER_DEGREE > maxMetres) {
        return null;
    }

    return { name: nearest, distance_metres: best * METRES_PER_DEGREE };
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
    const heat = new HeatLayer([], options.heat ?? {}).addTo(map);

    function hotSpots(incidents, cells = 5, limit = 6) {
        const size = map.getSize();
        const cellW = size.x / cells;
        const cellH = size.y / cells;
        const buckets = new Map();

        incidents.forEach((incident) => {
            const point = map.latLngToContainerPoint([incident.latitude, incident.longitude]);

            if (point.x < 0 || point.y < 0 || point.x > size.x || point.y > size.y) {
                return;
            }

            const key = `${Math.floor(point.x / cellW)}:${Math.floor(point.y / cellH)}`;
            let bucket = buckets.get(key);

            if (!bucket) {
                bucket = { count: 0, types: new Map(), labels: new Map(), points: [] };
                buckets.set(key, bucket);
            }

            bucket.count += 1;
            bucket.points.push(incident);
            bucket.types.set(incident.incident_type, (bucket.types.get(incident.incident_type) ?? 0) + 1);
            const label = incident.location_label ?? incident.incident_number ?? null;

            if (label) {
                bucket.labels.set(label, (bucket.labels.get(label) ?? 0) + 1);
            }
        });

        return [...buckets.values()]
            .sort((a, b) => b.count - a.count)
            .slice(0, limit)
            .map((bucket) => {
                let dominantType = null;
                let dominantCount = 0;

                bucket.types.forEach((count, type) => {
                    if (count > dominantCount) {
                        dominantType = type;
                        dominantCount = count;
                    }
                });

                let label = null;
                let labelCount = 0;

                bucket.labels.forEach((count, candidate) => {
                    if (count > labelCount) {
                        label = candidate;
                        labelCount = count;
                    }
                });

                const dominant = bucket.points.find((incident) => incident.incident_type === dominantType);
                const latitude = bucket.points.reduce((sum, incident) => sum + incident.latitude, 0) / bucket.count;
                const longitude = bucket.points.reduce((sum, incident) => sum + incident.longitude, 0) / bucket.count;

                return {
                    count: bucket.count,
                    label: label ?? `${bucket.count} incidents`,
                    dominantLabel: dominant?.incident_type_label ?? dominantType,
                    center: [latitude, longitude],
                };
            });
    }

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
        heat,
        addIncident,
        setIncidents,
        fitIncidents,
        clear,
        setHeatPoints(points) {
            heat.setPoints(points);
        },
        setHeatVisible(visible) {
            heat.setVisible(visible);
        },
        hotSpots,
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
