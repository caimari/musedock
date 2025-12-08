<?php
namespace Screenart\Musedock\Services\AI\Exceptions;

class ProviderNotActiveException extends AIConfigurationException
{
    protected $message = 'El proveedor de IA seleccionado no está activo.';

    public function __construct(string $providerName = '', int $code = 0, \Throwable $previous = null)
    {
        if (!empty($providerName)) {
            $this->message = sprintf("El proveedor de IA '%s' no está activo.", $providerName);
        }
        parent::__construct($this->message, $code, $previous);
    }
}