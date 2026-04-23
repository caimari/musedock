{{-- Blog Layout: Mosaic (varied square cards with overlay) --}}
@if(!empty($posts) && count($posts) > 0)
    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'top'])

    @php
        // Patrón de tamaños cíclico para variedad visual
        // hero = 2x2, wide = 2x1, tall = 1x2, normal = 1x1
        $__sizePattern = ['hero', 'normal', 'normal', 'normal', 'normal', 'wide', 'normal', 'normal', 'tall', 'normal', 'normal', 'normal', 'wide', 'normal', 'normal', 'normal'];
        $__patternLen = count($__sizePattern);
    @endphp

    <div class="mosaic-grid mt-4">
    @foreach($posts as $__idx => $post)
    {!! render_ad_slot('in-feed', ['index' => $__idx]) !!}
    @php
        $post = is_object($post) ? $post : (object)$post;
        $isBrief = (($post->post_type ?? 'post') === 'brief');

        if (!$isBrief && $post->featured_image && !($post->hide_featured_image ?? false)) {
            $imageUrl = (str_starts_with($post->featured_image, '/') || str_starts_with($post->featured_image, 'http'))
                ? $post->featured_image
                : asset($post->featured_image);
        } else {
            $imageUrl = '/assets/themes/default/img/blog-default.svg';
        }
        $imageUrl = media_thumb_url($imageUrl, 'medium');

        $dateVal = $post->published_at ?? $post->created_at;
        $dateStr = $dateVal instanceof \DateTime ? $dateVal->format('d/m/Y') : date('d/m/Y', strtotime($dateVal));

        $postAuthorName = null;
        if (!empty($post->user_id)) {
            if (($post->user_type ?? '') === 'superadmin' && !empty($post->tenant_id)) {
                $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                $__stmt = $__pdo->prepare("SELECT name FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
                $__stmt->execute([$post->tenant_id]);
                $__ra = $__stmt->fetch(\PDO::FETCH_OBJ);
                $postAuthorName = $__ra ? $__ra->name : null;
            }
            if (!$postAuthorName) {
                $__author = match($post->user_type ?? 'admin') {
                    'superadmin' => \Screenart\Musedock\Models\SuperAdmin::find($post->user_id),
                    'admin' => \Screenart\Musedock\Models\Admin::find($post->user_id),
                    'user' => \Screenart\Musedock\Models\User::find($post->user_id),
                    default => null,
                };
                $postAuthorName = $__author ? $__author->name : null;
            }
        }

        // Categorías (max 2) — cada chip lleva su tipo ('category'|'tag') para construir URL
        $__mosaicCats = [];
        $__rawCats = !empty($post->categories) ? (array)$post->categories : [];
        if (empty($__rawCats) && !empty($post->id)) {
            $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
            $__catStmt = $__pdo->prepare("SELECT c.name, c.slug, c.color FROM blog_categories c INNER JOIN blog_post_categories pc ON pc.category_id = c.id WHERE pc.post_id = ?");
            $__catStmt->execute([$post->id]);
            $__rawCats = $__catStmt->fetchAll(\PDO::FETCH_OBJ);
        }
        foreach ($__rawCats as $__c) {
            $__o = is_object($__c) ? $__c : (object)$__c;
            $__o->__type = 'category';
            $__mosaicCats[] = $__o;
        }
        // Fallback: si el post no tiene categorías, usar tags para que todas las cards
        // (incluida la hero) muestren chips encima del título.
        if (empty($__mosaicCats)) {
            $__rawTags = !empty($post->tags) ? (array)$post->tags : [];
            if (empty($__rawTags) && !empty($post->id)) {
                $__pdo = $__pdo ?? \Screenart\Musedock\Database::connect();
                $__tagStmt = $__pdo->prepare("SELECT t.name, t.slug, t.color FROM blog_tags t INNER JOIN blog_post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? LIMIT 2");
                $__tagStmt->execute([$post->id]);
                $__rawTags = $__tagStmt->fetchAll(\PDO::FETCH_OBJ);
            }
            foreach ($__rawTags as $__t) {
                $__o = is_object($__t) ? $__t : (object)$__t;
                $__o->__type = 'tag';
                $__mosaicCats[] = $__o;
            }
        }
        if (count($__mosaicCats) > 2) {
            shuffle($__mosaicCats);
            $__mosaicCats = array_slice($__mosaicCats, 0, 2);
        }

        // Excerpt
        $__excerpt = $post->excerpt ?: strip_tags($post->content ?? '');
        $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));

        // Tamaño de la card según el patrón
        $__cardSize = $__sizePattern[$__idx % $__patternLen];
        $__isLarge = in_array($__cardSize, ['hero', 'wide', 'tall']);
        $__excerptLen = $__isLarge ? 150 : 90;
        $__excerpt = mb_strlen($__excerpt) > $__excerptLen ? mb_substr($__excerpt, 0, $__excerptLen) . '...' : $__excerpt;
    @endphp
    <article class="mosaic-card mosaic-{{ $__cardSize }}">
        {{-- Link principal: cubre toda la tarjeta vía ::after, pero permite
             que los chips de categoría (declarados después con mayor z-index)
             sean clicables independientemente. --}}
        <a href="{{ blog_url($post->slug) }}" class="mosaic-card-link" aria-label="{{ $post->title }}">
            <img src="{{ $imageUrl }}" alt="{{ $post->title }}" loading="lazy" class="mosaic-card-img">
        </a>
        <div class="mosaic-card-overlay">
            @if(!empty($__mosaicCats))
            <div class="mosaic-card-cats">
                @foreach($__mosaicCats as $cat)
                    <a href="{{ blog_url($cat->slug ?? '', $cat->__type ?? 'category') }}" class="mosaic-card-cat">{{ $cat->name }}</a>
                @endforeach
            </div>
            @endif
            <h2 class="mosaic-card-title"><a href="{{ blog_url($post->slug) }}" class="mosaic-card-title-link">{{ $post->title }}</a></h2>
            @if($__excerpt)
            <p class="mosaic-card-excerpt">{{ $__excerpt }}</p>
            @endif
            <div class="mosaic-card-meta">
                @if($postAuthorName)<span>{{ $postAuthorName }}</span>@endif
                <span>{{ $dateStr }}</span>
            </div>
        </div>
    </article>
    @endforeach
    </div>

    @include('blog.layouts._blog-pagination')
    @include('blog.layouts._blog-header-ticker', ['tickerPosition' => 'bottom'])
