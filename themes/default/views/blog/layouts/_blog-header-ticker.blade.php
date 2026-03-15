{{-- Blog Ticker: Clock + Top Tags + Latest Post marquee --}}
{{-- Accepts $tickerPosition = 'top' (default) or 'bottom' --}}
@php
    $tickerPosition = $tickerPosition ?? 'top';

    // Legacy: old unified toggle (used as default for new individual toggles)
    $legacyTicker = themeOption('blog.blog_header_ticker', false);

    // Individual toggle settings (default to legacy value for backward compat)
    $showTopTags    = themeOption('blog.blog_ticker_tags', $legacyTicker);
    $showLatestPost = themeOption('blog.blog_ticker_latest', $legacyTicker);
    $topTagsPos     = themeOption('blog.blog_ticker_tags_position', 'top');
    $latestPostPos  = themeOption('blog.blog_ticker_latest_position', 'top');

    // Clock only shows in 'top' position
    $showClock = ($tickerPosition === 'top') ? themeOption('blog.blog_ticker_clock', false) : false;
    $clockLocale = themeOption('blog.blog_header_clock_locale', 'es');
    $clockTimezone = themeOption('blog.blog_header_clock_timezone', 'Europe/Madrid');

    // Determine what to render in THIS position
    $renderTags    = $showTopTags && ($topTagsPos === $tickerPosition);
    $renderLatest  = $showLatestPost && ($latestPostPos === $tickerPosition);

    // Skip entirely if nothing to render here
    $tickerSkip = (!$showClock && !$renderTags && !$renderLatest);
