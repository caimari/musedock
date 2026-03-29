{{-- Search Overlay --}}
@php
    $__searchEnabled = $headerSearchEnabled ?? themeOption('header.header_search_enabled', false);
    $__searchLang = $currentLang ?? 'es';
@endphp
@if($__searchEnabled)
<div class="md-search-overlay" id="md-search-overlay">
    <div class="md-search-overlay-inner">
        <button type="button" class="md-search-close" id="md-search-close" aria-label="Cerrar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <form action="{{ url('/search') }}" method="GET" class="md-search-form">
            <div class="md-search-input-wrap">
                <svg class="md-search-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" name="q" class="md-search-input" placeholder="{{ $__searchLang === 'en' ? 'Search...' : 'Buscar...' }}" autocomplete="off" autofocus>
            </div>
            <p class="md-search-hint">{{ $__searchLang === 'en' ? 'Press Enter to search or Esc to close' : 'Pulsa Enter para buscar o Esc para cerrar' }}</p>
        </form>
    </div>
</div>

<style>
.header-search-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px;
    color: var(--header-link-color, #333);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
    vertical-align: middle;
}
.header-search-toggle:hover {
    color: var(--header-link-hover-color, #ff5e15);
}
.md-search-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 18vh;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.25s ease, visibility 0.25s ease;
}
.md-search-overlay.active {
    opacity: 1;
    visibility: visible;
}
.md-search-overlay-inner {
    width: 100%;
    max-width: 640px;
    padding: 0 20px;
    position: relative;
}
.md-search-close {
    position: absolute;
    top: -60px;
    right: 20px;
    background: none;
    border: none;
    color: rgba(255,255,255,0.6);
    cursor: pointer;
    padding: 8px;
    transition: color 0.2s;
}
.md-search-close:hover {
    color: #fff;
}
.md-search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.md-search-icon {
    position: absolute;
    left: 20px;
    color: rgba(255,255,255,0.4);
    pointer-events: none;
}
.md-search-input {
    width: 100%;
    padding: 18px 20px 18px 56px;
    font-size: 20px;
    font-weight: 400;
    color: #fff;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 12px;
    outline: none;
    font-family: inherit;
    transition: border-color 0.2s, background 0.2s;
}
.md-search-input::placeholder {
    color: rgba(255,255,255,0.35);
}
.md-search-input:focus {
    border-color: rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.12);
}
.md-search-hint {
    text-align: center;
    color: rgba(255,255,255,0.35);
    font-size: 13px;
    margin-top: 14px;
}
@media (max-width: 575px) {
    .md-search-overlay { padding-top: 12vh; }
    .md-search-input { font-size: 17px; padding: 15px 16px 15px 48px; }
    .md-search-close { top: -50px; right: 10px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var overlay = document.getElementById('md-search-overlay');
    if (!overlay) return;
    var input = overlay.querySelector('.md-search-input');
    var closeBtn = document.getElementById('md-search-close');

    // Open
    document.querySelectorAll('.header-search-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(function() { input.focus(); }, 100);
        });
    });

    // Close
    function closeSearch() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        input.value = '';
    }
    closeBtn.addEventListener('click', closeSearch);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeSearch();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) closeSearch();
    });
});
</script>
@endif
