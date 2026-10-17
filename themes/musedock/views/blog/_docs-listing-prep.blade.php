{{--
    Listados de categoría y etiqueta: las guías (post_type docs) viven en /docs/…, no en el blog.
    A cada guía le pone su URL de /docs (en el idioma de la página) y, si no tiene imagen, la portada de su producto
    (MuseDock CMS / Panel / Portal). Pinta la miga de pan: la de docs (Docs / Producto / Sección) si la página es de docs;
    si no, la normal del blog (Inicio / Categoría|Etiqueta / nombre).
    Uso: @include('blog._docs-listing-prep', ['posts' => $posts, 'crumbCategory' => $category, 'crumbType' => 'category', 'crumbName' => $category->name])
--}}
@php
    $__pdo = \Screenart\Musedock\Database::connect();
    $__tenantId = tenant_id();
    $__catTf = $__tenantId ? 'tenant_id = ' . (int) $__tenantId : 'tenant_id IS NULL';
    $__cats = [];
    foreach ($__pdo->query("SELECT id, parent_id, name, slug FROM blog_categories WHERE {$__catTf}")->fetchAll(\PDO::FETCH_OBJ) as $__c) {
        $__cats[(int) $__c->id] = $__c;
    }
    $__docsRoot = 0;
    foreach ($__cats as $__c) {
        if ($__c->slug === 'docs' && empty($__c->parent_id)) { $__docsRoot = (int) $__c->id; break; }
    }
    // Producto (hijo directo de «docs») de una categoría, o null si no está bajo docs
    $__productOf = function (int $catId) use ($__cats, $__docsRoot) {
        $guard = 0;
        while ($__docsRoot && $catId && isset($__cats[$catId]) && $guard++ < 20) {
            if ((int) ($__cats[$catId]->parent_id ?? 0) === $__docsRoot) return $__cats[$catId];
            $catId = (int) ($__cats[$catId]->parent_id ?? 0);
        }
        return null;
    };
    $__urlLang = \Screenart\Musedock\Services\LanguageUrl::prefixLanguage();
    $__covers = ['cms' => 'guia-cms.svg', 'panel' => 'guia-panel.svg', 'portal' => 'guia-portal.svg'];
    $__allDocs = !empty($posts);
    $__firstProduct = null;
    foreach (($posts ?? []) as $__p) {
        if (($__p->post_type ?? 'post') !== 'docs') { $__allDocs = false; continue; }
        $__prod = null;
        foreach (($__p->categories ?? []) as $__pc) {
            if ($__prod = $__productOf((int) $__pc->id)) break;
        }
        $__firstProduct ??= $__prod;
        $__paths = \Screenart\Musedock\Services\LanguageUrl::postPaths($__p);
        $__base = (string) (($__p->base_locale ?? '') ?: site_default_language());
        $__p->public_url = ($__urlLang !== null && isset($__paths[$__urlLang])) ? $__paths[$__urlLang] : ($__paths[$__base] ?? '/docs/' . $__p->slug);
        if (empty($__p->featured_image) || !empty($__p->hide_featured_image)) {
            $__p->featured_image = '/assets/themes/musedock/images/guides/' .($__covers[$__prod->slug ?? 'cms'] ?? 'guia-cms.svg');
            $__p->hide_featured_image = 0;
        }
    }

    // Miga de pan de docs: Docs / Producto / Sección (o / Etiqueta)
    $__docsCrumb = null;
    $__crumbCat = $crumbCategory ?? null;
    $__crumbProd = $__crumbCat ? $__productOf((int) $__crumbCat->id) : null; // una etiqueta puede mezclar productos
    if ($__docsRoot && ($__crumbProd || ($__crumbCat && (int) $__crumbCat->id === $__docsRoot) || (!$__crumbCat && $__allDocs))) {
        $__docsHome = ($__urlLang ? '/' . $__urlLang : '') . '/docs/';
        $__docsCrumb = [['Docs', $__docsHome]];
        if ($__crumbProd) {
            $__docsCrumb[] = [$__crumbProd->name, $__docsHome . '?product=' . urlencode($__crumbProd->slug)];
        }
        if (!$__crumbCat || ((!$__crumbProd || (int) $__crumbCat->id !== (int) $__crumbProd->id) && (int) $__crumbCat->id !== $__docsRoot)) {
            $__docsCrumb[] = [(string) ($crumbName ?? ''), null];
        }
    }
    $__type = ($crumbType ?? 'category') === 'tag' ? 'tag' : 'category';
@endphp
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-2" style="background: transparent; padding-left: 0;">
        <li class="breadcrumb-item"><a href="/" style="color: #333;">{{ __('common.home') }}</a></li>
        @if($__docsCrumb)
            @foreach($__docsCrumb as $__ci => $__crumb)
                @if($__crumb[1])
                <li class="breadcrumb-item"><a href="{{ $__crumb[1] }}" style="color: #333;{{ $__ci === 1 ? ' font-weight: 700;' : '' }}">{{ $__crumb[0] }}</a></li>
                @else
                <li class="breadcrumb-item active" aria-current="page" style="color: #666;">{{ $__crumb[0] }}</li>
                @endif
            @endforeach
        @else
            @if(blog_prefix() !== '')
            <li class="breadcrumb-item"><a href="{{ blog_url() }}" style="color: #333;">{{ __('blog.title') }}</a></li>
            @endif
            <li class="breadcrumb-item"><a href="{{ blog_url('', $__type) }}" style="color: #555;">{{ __('blog.frontend.' . $__type) }}</a></li>
            <li class="breadcrumb-item active" aria-current="page" style="color: #666;">{{ $crumbName ?? '' }}</li>
        @endif
    </ol>
</nav>
