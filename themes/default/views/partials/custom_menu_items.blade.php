{{-- resources/views/partials/custom_menu_items.blade.php --}}
<!-- DEBUG: Cargando partials/custom_menu_items.blade.php -->

@foreach($items as $item)
    <!-- DEBUG: Procesando item: {{ $item->title ?? 'SIN TITULO' }} -->
    <li class="{{ $options['li_class'] ?? '' }}{{ $item->hasChildren() ? ' menu-item-has-children' : '' }}">
        <a href="{{ $item->url() }}" class="{{ $options['a_class'] ?? '' }}">{{ $item->title }}</a>
        @if($item->hasChildren())
            <ul class="{{ $options['submenu_class'] ?? 'submenu' }}">
                 <!-- DEBUG: Incluyendo subitems para {{ $item->title }} -->
                @include('partials.custom_menu_items', ['items' => $item->children(), 'options' => $options])
            </ul>
        @endif
    </li>
@endforeach