@php
  $categories = $categories ?? [];
@endphp

<aside class="ziph-side-widget-area">
  @if(!empty($categories))
  <div class="ziph-side-widget widget_categories mb-4">
    <h4 class="ziph-widget-title">{{ __('blog.categories') ?? __('Categorías') }}</h4>
    <ul class="list-unstyled mb-0">
      @foreach($categories as $category)
        @php
          $slug = is_object($category) ? ($category->slug ?? '') : ($category['slug'] ?? '');
          $name = is_object($category) ? ($category->name ?? '') : ($category['name'] ?? '');
        @endphp
        @if($slug && $name)
        <li class="mb-2">
          <a href="/blog/category/{{ $slug }}">{{ $name }}</a>
        </li>
        @endif
      @endforeach
    </ul>
  </div>
  @endif

  @custommenu('sidebar', null, [
    'ul_class' => 'list-unstyled mb-0',
    'li_class' => 'mb-2',
  ])
</aside>
