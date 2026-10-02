@if (session('status'))
    <x-alert type="success" class="mt-4">{{ session('status') }}</x-alert>
@endif
