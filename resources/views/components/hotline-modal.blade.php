@props(['hotline'])

@php
    $hotlineText = trim((string) $hotline);
    $hotlineDigits = preg_replace('/\D/', '', $hotlineText) ?? '';
    $telHotline = $hotlineDigits === ''
        ? ''
        : (str_starts_with($hotlineText, '+') ? '+'.$hotlineDigits : $hotlineDigits);
@endphp

<template x-teleport="body">
    <div x-show="hotlineOpen" x-cloak x-transition.opacity
        class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4">
        <div x-ref="hotlineDialog" x-show="hotlineOpen" x-transition @click.self="closeHotline()" @keydown.escape.window="closeHotline()" @keydown.tab="trapHotline($event)"
            class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg" role="dialog"
            tabindex="-1" aria-modal="true" aria-labelledby="hotline-dialog-title" aria-describedby="hotline-dialog-description">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                </span>
                <div class="min-w-0">
                    <h2 id="hotline-dialog-title" class="text-base font-semibold text-fg">Emergency hotline</h2>
                    <p id="hotline-dialog-description" class="mt-1 text-sm text-muted">
                        For life-threatening emergencies, call our 24/7 hotline
                        <span class="mono font-medium text-fg">{{ $hotline }}</span>.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-muted">
                    <input type="checkbox" x-model="hotlineDontShow" class="h-4 w-4 accent-[var(--color-primary)]">
                    Don't show again
                </label>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-tertiary min-h-11" @click="closeHotline()">Browse map</button>
                    @if ($telHotline !== '')
                        <a href="tel:{{ $telHotline }}" class="btn btn-primary min-h-11">Call now</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</template>
