<?php

namespace Screenart\Musedock;

/**
 * SafeHtml - Clase para sanitización segura de HTML
 *
 * Esta clase previene XSS (Cross-Site Scripting) mediante:
 * - Escape completo de HTML (método escape)
 * - Sanitización permitiendo solo tags seguros (método sanitize)
 * - Conversión automática a string seguro
 *
 * @package Screenart\Musedock
 */
class SafeHtml
{
    protected $html;
    protected $sanitized;
    protected $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre',
        'a', 'img', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
        'div', 'span', 'hr'
    ];

    protected $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'table' => ['class'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'div' => ['class'],
        'span' => ['class'],
    ];

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
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitiza HTML permitiendo solo tags seguros
     *
     * @param string $html
     * @return string
     */
    public function sanitize($html)
    {
        if (empty($html)) {
            return '';
        }

        // Prevenir ataques nulos
        $html = str_replace("\0", '', $html);

        // Remover scripts y estilos inline peligrosos
        $html = $this->removeScripts($html);

        // Usar DOMDocument para parsear HTML de forma segura
        if (class_exists('DOMDocument')) {
            return $this->sanitizeWithDOM($html);
        }

        // Fallback: strip_tags con tags permitidos
        $allowedTagsString = '<' . implode('><', $this->allowedTags) . '>';
        $cleaned = strip_tags($html, $allowedTagsString);

        // Sanitizar atributos peligrosos
        $cleaned = $this->sanitizeAttributes($cleaned);

        return $cleaned;
    }

    /**
     * Sanitiza HTML usando DOMDocument (método más seguro)
     *
     * @param string $html
     * @return string
     */
    protected function sanitizeWithDOM($html)
    {
        $dom = new \DOMDocument();

        // Suprimir errores de HTML malformado
        libxml_use_internal_errors(true);

        // Cargar HTML con codificación UTF-8
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        // Recorrer todos los nodos y eliminar los no permitidos
        $this->cleanNode($dom->documentElement);

        // Obtener HTML limpio
        $cleaned = $dom->saveHTML($dom->documentElement);

        // Remover el wrapper XML
        $cleaned = preg_replace('/^<\?xml[^>]*>/', '', $cleaned);

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

        // Si es un elemento
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($node->nodeName);

            // Si el tag no está permitido, reemplazarlo con su contenido de texto
            if (!in_array($tagName, $this->allowedTags)) {
                $textNode = $node->ownerDocument->createTextNode($node->textContent);
                $node->parentNode->replaceChild($textNode, $node);
                return;
            }

            // Limpiar atributos
            if ($node->hasAttributes()) {
                $attributesToRemove = [];

                foreach ($node->attributes as $attr) {
                    $attrName = strtolower($attr->name);

                    // Verificar si el atributo está permitido para este tag
                    $allowed = isset($this->allowedAttributes[$tagName]) &&
                               in_array($attrName, $this->allowedAttributes[$tagName]);

                    if (!$allowed) {
                        $attributesToRemove[] = $attrName;
                    } else {
                        // Sanitizar el valor del atributo
                        $attrValue = $attr->value;

                        // Prevenir javascript: y data: en href/src
                        if (in_array($attrName, ['href', 'src'])) {
                            if (preg_match('/^(javascript|data|vbscript):/i', $attrValue)) {
                                $attributesToRemove[] = $attrName;
                            }
                        }

                        // Prevenir event handlers (onclick, onerror, etc.)
                        if (preg_match('/^on/i', $attrName)) {
                            $attributesToRemove[] = $attrName;
                        }
                    }
                }

                // Remover atributos no permitidos
                foreach ($attributesToRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }
            }
        }

        // Procesar hijos recursivamente
        if ($node->hasChildNodes()) {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                $this->cleanNode($child);
            }
        }
    }

    /**
     * Remueve scripts y estilos inline peligrosos
     *
     * @param string $html
     * @return string
     */
    protected function removeScripts($html)
    {
        // Remover <script>
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

        // Remover <style>
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);

        // Remover event handlers inline
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // Remover javascript: en atributos
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $html);

        // Remover expresiones CSS peligrosas
        $html = preg_replace('/expression\s*\(/i', '', $html);

        return $html;
    }

    /**
     * Sanitiza atributos peligrosos (fallback cuando no hay DOMDocument)
     *
     * @param string $html
     * @return string
     */
    protected function sanitizeAttributes($html)
    {
        // Remover event handlers
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // Remover javascript: y data: URLs
        $html = preg_replace('/(href|src)\s*=\s*["\']?(javascript|data|vbscript):[^"\'\s>]*/i', '', $html);

        // Remover style con expresiones CSS peligrosas
        $html = preg_replace('/style\s*=\s*["\'][^"\']*expression\s*\([^"\']*["\']/i', '', $html);

        return $html;
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
        return $this->sanitized;
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
     * Método estático para escapar completamente
     *
     * @param string $html
     * @return string
     */
    public static function escapeAll($html)
    {
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
