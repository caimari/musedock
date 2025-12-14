{{-- Play Bootstrap: Custom Menu Partial --}}
<ul id="{{ $options['ul_id'] ?? 'nav' }}" class="{{ $options['ul_class'] ?? 'navbar-nav mx-auto' }}">
    @include('partials.custom_menu_items', ['items' => $items, 'options' => $options])
</ul>