@endphp
@if(!$tickerSkip)
@php
    // Only query DB if we need data
    $tickerTags = [];
    $tickerPosts = [];
    try {
        $pdo = \Screenart\Musedock\Database::connect();
        $tenantId = tenant_id();

        if ($renderTags) {
            if ($tenantId) {
                $stmt = $pdo->prepare("
                    SELECT t.id, t.name, t.slug, COUNT(pt.post_id) as post_count
                    FROM blog_tags t
                    INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                    INNER JOIN blog_posts bp ON bp.id = pt.post_id
                    WHERE bp.tenant_id = ? AND bp.status = 'published'
                    GROUP BY t.id, t.name, t.slug
                    ORDER BY post_count DESC
                    LIMIT 6
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->query("
                    SELECT t.id, t.name, t.slug, COUNT(pt.post_id) as post_count
                    FROM blog_tags t
                    INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                    INNER JOIN blog_posts bp ON bp.id = pt.post_id
                    WHERE bp.tenant_id IS NULL AND bp.status = 'published'
                    GROUP BY t.id, t.name, t.slug
                    ORDER BY post_count DESC
                    LIMIT 6
                ");
            }
            $tickerTags = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }

        if ($renderLatest) {
            if ($tenantId) {
                $stmt = $pdo->prepare("
                    SELECT title, slug FROM blog_posts
                    WHERE tenant_id = ? AND status = 'published'
                    ORDER BY published_at DESC
                    LIMIT 8
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->query("
                    SELECT title, slug FROM blog_posts
                    WHERE tenant_id IS NULL AND status = 'published'
                    ORDER BY published_at DESC
                    LIMIT 8
                ");
            }
            $tickerPosts = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }
    } catch (\Exception $e) {
        // Silencioso
    }
@endphp

@if($showClock || !empty($tickerTags) || !empty($tickerPosts))
<div class="blog-header-ticker {{ $tickerPosition === 'bottom' ? 'mt-3' : 'mb-2' }}">
    {{-- Live Clock (top only) --}}
    @if($showClock)
    <div class="ticker-clock-row">
        <span class="ticker-label-clock"><i class="fas fa-clock"></i></span>
        <span class="ticker-clock-display" id="tickerLiveClock"></span>
    </div>
    @endif

    {{-- Top Tags --}}
    @if($renderTags && !empty($tickerTags))
    <div class="ticker-tags-row">
        <span class="ticker-label-tags"><i class="fas fa-hashtag"></i> Top Tags</span>
        <div class="ticker-tags-list">
            @foreach($tickerTags as $tag)
            <a href="{{ blog_url($tag->slug, 'tag') }}" class="ticker-tag">{{ $tag->name }}</a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Latest Post ticker --}}
    @if($renderLatest && !empty($tickerPosts))
    <div class="ticker-latest-row">
        <span class="ticker-label-latest">Latest Post</span>
        <div class="ticker-marquee-wrapper">
            <div class="ticker-marquee">
                @foreach($tickerPosts as $tp)
                <a href="{{ blog_url($tp->slug) }}" class="ticker-marquee-item">
                    <i class="fas fa-arrow-circle-right"></i> {{ $tp->title }}
                </a>
                @endforeach
                {{-- Duplicate for seamless loop --}}
                @foreach($tickerPosts as $tp)
                <a href="{{ blog_url($tp->slug) }}" class="ticker-marquee-item">
                    <i class="fas fa-arrow-circle-right"></i> {{ $tp->title }}
                </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>

@if($showClock)
<script>
(function() {
    var clockEl = document.getElementById('tickerLiveClock');
    if (!clockEl) return;

    var tz = @json($clockTimezone);
    var locale = @json($clockLocale);

    var localeMap = {
        'es': 'es-ES',
        'en': 'en-US',
        'fr': 'fr-FR',
        'de': 'de-DE',
        'pt': 'pt-PT'
    };
    var fullLocale = localeMap[locale] || 'es-ES';

    var dateOpts = {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: tz
    };

    var timeOpts = {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        timeZone: tz
    };

    if (locale === 'en') {
        timeOpts.hour12 = true;
    } else {
        timeOpts.hour12 = false;
    }

    var dateFmt, timeFmt;
    try {
        dateFmt = new Intl.DateTimeFormat(fullLocale, dateOpts);
        timeFmt = new Intl.DateTimeFormat(fullLocale, timeOpts);
    } catch(e) {
        dateFmt = new Intl.DateTimeFormat('es-ES', dateOpts);
        timeFmt = new Intl.DateTimeFormat('es-ES', timeOpts);
    }

    function updateClock() {
        var now = new Date();
        var dateStr = dateFmt.format(now);
        var timeStr = timeFmt.format(now);
        dateStr = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);
        clockEl.textContent = dateStr + '  \u00B7  ' + timeStr;
    }

    updateClock();
    setInterval(updateClock, 1000);
})();
</script>
@endif

{{-- Only output styles once (top position) to avoid duplication --}}
@if($tickerPosition === 'top')
<style>
.blog-header-ticker {
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
}

/* Clock row */
.ticker-clock-row {
    display: flex;
    align-items: center;
    border-bottom: 1px solid #e5e5e5;
    background: #fafafa;
}
.ticker-label-clock {
    background: #333;
    color: #fff;
    font-weight: 700;
    font-size: 0.8rem;
    padding: 6px 14px;
    white-space: nowrap;
}
.ticker-clock-display {
    padding: 6px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

/* Top Tags row */
.ticker-tags-row {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e5e5e5;
}
.ticker-label-tags {
    background: #333;
    color: #fff;
    font-weight: 700;
    font-size: 0.8rem;
    padding: 6px 14px;
    white-space: nowrap;
    letter-spacing: 0.5px;
}
.ticker-tags-list {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 14px;
    flex-wrap: wrap;
}
.ticker-tag {
    display: inline-block;
    background: #e74c3c;
    color: #fff !important;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 3px;
    text-decoration: none !important;
    text-transform: lowercase;
    transition: background 0.2s;
}
.ticker-tag:hover {
    background: #c0392b;
    text-decoration: none !important;
    color: #fff !important;
}

/* Latest Post row */
.ticker-latest-row {
    display: flex;
    align-items: center;
    overflow: hidden;
}
.ticker-label-latest {
    background: #e74c3c;
    color: #fff;
    font-weight: 700;
    font-size: 0.8rem;
    padding: 8px 14px;
    white-space: nowrap;
    letter-spacing: 0.5px;
    flex-shrink: 0;
}
.ticker-marquee-wrapper {
    flex: 1;
    overflow: hidden;
    position: relative;
}
.ticker-marquee {
    display: flex;
    white-space: nowrap;
    animation: tickerScroll 30s linear infinite;
}
.ticker-marquee:hover {
    animation-play-state: paused;
}
.ticker-marquee-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 24px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #333 !important;
    text-decoration: none !important;
    white-space: nowrap;
    flex-shrink: 0;
}
.ticker-marquee-item:hover {
    color: #e74c3c !important;
    text-decoration: none !important;
}
.ticker-marquee-item i {
    color: #e74c3c;
    font-size: 0.75rem;
}

@keyframes tickerScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

@media (max-width: 767.98px) {
    .ticker-tags-row { display: none !important; }
    .ticker-clock-display { font-size: 0.75rem; padding: 5px 10px; }
    .ticker-label-clock { font-size: 0.7rem; padding: 5px 10px; }
    .ticker-label-tags, .ticker-label-latest { font-size: 0.7rem; padding: 5px 10px; }
    .ticker-tag { font-size: 0.65rem; padding: 2px 6px; }
    .ticker-marquee-item { font-size: 0.75rem; padding: 6px 16px; }
}
</style>
@endif

@endif
@endif
