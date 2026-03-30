<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\SessionCleaner;

use Screenart\Musedock\Traits\RequiresPermission;
class AdminController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.view');
        // SessionCleaner::cleanExpiredTokens(); // Método no existe, comentado

        // Redirección dinámica basada en admin_path (ej: /panel/dashboard)
        $adminPath = '/' . admin_path();

        if (!isset($_SESSION['admin'])) {
            header("Location: {$adminPath}/login");
            exit;
        }

        header("Location: {$adminPath}/dashboard");
        exit;
    }
}
