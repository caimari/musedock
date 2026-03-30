#!/usr/bin/env php
<?php
/**
 * Seed initial skins into theme_skins table.
 * Usage: php cli/seed-skins.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ROOT', dirname(__DIR__));
define('CLI_MODE', true);

require_once APP_ROOT . '/core/bootstrap.php';

use Screenart\Musedock\Models\ThemeSkin;

echo "Seeding theme skins...\n\n";

// ============================================================
// SKIN 0: Default - Configuración por defecto del tema
// Colores naranja (#ff5e15), fondo claro, Poppins, layout grid
// ============================================================
$defaultSkin = [
    'slug' => 'default-original',
    'name' => 'Default Original',
    'description' => 'Configuración original del tema por defecto de MuseDock. Colores naranjas, fondo claro, tipografía Poppins y layout clásico.',
    'author' => 'MuseDock',
    'version' => '1.0',
    'theme_slug' => 'default',
    'screenshot' => null,
    'is_global' => 1,
    'tenant_id' => null,
    'is_active' => 1,
    'options' => json_encode([
        'topbar' => [
            'topbar_enabled' => true,
            'topbar_show_address' => false,
            'topbar_show_email' => true,
            'topbar_show_whatsapp' => true,
            'topbar_whatsapp_icon' => 'whatsapp',
            'topbar_bg_color' => '#1a2a40',
            'topbar_text_color' => '#ffffff'
        ],
        'hero' => [
            'hero_title_color' => '#ffffff',
            'hero_title_font' => 'inherit',
            'hero_subtitle_color' => '#ffffff',
            'hero_overlay_color' => '#000000',
            'hero_overlay_opacity' => '0.5'
        ],
        'blog' => [
            'blog_layout' => 'grid',
            'blog_header_ticker' => false,
            'blog_sidebar_related_posts' => true,
            'blog_sidebar_related_posts_count' => '4',
            'blog_sidebar_tags' => true,
            'blog_sidebar_categories' => true
        ],
        'header' => [
            'header_layout' => 'default',
            'header_content_width' => 'full',
            'header_bg_color' => '#f8f9fa',
            'header_logo_text_color' => '#1a2a40',
            'header_logo_font' => 'inherit',
            'header_link_color' => '#333333',
            'header_link_hover_color' => '#ff5e15',
            'header_menu_font' => "'Poppins', sans-serif",
            'header_menu_uppercase' => true,
            'header_tagline_color' => '#111827',
            'header_tagline_enabled' => true,
            'header_sticky' => false,
            'header_cta_enabled' => false,
            'header_cta_text_es' => 'Iniciar sesión',
            'header_cta_text_en' => 'Login',
            'header_cta_url' => '#',
            'header_cta_bg_color' => '#ff5e15',
            'header_cta_text_color' => '#ffffff',
            'header_lang_selector_enabled' => true
        ],
        'footer' => [
            'footer_layout' => 'default',
            'footer_content_width' => 'full',
            'footer_bg_color' => '#f8fafe',
            'footer_bottom_bg_color' => '#ffffff',
            'footer_text_color' => '#333333',
            'footer_heading_color' => '#333333',
            'footer_link_color' => '#333333',
            'footer_link_hover_color' => '#ff5e15',
            'footer_icon_color' => '#333333',
            'footer_border_color' => '#e5e5e5',
            'footer_cookie_icon_enabled' => true,
            'footer_cookie_icon' => 'emoji',
            'footer_cookie_banner_layout' => 'card'
        ],
        'scroll_to_top' => [
            'scroll_to_top_enabled' => true,
            'scroll_to_top_bg_color' => '#ff5e15',
            'scroll_to_top_icon_color' => '#ffffff',
            'scroll_to_top_hover_bg_color' => '#e54c08'
        ],
        'custom_code' => [
            'custom_css' => '',
            'custom_js' => ''
        ]
    ], JSON_UNESCAPED_UNICODE)
];

// ============================================================
// SKIN 1: Editorial Clasico
// Based on News Magazine X theme styling (Oxygen + Encode Sans Condensed)
// Red accent, newspaper layout, dark footer
// ============================================================
$editorialClasico = [
    'slug' => 'editorial-clasico',
    'name' => 'Editorial Clasico',
    'description' => 'Estilo editorial de noticias y revista con acento rojo, tipografia condensada y layout de periodico. Ideal para blogs de noticias, revistas y portales informativos.',
    'author' => 'MuseDock',
    'version' => '1.0',
    'theme_slug' => 'default',
    'screenshot' => null,
    'is_global' => 1,
    'tenant_id' => null,
    'is_active' => 1,
    'options' => json_encode([
        'topbar' => [
            'topbar_enabled' => true,
            'topbar_show_address' => false,
            'topbar_show_email' => true,
            'topbar_show_whatsapp' => false,
            'topbar_bg_color' => '#f84643',
            'topbar_text_color' => '#ffffff'
        ],
        'hero' => [
            'hero_title_color' => '#292929',
            'hero_title_font' => "'Oswald', sans-serif",
            'hero_subtitle_color' => '#67737e',
            'hero_overlay_color' => '#000000',
            'hero_overlay_opacity' => '0.4'
        ],
        'blog' => [
            'blog_layout' => 'newspaper',
            'blog_header_ticker' => true,
            'blog_sidebar_related_posts' => true,
            'blog_sidebar_related_posts_count' => '4',
            'blog_sidebar_tags' => true,
            'blog_sidebar_categories' => true
        ],
        'header' => [
            'header_layout' => 'logo-above',
            'header_content_width' => 'full',
            'header_bg_color' => '#ffffff',
            'header_logo_text_color' => '#292929',
            'header_logo_font' => "'Oswald', sans-serif",
            'header_link_color' => '#292929',
            'header_link_hover_color' => '#f84643',
            'header_menu_font' => "'Oswald', sans-serif",
            'header_menu_uppercase' => true,
            'header_tagline_color' => '#67737e',
            'header_tagline_enabled' => true,
            'header_sticky' => false,
            'header_cta_enabled' => false,
            'header_cta_bg_color' => '#f84643',
            'header_cta_text_color' => '#ffffff',
            'header_lang_selector_enabled' => false
        ],
        'footer' => [
            'footer_layout' => 'default',
            'footer_content_width' => 'full',
            'footer_bg_color' => '#1a1a2e',
            'footer_bottom_bg_color' => '#12121e',
            'footer_text_color' => '#adadad',
            'footer_heading_color' => '#ececec',
            'footer_link_color' => '#adadad',
            'footer_link_hover_color' => '#f84643',
            'footer_icon_color' => '#adadad',
            'footer_border_color' => '#484848',
            'footer_cookie_icon_enabled' => true,
            'footer_cookie_icon' => 'emoji',
            'footer_cookie_banner_layout' => 'card'
        ],
        'scroll_to_top' => [
            'scroll_to_top_enabled' => true,
            'scroll_to_top_bg_color' => '#f84643',
            'scroll_to_top_icon_color' => '#ffffff',
            'scroll_to_top_hover_bg_color' => '#d7403e'
        ],
        'custom_code' => [
            'custom_css' => '/* Editorial Clasico - Custom CSS */
