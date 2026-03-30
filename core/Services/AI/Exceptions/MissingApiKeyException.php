<?php
namespace Screenart\Musedock\Services\AI\Exceptions;

class MissingApiKeyException extends AIConfigurationException
{
    protected $message = 'Falta la clave API para el proveedor de IA seleccionado.';

     public function __construct(string $providerName = '', int $code = 0, \Throwable $previous = null)
    {
        if (!empty($providerName)) {
            $this->message = sprintf("Falta la clave API para el proveedor de IA '%s'.", $providerName);
        }
        parent::__construct($this->message, $code, $previous);
    }
}