@props(['width' => 'max-w-[1600px]'])

@if (session('status'))
    <div class="border-b border-success/30 bg-success/10 px-4 py-3 text-sm text-success" role="status">
        <div class="mx-auto {{ $width }}">{{ session('status') }}</div>
    </div>
@endif

@if ($errors->any())
    <div class="border-b border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
        <div class="mx-auto {{ $width }}">
            <p class="font-semibold">Something needs attention.</p>
            <ul class="mt-1 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
