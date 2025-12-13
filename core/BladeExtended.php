<?php

namespace Screenart\Musedock;

use eftec\bladeone\BladeOne;

class BladeExtended extends BladeOne
{
    protected $namespaces = [];
    protected $pathResolver = null;

    /**
     * Constructor que configura BladeOne para lanzar excepciones en lugar de echo
     */
    public function __construct($templatePath = null, $compiledPath = null, $mode = 0)
    {
        parent::__construct($templatePath, $compiledPath, $mode);

        // Configurar para que lance excepciones en lugar de echo
        $this->throwOnError = true;
    }

    /**
     * Override del método showError para lanzar excepciones en lugar de echo
     * Esto asegura que los errores se puedan capturar con try/catch
     */
    public function showError($id, $text, $critic = false, $alwaysThrow = false): string
    {
        // Siempre lanzar excepción para que se pueda capturar
        throw new \RuntimeException("BladeOne Error [$id]: $text");
    }

    /**
     * Añadir un namespace para vistas, ejemplo @include('Modulo::admin.index')
     */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, '/');
    }

    /**
     * Definir un resolver personalizado para rutas de vistas.
     * Esto permite lógica personalizada para resolver plantillas.
     */
    public function setPathResolver(callable $resolver): void
    {
        $this->pathResolver = $resolver;
    }

    /**
     * Override de getTemplateFile para soportar:
     * - Namespaces (ej: MediaManager::admin._modal)
     * - Resolver dinámico
     * - Fallback a la lógica original de BladeOne
     */
    public function getTemplateFile($templateName = ''): string
    {
        // Si hay un path resolver personalizado, usarlo
        if ($this->pathResolver) {
            $resolved = call_user_func($this->pathResolver, $templateName);
            if ($resolved) {
                return $resolved;
            }
        }

        // Si tiene namespace (Namespace::view.name)
        if (strpos($templateName, '::') !== false) {
            [$namespace, $view] = explode('::', $templateName, 2);
            if (isset($this->namespaces[$namespace])) {
                return $this->namespaces[$namespace] . '/' . str_replace('.', '/', $view) . '.blade.php';
            }
        }

        // Para vistas normales, construir la ruta completa con la extensión .blade.php
        // Si ya tiene la extensión, no la agregamos dos veces
        $viewPath = str_replace('.', '/', $templateName);

        // Si no termina en .blade.php, agregarlo
        if (!preg_match('/\.blade\.php$/', $viewPath)) {
            $viewPath .= '.blade.php';
        }

        // Obtener el templatePath correcto (puede ser string o array)
        $basePath = is_array($this->templatePath) ? $this->templatePath[0] : $this->templatePath;

        // Construir ruta completa usando templatePath (directorio base de vistas)
        $fullPath = rtrim($basePath, '/') . '/' . $viewPath;

        // Verificar si el archivo existe, si no, usar lógica por defecto de BladeOne
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // Fallback a la lógica original de BladeOne
        return parent::getTemplateFile($templateName);
    }
}
