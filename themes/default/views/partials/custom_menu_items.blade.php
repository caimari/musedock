{{-- resources/views/partials/custom_menu_items.blade.php --}}
@foreach($items as $item)
    <li class="{{ $options['li_class'] ?? '' }}{{ $item->hasChildren() ? ' ' . ($options['parent_class'] ?? 'menu-item-has-children') : '' }}">
        <a href="{{ $item->url() }}" class="{{ $options['a_class'] ?? '' }}">{{ $item->title }}</a>
        @if($item->hasChildren())
            <ul class="{{ $options['submenu_class'] ?? 'submenu' }}">
                @include('partials.custom_menu_items', ['items' => $item->children(), 'options' => $options])
            </ul>
        @endif
    </li>
@endforeach