@else
    <p class="text-muted text-center">{{ __('blog.no_posts') }}</p>
@endif

@include('blog.layouts._blog-shared-styles')
<style>
/* ============================================
   BLOG LAYOUT: MOSAIC — Varied card sizes
   ============================================ */
.mosaic-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-auto-rows: minmax(280px, 1fr);
    grid-auto-flow: dense;
    gap: 12px;
}
.mosaic-card {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    background: #1a1a1a;
}
.mosaic-card-link {
    display: block;
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    min-height: 280px;
    text-decoration: none !important;
    color: #fff !important;
    overflow: hidden;
    z-index: 1;
}
.mosaic-card-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.4s ease, filter 0.4s ease;
}
.mosaic-card:hover .mosaic-card-img {
    transform: scale(1.05);
    filter: brightness(0.7);
}
/* Gradient overlay */
.mosaic-card-link::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.82) 0%, rgba(0,0,0,0.25) 50%, transparent 100%);
    z-index: 1;
    pointer-events: none;
}
/* Content overlay — sibling del link; permite clics pasantes salvo en
   chips/título/enlaces internos (que recuperan pointer-events). */
.mosaic-card-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 16px 18px;
    z-index: 2;
    color: #fff;
    pointer-events: none;
}
.mosaic-card-overlay a,
.mosaic-card-overlay .mosaic-card-cats,
.mosaic-card-overlay .mosaic-card-title {
    pointer-events: auto;
}
/* Categories */
.mosaic-card-cats {
    display: flex;
    gap: 6px;
    margin-bottom: 8px;
    flex-wrap: nowrap;
    overflow: hidden;
}
.mosaic-card-cat {
    display: inline-block;
    background: rgba(255,255,255,0.18);
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
/* Forzar blanco en chips y título aunque sean <a> — evita colores de
   :link/:visited del tema. */
a.mosaic-card-cat,
a.mosaic-card-cat:link,
a.mosaic-card-cat:visited,
a.mosaic-card-cat:hover,
a.mosaic-card-cat:active,
a.mosaic-card-cat:focus,
a.mosaic-card-title-link,
a.mosaic-card-title-link:link,
a.mosaic-card-title-link:visited,
a.mosaic-card-title-link:hover,
a.mosaic-card-title-link:active,
a.mosaic-card-title-link:focus {
    color: #fff !important;
    text-decoration: none !important;
}
a.mosaic-card-cat:hover { background: rgba(255,255,255,0.32); }
/* Title */
.mosaic-card-title {
    color: #fff !important;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.3;
    margin: 0 0 4px !important;
    text-shadow: 0 1px 3px rgba(0,0,0,0.4);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
/* Excerpt */
.mosaic-card-excerpt {
    color: rgba(255,255,255,0.78);
    font-size: 0.75rem;
    line-height: 1.45;
    margin: 0 0 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}
/* Meta */
.mosaic-card-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.7);
}
.mosaic-card-meta span + span::before {
    content: '|';
    margin-right: 8px;
    opacity: 0.4;
}

