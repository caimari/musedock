<?php

namespace Screenart\Musedock;

/**
 * Sistema de Hooks Simple (similar a WordPress)
 * Para filtros y acciones en el contenido
 */
class Hooks
{
    private static array $filters = [];
    private static array $actions = [];

    /**
     * Registrar un filtro
     */
    public static function addFilter(string $tag, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$filters[$tag])) {
            self::$filters[$tag] = [];
        }

        if (!isset(self::$filters[$tag][$priority])) {
            self::$filters[$tag][$priority] = [];
        }

        self::$filters[$tag][$priority][] = $callback;
    }

    /**
     * Aplicar filtros a un valor
     */
    public static function applyFilters(string $tag, $value, ...$args)
    {
        if (!isset(self::$filters[$tag])) {
            return $value;
        }

        // Ordenar por prioridad
        ksort(self::$filters[$tag]);

        foreach (self::$filters[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func($callback, $value, ...$args);
            }
        }

        return $value;
    }

    /**
     * Registrar una acción
     */
    public static function addAction(string $tag, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$actions[$tag])) {
            self::$actions[$tag] = [];
        }

        if (!isset(self::$actions[$tag][$priority])) {
            self::$actions[$tag][$priority] = [];
        }

        self::$actions[$tag][$priority][] = $callback;
    }

    /**
     * Ejecutar acciones
     */
    public static function doAction(string $tag, ...$args): void
    {
        if (!isset(self::$actions[$tag])) {
            return;
        }

        // Ordenar por prioridad
        ksort(self::$actions[$tag]);

        foreach (self::$actions[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func($callback, ...$args);
            }
        }
    }

    /**
     * Verificar si existe un filtro
     */
    public static function hasFilter(string $tag): bool
    {
        return isset(self::$filters[$tag]) && !empty(self::$filters[$tag]);
    }

    /**
     * Verificar si existe una acción
     */
    public static function hasAction(string $tag): bool
    {
        return isset(self::$actions[$tag]) && !empty(self::$actions[$tag]);
    }
}
