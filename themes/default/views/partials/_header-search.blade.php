{{-- Header Search Icon --}}
@php
    $__searchEnabled = $headerSearchEnabled ?? false;
    $__searchMode = themeOption('header.header_search_mode', 'modal');
@endphp
@if($__searchEnabled)
    @if($__searchMode === 'inline')
    <form action="{{ url('/search') }}" method="GET" class="md-header-inline-search" role="search" style="display:flex;align-items:center;width:clamp(150px,16vw,240px);max-width:100%;border:1px solid #d9dfe4;border-radius:7px;background:#fff;overflow:hidden;">
        <input type="search" name="q" aria-label="{{ ['en' => 'Search content', 'ca' => 'Cercar contingut'][detectLanguage()] ?? 'Buscar contenido' }}" placeholder="{{ ['en' => 'Search…', 'ca' => 'Cercar…'][detectLanguage()] ?? 'Buscar…' }}" required style="width:100%;min-width:0;height:38px;border:0;background:transparent;padding:8px 10px;font:inherit;font-size:.85rem;color:#202c3e;">
        <button type="submit" aria-label="{{ ['en' => 'Search', 'ca' => 'Cercar'][detectLanguage()] ?? 'Buscar' }}" style="flex-shrink:0;border:0;background:transparent;color:#202c3e;padding:9px 11px;cursor:pointer;"><i class="fas fa-search" aria-hidden="true"></i></button>
    </form>
    @elseif($__searchMode === 'page')
    <a href="{{ url('/search') }}" class="header-search-toggle" aria-label="{{ __('search.search') }}" title="{{ __('search.search') }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </a>
    @else
    <button type="button" class="header-search-toggle" aria-label="{{ __('search.search') }}" title="{{ __('search.search') }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </button>
    @endif
@endif
