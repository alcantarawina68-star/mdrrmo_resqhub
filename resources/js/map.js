export function createIncidentMap(element, options = {}) {
    const { onSelect } = options;

    const map = L.map(element, {
        center: options.center ?? [13.181, 123.655],
        zoom: options.zoom ?? 13,
        zoomControl: options.zoomControl ?? true,
        attributionControl: options.attributionControl ?? true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const markers = L.layerGroup().addTo(map);

    function markerClass(incident) {
        const classes = ['incident-marker'];
        classes.push(`is-${incident.status}`);

        if (incident.priority === 'urgent') {
            classes.push('is-urgent');
        }

        return classes.join(' ');
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

        marker.on('click', () => onSelect?.(incident));
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

            const actions = document.createElement('div');
            actions.style.marginTop = '8px';

            const link = document.createElement('a');
            link.href = `/incidents/${incident.id}`;
            link.textContent = 'View details';
            link.style.color = 'var(--color-primary)';
            link.style.fontWeight = '500';

            actions.append(link);

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
        get center() {
            return map.getCenter();
        },
        get zoom() {
            return map.getZoom();
        },
    };
}
