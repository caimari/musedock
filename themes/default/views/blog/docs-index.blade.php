@extends('layouts.app')

@section('title', 'Documentación | ' . site_setting('site_name', ''))
@section('description', 'Documentación técnica de ' . site_setting('site_name', ''))

@section('content')
<div class="docs-index-layout" style="background:#fff; min-height:80vh;">
    <div class="container" style="padding-top:2rem; padding-bottom:3rem;">
        <div class="docs-index-header" style="margin-bottom:2.5rem;">
            <h1 style="font-size:2.2rem; font-weight:700; color:#111827; margin-bottom:0.5rem;">Documentación</h1>
            <p style="font-size:1.05rem; color:#6b7280; max-width:600px;">Todo lo que necesitas para configurar, personalizar y sacar el máximo partido a {{ site_setting('site_name', 'MuseDock') }}.</p>
        </div>

        @if(empty($products))
            <p class="text-muted">No hay documentación disponible todavía.</p>
        @else
            {{-- Product cards --}}
            <div class="docs-products-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
                @foreach($products as $productSlug => $product)
                @if($productSlug === '_general') @continue @endif
                @php
                    $firstPostUrl = '#';
                    foreach ($product->sections as $sec) {
                        if (!empty($sec->posts)) { $firstPostUrl = $sec->posts[0]->url; break; }
                    }
                    $hasContent = $product->postCount > 0;
                @endphp
                <a href="{{ $hasContent ? $firstPostUrl : '#' }}" class="docs-product-card" style="display:block; border:1px solid {{ $hasContent ? '#e5e7eb' : '#f3f4f6' }}; border-radius:12px; overflow:hidden; transition:all 0.2s; text-decoration:none; color:inherit; {{ $hasContent ? 'cursor:pointer;' : 'opacity:0.7; pointer-events:none;' }}">
                    <div style="padding:1.5rem 1.75rem;">
                        <h2 style="font-size:1.25rem; font-weight:700; color:#111827; margin:0 0 0.4rem;">{{ $product->name }}</h2>
                        @if($product->description)
                        <p style="font-size:0.88rem; color:#6b7280; margin:0 0 1rem; line-height:1.5;">{{ $product->description }}</p>
                        @endif

                        @if($hasContent)
                            @php
                                $sectionNames = [];
                                foreach ($product->sections as $sec) {
                                    if (!empty($sec->name)) $sectionNames[] = $sec->name;
                                }
                            @endphp
                            @if(!empty($sectionNames))
                            <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.75rem;">
                                @foreach($sectionNames as $sn)
                                <span style="font-size:0.72rem; padding:0.2rem 0.6rem; background:#f3f4f6; border-radius:100px; color:#6b7280;">{{ $sn }}</span>
                                @endforeach
                            </div>
                            @endif
                            <div style="font-size:0.82rem; font-weight:500; color:var(--header-link-hover-color, #2563eb);">
                                {{ $product->postCount }} {{ $product->postCount === 1 ? 'artículo' : 'artículos' }} →
                            </div>
                        @else
                            <p style="font-size:0.85rem; color:#d1d5db; font-style:italic; margin:0.5rem 0 0;">Próximamente</p>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>

            {{-- General (uncategorized) docs if any --}}
            @if(isset($products['_general']) && $products['_general']->postCount > 0)
            <div style="margin-top:1.5rem;">
                <h2 style="font-size:1.1rem; font-weight:600; color:#6b7280; margin-bottom:0.75rem;">Otros</h2>
                @foreach($products['_general']->sections as $section)
                    @foreach($section->posts as $docPost)
                    <a href="{{ $docPost->url }}" style="display:block; padding:0.3rem 0; font-size:0.9rem; color:var(--header-link-hover-color, #2563eb); text-decoration:none;">
                        → {{ $docPost->title }}
                    </a>
                    @endforeach
                @endforeach
            </div>
            @endif
        @endif
    </div>
</div>

<style>
.docs-product-card:hover {
    border-color: var(--header-link-hover-color, #2563eb) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    transform: translateY(-2px);
}
@media (max-width: 575px) {
    .docs-products-grid { grid-template-columns: 1fr !important; }
}
</style>
@endsection