@import url(\'https://fonts.googleapis.com/css2?family=Oxygen:wght@400;700&family=Encode+Sans+Condensed:wght@400;500;600;700&display=swap\');

body {
    font-family: "Oxygen", sans-serif !important;
    font-size: 14px;
    color: #67737e;
    line-height: 1.5;
}

h1, h2, h3, h4, h5, h6 {
    font-family: "Encode Sans Condensed", sans-serif !important;
    color: #292929;
    letter-spacing: 0.2px;
}

h1 { font-weight: 700; font-size: 42px; line-height: 1.2; }
h2 { font-weight: 600; font-size: 32px; line-height: 1.2; }
h3 { font-weight: 700; font-size: 20px; line-height: 1.4; }
h4 { font-weight: 700; font-size: 17px; line-height: 1.3; }

a { color: #f84643; }
a:hover { color: #d7403e; }

/* Header editorial style */
.header-bottom {
    border-bottom: 1px solid #e8e8e8 !important;
}

.site-name, .site-name a {
    font-family: "Encode Sans Condensed", sans-serif !important;
    font-weight: 700 !important;
    font-size: 40px !important;
    letter-spacing: 0;
}

.header-nav a {
    font-family: "Encode Sans Condensed", sans-serif !important;
    font-weight: 700 !important;
    font-size: 19px !important;
}

/* Blog cards */
.card-title a, .post-title a {
    font-family: "Encode Sans Condensed", sans-serif !important;
    font-weight: 700;
    color: #292929;
}

.card-title a:hover, .post-title a:hover {
    color: #f84643;
}

.post-meta, .card-meta {
    color: #8e9ba7;
    font-size: 13px;
}

/* Category badges */
.badge-category, .post-category a {
    background-color: #333333;
    color: #ffffff;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Footer editorial */
.footer-area {
    font-family: "Oxygen", sans-serif;
}

/* Responsive */
@media (max-width: 768px) {
    h1 { font-size: 32px; }
    h2 { font-size: 19px; }
    .site-name, .site-name a { font-size: 28px !important; }
    .header-nav a { font-size: 15px !important; }
}

@media (max-width: 480px) {
    h1 { font-size: 28px; }
    h2 { font-size: 22px; }
}',
            'custom_js' => ''
        ]
    ], JSON_UNESCAPED_UNICODE)
];

