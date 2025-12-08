<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class MusedockController {
    use RequiresPermission;

    public function index() {
        SessionSecurity::startSession();

        // Obtener usuario autenticado
        $auth = SessionSecurity::getAuthenticatedUser();

        // Si no hay usuario autenticado, redirigir al login
        if (!$auth) {
            header("Location: /musedock/login");
            exit;
        }

        // Redirigir al dashboard
        header("Location: /musedock/dashboard");
        exit;
    }
}
