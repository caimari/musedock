<?php

namespace WpImporter\Services;

use Screenart\Musedock\Logger;

/**
 * Cliente REST API para WordPress
 * Consume /wp-json/wp/v2/ para extraer contenido
 */
class WpApiClient
{
    private string $siteUrl;
    private ?string $username;
    private ?string $appPassword;
    private int $perPage = 100;
    private int $timeout = 30;
    private bool $useRestRouteParam = false;

    public function __construct(string $siteUrl, ?string $username = null, ?string $appPassword = null)
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->username = $username;
        $this->appPassword = $appPassword;
    }

    /**
     * Verificar conexión con el sitio WordPress
     */
    public function testConnection(): array
    {
        $response = $this->request('/wp-json/');

        if ($response['error']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];

        return [
            'success' => true,
            'site_name' => $data['name'] ?? '',
            'site_description' => $data['description'] ?? '',
            'site_url' => $data['url'] ?? $this->siteUrl,
            'gmt_offset' => $data['gmt_offset'] ?? 0,
            'has_rest_api' => true,
            'namespaces' => $data['namespaces'] ?? [],
        ];
    }

    /**
     * Obtener resumen de contenido disponible para importar
     */
    public function getSummary(): array
    {
        $summary = [
            'posts' => 0,
            'pages' => 0,
            'categories' => 0,
            'tags' => 0,
            'media' => 0,
        ];

        // Hacer requests en paralelo con HEAD para contar
        $endpoints = [
            'posts' => '/wp-json/wp/v2/posts',
            'pages' => '/wp-json/wp/v2/pages',
            'categories' => '/wp-json/wp/v2/categories',
            'tags' => '/wp-json/wp/v2/tags',
            'media' => '/wp-json/wp/v2/media',
        ];

        foreach ($endpoints as $key => $endpoint) {
            $response = $this->request($endpoint, ['per_page' => 1], true);
            if (!$response['error'] && isset($response['headers']['x-wp-total'])) {
                $summary[$key] = (int) $response['headers']['x-wp-total'];
            }
        }

        return $summary;
    }

    /**
     * Obtener todos los posts (paginados)
     */
    public function getPosts(int $page = 1, string $status = 'publish'): array
    {
        $params = [
            'per_page' => $this->perPage,
            'page' => $page,
            'status' => $status,
            '_embed' => 1, // Incluir featured_media, author, terms
        ];

        return $this->fetchPaginated('/wp-json/wp/v2/posts', $params);
    }

    /**
     * Obtener todos los posts de todas las páginas
     */
    public function getAllPosts(string $status = 'publish'): array
    {
        return $this->fetchAll('/wp-json/wp/v2/posts', [
            'status' => $status,
            '_embed' => 1,
        ]);
    }

    /**
     * Obtener todas las páginas
     */
    public function getPages(int $page = 1): array
    {
        $params = [
            'per_page' => $this->perPage,
            'page' => $page,
            'status' => 'publish',
            '_embed' => 1,
        ];

        return $this->fetchPaginated('/wp-json/wp/v2/pages', $params);
    }

    /**
     * Obtener todas las páginas de todas las páginas
     */
    public function getAllPages(): array
    {
        return $this->fetchAll('/wp-json/wp/v2/pages', [
            'status' => 'publish',
            '_embed' => 1,
        ]);
    }

    /**
     * Obtener todas las categorías
     */
    public function getAllCategories(): array
    {
        return $this->fetchAll('/wp-json/wp/v2/categories', [
            'hide_empty' => false,
        ]);
    }

    /**
     * Obtener todos los tags
     */
    public function getAllTags(): array
    {
        return $this->fetchAll('/wp-json/wp/v2/tags', [
            'hide_empty' => false,
        ]);
    }

    /**
     * Obtener todos los media items
     */
    public function getAllMedia(): array
    {
        return $this->fetchAll('/wp-json/wp/v2/media');
    }

    /**
     * Obtener un media item por ID
     */
    public function getMedia(int $id): ?array
    {
        $response = $this->request("/wp-json/wp/v2/media/{$id}");
        if ($response['error']) {
            return null;
        }
        return $response['data'];
    }

    /**
     * Obtener settings del sitio (logo, favicon, etc.)
     * Requiere autenticación
     */
    public function getSiteSettings(): ?array
    {
        $response = $this->request('/wp-json/wp/v2/settings');
        if ($response['error']) {
            return null;
        }
        return $response['data'];
    }

    /**
     * Obtener menús de navegación (requiere WP 5.9+ con block themes o Menus API)
     */
    /**
     * Obtener menús de WordPress con sus items.
     *
     * Retorna array de menus, cada uno con:
     *   ['id' => int, 'name' => string, 'slug' => string, 'items' => [...]]
     *
     * Estrategia:
     * 1. wp-api-menus plugin (/menus/v1/menus + /menus/v1/menus/{id})
     * 2. WP 5.9+ navigation blocks (/wp/v2/menu-items por cada menu)
     * 3. Fallback: /wp/v2/menu-items plano
     */
    public function getMenus(): array
    {
        // Estrategia 1: wp-api-menus plugin (más común y fiable)
        $menusResponse = $this->request('/wp-json/menus/v1/menus');
        if (!$menusResponse['error'] && is_array($menusResponse['data']) && !empty($menusResponse['data'])) {
            Logger::info("WpApiClient: Obteniendo menús via wp-api-menus plugin");
            $result = [];
            foreach ($menusResponse['data'] as $menu) {
                $menuId = $menu['ID'] ?? $menu['term_id'] ?? $menu['id'] ?? 0;
                $menuName = $menu['name'] ?? $menu['title'] ?? 'Menu ' . $menuId;
                $menuSlug = $menu['slug'] ?? '';

                // Obtener items de este menú
                $itemsResponse = $this->request("/wp-json/menus/v1/menus/{$menuId}");
                $items = [];
                if (!$itemsResponse['error'] && is_array($itemsResponse['data'])) {
                    // El plugin devuelve el menú con 'items' dentro
                    $items = $itemsResponse['data']['items'] ?? $itemsResponse['data'] ?? [];
                }

                $result[] = [
                    'id' => $menuId,
                    'name' => $menuName,
                    'slug' => $menuSlug,
                    'items' => $items,
                ];
            }
            return $result;
        }

        // Estrategia 2: WP 5.9+ nav menus + menu-items API (requiere auth)
        // Primero obtener los menús registrados
        $menusResponse = $this->request('/wp-json/wp/v2/menus', ['per_page' => 100]);
        if (!$menusResponse['error'] && is_array($menusResponse['data']) && !empty($menusResponse['data'])) {
            Logger::info("WpApiClient: Obteniendo menús via WP 5.9+ /wp/v2/menus");
            $result = [];
            foreach ($menusResponse['data'] as $menu) {
                $menuId = $menu['id'] ?? 0;
                $menuName = $menu['name'] ?? 'Menu #' . $menuId;
                $menuSlug = $menu['slug'] ?? 'menu-' . $menuId;

                // Obtener items de este menú
                $itemsResponse = $this->request('/wp-json/wp/v2/menu-items', [
                    'menus' => $menuId,
                    'per_page' => 100,
                ]);
                $items = [];
                if (!$itemsResponse['error'] && is_array($itemsResponse['data'])) {
                    $items = $itemsResponse['data'];
                }

                $result[] = [
                    'id' => $menuId,
                    'name' => $menuName,
                    'slug' => $menuSlug,
                    'items' => $items,
                ];
            }
            return $result;
        }

        // Estrategia 3: Fallback — solo menu-items plano
        $response = $this->request('/wp-json/wp/v2/menu-items', ['per_page' => 100]);
        if (!$response['error'] && is_array($response['data']) && !empty($response['data'])) {
            Logger::info("WpApiClient: Obteniendo menús via /wp/v2/menu-items (fallback)");
            // Agrupar items por menu term ID
            $grouped = [];
            foreach ($response['data'] as $item) {
                $menuIds = $item['menus'] ?? [];
                foreach ($menuIds as $mId) {
                    $grouped[$mId][] = $item;
                }
            }
            $result = [];
            foreach ($grouped as $mId => $items) {
                $result[] = [
                    'id' => $mId,
                    'name' => 'Menu #' . $mId,
                    'slug' => 'menu-' . $mId,
                    'items' => $items,
                ];
            }
            return $result;
        }

        Logger::warning("WpApiClient: No se pudieron obtener menús por ninguna estrategia");
        return [];
    }

    /**
     * Obtener sliders de MetaSlider (si el plugin tiene endpoint API)
     * Fallback: extraer del HTML de la home page
     */
    public function getSliders(): array
    {
        // Estrategia 1: MetaSlider REST API (si existe)
        $response = $this->request('/wp-json/metaslider/v1/sliders');
        if (!$response['error'] && is_array($response['data']) && !empty($response['data'])) {
            Logger::info("WpApiClient: Obteniendo sliders via MetaSlider API");
            return $response['data'];
        }

        // Estrategia 2: Extraer imágenes del shortcode [metaslider] desde la home
        $html = $this->fetchHomepageHtml();
        if (!$html) {
            return [];
        }

        $sliders = [];

        // Buscar contenedores de MetaSlider en el HTML renderizado
        if (preg_match_all('/<div[^>]+class="[^"]*metaslider[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/si', $html, $matches)) {
            foreach ($matches[0] as $idx => $sliderHtml) {
                $slides = [];
                // Extraer imágenes del slider
                if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*(?:alt=["\']([^"\']*)["\'])?[^>]*>/i', $sliderHtml, $imgMatches, PREG_SET_ORDER)) {
                    foreach ($imgMatches as $sortOrder => $img) {
                        $imgUrl = $img[1];
                        $alt = $img[2] ?? '';
                        // Normalizar protocol-relative URLs
                        if (strpos($imgUrl, '//') === 0) {
                            $imgUrl = 'https:' . $imgUrl;
                        }
                        // Ignorar imágenes tiny o iconos
                        if (strpos($imgUrl, 'wp-emoji') !== false || strpos($imgUrl, 'gravatar') !== false) {
                            continue;
                        }
                        $slides[] = [
                            'image_url' => $imgUrl,
                            'title' => $alt,
                            'sort_order' => $sortOrder,
                        ];
                    }
                }

                // Intentar extraer links asociados a las imágenes
                if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>\s*<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $sliderHtml, $linkMatches, PREG_SET_ORDER)) {
                    foreach ($linkMatches as $i => $link) {
                        foreach ($slides as &$slide) {
                            if ($slide['image_url'] === $link[2]) {
                                $slide['link_url'] = $link[1];
                                break;
                            }
                        }
                        unset($slide);
                    }
                }

                if (!empty($slides)) {
                    $sliders[] = [
                        'name' => 'Slider ' . ($idx + 1),
                        'slides' => $slides,
                    ];
                }
            }
        }

        // Fallback: buscar cualquier slider genérico (swiper, slick, owl, etc.)
        if (empty($sliders)) {
            $sliderPatterns = [
                '/<div[^>]+class="[^"]*(?:swiper-wrapper|slick-track|owl-stage)[^"]*"[^>]*>(.*?)<\/div>/si',
                '/<ul[^>]+class="[^"]*(?:slides|slider)[^"]*"[^>]*>(.*?)<\/ul>/si',
            ];

            foreach ($sliderPatterns as $pattern) {
                if (preg_match($pattern, $html, $match)) {
                    $slides = [];
                    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*(?:alt=["\']([^"\']*)["\'])?[^>]*>/i', $match[1], $imgMatches, PREG_SET_ORDER)) {
                        foreach ($imgMatches as $sortOrder => $img) {
                            $imgUrl = $img[1];
                            if (strpos($imgUrl, '//') === 0) $imgUrl = 'https:' . $imgUrl;
                            if (strpos($imgUrl, 'wp-emoji') !== false || strpos($imgUrl, 'gravatar') !== false) continue;
                            $slides[] = [
                                'image_url' => $imgUrl,
                                'title' => $img[2] ?? '',
                                'sort_order' => $sortOrder,
                            ];
                        }
                    }
                    if (!empty($slides)) {
                        $sliders[] = [
                            'name' => 'Slider importado',
                            'slides' => $slides,
                        ];
                        break;
                    }
                }
            }
        }

        // WP Logo Showcase plugin: wpls-logo-cnt divs with wp-post-image
        if (strpos($html, 'wpls-logo') !== false) {
            if (preg_match_all('/<div[^>]+class="[^"]*wpls-logo-cnt[^"]*"[^>]*>.*?<img[^>]*src=["\']([^"\']+)["\'][^>]*>.*?<\/div>\s*<\/div>/si', $html, $logoMatches)) {
                $slides = [];
                $seen = [];
                foreach ($logoMatches[1] as $sortOrder => $imgUrl) {
                    if (strpos($imgUrl, '//') === 0) $imgUrl = 'https:' . $imgUrl;
                    $basename = basename(parse_url($imgUrl, PHP_URL_PATH) ?: '');
                    if (isset($seen[$basename])) continue;
                    $seen[$basename] = true;

                    $title = pathinfo($basename, PATHINFO_FILENAME);
                    $title = str_replace(['-', '_'], ' ', $title);
                    $title = preg_replace('/-?\d+x\d+$/', '', $title);

                    $slides[] = [
                        'image_url' => $imgUrl,
                        'title' => ucwords(trim($title)),
                        'sort_order' => $sortOrder,
                    ];
                }
                if (count($slides) >= 2) {
                    $sliders[] = [
                        'name' => 'Logo Carousel',
                        'slides' => $slides,
                        'theme' => 'portrait-carousel',
                    ];
                    Logger::info("WpApiClient: WP Logo Showcase detectado con " . count($slides) . " logos");
                }
            }
        }

        if (!empty($sliders)) {
            Logger::info("WpApiClient: Detectados " . count($sliders) . " sliders via HTML parsing");
        }

        return $sliders;
    }

    /**
     * Detectar carousels (Carousel Slider plugin / Owl Carousel) en el contenido de las páginas WP.
     * Escanea el content.rendered de todas las páginas buscando HTML de carousel-slider.
     *
     * @param array $wpPages Array de páginas del WP REST API (con content.rendered)
     * @return array [ ['name' => ..., 'slides' => [...], 'theme' => 'portrait-carousel', 'wp_page_slug' => ...], ... ]
     */
    public function getCarouselSlidersFromPages(array $wpPages): array
    {
        $carousels = [];

        foreach ($wpPages as $page) {
            $content = $page['content']['rendered'] ?? '';
            $pageTitle = html_entity_decode($page['title']['rendered'] ?? '', ENT_QUOTES, 'UTF-8');
            $pageSlug = $page['slug'] ?? '';

            if (empty($content)) {
                continue;
            }

            // Estrategia: buscar indicadores de carousel en el contenido y extraer
            // todos los items directamente. No intentar matchear divs anidados con regex.
            $hasCarouselSlider = (strpos($content, 'carousel-slider-outer') !== false || strpos($content, 'carousel-slider__item') !== false);
            $hasOwlCarousel = (!$hasCarouselSlider && strpos($content, 'owl-carousel') !== false);

            // Patrón 1: Carousel Slider plugin — extraer CADA carousel-slider-outer por separado
            if ($hasCarouselSlider) {
                // Splitear el contenido por cada carousel-slider-outer wrapper
                $carouselBlocks = $this->splitCarouselBlocks($content);

                foreach ($carouselBlocks as $blockIndex => $blockHtml) {
                    $slides = [];
                    $sortOrder = 0;

                    // Extraer cada carousel-slider__item con su contenido
                    if (preg_match_all('/<div[^>]+class="[^"]*carousel-slider__item[^"]*"[^>]*>(.*?)(?=<div[^>]+class="[^"]*carousel-slider__item|<\/div>\s*<\/div>\s*<\/div>)/si', $blockHtml, $itemMatches)) {
                        foreach ($itemMatches[1] as $itemHtml) {
                            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*(?:alt=["\']([^"\']*)["\'])?[^>]*>/i', $itemHtml, $imgMatch)) {
                                $imgUrl = $imgMatch[1];
                                // Normalizar protocol-relative URLs
                                if (strpos($imgUrl, '//') === 0) {
                                    $imgUrl = 'https:' . $imgUrl;
                                }
                                if (strpos($imgUrl, 'wp-emoji') !== false || strpos($imgUrl, 'gravatar') !== false) {
                                    continue;
                                }
                                $title = $imgMatch[2] ?? '';
                                if (preg_match('/<(?:h[1-6])[^>]*class="[^"]*title[^"]*"[^>]*>(.*?)<\/(?:h[1-6])>/si', $itemHtml, $captionMatch)) {
                                    $captionTitle = strip_tags($captionMatch[1]);
                                    if (!empty($captionTitle)) {
                                        $title = $captionTitle;
                                    }
                                }
                                $slide = [
                                    'image_url' => $imgUrl,
                                    'title' => $title,
                                    'sort_order' => $sortOrder++,
                                ];
                                if (preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>.*?<img/si', $itemHtml, $linkMatch)) {
                                    $slide['link_url'] = $linkMatch[1];
                                }
                                $slides[] = $slide;
                            }
                        }
                    }

                    if (!empty($slides)) {
                        $suffix = count($carouselBlocks) > 1 ? ' #' . ($blockIndex + 1) : '';
                        $carouselName = $pageTitle ? "Carousel - {$pageTitle}{$suffix}" : 'Carousel importado' . $suffix;
                        $carousels[] = [
                            'name' => $carouselName,
                            'slides' => $slides,
                            'theme' => 'portrait-carousel',
                            'wp_page_slug' => $pageSlug,
                        ];
                        Logger::info("WpApiClient: Carousel detectado en '{$pageTitle}'{$suffix} con " . count($slides) . " slides");
                    }
                }
            }

            // Patrón 2: Owl Carousel genérico (sin carousel-slider plugin)
            if ($hasOwlCarousel) {
                $slides = [];
                // Extraer todas las imágenes dentro de elementos owl-carousel
                if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*(?:alt=["\']([^"\']*)["\'])?[^>]*>/i', $content, $imgMatches, PREG_SET_ORDER)) {
                    foreach ($imgMatches as $sortOrder => $img) {
                        if (strpos($img[1], 'wp-emoji') !== false || strpos($img[1], 'gravatar') !== false) continue;
                        // Solo incluir imágenes que están cerca del contexto owl-carousel
                        $slides[] = [
                            'image_url' => $img[1],
                            'title' => $img[2] ?? '',
                            'sort_order' => $sortOrder,
                        ];
                    }
                }
                if (!empty($slides) && count($slides) >= 3) { // Solo si hay al menos 3 imágenes (es realmente un carousel)
                    $carouselName = $pageTitle ? "Carousel - {$pageTitle}" : 'Carousel importado';
                    $carousels[] = [
                        'name' => $carouselName,
                        'slides' => $slides,
                        'theme' => 'portrait-carousel',
                        'wp_page_slug' => $pageSlug,
                    ];
                    Logger::info("WpApiClient: Owl Carousel detectado en página '{$pageTitle}' con " . count($slides) . " slides");
                }
            }
        }

        if (!empty($carousels)) {
            Logger::info("WpApiClient: Total carousels detectados: " . count($carousels));
        }

        return $carousels;
    }

    /**
     * Detectar Smart Slider 3 / hero sliders desde el HTML renderizado del frontend
     * Estos sliders NO aparecen en el REST API content, solo en el HTML del front
     */
    public function getHeroSliderFromHtml(): array
    {
        $html = $this->fetchHomepageHtml();
        if (!$html) return [];

        $sliders = [];

        // Smart Slider 3: buscar imágenes en el bloque n2-ss
        if (strpos($html, 'n2-ss') !== false) {
            $slides = [];
            $sortOrder = 0;

            // Capturar el bloque del Smart Slider: desde el div con id/class n2-ss hasta su cierre
            // Estrategia robusta: buscar el contenedor n2-ss-align o n2-ss-slider y extraer todo su contenido
            $block = '';
            if (preg_match('/<div[^>]+(?:id|class)="[^"]*n2-ss[^"]*"[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/si', $html, $ssBlock)) {
                $block = $ssBlock[0];
            } elseif (preg_match('/<div[^>]+data-creator="Smart Slider[^"]*"[^>]*>.*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/si', $html, $ssBlock)) {
                $block = $ssBlock[0];
            }

            if ($block) {
                // Buscar imágenes: data-src (lazy) o src con uploads o wp-content
                if (preg_match_all('/(?:data-src|src)=["\']([^"\']*(?:uploads|wp-content)[^"\']+\.(?:jpg|jpeg|png|webp|gif))["\']/i', $block, $imgMatches)) {
                    $seen = [];
                    foreach ($imgMatches[1] as $imgUrl) {
                        // Normalizar protocol-relative
                        if (strpos($imgUrl, '//') === 0) {
                            $imgUrl = 'https:' . $imgUrl;
                        }
                        // Skip thumbnails y duplicados
                        $basename = basename(parse_url($imgUrl, PHP_URL_PATH) ?: '');
                        $baseKey = preg_replace('/-\d+x\d+\./', '.', $basename);
                        if (isset($seen[$baseKey])) continue;
                        if (strpos($imgUrl, 'gravatar') !== false) continue;
                        $seen[$baseKey] = true;

                        $title = pathinfo($basename, PATHINFO_FILENAME);
                        $title = str_replace(['-', '_'], ' ', $title);
                        $title = preg_replace('/\s+\d+x\d+$/', '', $title);

                        $slides[] = [
                            'image_url' => $imgUrl,
                            'title' => ucwords($title),
                            'sort_order' => $sortOrder++,
                        ];
                    }
                }
            }

            if (!empty($slides)) {
                $sliders[] = [
                    'name' => 'Hero Slider',
                    'slides' => $slides,
                    'theme' => null, // tema default (hero grande)
                    'wp_page_slug' => 'home',
                    'is_hero' => true,
                ];
                Logger::info("WpApiClient: Smart Slider 3 detectado con " . count($slides) . " slides");
            }
        }

        // Fallback: hero con background-image en secciones slider/hero/banner del tema
        if (empty($sliders)) {
            $heroPatterns = [
                // <section class="slider"> o similar con background-image en hijos
                '/<(?:section|div)[^>]*class="[^"]*(?:slider|hero|banner|masthead)[^"]*"[^>]*>.*?background-image\s*:\s*url\(["\']?([^"\')\s]+)["\']?\)/si',
                // Directamente un div con style="background-image:url(...)"
                '/<div[^>]*class="[^"]*(?:slider|hero|banner|slide)[^"]*"[^>]*style="[^"]*background-image\s*:\s*url\(["\']?([^"\')\s]+)["\']?\)/si',
                // li dentro de .slides con background-image
                '/<li[^>]*>\s*<div[^>]*style="[^"]*background-image\s*:\s*url\(["\']?([^"\')\s]+)["\']?\)/si',
            ];

            $heroSlides = [];
            $seen = [];
            foreach ($heroPatterns as $pattern) {
                if (preg_match_all($pattern, $html, $bgMatches)) {
                    foreach ($bgMatches[1] as $bgUrl) {
                        if (strpos($bgUrl, '//') === 0) $bgUrl = 'https:' . $bgUrl;
                        $basename = basename(parse_url($bgUrl, PHP_URL_PATH) ?: '');
                        $baseKey = preg_replace('/-\d+x\d+\./', '.', $basename);
                        if (isset($seen[$baseKey]) || empty($basename)) continue;
                        $seen[$baseKey] = true;

                        $title = pathinfo($basename, PATHINFO_FILENAME);
                        $title = str_replace(['-', '_'], ' ', $title);

                        $heroSlides[] = [
                            'image_url' => $bgUrl,
                            'title' => ucwords($title),
                            'sort_order' => count($heroSlides),
                        ];
                    }
                }
            }

            if (!empty($heroSlides)) {
                $sliders[] = [
                    'name' => 'Hero Slider',
                    'slides' => $heroSlides,
                    'theme' => null,
                    'wp_page_slug' => 'home',
                    'is_hero' => true,
                ];
                Logger::info("WpApiClient: Hero background-image detectado con " . count($heroSlides) . " slides");
            }
        }

        return $sliders;
    }

    /**
     * Separar el HTML en bloques individuales de carousel-slider-outer
     * usando conteo de divs anidados para encontrar los cierres correctos
     */
    private function splitCarouselBlocks(string $content): array
    {
        $blocks = [];
        $searchStart = 0;
        $len = strlen($content);

        while ($searchStart < $len) {
            // Buscar el siguiente carousel-slider-outer
            $pattern = '/<div[^>]+class="[^"]*carousel-slider-outer[^"]*"[^>]*>/si';
            if (!preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $searchStart)) {
                break;
            }

            $startPos = $match[0][1];
            $pos = $startPos + strlen($match[0][0]);
            $depth = 1;

            // Contar divs anidados para encontrar el cierre correcto
            while ($depth > 0 && $pos < $len) {
                $nextOpen = strpos($content, '<div', $pos);
                $nextClose = strpos($content, '</div>', $pos);

                if ($nextClose === false) break;

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $depth++;
                    $pos = $nextOpen + 4;
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $endPos = $nextClose + 6;
                        $blocks[] = substr($content, $startPos, $endPos - $startPos);
                        $searchStart = $endPos;
                    } else {
                        $pos = $nextClose + 6;
                    }
                }
            }

            // Safety: si no cerramos, avanzar para no loop infinito
            if ($depth > 0) {
                $searchStart = $pos;
            }
        }

        // Si no encontramos outer wrappers pero hay items, tratar todo como 1 bloque
        if (empty($blocks) && strpos($content, 'carousel-slider__item') !== false) {
            $blocks[] = $content;
        }

        return $blocks;
    }

    /**
     * Descargar un archivo desde una URL
     */
    public function downloadFile(string $url, string $destPath): bool
    {
        // Pre-validar URL: skip rápido si tiene caracteres problemáticos o extensiones inválidas
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === false || $path === null) {
            Logger::debug("WpApiClient: URL inválida, skip: {$url}");
            return false;
        }

        // Detectar caracteres Unicode no-ASCII en el filename (ej: εὐδαιμονία)
        $filename = basename($path);
        if (preg_match('/[^\x20-\x7E]/', $filename)) {
            // Intentar URL-encode del path para caracteres Unicode
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
            $url = preg_replace('#' . preg_quote($path, '#') . '#', $encodedPath, $url, 1);
        }

        $ch = curl_init($url);
        $fp = fopen($destPath, 'wb');

        if (!$fp) {
            Logger::error("WpApiClient: No se pudo abrir archivo para escritura: {$destPath}");
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'MuseDock-WP-Importer/1.0',
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        fclose($fp);

        if (!$result || $httpCode >= 400) {
            Logger::debug("WpApiClient: Error descargando {$url}: HTTP {$httpCode} - {$error}");
            @unlink($destPath);
            return false;
        }

        return true;
    }

    /**
     * Obtener el HTML de la página principal (para extraer estilos)
     */
    public function fetchHomepageHtml(): ?string
    {
        $ch = curl_init($this->siteUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MuseDock-Importer/1.0)',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 || !$html) {
            return null;
        }

        return $html;
    }

    /**
     * Obtener las hojas de estilo CSS del sitio
     */
    public function fetchStylesheets(string $html): array
    {
        $stylesheets = [];

        // Extraer URLs de <link rel="stylesheet">
        if (preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $stylesheets = array_merge($stylesheets, $matches[1]);
        }
        // También formato href antes de rel
        if (preg_match_all('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $matches)) {
            $stylesheets = array_merge($stylesheets, $matches[1]);
        }

        $stylesheets = array_unique($stylesheets);

        // Resolver URLs relativas
        $resolved = [];
        foreach ($stylesheets as $url) {
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            } elseif (strpos($url, 'http') !== 0) {
                $url = rtrim($this->siteUrl, '/') . '/' . ltrim($url, '/');
            }
            $resolved[] = $url;
        }

        return $resolved;
    }

    /**
     * Descargar y concatenar el contenido CSS de varias hojas de estilo
     */
    public function fetchCssContent(array $stylesheetUrls): string
    {
        $css = '';
        foreach ($stylesheetUrls as $url) {
            // Ignorar CDN de terceros (Google Fonts se extrae aparte)
            if (strpos($url, 'fonts.googleapis.com') !== false) {
                continue;
            }
            if (strpos($url, 'fonts.bunny.net') !== false) {
                continue;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'MuseDock-Importer/1.0',
            ]);

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode < 400 && $content) {
                $css .= "\n/* Source: {$url} */\n" . $content;
            }
        }

        return $css;
    }

    // ====================================================================
    // PRIVATE METHODS
    // ====================================================================

    /**
     * Realizar una petición HTTP a la API de WordPress
     */
    private function request(string $endpoint, array $params = [], bool $includeHeaders = false): array
    {
        // Si ya detectamos que /wp-json/ no funciona, usar ?rest_route=
        if ($this->useRestRouteParam && strpos($endpoint, '/wp-json/') === 0) {
            $restRoute = substr($endpoint, strlen('/wp-json'));
            $params['rest_route'] = $restRoute;
            $url = $this->siteUrl . '/';
        } else {
            $url = $this->siteUrl . $endpoint;
        }

        if (!empty($params)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($params);
        }

        $ch = curl_init($url);

        $headers = ['Accept: application/json'];

        // Autenticación con Application Passwords
        if ($this->username && $this->appPassword) {
            $credentials = base64_encode($this->username . ':' . $this->appPassword);
            $headers[] = "Authorization: Basic {$credentials}";
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'MuseDock-WP-Importer/1.0',
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($header);
            },
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::error("WpApiClient: cURL error en {$endpoint}: {$error}");
            return ['data' => null, 'error' => "Error de conexión: {$error}", 'headers' => []];
        }

        if ($httpCode >= 400) {
            // Auto-detect: si /wp-json/ da 404, intentar con ?rest_route=
            if ($httpCode === 404 && !$this->useRestRouteParam && strpos($endpoint, '/wp-json/') === 0) {
                Logger::info("WpApiClient: /wp-json/ devolvió 404, probando con ?rest_route=");
                $this->useRestRouteParam = true;
                return $this->request($endpoint, $params, $includeHeaders);
            }

            $msg = "HTTP {$httpCode}";
            $decoded = json_decode($body, true);
            if ($decoded && isset($decoded['message'])) {
                $msg .= ': ' . $decoded['message'];
            }
            Logger::error("WpApiClient: Error en {$endpoint}: {$msg}");
            return ['data' => null, 'error' => $msg, 'headers' => $responseHeaders];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['data' => null, 'error' => 'Respuesta no es JSON válido', 'headers' => $responseHeaders];
        }

        return [
            'data' => $data,
            'error' => null,
            'headers' => $responseHeaders,
        ];
    }

    /**
     * Obtener una página de resultados con metadatos de paginación
     */
    private function fetchPaginated(string $endpoint, array $params): array
    {
        $response = $this->request($endpoint, $params, true);

        return [
            'data' => $response['data'] ?? [],
            'error' => $response['error'],
            'total' => (int) ($response['headers']['x-wp-total'] ?? 0),
            'total_pages' => (int) ($response['headers']['x-wp-totalpages'] ?? 0),
        ];
    }

    /**
     * Obtener todos los resultados de todas las páginas
     */
    private function fetchAll(string $endpoint, array $params = []): array
    {
        $allItems = [];
        $page = 1;
        $params['per_page'] = $this->perPage;

        do {
            $params['page'] = $page;
            $response = $this->request($endpoint, $params, true);

            if ($response['error']) {
                Logger::error("WpApiClient: Error en fetchAll {$endpoint} página {$page}: {$response['error']}");
                break;
            }

            $items = $response['data'] ?? [];
            if (empty($items)) {
                break;
            }

            $allItems = array_merge($allItems, $items);
            $totalPages = (int) ($response['headers']['x-wp-totalpages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $allItems;
    }
}