// ============================================================
// SKIN 2: Corporativo Moderno
// Clean, professional, blue accent
// ============================================================
$corporativoModerno = [
    'slug' => 'corporativo-moderno',
    'name' => 'Corporativo Moderno',
    'description' => 'Estilo corporativo limpio y profesional con acento azul, ideal para empresas, consultoras y servicios profesionales.',
    'author' => 'MuseDock',
    'version' => '1.0',
    'theme_slug' => 'default',
    'screenshot' => null,
    'is_global' => 1,
    'tenant_id' => null,
    'is_active' => 1,
    'options' => json_encode([
        'topbar' => [
            'topbar_enabled' => true,
            'topbar_show_address' => true,
            'topbar_show_email' => true,
            'topbar_show_whatsapp' => true,
            'topbar_bg_color' => '#1e293b',
            'topbar_text_color' => '#e2e8f0'
        ],
        'hero' => [
            'hero_title_color' => '#ffffff',
            'hero_title_font' => "'Montserrat', sans-serif",
            'hero_subtitle_color' => '#e2e8f0',
            'hero_overlay_color' => '#0f172a',
            'hero_overlay_opacity' => '0.6'
        ],
        'blog' => [
            'blog_layout' => 'grid',
            'blog_header_ticker' => false,
            'blog_sidebar_related_posts' => true,
            'blog_sidebar_related_posts_count' => '3',
            'blog_sidebar_tags' => false,
            'blog_sidebar_categories' => true
        ],
        'header' => [
            'header_layout' => 'default',
            'header_content_width' => 'full',
            'header_bg_color' => '#ffffff',
            'header_logo_text_color' => '#1e293b',
            'header_logo_font' => "'Montserrat', sans-serif",
            'header_link_color' => '#334155',
            'header_link_hover_color' => '#3b82f6',
            'header_menu_font' => "'Montserrat', sans-serif",
            'header_menu_uppercase' => true,
            'header_tagline_color' => '#64748b',
            'header_tagline_enabled' => false,
            'header_sticky' => true,
            'header_cta_enabled' => true,
            'header_cta_text_es' => 'Contactar',
            'header_cta_text_en' => 'Contact Us',
            'header_cta_bg_color' => '#3b82f6',
            'header_cta_text_color' => '#ffffff',
            'header_lang_selector_enabled' => true
        ],
        'footer' => [
            'footer_layout' => 'default',
            'footer_content_width' => 'full',
            'footer_bg_color' => '#0f172a',
            'footer_bottom_bg_color' => '#020617',
            'footer_text_color' => '#94a3b8',
            'footer_heading_color' => '#f1f5f9',
            'footer_link_color' => '#94a3b8',
            'footer_link_hover_color' => '#3b82f6',
            'footer_icon_color' => '#64748b',
            'footer_border_color' => '#1e293b',
            'footer_cookie_icon_enabled' => true,
            'footer_cookie_icon' => 'fa-shield-alt',
            'footer_cookie_banner_layout' => 'bar'
        ],
        'scroll_to_top' => [
            'scroll_to_top_enabled' => true,
            'scroll_to_top_bg_color' => '#3b82f6',
            'scroll_to_top_icon_color' => '#ffffff',
            'scroll_to_top_hover_bg_color' => '#2563eb'
        ],
        'custom_code' => [
            'custom_css' => '/* Corporativo Moderno - Custom CSS */
@import url(\'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap\');

body {
    font-family: "Inter", sans-serif !important;
    font-size: 15px;
    color: #334155;
    line-height: 1.6;
}

h1, h2, h3, h4, h5, h6 {
    font-family: "Montserrat", sans-serif !important;
    color: #1e293b;
    font-weight: 700;
}

a { color: #3b82f6; }
a:hover { color: #2563eb; }

.card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.btn-primary {
    background: #3b82f6 !important;
    border-color: #3b82f6 !important;
}',
            'custom_js' => ''
        ]
    ], JSON_UNESCAPED_UNICODE)
];

// ============================================================
// SKIN 3: Minimalista Elegante
// ============================================================
$minimalistaElegante = [
    'slug' => 'minimalista-elegante',
    'name' => 'Minimalista Elegante',
    'description' => 'Estilo minimalista con tipografia serif elegante, ideal para portafolios creativos, estudios de diseno y blogs personales.',
    'author' => 'MuseDock',
    'version' => '1.0',
    'theme_slug' => 'default',
    'screenshot' => null,
    'is_global' => 1,
    'tenant_id' => null,
    'is_active' => 1,
    'options' => json_encode([
        'topbar' => [
            'topbar_enabled' => false,
            'topbar_bg_color' => '#111111',
            'topbar_text_color' => '#ffffff'
        ],
        'hero' => [
            'hero_title_color' => '#ffffff',
            'hero_title_font' => "'Playfair Display', serif",
            'hero_subtitle_color' => '#e5e5e5',
            'hero_overlay_color' => '#000000',
            'hero_overlay_opacity' => '0.3'
        ],
        'blog' => [
            'blog_layout' => 'minimal',
            'blog_header_ticker' => false,
            'blog_sidebar_related_posts' => false,
            'blog_sidebar_tags' => false,
            'blog_sidebar_categories' => false
        ],
        'header' => [
            'header_layout' => 'centered',
            'header_content_width' => 'boxed',
            'header_bg_color' => '#ffffff',
            'header_logo_text_color' => '#111111',
            'header_logo_font' => "'Playfair Display', serif",
            'header_link_color' => '#111111',
            'header_link_hover_color' => '#888888',
            'header_menu_font' => "'Raleway', sans-serif",
            'header_menu_uppercase' => true,
            'header_tagline_color' => '#999999',
            'header_tagline_enabled' => true,
            'header_sticky' => false,
            'header_cta_enabled' => false,
            'header_cta_bg_color' => '#111111',
            'header_cta_text_color' => '#ffffff',
            'header_lang_selector_enabled' => false
        ],
        'footer' => [
            'footer_layout' => 'default',
            'footer_content_width' => 'boxed',
            'footer_bg_color' => '#fafafa',
            'footer_bottom_bg_color' => '#ffffff',
            'footer_text_color' => '#666666',
            'footer_heading_color' => '#111111',
            'footer_link_color' => '#444444',
            'footer_link_hover_color' => '#111111',
            'footer_icon_color' => '#444444',
            'footer_border_color' => '#eeeeee',
            'footer_cookie_icon_enabled' => true,
            'footer_cookie_icon' => 'fa-cog',
            'footer_cookie_banner_layout' => 'card'
        ],
        'scroll_to_top' => [
            'scroll_to_top_enabled' => true,
            'scroll_to_top_bg_color' => '#111111',
            'scroll_to_top_icon_color' => '#ffffff',
            'scroll_to_top_hover_bg_color' => '#333333'
        ],
        'custom_code' => [
            'custom_css' => '/* Minimalista Elegante - Custom CSS */
@import url(\'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Raleway:wght@300;400;500;600&display=swap\');

body {
    font-family: "Raleway", sans-serif !important;
    font-size: 15px;
    color: #444444;
    line-height: 1.7;
    letter-spacing: 0.3px;
}

h1, h2, h3, h4, h5, h6 {
    font-family: "Playfair Display", serif !important;
    color: #111111;
    font-weight: 600;
}

a { color: #111111; }
a:hover { color: #888888; text-decoration: none; }

.site-name, .site-name a {
    font-family: "Playfair Display", serif !important;
    font-weight: 400 !important;
    letter-spacing: 2px;
}

.card {
    border: none;
    box-shadow: none;
}

img { border-radius: 0 !important; }',
            'custom_js' => ''
        ]
    ], JSON_UNESCAPED_UNICODE)
];

// Insert skins
$skins = [$defaultSkin, $editorialClasico, $corporativoModerno, $minimalistaElegante];

foreach ($skins as $skin) {
    try {
        if (ThemeSkin::saveSkin($skin)) {
            echo "  ✓ Skin '{$skin['name']}' created\n";
        } else {
            echo "  ✗ Error creating skin '{$skin['name']}'\n";
        }
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";
