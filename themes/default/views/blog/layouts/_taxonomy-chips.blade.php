{{--
  _taxonomy-chips.blade.php
  Renders styled tag/category chips for a post in listings and single view.

  Usage:
    @include('blog.layouts._taxonomy-chips', ['post' => $post])

  The $post object must have:
    - $post->categories (array of objects with ->name, ->slug, ->color)
    - $post->tags       (array of objects with ->name, ->slug, ->color)
--}}
@php
  $__cats = !empty($post->categories) ? (array)$post->categories : [];
  $__tags = !empty($post->tags)       ? (array)$post->tags       : [];
  $__hasTaxonomy = !empty($__cats) || !empty($__tags);
@endphp
@if($__hasTaxonomy)
<div class="post-taxonomy-chips">
  @foreach($__cats as $__cat)
    @php
      // Category chip — use defined color or fallback amber
      $__color = !empty($__cat->color) ? trim($__cat->color) : null;
      if ($__color) {
          // Derive a light tinted bg from the hex color (20% opacity via hex8)
          $__hex = ltrim($__color, '#');
          if (strlen($__hex) === 3) { $__hex = $__hex[0].$__hex[0].$__hex[1].$__hex[1].$__hex[2].$__hex[2]; }
          $__r = hexdec(substr($__hex,0,2));
          $__g = hexdec(substr($__hex,2,2));
          $__b = hexdec(substr($__hex,4,2));
          $__chipStyle = "background:rgba({$__r},{$__g},{$__b},0.12);color:{$__color};border-color:rgba({$__r},{$__g},{$__b},0.35);";
      } else {
          $__chipStyle = 'background:#fff8e6;color:#c47a00;border-color:rgba(240,208,128,0.8);';
      }
    @endphp
    <a href="{{ blog_url($__cat->slug, 'category') }}"
       class="tx-chip tx-chip-cat"
       style="{{ $__chipStyle }}">{{ $__cat->name }}</a>
  @endforeach
  @foreach($__tags as $__tag)
    @php
      $__color = !empty($__tag->color) ? trim($__tag->color) : null;
      if ($__color) {
          $__hex = ltrim($__color, '#');
          if (strlen($__hex) === 3) { $__hex = $__hex[0].$__hex[0].$__hex[1].$__hex[1].$__hex[2].$__hex[2]; }
          $__r = hexdec(substr($__hex,0,2));
          $__g = hexdec(substr($__hex,2,2));
          $__b = hexdec(substr($__hex,4,2));
          $__chipStyle = "background:rgba({$__r},{$__g},{$__b},0.10);color:{$__color};border-color:rgba({$__r},{$__g},{$__b},0.32);";
      } else {
          $__chipStyle = 'background:#eaf0fb;color:#1a4fa0;border-color:rgba(154,184,232,0.8);';
      }
    @endphp
    <a href="{{ blog_url($__tag->slug, 'tag') }}"
       class="tx-chip tx-chip-tag"
       style="{{ $__chipStyle }}">{{ $__tag->name }}</a>
  @endforeach
</div>
@endif

<style>
.post-taxonomy-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}
.tx-chip {
  display: inline-block;
  padding: 2px 8px;
  font-family: 'JetBrains Mono', 'Fira Mono', 'Courier New', monospace;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  border-radius: 3px;
  border: 1px solid;
  text-decoration: none;
  white-space: nowrap;
  transition: filter 0.15s ease, opacity 0.15s ease;
  line-height: 1.7;
}
.tx-chip:hover {
  filter: brightness(0.88);
  opacity: 0.9;
  text-decoration: none;
}
.tx-chip:visited { color: inherit; }
@media (max-width: 575px) {
  .tx-chip { font-size: 9.5px; padding: 2px 6px; }
}
</style>
