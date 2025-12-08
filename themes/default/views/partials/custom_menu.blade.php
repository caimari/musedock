{{-- resources/views/partials/custom_menu.blade.php --}}
<!-- DEBUG: Cargando partials/custom_menu.blade.php -->
<h1>MENU PRINCIPAL CARGADO</h1> {{-- O algo menos intrusivo --}}

<ul id="{{ $options['ul_id'] ?? 'navigation' }}" class="{{ $options['nav_class'] ?? 'navigation' }}">
    @include('partials.custom_menu_items', ['items' => $items, 'options' => $options])
</ul>