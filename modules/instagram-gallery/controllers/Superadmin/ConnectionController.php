<?php

namespace Modules\InstagramGallery\Controllers\Superadmin;

use Screenart\Musedock\View;

/**
 * Superadmin Social Publisher Controller.
 *
 * Hereda del Tenant controller y sobreescribe el scope para que el
 * superadmin gestione cuentas GLOBALES del CMS principal (tenant_id = NULL)
 * — no las de los tenants. Las cuentas de los tenants se gestionan desde
 * /musedock/domain-manager/{id}/edit (sección Instagram).
 *
 * Toda la lógica (credenciales, OAuth, Facebook, hashtags, sync…) se
 * reutiliza tal cual; sólo cambiamos el basePath a "musedock" y el
 * tenantId a null.
 */
class ConnectionController extends \Modules\InstagramGallery\Controllers\Tenant\ConnectionController
{
    public function __construct()
    {
        parent::__construct();
        // Superadmin: panel en /musedock, sin tenant.
        $this->basePath = 'musedock';
        $this->tenantId = null;
        $this->legacyCallbackPath = '/musedock/instagram/callback';
    }

    /**
     * La vista usa el mismo template que el tenant: una sola página con
     * acordeones por cuenta, modales SweetAlert2, etc. El template vive
     * en views/tenant/instagram/index.blade.php y es agnóstico del scope.
     */
    public function index()
    {
        $this->checkPermission('instagram.view');
        $connections = \Modules\InstagramGallery\Models\InstagramConnection::getByTenant(null, false);

        // IMPORTANTE: usamos la vista superadmin (wrapper que @include-a la del
        // tenant) para que View::renderModule detecte contexto superadmin y
        // cargue el layout correcto (sidebar del superadmin, no el del tenant).
        return View::renderModule('instagram-gallery', 'superadmin.instagram.index', [
            'connections' => $connections,
            'tenantId' => null,
            'basePath' => $this->basePath,
        ]);
    }
}
