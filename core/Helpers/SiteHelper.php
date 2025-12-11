<?php
// Guardar como: core/Helpers/SiteHelper.php

namespace Screenart\Musedock\Helpers;

use Screenart\Musedock\Database;

class SiteHelper
{
    private static $settingsCache = null;
    private static $socialCache = null;
    
    /**
     * Obtiene todos los settings del sitio
     */
    public static function getAllSettings()
    {
        if (self::$settingsCache === null) {
            try {
                $pdo = Database::connect();
                $keyCol = Database::qi('key');
                $stmt = $pdo->query("SELECT {$keyCol}, value FROM settings");
                self::$settingsCache = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            } catch (\Exception $e) {
                \Screenart\Musedock\Logger::log("Error cargando settings: " . $e->getMessage(), 'ERROR');
                self::$settingsCache = [];
            }
        }
        
        return self::$settingsCache;
    }
    
    /**
     * Obtiene un setting específico
     */
    public static function getSetting($key, $default = null)
    {
        $settings = self::getAllSettings();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Obtiene las redes sociales configuradas
     */
    public static function getSocialNetworks()
    {
        if (self::$socialCache === null) {
            $settings = self::getAllSettings();
            $social = [];
            
            // Buscar todas las redes sociales en settings
            foreach ($settings as $key => $value) {
                if (strpos($key, 'social_') === 0 && !empty($value)) {
                    $network = str_replace('social_', '', $key);
                    $social[$network] = $value;
                }
            }
            
            self::$socialCache = $social;
        }
        
        return self::$socialCache;
    }
    
    /**
     * Renderiza metadatos SEO básicos
     */
    public static function renderSeoMeta($pageTitle = null, $pageDescription = null)
    {
        $title = $pageTitle ? $pageTitle . ' | ' . self::getSetting('site_name') : self::getSetting('site_name');
        $description = $pageDescription ?? self::getSetting('site_description');
        $keywords = self::getSetting('site_keywords');
        
        $html = "<title>{$title}</title>\n";
        $html .= "<meta name=\"description\" content=\"{$description}\">\n";
        
        if ($keywords) {
            $html .= "<meta name=\"keywords\" content=\"{$keywords}\">\n";
        }
        
        // Open Graph
        $html .= "<meta property=\"og:title\" content=\"{$title}\">\n";
        $html .= "<meta property=\"og:description\" content=\"{$description}\">\n";
        $html .= "<meta property=\"og:site_name\" content=\"" . self::getSetting('site_name') . "\">\n";
        $html .= "<meta property=\"og:url\" content=\"" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}\">\n";
        
        // OG Image
        $ogImage = self::getSetting('og_image');
        if ($ogImage) {
            $html .= "<meta property=\"og:image\" content=\"{$ogImage}\">\n";
        }
        
        // Twitter Card
        $html .= "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        $html .= "<meta name=\"twitter:title\" content=\"{$title}\">\n";
        $html .= "<meta name=\"twitter:description\" content=\"{$description}\">\n";
        
        $twitterSite = self::getSetting('twitter_site');
        if ($twitterSite) {
            $html .= "<meta name=\"twitter:site\" content=\"{$twitterSite}\">\n";
        }
        
        if ($ogImage) {
            $html .= "<meta name=\"twitter:image\" content=\"{$ogImage}\">\n";
        }
        
        // Favicon
        $favicon = self::getSetting('site_favicon');
        if ($favicon) {
            $html .= "<link rel=\"icon\" href=\"{$favicon}\">\n";
        }
        
        return $html;
    }
    
    /**
     * Renderiza iconos de redes sociales
     */
    public static function renderSocialIcons($class = 'header__social')
    {
        $networks = self::getSocialNetworks();
        
        if (empty($networks)) {
            return '';
        }
        
        $html = "<ul class=\"{$class}\">\n";
        
        foreach ($networks as $network => $url) {
            $html .= "    <li class=\"ss-{$network}\">\n";
            $html .= "        <a href=\"{$url}\" target=\"_blank\"><span class=\"screen-reader-text\">" . ucfirst($network) . "</span></a>\n";
            $html .= "    </li>\n";
        }
        
        $html .= "</ul>";
        
        return $html;
    }
    
