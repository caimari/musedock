{{-- Header extras: social icons, clock, search - modular elements for header-actions --}}
@php
    $__hdrSocialEnabled = themeOption('header.header_social_enabled', false);
    $__hdrClockEnabled = themeOption('header.header_clock_enabled', false);
    $__hdrSearchEnabled = themeOption('header.header_search_enabled', false);
@endphp

@if($__hdrSocialEnabled)
<div class="header-social-icons">
    @if(site_setting('social_facebook', ''))
        <a href="{{ site_setting('social_facebook') }}" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
    @endif
    @if(site_setting('social_twitter', ''))
        <a href="{{ site_setting('social_twitter') }}" target="_blank" rel="noopener"><i class="fab fa-twitter"></i></a>
    @endif
    @if(site_setting('social_instagram', ''))
        <a href="{{ site_setting('social_instagram') }}" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
    @endif
    @if(site_setting('social_linkedin', ''))
        <a href="{{ site_setting('social_linkedin') }}" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a>
    @endif
    @if(site_setting('social_youtube', ''))
        <a href="{{ site_setting('social_youtube') }}" target="_blank" rel="noopener"><i class="fab fa-youtube"></i></a>
    @endif
    @if(site_setting('social_pinterest', ''))
        <a href="{{ site_setting('social_pinterest') }}" target="_blank" rel="noopener"><i class="fab fa-pinterest"></i></a>
    @endif
    @if(site_setting('social_tiktok', ''))
        <a href="{{ site_setting('social_tiktok') }}" target="_blank" rel="noopener"><i class="fab fa-tiktok"></i></a>
    @endif
    @if(site_setting('social_vimeo', ''))
        <a href="{{ site_setting('social_vimeo') }}" target="_blank" rel="noopener"><i class="fab fa-vimeo-v"></i></a>
    @endif
</div>
@endif

@if($__hdrClockEnabled)
<div class="header-clock" id="headerClockWrap">
    <span class="header-clock-display" id="headerLiveClock"></span>
</div>
<script>
(function(){
    var el = document.getElementById('headerLiveClock');
    if (!el) return;
    var tz = @json(themeOption('topbar.topbar_clock_timezone', 'Europe/Madrid'));
    var locale = @json(themeOption('topbar.topbar_clock_locale', 'es'));
    var localeMap = {'es':'es-ES','en':'en-US','fr':'fr-FR','de':'de-DE','pt':'pt-PT'};
    var full = localeMap[locale] || 'es-ES';
    var opts = {weekday:'long',year:'numeric',month:'long',day:'numeric',timeZone:tz};
    var fmt;
    try { fmt = new Intl.DateTimeFormat(full, opts); } catch(e) { fmt = new Intl.DateTimeFormat('es-ES', opts); }
    function update() {
        var s = fmt.format(new Date());
        el.textContent = s.charAt(0).toUpperCase() + s.slice(1);
    }
    update();
    setInterval(update, 30000);
})();
</script>
@endif
