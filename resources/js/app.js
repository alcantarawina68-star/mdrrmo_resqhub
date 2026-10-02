import Alpine from 'alpinejs';
import L from 'leaflet';
import {
    createIncidentMap,
    createLocationPicker,
    nearestBarangay,
    preferredBaseLayer,
    rememberBaseLayer,
    setMapBaseLayer,
} from './map';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function trapFocus(event, root) {
    if (!root) {
        return;
    }

    const focusable = [...root.querySelectorAll(FOCUSABLE)];
    if (!focusable.length) {
        return;
    }

    const first = focusable[0];
    const last = focusable.at(-1);

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

window.Alpine = Alpine;
window.L = L;
window.ResqHub = {
    createIncidentMap,
    createLocationPicker,
    nearestBarangay,
    preferredBaseLayer,
    setMapBaseLayer,
    trapFocus,
    confirmDialog: () => ({
        show: false,
        lastFocused: null,
        open() {
            this.lastFocused = document.activeElement;
            this.show = true;
            this.$nextTick(() => this.$refs.dialog?.focus());
        },
        close() {
            this.show = false;
            this.lastFocused?.focus?.();
        },
        trap(event) {
            trapFocus(event, this.$refs.dialog);
        },
    }),
};

Alpine.store('darkMode', {
    on: localStorage.getItem('darkMode') === 'true',
    toggle() {
        this.on = !this.on;
        this.apply();
    },
    apply() {
        localStorage.setItem('darkMode', this.on);
        document.documentElement.classList.toggle('dark', this.on);
    },
    init() {
        if (localStorage.getItem('darkMode') === null) {
            this.on = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        this.apply();
    },
});

/*
 * Single source of truth for the base layer (Standard / Satellite / Terrain) so
 * every map on the page and every switch stays in sync.
 */
Alpine.store('mapLayer', {
    key: preferredBaseLayer(),
    set(key) {
        this.key = key;
        rememberBaseLayer(key);
        setMapBaseLayer(key);
    },
});

Alpine.data('mapLayerSwitch', (layers) => ({
    layers,
    get key() {
        return this.$store.mapLayer.key;
    },
}));

const NOTIFICATION_POLL_MS = 30000;

/*
 * The header alert bell: unread badge, dropdown of recent alerts, and the
 * actions that read them.
 *
 * There is no realtime transport in this app (no broadcasting config, no Echo,
 * no SSE), so the panel is kept current by polling the feed endpoint. It pauses
 * while the tab is hidden and never has two requests in flight. A rising unread
 * count raises a toast, which is what the toasts store was built for.
 *
 * The initial item list arrives server-rendered in a JSON island inside the
 * component, so the dropdown is correct before the first poll rather than
 * flashing empty.
 */
Alpine.data('notificationBell', (initialUnread) => ({
    unread: initialUnread,
    items: [],
    open: false,
    loaded: false,
    endpoint: '',
    readUrlTemplate: '',
    readAllUrl: '',
    csrfToken: '',
    timer: null,
    polling: false,

    init() {
        this.endpoint = this.$root.dataset.feedUrl;
        this.readUrlTemplate = this.$root.dataset.readUrlTemplate;
        this.readAllUrl = this.$root.dataset.readAllUrl;
        this.csrfToken = this.$root.dataset.csrfToken;

        const island = this.$root.querySelector('[data-notification-items]');

        try {
            this.items = JSON.parse(island?.textContent || '[]');
            this.loaded = true;
        } catch {
            this.items = [];
        }

        if (!this.endpoint) {
            return;
        }

        this.timer = setInterval(() => {
            if (!document.hidden) {
                this.refresh();
            }
        }, NOTIFICATION_POLL_MS);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refresh();
            }
        });
    },

    destroy() {
        if (this.timer) {
            clearInterval(this.timer);
        }
    },

    toggle() {
        this.open = !this.open;

        if (this.open) {
            this.refresh();
        }
    },

    async refresh() {
        if (this.polling) {
            return;
        }

        this.polling = true;

        try {
            const response = await fetch(this.endpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const newest = payload.notifications?.[0];

            if (payload.unread > this.unread && newest && !newest.readAt) {
                this.$store.toasts.add(newest.title, 'info');
            }

            this.unread = payload.unread;
            this.items = payload.notifications ?? [];
            this.loaded = true;
        } catch {
            /* a failed poll is not worth surfacing; the bell just goes stale */
        } finally {
            this.polling = false;
        }
    },

    /**
     * Opening an alert both reads it and follows it. Reading first means the
     * next poll cannot re-toast the alert the user just dismissed.
     */
    async openItem(item) {
        this.open = false;

        if (!item.readAt) {
            await this.markRead(item.id);
        }

        window.location.href = item.url;
    },

    async markRead(id) {
        const response = await this.post(this.readUrlFor(id));

        if (!response || !response.ok) {
            return;
        }

        const now = new Date().toISOString();
        this.items = this.items.map((item) => (
            item.id === id ? { ...item, readAt: item.readAt ?? now } : item
        ));
        this.unread = Math.max(0, this.unread - 1);
    },

    async markAllRead() {
        const response = await this.post(this.readAllUrl);

        if (!response || !response.ok) {
            return;
        }

        const now = new Date().toISOString();
        this.items = this.items.map((item) => ({ ...item, readAt: item.readAt ?? now }));
        this.unread = 0;
    },

    readUrlFor(id) {
        return this.readUrlTemplate.replace('__ID__', encodeURIComponent(id));
    },

    async post(url) {
        try {
            return await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                credentials: 'same-origin',
            });
        } catch {
            /* the server still has the truth; the next poll will correct us */
            return null;
        }
    },
}));

Alpine.store('toasts', {
    items: [],
    add(message, type = 'info', duration = 4000) {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type, visible: true });
        setTimeout(() => this.dismiss(id), duration);
    },
    dismiss(id) {
        const item = this.items.find((t) => t.id === id);
        if (item) {
            item.visible = false;
            setTimeout(() => {
                this.items = this.items.filter((t) => t.id !== id);
            }, 300);
        }
    },
});

Alpine.store('bottomSheet', {
    open: false,
    lastFocused: null,
    toggle() {
        if (this.open) {
            this.close();
        } else {
            this.show();
        }
    },
    show() {
        this.lastFocused = document.activeElement;
        this.open = true;
        document.body.classList.add('bottom-sheet-open');
        Alpine.nextTick(() => document.getElementById('map-filter-sheet')?.focus());
    },
    close() {
        this.open = false;
        document.body.classList.remove('bottom-sheet-open');
        this.lastFocused?.focus?.();
    },
});

Alpine.start();
