{{-- resources/views/partials/custom_menu.blade.php --}}
<ul id="{{ $options['ul_id'] ?? 'navigation' }}" class="{{ $options['ul_class'] ?? $options['nav_class'] ?? 'navigation' }}">
    @include('partials.custom_menu_items', ['items' => $items, 'options' => $options])
</ul>