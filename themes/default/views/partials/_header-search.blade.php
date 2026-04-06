{{-- Header Search Icon --}}
@php
    $__searchEnabled = $headerSearchEnabled ?? false;
    $__searchMode = themeOption('header.header_search_mode', 'modal');
@endphp
@if($__searchEnabled)
    @if($__searchMode === 'page')
    <a href="{{ url('/search') }}" class="header-search-toggle" aria-label="{{ __('search.search') }}" title="{{ __('search.search') }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </a>
    @else
    <button type="button" class="header-search-toggle" aria-label="{{ __('search.search') }}" title="{{ __('search.search') }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </button>
    @endif
@endif
