<?php

/**
 * Funciones helper para el sistema de hooks
 * Compatibilidad con sintaxis tipo WordPress
 */

use Screenart\Musedock\Hooks;

if (!function_exists('add_filter')) {
    /**
     * Agregar un filtro
     */
    function add_filter(string $tag, callable $callback, int $priority = 10): void
    {
        Hooks::addFilter($tag, $callback, $priority);
    }
}

if (!function_exists('apply_filters')) {
    /**
     * Aplicar filtros a un valor
     */
    function apply_filters(string $tag, $value, ...$args)
    {
        return Hooks::applyFilters($tag, $value, ...$args);
    }
}

if (!function_exists('add_action')) {
    /**
     * Agregar una acción
     */
    function add_action(string $tag, callable $callback, int $priority = 10): void
    {
        Hooks::addAction($tag, $callback, $priority);
    }
}

if (!function_exists('do_action')) {
    /**
     * Ejecutar acciones
     */
    function do_action(string $tag, ...$args): void
    {
        Hooks::doAction($tag, ...$args);
    }
}

if (!function_exists('has_filter')) {
    /**
     * Verificar si existe un filtro
     */
    function has_filter(string $tag): bool
    {
        return Hooks::hasFilter($tag);
    }
}

if (!function_exists('has_action')) {
    /**
     * Verificar si existe una acción
     */
    function has_action(string $tag): bool
    {
        return Hooks::hasAction($tag);
    }
}

// ============================================================================
// REGISTRAR FILTROS CORE DE SHORTCODES
// ============================================================================

// Registrar el procesamiento de shortcodes de sliders en el filtro 'the_content'
add_filter('the_content', function ($content) {
    return \Screenart\Musedock\Helpers\SliderHelper::processShortcodes($content);
}, 10);
