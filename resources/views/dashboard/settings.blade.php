<x-layouts.dashboard title="Site Information">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h2 class="text-base font-semibold text-fg">Agency and contact information</h2>
            <p class="mt-1 text-sm text-muted">These details appear on the public site and in SMS messages. Changes apply immediately.</p>
        </div>

        <form method="POST" action="{{ route('dashboard.settings.update') }}" class="flex flex-col gap-4 border border-border bg-surface p-6">
            @csrf

            @foreach ($fields as $field)
                <div class="field">
                    <label class="label" for="{{ $field['key'] }}">{{ $field['label'] }}</label>
                    <input id="{{ $field['key'] }}" type="text" name="{{ $field['key'] }}"
                        class="input" value="{{ old($field['key'], $field['value']) }}">
                    @if ($field['help'])
                        <p class="mt-1 text-xs text-muted">{{ $field['help'] }}</p>
                    @endif
                </div>
            @endforeach

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
</x-layouts.dashboard>