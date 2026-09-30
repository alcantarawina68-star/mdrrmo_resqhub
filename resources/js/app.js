import Alpine from 'alpinejs';
import L from 'leaflet';
import { createIncidentMap } from './map';

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
        Alpine.nextTick(() => document.getElementById('map-filter-sheet')?.focus());
    },
    close() {
        this.open = false;
        this.lastFocused?.focus?.();
    },
});

Alpine.start();
