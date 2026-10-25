<x-layouts.app title="Report submitted">
    <div class="mx-auto w-full max-w-xl px-4 py-12 sm:px-6">
        <div class="card p-8 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success/10 text-success">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </span>

            <h1 class="mt-4 text-2xl font-semibold text-fg">Report submitted</h1>
            <p class="mt-2 text-sm text-muted">
                Thank you. Our team will verify your report before responders are dispatched.
            </p>

            <div class="mt-6 rounded-lg border border-border bg-bg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted">Reference number</p>
                <p class="mono mt-1 text-xl font-semibold text-fg">{{ $incidentNumber }}</p>
            </div>

            <p class="mt-4 text-sm text-muted">
                Keep this reference number. You can use it when following up with
                {{ site_setting('agency_short_name') }}.
            </p>

            @if (! blank($contactNumber))
                <p class="mt-2 text-sm text-muted">
                    We may contact you at <span class="font-medium text-fg">{{ $contactNumber }}</span> if we need more details.
                </p>
            @endif

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <a href="{{ route('home') }}" class="btn btn-primary">Submit another report</a>
                <a href="{{ route('home') }}" class="btn btn-secondary">Back to home</a>
            </div>
        </div>
    </div>
</x-layouts.app>