    /**
     * Renderiza banner de cookies
     */
    public static function renderCookieBanner()
    {
        $enabled = self::getSetting('cookies_enabled', false);
        
        if (!$enabled) {
            return '';
        }
        
        $text = self::getSetting('cookies_text', 'Utilizamos cookies para mejorar tu experiencia');
        $acceptBasicText = self::getSetting('cookies_accept_basic', 'Aceptar básicas');
        $acceptAllText = self::getSetting('cookies_accept_all', 'Aceptar todas');
        $moreInfoText = self::getSetting('cookies_more_info', 'Más información');
        $moreInfoUrl = self::getSetting('cookies_policy_url', '/politica-cookies');
        
        $html = <<<HTML
<div id="cookie-banner" class="cookie-banner" style="display: none;">
    <div>
        <p>{$text}</p>
    </div>
    <div class="cookie-buttons">
        <button id="accept-basic-cookies" class="cookie-accept-basic">{$acceptBasicText}</button>
        <button id="accept-all-cookies" class="cookie-accept-all">{$acceptAllText}</button>
        <a href="{$moreInfoUrl}" class="cookie-settings">{$moreInfoText}</a>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cookieBanner = document.getElementById('cookie-banner');
    const acceptBasicBtn = document.getElementById('accept-basic-cookies');
    const acceptAllBtn = document.getElementById('accept-all-cookies');
    
    // Comprobar si ya se han aceptado las cookies
    const cookiesAccepted = localStorage.getItem('cookies_accepted');
    
    if (!cookiesAccepted) {
        cookieBanner.style.display = 'flex';
    }
    
    // Función para aceptar cookies básicas (esenciales)
    acceptBasicBtn.addEventListener('click', function() {
        // Guardar preferencia de cookies básicas
        localStorage.setItem('cookies_accepted', 'basic');
        cookieBanner.style.display = 'none';
        
        // Aquí puedes desactivar las cookies no esenciales
        disableNonEssentialCookies();
    });
    
    // Función para aceptar todas las cookies
    acceptAllBtn.addEventListener('click', function() {
        // Guardar preferencia de todas las cookies
        localStorage.setItem('cookies_accepted', 'all');
        cookieBanner.style.display = 'none';
        
        // Aquí puedes habilitar todas las cookies
        enableAllCookies();
    });
    
    // Función para desactivar cookies no esenciales
    function disableNonEssentialCookies() {
        // Desactivar Google Analytics si existe
        if (typeof ga !== 'undefined') {
            window['ga-disable-' + ga.getAll()[0].get('trackingId')] = true;
        }
        
        // Eliminar cookies de marketing conocidas
        const nonEssentialCookies = ['_ga', '_gid', '_gat', '_fbp', 'fr'];
        
        document.cookie.split(';').forEach(function(c) {
            const cookieName = c.trim().split('=')[0];
            if (nonEssentialCookies.includes(cookieName)) {
                document.cookie = cookieName + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            }
        });
        
        console.log('Cookies no esenciales desactivadas');
    }
    
    // Función para habilitar todas las cookies
    function enableAllCookies() {
        // No hacemos nada especial aquí, simplemente permitimos que todas las cookies se creen normalmente
        console.log('Todas las cookies habilitadas');
    }
    
    // Verificar y aplicar preferencias guardadas
    if (cookiesAccepted === 'basic') {
        disableNonEssentialCookies();
    } else if (cookiesAccepted === 'all') {
        enableAllCookies();
    }
});
</script>
<style>
.cookie-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #333;
    color: #fff;
    padding: 1rem;
    z-index: 9999;
    display: none;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
}
.cookie-banner p {
    margin: 0;
    padding-right: 1rem;
}
.cookie-buttons {
    display: flex;
    gap: 0.5rem;
}
.cookie-buttons button, .cookie-buttons a {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.875rem;
}
.cookie-accept-basic {
    background: #5cb85c;
    color: white;
}
.cookie-accept-all {
    background: #428bca;
    color: white;
}
.cookie-settings {
    background: #f0f0f0;
    color: #333;
}
</style>
HTML;
        
        return $html;
    }
    
    /**
     * Limpia la caché de settings
     */
    public static function clearCache()
    {
        self::$settingsCache = null;
        self::$socialCache = null;
    }
}