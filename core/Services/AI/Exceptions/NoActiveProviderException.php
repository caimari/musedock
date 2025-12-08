<?php
namespace Screenart\Musedock\Services\AI\Exceptions;

class NoActiveProviderException extends AIConfigurationException
{
    protected $message = 'No hay proveedores de IA activos o configurados por defecto.';
}