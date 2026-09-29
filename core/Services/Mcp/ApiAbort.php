<?php

namespace Screenart\Musedock\Services\Mcp;

/**
 * Se lanza en lugar de "echo + exit" cuando la API v1 se ejecuta en modo
 * interno (desde el servidor MCP), para poder capturar la respuesta.
 */
class ApiAbort extends \RuntimeException
{
    public function __construct(public readonly int $status, public readonly array $payload)
    {
        parent::__construct($payload['error']['message'] ?? $payload['message'] ?? 'API aborted', $status);
    }
}
