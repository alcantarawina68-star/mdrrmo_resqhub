@props(['links' => [], 'label' => 'Main'])

<nav {{ $attributes->merge(['class' => 'gap-1']) }} aria-label="{{ $label }}">
    @foreach ($links as $link)
        <a href="{{ $link['href'] }}" @class(['nav-link', 'nav-link-active' => $link['active']])>{{ $link['label'] }}</a>
    @endforeach
</nav>
