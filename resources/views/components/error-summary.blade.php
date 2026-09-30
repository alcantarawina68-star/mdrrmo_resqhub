@if ($errors->any())
    <x-alert type="danger" class="mt-4">
        <p class="font-semibold">Please fix the following before continuing:</p>
        <ul class="mt-1 list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
