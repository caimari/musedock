<?php
namespace Screenart\Musedock\Widgets;

use Screenart\Musedock\Widgets\WidgetBase;

abstract class WidgetBase
{
    // Propiedades estáticas obligatorias que cada widget debe definir
    public static string $slug;         // Identificador único (ej: 'text', 'menu')
    public static string $name;         // Nombre legible para el admin (ej: 'Texto/HTML')
    public static string $description;  // Descripción corta

    /**
     * Renderiza el formulario de configuración del widget en el admin.
     * Debe ser implementado por cada clase de widget.
     *
     * @param array $config Configuración actual de la instancia.
     * @param int|null $instanceId ID único de esta instancia (para IDs de HTML).
     * @return string HTML del formulario.
     */
    abstract public function form(array $config = [], ?int $instanceId = null): string;

    /**
     * Renderiza el widget en el frontend.
     * Debe ser implementado por cada clase de widget.
     *
     * @param array $config Configuración guardada de la instancia.
     * @return string HTML a mostrar en el sitio.
     */
    abstract public function render(array $config = []): string;

    /**
     * (Opcional pero recomendado) Valida y/o sanitiza la configuración
     * antes de guardarla en la base de datos.
     * Por defecto, devuelve la configuración tal cual. Sobrescribir si es necesario.
     *
     * @param array $config Configuración recibida del formulario.
     * @return array Configuración limpia y validada.
     */
    public function sanitizeConfig(array $config): array
    {
        // Implementar sanitización específica en cada widget si es necesario
        return $config;
    }
}