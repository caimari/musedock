<?php
namespace Screenart\Musedock\Services\AI\Providers;

/**
 * Clase base abstracta para todos los proveedores de IA
 */
abstract class AbstractProvider
{
    protected $config;
    
    /**
     * Constructor
     * 
     * @param array $config Configuración del proveedor
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * Establece la configuración del proveedor
     * 
     * @param array $config
     * @return self
     */
    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
    
    /**
     * Obtiene la configuración
     * 
     * @param string|null $key Clave específica o toda la configuración
     * @param mixed $default Valor por defecto si la clave no existe
     * @return mixed
     */
    public function getConfig($key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Genera contenido basado en el prompt
     * 
     * @param string $prompt Prompt para la IA
     * @param array $options Opciones adicionales
     * @return array Resultado con contenido y metadatos
     */
    abstract public function generate($prompt, array $options = []);
    
    /**
     * Devuelve información sobre el proveedor
     * 
     * @return array
     */
    abstract public function getInfo();
    
    /**
     * Lista los modelos disponibles para este proveedor
     * 
     * @return array
     */
    abstract public function getAvailableModels();
    
    /**
     * Valida la configuración del proveedor
     * 
     * @return bool|string True si es válida, mensaje de error si no
     */
    abstract public function validateConfig();
}