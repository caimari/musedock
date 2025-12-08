<?php
namespace Screenart\Musedock;

/**
 * ModuleAutoloader - Sistema centralizado de autoload para módulos
 */
class ModuleAutoloader
{
    /** @var array Prefijos de namespace registrados y sus directorios base */
    protected static $prefixes = [];
    
    /** @var bool Si el autoloader ya ha sido inicializado */
    protected static $initialized = false;
    
    /** @var bool Modo de depuración activado/desactivado */
    protected static $debugMode = false;
    
    /**
     * Activa el modo de depuración para loguear todas las operaciones
     */
    public static function enableDebug()
    {
        self::$debugMode = true;
        self::log("Modo de depuración activado");
    }
    
    /**
     * Inicializa el autoloader (registra el handler en spl_autoload)
     */
    public static function init()
    {
        // No volver a inicializar si ya está hecho
        if (self::$initialized) {
            return true;
        }
        
        // Registrar autoloader con alta prioridad (prepend=true)
        $result = spl_autoload_register([self::class, 'autoload'], true, true);
        
        if ($result) {
            self::$initialized = true;
            self::log("ModuleAutoloader inicializado con éxito");
        } else {
            self::log("Error al inicializar ModuleAutoloader", "ERROR");
        }
        
        return $result;
    }
    
    /**
     * Registra un namespace PSR-4 y su directorio base
     */
    public static function registerNamespace($namespace, $baseDir)
    {
        // Normalizar el namespace (asegurar que termina con \)
        $namespace = rtrim($namespace, '\\') . '\\';
        
        // Normalizar el directorio (asegurar que termina con /)
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        // Verificar que el directorio existe
        if (!is_dir($baseDir)) {
            self::log("Error al registrar namespace '$namespace': El directorio '$baseDir' no existe", "ERROR");
            return false;
        }
        
        // Registrar el namespace
        self::$prefixes[$namespace] = $baseDir;
        self::log("Namespace registrado: '$namespace' => '$baseDir'");
        
        return true;
    }
    
    /**
     * Función de autoload que busca y carga clases según los namespaces registrados
     */
    public static function autoload($class)
    {
        self::log("Intentando cargar: '$class'");
        
        // Buscar en todos los namespaces registrados
        foreach (self::$prefixes as $prefix => $baseDir) {
            // Verificar si la clase comienza con este namespace
            if (strpos($class, $prefix) === 0) {
                // Extraer la parte relativa de la clase (sin el namespace)
                $relativeClass = substr($class, strlen($prefix));
                
                // Convertir los separadores de namespace a separadores de directorio
                $filePath = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                
                self::log("Buscando archivo: '$filePath'");
                
                // Verificar si el archivo existe
                if (file_exists($filePath)) {
                    require_once $filePath;
                    self::log("Archivo cargado: '$filePath'");
                    
                    // Verificar si la clase ahora existe
                    if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
                        self::log("Clase '$class' cargada correctamente");
                        return true;
                    } else {
                        self::log("Archivo cargado pero la clase '$class' no fue declarada", "WARNING");
                    }
                }
            }
        }
        
        self::log("No se encontró la clase '$class' en ningún namespace registrado");
        return false;
    }
    
    /**
     * Devuelve todos los namespaces registrados (útil para depuración)
     */
    public static function getRegisteredNamespaces()
    {
        return self::$prefixes;
    }
    
    /**
     * Verifica si una clase puede ser cargada por este autoloader
     */
    public static function canLoad($class)
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (strpos($class, $prefix) === 0) {
                $relativeClass = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                if (file_exists($file)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Logueo interno con soporte para el sistema Logger global
     */
    protected static function log($message, $level = "DEBUG")
    {
        if (!self::$debugMode && $level == "DEBUG") {
            return;
        }
        
        if (class_exists('\Screenart\Musedock\Logger', false)) {
            // Usar el logger global si está disponible
            Logger::log($message, $level, ['source' => 'ModuleAutoloader']);
        } else {
            // Fallback a error_log de PHP
            error_log("[ModuleAutoloader][$level] $message");
        }
    }
}