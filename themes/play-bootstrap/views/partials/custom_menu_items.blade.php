{{-- Play Bootstrap: Custom Menu Items Partial --}}
@foreach($items as $item)
    @php
        $hasChildren = $item->hasChildren();
        $liClass = $options['li_class'] ?? 'nav-item';
        if ($hasChildren) {
            $liClass .= ' ' . ($options['parent_class'] ?? 'nav-item-has-children');
        }
    @endphp
    <li class="{{ $liClass }}">
        <a href="{{ $item->url() }}" class="{{ $options['a_class'] ?? '' }}">{{ $item->title }}</a>
        @if($hasChildren)
            <ul class="{{ $options['submenu_class'] ?? 'ud-submenu' }}">
                @foreach($item->children() as $child)
                    <li class="{{ $options['submenu_item_class'] ?? 'ud-submenu-item' }}">
                        <a href="{{ $child->url() }}" class="{{ $options['submenu_link_class'] ?? 'ud-submenu-link' }}">{{ $child->title }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </li>
@endforeach
