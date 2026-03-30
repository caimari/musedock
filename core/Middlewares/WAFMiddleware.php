<?php

namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Security\WAF;

/**
 * Middleware que integra el WAF en todas las peticiones
 */
class WAFMiddleware
{
    public function handle(): bool
    {
        // Verificar si WAF está deshabilitado por config
        if (defined('WAF_ENABLED') && WAF_ENABLED === false) {
            return true;
        }

        // Ejecutar protección WAF
        return WAF::protect();
    }
}
