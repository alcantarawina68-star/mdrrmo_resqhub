import Alpine from 'alpinejs';
import L from 'leaflet';
import { createIncidentMap } from './map';

window.Alpine = Alpine;
window.L = L;
window.ResqHub = {
    createIncidentMap,
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
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
});

Alpine.start();
