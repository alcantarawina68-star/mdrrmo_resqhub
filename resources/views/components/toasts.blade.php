{{--
    Toast stack, rendered once per layout.

    Extracted from app.blade.php and dashboard.blade.php, which carried a
    byte-identical copy of this markup. `resources/js/app.js` is the only
    producer-facing half: the toasts store and anything that calls
    $store.toasts.add().
--}}
<div x-data x-cloak class="pointer-events-none fixed inset-x-0 top-20 z-[100] flex flex-col items-end gap-2 px-4" role="region" aria-label="Notifications" aria-live="polite">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="toast" :class="{
                'border-success/30 bg-success/10 text-success': toast.type === 'success',
                'border-danger/30 bg-danger/10 text-danger': toast.type === 'danger',
                'border-warning/40 bg-warning/15 text-warning-fg': toast.type === 'warning',
                'border-border bg-surface text-fg': toast.type === 'info',
            }"
            x-show="toast.visible"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-full opacity-0">
            <span class="flex-1 text-sm" x-text="toast.message"></span>
            <button type="button" class="shrink-0 text-muted hover:text-fg" @click="$store.toasts.dismiss(toast.id)">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    </template>
</div>