/* ============================================
   SIZE VARIANTS
   ============================================ */

/* HERO: 2 columns x 2 rows — the biggest */
.mosaic-hero {
    grid-column: span 2;
    grid-row: span 2;
}
.mosaic-hero .mosaic-card-overlay { padding: 24px 28px; }
.mosaic-hero .mosaic-card-title {
    font-size: 1.5rem;
    -webkit-line-clamp: 3;
}
.mosaic-hero .mosaic-card-excerpt {
    font-size: 0.85rem;
    -webkit-line-clamp: 3;
}
.mosaic-hero .mosaic-card-cat {
    font-size: 0.72rem;
    padding: 3px 10px;
}
.mosaic-hero .mosaic-card-meta { font-size: 0.8rem; }

/* WIDE: 2 columns x 1 row — horizontal panoramic */
.mosaic-wide {
    grid-column: span 2;
}
.mosaic-wide .mosaic-card-overlay { padding: 18px 22px; }
.mosaic-wide .mosaic-card-title {
    font-size: 1.15rem;
    -webkit-line-clamp: 2;
}
.mosaic-wide .mosaic-card-excerpt {
    font-size: 0.8rem;
    -webkit-line-clamp: 2;
}
.mosaic-wide .mosaic-card-cat {
    font-size: 0.68rem;
    padding: 3px 9px;
}

/* TALL: 1 column x 2 rows — vertical */
.mosaic-tall {
    grid-row: span 2;
}
.mosaic-tall .mosaic-card-overlay { padding: 20px 18px; }
.mosaic-tall .mosaic-card-title {
    font-size: 1.1rem;
    -webkit-line-clamp: 3;
}
.mosaic-tall .mosaic-card-excerpt {
    font-size: 0.78rem;
    -webkit-line-clamp: 3;
}

/* NORMAL: 1x1 — default, no extra rules needed */

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 991px) {
    .mosaic-grid {
        grid-template-columns: repeat(2, 1fr);
        grid-auto-rows: minmax(240px, 1fr);
    }
    .mosaic-hero {
        grid-column: span 2;
        grid-row: span 1;
    }
    .mosaic-tall {
        grid-row: span 1;
    }
    .mosaic-hero .mosaic-card-title { font-size: 1.25rem; }
}
@media (max-width: 575px) {
    .mosaic-grid {
        grid-template-columns: 1fr;
        grid-auto-rows: minmax(220px, auto);
        gap: 10px;
    }
    .mosaic-hero,
    .mosaic-wide {
        grid-column: span 1;
        grid-row: span 1;
    }
    .mosaic-tall {
        grid-row: span 1;
    }
    .mosaic-card-title { font-size: 0.95rem; }
    .mosaic-hero .mosaic-card-title { font-size: 1.1rem; }
    .mosaic-card-excerpt { -webkit-line-clamp: 2; }
}
</style>
