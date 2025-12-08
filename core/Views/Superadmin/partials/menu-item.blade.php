<li class="list-group-item" data-id="{{ $item->id }}" data-type="{{ $item->type }}" data-link="{{ $item->link }}">
    <span class="drag-handle me-2">☰</span>
    <input class="form-control form-control-sm d-inline-block w-25 item-title" type="text" value="{{ $item->title }}" placeholder="Título">
    <input class="form-control form-control-sm d-inline-block w-50 item-link ms-2" type="text" value="{{ $item->link }}" placeholder="Slug/URL">
    <button type="button" class="btn btn-danger btn-sm ms-2 delete-item">🗑️</button>

    @if (!empty($item->children))
        <ul class="list-group ms-4">
            @foreach ($item->children as $child)
                @include('superadmin.menus.partials.menu-item', ['item' => $child])
            @endforeach
        </ul>
    @endif
</li>
