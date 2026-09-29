<?php

namespace Screenart\Musedock;

/**
 * SafeHtml - Clase para sanitización segura de HTML
 *
 * Esta clase previene XSS (Cross-Site Scripting) mediante:
 * - Escape completo de HTML (método escape)
 * - Sanitización por lista blanca de tags/atributos (método sanitize)
 * - Lista blanca de esquemas de URL (http, https, mailto, tel y rutas relativas)
 * - Iframes solo de proveedores de embeds conocidos
 * - Conversión automática a string seguro
 *
 * Se usa para contenido que llega desde fuentes no confiables (API REST, MCP/IA).
 *
 * @package Screenart\Musedock
 */
class SafeHtml
{
    protected $html;
    protected $sanitized;
    protected $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'mark',
        'small', 'sub', 'sup', 'abbr', 'cite', 'q', 'kbd', 'time',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'blockquote', 'code', 'pre',
        'a', 'img', 'picture', 'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'colgroup', 'col',
        'div', 'span', 'hr', 'section', 'article', 'aside', 'header', 'footer',
        'details', 'summary',
        'iframe', 'video', 'audio', 'source', 'figure', 'figcaption'
    ];

    protected $allowedAttributes = [
        '*' => ['class', 'title', 'lang', 'dir'],
        'a' => ['href', 'target', 'rel', 'id', 'name'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'srcset', 'sizes', 'style'],
        'td' => ['colspan', 'rowspan', 'style'],
        'th' => ['colspan', 'rowspan', 'scope', 'style'],
        'col' => ['span'],
        'colgroup' => ['span'],
        'ol' => ['start', 'reversed', 'type'],
        'p' => ['style'],
        'h1' => ['id', 'style'], 'h2' => ['id', 'style'], 'h3' => ['id', 'style'],
        'h4' => ['id', 'style'], 'h5' => ['id', 'style'], 'h6' => ['id', 'style'],
        'div' => ['style', 'id'],
        'span' => ['style'],
        'section' => ['id'],
        'blockquote' => ['cite'],
        'q' => ['cite'],
        'time' => ['datetime'],
        'details' => ['open'],
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'allow', 'loading', 'referrerpolicy', 'style'],
        'video' => ['src', 'width', 'height', 'controls', 'autoplay', 'loop', 'muted', 'poster', 'preload', 'playsinline', 'style'],
        'audio' => ['src', 'controls', 'loop', 'muted', 'preload'],
        'source' => ['src', 'type', 'srcset', 'media', 'sizes'],
        'figure' => ['style'],
    ];

    /**
     * Tags que se eliminan junto con todo su contenido (no se "desenvuelven").
     */
    protected $dropWithContent = [
        'script', 'style', 'object', 'embed', 'applet', 'svg', 'math', 'template', 'noscript',
        'form', 'input', 'button', 'textarea', 'select', 'option', 'meta', 'link', 'base',
        'frame', 'frameset', 'head', 'title', 'xml',
    ];

    /**
     * Hosts permitidos en <iframe src> (se aceptan también sus subdominios).
     */
    protected $allowedIframeHosts = [
        'youtube.com', 'youtube-nocookie.com', 'player.vimeo.com', 'dailymotion.com',
        'open.spotify.com', 'w.soundcloud.com', 'google.com', 'maps.google.com',
        'docs.google.com', 'calendar.google.com', 'facebook.com', 'instagram.com',
        'platform.twitter.com', 'tiktok.com', 'loom.com', 'canva.com', 'codepen.io',
    ];

    protected $urlAttributes = ['href', 'src', 'poster', 'cite'];

    protected $allowedSchemes = ['http', 'https', 'mailto', 'tel'];

    /**
     * Constructor
     *
     * @param string $html HTML sin sanitizar
     * @param bool $autoSanitize Si es true, sanitiza automáticamente (por defecto: true)
     */
    public function __construct($html, $autoSanitize = true)
    {
        $this->html = $html;

        if ($autoSanitize) {
            $this->sanitized = $this->sanitize($html);
        } else {
            // Si no se auto-sanitiza, se escapa completamente por seguridad
            $this->sanitized = $this->escape($html);
        }
    }

    /**
     * Escapa HTML completamente (convierte todos los tags a entidades)
     *
     * @param string $html
     * @return string
     */
    public function escape($html)
    {
        return htmlspecialchars((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitiza HTML permitiendo solo tags seguros
     *
     * @param string $html
     * @return string
     */
    public function sanitize($html)
    {
        $html = (string)$html;
        if ($html === '') {
            return '';
        }

        // Prevenir ataques nulos
        $html = str_replace("\0", '', $html);

        // Sin DOMDocument no hay forma fiable de sanitizar: escapar todo
        if (!class_exists('DOMDocument')) {
            return $this->escape($html);
        }

        return $this->sanitizeWithDOM($html);
    }

    /**
     * Sanitiza HTML usando DOMDocument.
     *
     * El fragmento se envuelve en un contenedor propio para conservar TODOS los
     * nodos raíz (con LIBXML_HTML_NOIMPLIED solo sobrevivía el primero).
     *
     * @param string $html
     * @return string
     */
    protected function sanitizeWithDOM($html)
    {
        $dom = new \DOMDocument();

        // Suprimir errores de HTML malformado
        $previous = libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="__safehtml_root">' . $html . '</div></body></html>',
            LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__safehtml_root');
        if (!$root) {
            return $this->escape($html);
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            $this->cleanNode($child);
        }

        $cleaned = '';
        foreach ($root->childNodes as $child) {
            $cleaned .= $dom->saveHTML($child);
        }

        return $cleaned;
    }

    /**
     * Limpia un nodo DOM recursivamente
     *
     * @param \DOMNode $node
     */
    protected function cleanNode($node)
    {
        if (!$node) {
            return;
        }

        // Comentarios, PIs, CDATA: fuera (pueden esconder condicionales de IE, etc.)
        if (in_array($node->nodeType, [XML_COMMENT_NODE, XML_PI_NODE, XML_CDATA_SECTION_NODE], true)) {
            $node->parentNode->removeChild($node);
            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        $tagName = strtolower($node->nodeName);

        // Tags peligrosos: eliminar con su contenido
        if (in_array($tagName, $this->dropWithContent, true)) {
            $node->parentNode->removeChild($node);
            return;
        }

        // Tags no permitidos: desenvolver (conservar hijos ya limpios)
        if (!in_array($tagName, $this->allowedTags, true)) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                $this->cleanNode($child);
            }
            $parent = $node->parentNode;
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            return;
        }

        // Iframes solo de proveedores conocidos
        if ($tagName === 'iframe' && !$this->isAllowedIframeSrc($node->getAttribute('src'))) {
            $node->parentNode->removeChild($node);
            return;
        }

        $this->cleanAttributes($node, $tagName);

        // Enlaces que abren ventana nueva: evitar reverse tabnabbing
        if ($tagName === 'a' && strtolower($node->getAttribute('target')) === '_blank') {
            $rel = array_filter(preg_split('/\s+/', strtolower($node->getAttribute('rel'))));
            $rel = array_unique(array_merge($rel, ['noopener', 'noreferrer']));
            $node->setAttribute('rel', implode(' ', $rel));
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    /**
     * Elimina atributos no permitidos y valida URLs/estilos de los permitidos.
     */
    protected function cleanAttributes(\DOMElement $node, string $tagName): void
    {
        if (!$node->hasAttributes()) {
            return;
        }

        $allowed = array_merge(
            $this->allowedAttributes['*'] ?? [],
            $this->allowedAttributes[$tagName] ?? []
        );

        $toRemove = [];
        foreach ($node->attributes as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;

            if (!in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                $toRemove[] = $attr->name;
                continue;
            }

            if (in_array($name, $this->urlAttributes, true) && !$this->isSafeUrl($value)) {
                $toRemove[] = $attr->name;
                continue;
            }

            if (in_array($name, ['srcset'], true) && !$this->isSafeSrcset($value)) {
                $toRemove[] = $attr->name;
                continue;
            }

            if ($name === 'style' && !$this->isSafeStyle($value)) {
                $toRemove[] = $attr->name;
                continue;
            }

            if ($name === 'id' && !preg_match('/^[A-Za-z][A-Za-z0-9_\-:.]{0,99}$/', $value)) {
                $toRemove[] = $attr->name;
            }
        }

        foreach ($toRemove as $name) {
            $node->removeAttribute($name);
        }
    }

    /**
     * URL segura: relativa o con esquema en lista blanca.
     * Normaliza como lo hacen los navegadores (ignoran espacios/controles),
     * así " javascript:" o "java\tscript:" no se cuelan.
     */
    protected function isSafeUrl(string $url): bool
    {
        $normalized = preg_replace('/[\x00-\x20\x7f]+/', '', $url);

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $m)) {
            return in_array(strtolower($m[1]), $this->allowedSchemes, true);
        }

        return true; // relativa: /ruta, #ancla, ?q=, ruta/relativa
    }

    protected function isSafeSrcset(string $srcset): bool
    {
        foreach (explode(',', $srcset) as $candidate) {
            $url = preg_split('/\s+/', trim($candidate))[0] ?? '';
            if ($url !== '' && !$this->isSafeUrl($url)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Estilo inline seguro: sin url(), expresiones, bindings, imports ni escapes
     * CSS (que podrían ocultar lo anterior) y sin overlays position:fixed.
     */
    protected function isSafeStyle(string $style): bool
    {
        return !preg_match('/url\s*\(|expression|javascript|vbscript|behavior|binding|@import|\\\\|position\s*:\s*(fixed|sticky)/i', $style);
    }

    protected function isAllowedIframeSrc(string $src): bool
    {
        $src = trim($src);
        if (!preg_match('#^https://#i', $src)) {
            return false;
        }

        $host = strtolower((string)parse_url($src, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach ($this->allowedIframeHosts as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Permite configurar tags permitidos personalizados
     *
     * @param array $tags
     * @return $this
     */
    public function setAllowedTags(array $tags)
    {
        $this->allowedTags = $tags;
        return $this;
    }

    /**
     * Permite configurar atributos permitidos personalizados
     *
     * @param array $attributes
     * @return $this
     */
    public function setAllowedAttributes(array $attributes)
    {
        $this->allowedAttributes = $attributes;
        return $this;
    }

    /**
     * Obtiene el HTML sanitizado
     *
     * @return string
     */
    public function getSanitized()
    {
        return $this->sanitized;
    }

    /**
     * Obtiene el HTML original (sin sanitizar) - usar con precaución
     *
     * @return string
     */
    public function getRaw()
    {
        return $this->html;
    }

    /**
     * Conversión automática a string devuelve HTML sanitizado
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->sanitized;
    }

    /**
     * Método estático para sanitizar rápidamente
     *
     * @param string $html
     * @param bool $allowHtmlTags Si es false, escapa todo (por defecto: true)
     * @return string
     */
    public static function clean($html, $allowHtmlTags = true)
    {
        $instance = new self($html, $allowHtmlTags);
        return $instance->getSanitized();
    }

    /**
     * Texto plano: elimina todo el HTML y decodifica entidades.
     * Para campos como excerpt, títulos o meta descripciones.
     */
    public static function plain($text): string
    {
        $text = strip_tags(str_replace("\0", '', (string)$text));
        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Método estático para escapar completamente
     *
     * @param string $html
     * @return string
     */
    public static function escapeAll($html)
    {
        return htmlspecialchars((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
