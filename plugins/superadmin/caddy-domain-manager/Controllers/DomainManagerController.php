<?php
/**
 * DomainManagerController - Gestiona dominios custom de tenants
 *
 * Este controlador NO modifica el CRUD básico de tenants.
 * Solo añade funcionalidad de integración con Caddy.
 */

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Mail\Mailer;
use CaddyDomainManager\Services\CaddyService;
use PDO;

class DomainManagerController
{
    use RequiresPermission;

    private CaddyService $caddyService;

    public function __construct()
    {
        $this->caddyService = new CaddyService();
    }

    /**
     * Verifica que multitenancy esté habilitado
     */
    private function checkMultitenancyEnabled(): void
    {
        $envValue = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($envValue !== null) {
            $enabled = filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        } else {
            $enabled = setting('multi_tenant_enabled', config('multi_tenant_enabled', false));
        }

        if (!$enabled) {
            flash('error', 'La funcionalidad de multitenancy no está habilitada.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    /**
     * Lista todos los tenants con estado de Caddy
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $pdo = Database::connect();

        // Filtros
        $caddyStatusFilter = $_GET['caddy_status'] ?? '';
        $statusFilter = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';

        // Construir query
        $sql = "SELECT * FROM tenants WHERE 1=1";
        $params = [];

        if ($caddyStatusFilter) {
            $sql .= " AND caddy_status = ?";
            $params[] = $caddyStatusFilter;
        }

        if ($statusFilter) {
            $sql .= " AND status = ?";
            $params[] = $statusFilter;
        }

        if ($search) {
            $sql .= " AND (domain LIKE ? OR name LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tenants = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Verificar disponibilidad de Caddy API
        $caddyApiAvailable = $this->caddyService->isApiAvailable();

        return View::renderSuperadmin('plugins.caddy-domain-manager.index', [
            'title' => 'Domain Manager',
            'tenants' => $tenants,
            'caddyApiAvailable' => $caddyApiAvailable,
            'filters' => [
                'caddy_status' => $caddyStatusFilter,
                'status' => $statusFilter,
                'search' => $search
            ]
        ]);
    }

    /**
     * Formulario para crear nuevo tenant con dominio custom
     */
    public function create()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $caddyApiAvailable = $this->caddyService->isApiAvailable();

        return View::renderSuperadmin('plugins.caddy-domain-manager.create', [
            'title' => 'Nuevo Dominio Custom',
            'caddyApiAvailable' => $caddyApiAvailable
        ]);
    }

    /**
     * Almacena nuevo tenant y configura en Caddy
     */
    public function store()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        // Validar CSRF
        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/domain-manager/create');
            exit;
        }

        // Recoger datos
        $name = trim($_POST['name'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $includeWww = isset($_POST['include_www']);
        $configureInCaddy = isset($_POST['configure_caddy']);
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminName = trim($_POST['admin_name'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $sendWelcomeEmail = isset($_POST['send_welcome_email']);

        // Validaciones
        $errors = $this->validateInput($name, $domain, $adminEmail, $adminName, $adminPassword);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            header('Location: /musedock/domain-manager/create');
            exit;
        }

        // Sanitizar dominio
        $domain = $this->sanitizeDomain($domain);

        // Verificar dominio único
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
        $stmt->execute([$domain]);

        if ($stmt->fetch()) {
            flash('error', "El dominio '{$domain}' ya está registrado.");
            header('Location: /musedock/domain-manager/create');
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Generar slug
            $slug = $this->generateSlug($name);

            // Determinar estado inicial de Caddy
            $caddyStatus = $configureInCaddy ? 'pending_dns' : 'not_configured';
            $caddyRouteId = $configureInCaddy ? $this->caddyService->generateRouteId($domain) : null;

            // Insertar tenant
            $stmt = $pdo->prepare("
                INSERT INTO tenants (name, slug, domain, status, include_www, caddy_status, caddy_route_id, created_at)
                VALUES (?, ?, ?, 'active', ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $slug, $domain, $includeWww ? 1 : 0, $caddyStatus, $caddyRouteId]);

            $tenantId = $pdo->lastInsertId();

            // Crear admin del tenant
            $this->createTenantAdmin($pdo, $tenantId, $adminEmail, $adminName, $adminPassword);

            // Crear permisos y roles por defecto
            $this->createDefaultPermissionsAndRoles($pdo, $tenantId);

            $pdo->commit();

            // Configurar en Caddy si se solicitó
            $caddyMessage = '';
            if ($configureInCaddy) {
                $caddyResult = $this->configureDomainInCaddy($tenantId, $domain, $includeWww);

                if ($caddyResult['success']) {
                    $caddyMessage = " Dominio configurado en Caddy.";
                } else {
                    $caddyMessage = " Error al configurar Caddy: " . $caddyResult['error'];
                }
            }

            // Enviar email de bienvenida si se solicitó
            $emailMessage = '';
            if ($sendWelcomeEmail) {
                $emailSent = $this->sendWelcomeEmail($adminEmail, $adminName, $adminPassword, $domain, $name);
                if ($emailSent) {
                    $emailMessage = " Email de bienvenida enviado.";
                } else {
                    $emailMessage = " No se pudo enviar el email de bienvenida.";
                }
            }

            flash('success', "Tenant '{$name}' creado correctamente.{$caddyMessage}{$emailMessage}");

            header('Location: /musedock/domain-manager');
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            Logger::log("[DomainManager] Error creando tenant: " . $e->getMessage(), 'ERROR');
            flash('error', 'Error al crear el tenant: ' . $e->getMessage());
            header('Location: /musedock/domain-manager/create');
            exit;
        }
    }

    /**
     * Formulario de edición
     */
    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $tenant = $this->getTenant($id);

        if (!$tenant) {
            flash('error', 'Tenant no encontrado.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        $caddyApiAvailable = $this->caddyService->isApiAvailable();

        // Obtener info de Caddy si existe route_id
        $caddyRouteInfo = null;
        if ($tenant->caddy_route_id && $caddyApiAvailable) {
            $caddyRouteInfo = $this->caddyService->getRoute($tenant->caddy_route_id);
        }

        return View::renderSuperadmin('plugins.caddy-domain-manager.edit', [
            'title' => 'Editar Dominio: ' . $tenant->domain,
            'tenant' => $tenant,
            'caddyApiAvailable' => $caddyApiAvailable,
            'caddyRouteInfo' => $caddyRouteInfo
        ]);
    }

    /**
     * Actualiza tenant
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        // Validar CSRF
        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header("Location: /musedock/domain-manager/{$id}/edit");
            exit;
        }

        $tenant = $this->getTenant($id);

        if (!$tenant) {
            flash('error', 'Tenant no encontrado.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $newIncludeWww = isset($_POST['include_www']);
        $caddyStatus = trim($_POST['caddy_status'] ?? $tenant->caddy_status);

        // Guardar estado anterior para detectar cambios
        $oldIncludeWww = (bool)($tenant->include_www ?? false);

        if (empty($name)) {
            flash('error', 'El nombre es obligatorio.');
            header("Location: /musedock/domain-manager/{$id}/edit");
            exit;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                UPDATE tenants
                SET name = ?, status = ?, include_www = ?, caddy_status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $status, $newIncludeWww ? 1 : 0, $caddyStatus, $id]);

            // Si cambió include_www Y está configurado en Caddy, reconfigurar
            $needsCaddyUpdate = ($oldIncludeWww !== $newIncludeWww) &&
                               $tenant->caddy_route_id &&
                               in_array($tenant->caddy_status, ['active', 'error']);

            if ($needsCaddyUpdate && $this->caddyService->isApiAvailable()) {
                // Primero eliminar la ruta antigua
                $this->caddyService->removeDomain($tenant->caddy_route_id);

                // Reconfigurar con nuevo valor de www
                $result = $this->configureDomainInCaddy($id, $tenant->domain, $newIncludeWww);

                if ($result['success']) {
                    flash('success', "Tenant actualizado y reconfigurado en Caddy con " . ($newIncludeWww ? 'www incluido' : 'sin www') . ".");
                } else {
                    flash('warning', "Tenant actualizado, pero error al reconfigurar Caddy: " . $result['error']);
                }
            } else {
                flash('success', 'Tenant actualizado correctamente.');
            }

            header('Location: /musedock/domain-manager');
            exit;

        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error actualizando tenant: " . $e->getMessage(), 'ERROR');
            flash('error', 'Error al actualizar el tenant.');
            header("Location: /musedock/domain-manager/{$id}/edit");
            exit;
        }
    }

    /**
     * Elimina tenant y su configuración de Caddy
     */
    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $tenant = $this->getTenant($id);

        if (!$tenant) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Tenant no encontrado']);
                exit;
            }
            flash('error', 'Tenant no encontrado.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        $caddyWarning = null;

        try {
            // Intentar eliminar de Caddy primero si existe configuración
            if ($tenant->caddy_route_id) {
                $caddyResult = $this->caddyService->removeDomain($tenant->caddy_route_id);
                if (!$caddyResult['success']) {
                    // Guardar warning pero continuar con eliminación de BD
                    $caddyWarning = "La ruta '{$tenant->caddy_route_id}' podría seguir en Caddy. Error: " . $caddyResult['error'];
                    Logger::log("[DomainManager] Warning eliminando de Caddy: " . $caddyWarning, 'WARNING');
                } else {
                    Logger::log("[DomainManager] Ruta eliminada de Caddy: {$tenant->caddy_route_id}", 'INFO');
                }
            }

            // Eliminar tenant y datos relacionados de la BD
            $pdo = Database::connect();
            $pdo->beginTransaction();

            try {
                // Eliminar en orden de dependencias
                $pdo->prepare("DELETE FROM user_roles WHERE tenant_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM permissions WHERE tenant_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM roles WHERE tenant_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM admins WHERE tenant_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM tenants WHERE id = ?")->execute([$id]);

                $pdo->commit();
            } catch (\Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            Logger::log("[DomainManager] Tenant eliminado: {$tenant->domain} (ID: {$id})", 'INFO');

            // Preparar mensaje según resultado de Caddy
            $message = "Tenant '{$tenant->name}' eliminado correctamente.";
            if ($caddyWarning) {
                $message .= " Advertencia: " . $caddyWarning;
            }

            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'caddy_warning' => $caddyWarning
                ]);
                exit;
            }

            if ($caddyWarning) {
                flash('warning', $message);
            } else {
                flash('success', $message);
            }
            header('Location: /musedock/domain-manager');
            exit;

        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error eliminando tenant: " . $e->getMessage(), 'ERROR');

            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
                exit;
            }

            flash('error', 'Error al eliminar el tenant: ' . $e->getMessage());
            header('Location: /musedock/domain-manager');
            exit;
        }
    }

    /**
     * Reconfigura un dominio en Caddy
     */
    public function reconfigure($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $tenant = $this->getTenant($id);

        if (!$tenant) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Tenant no encontrado']);
                exit;
            }
            flash('error', 'Tenant no encontrado.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        // Validar que tenga sentido reconfigurar
        $caddyStatus = $tenant->caddy_status ?? 'not_configured';

        // Si ya está activo Y tiene SSL verificado, preguntar confirmación
        if ($caddyStatus === 'active' && !($tenant->caddy_error_log ?? '')) {
            // Verificar si realmente está funcionando
            $verification = $this->caddyService->verifyDomain($tenant->domain);

            if ($verification['success'] && ($verification['ssl_valid'] ?? false)) {
                if ($this->isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'El dominio ya está activo y funcionando correctamente. No necesita reconfiguración.',
                        'status' => 'already_active'
                    ]);
                    exit;
                }
                flash('info', "El dominio '{$tenant->domain}' ya está activo y funcionando correctamente.");
                header('Location: /musedock/domain-manager');
                exit;
            }
        }

        // Si está en estado 'configuring', no permitir otra configuración
        if ($caddyStatus === 'configuring') {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'El dominio está siendo configurado actualmente. Por favor espere.',
                    'status' => 'in_progress'
                ]);
                exit;
            }
            flash('warning', 'El dominio está siendo configurado actualmente. Por favor espere.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        // Si existe una ruta antigua, eliminarla primero
        if ($tenant->caddy_route_id) {
            $this->caddyService->removeDomain($tenant->caddy_route_id);
        }

        // Proceder con la reconfiguración
        $result = $this->configureDomainInCaddy($id, $tenant->domain, (bool)($tenant->include_www ?? true));

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        if ($result['success']) {
            $sslMsg = ($result['ssl_verified'] ?? false) ? ' con SSL activo' : '. SSL se está generando.';
            flash('success', "Dominio '{$tenant->domain}' reconfigurado en Caddy{$sslMsg}");
        } else {
            flash('error', "Error al reconfigurar dominio: " . $result['error']);
        }

        header('Location: /musedock/domain-manager');
        exit;
    }

    /**
     * Verifica el estado de un dominio (AJAX)
     */
    public function checkStatus($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $tenant = $this->getTenant($id);

        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado']);
            exit;
        }

        // Verificar dominio
        $verification = $this->caddyService->verifyDomain($tenant->domain);

        // Verificar ruta en Caddy
        $routeExists = false;
        if ($tenant->caddy_route_id) {
            $routeExists = $this->caddyService->routeExists($tenant->caddy_route_id);
        }

        echo json_encode([
            'success' => true,
            'domain' => $tenant->domain,
            'caddy_status' => $tenant->caddy_status,
            'route_exists' => $routeExists,
            'domain_responds' => $verification['success'],
            'ssl_valid' => $verification['ssl_valid'] ?? false,
            'http_code' => $verification['http_code'] ?? null
        ]);
        exit;
    }

    /**
     * Configura un dominio en Caddy y actualiza BD
     * Incluye verificación de SSL con espera asíncrona
     */
    private function configureDomainInCaddy(int $tenantId, string $domain, bool $includeWww): array
    {
        $pdo = Database::connect();

        // Actualizar estado a "configuring"
        $stmt = $pdo->prepare("UPDATE tenants SET caddy_status = 'configuring', caddy_error_log = NULL WHERE id = ?");
        $stmt->execute([$tenantId]);

        // Intentar añadir a Caddy
        $result = $this->caddyService->addDomain($domain, $includeWww);

        if ($result['success']) {
            // Caddy aceptó la ruta, pero el certificado SSL puede tardar
            $stmt = $pdo->prepare("
                UPDATE tenants
                SET caddy_status = 'configuring',
                    caddy_route_id = ?,
                    caddy_configured_at = NOW(),
                    caddy_error_log = 'Esperando certificado SSL...'
                WHERE id = ?
            ");
            $stmt->execute([$result['route_id'], $tenantId]);

            Logger::log("[DomainManager] Ruta añadida en Caddy: {$domain}, esperando SSL...", 'INFO');

            // Esperar y verificar SSL (máx 20 segundos)
            $sslVerified = false;
            $maxAttempts = 4;
            $waitTime = 5; // segundos entre intentos

            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep($waitTime);

                $verification = $this->caddyService->verifyDomain($domain);

                if ($verification['success'] && ($verification['ssl_valid'] ?? false)) {
                    $sslVerified = true;
                    break;
                }
            }

            if ($sslVerified) {
                // SSL verificado - estado activo
                $stmt = $pdo->prepare("
                    UPDATE tenants
                    SET caddy_status = 'active',
                        caddy_error_log = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$tenantId]);

                Logger::log("[DomainManager] Dominio configurado con SSL: {$domain}", 'INFO');

                return [
                    'success' => true,
                    'route_id' => $result['route_id'],
                    'ssl_verified' => true,
                    'message' => 'Dominio configurado con SSL activo'
                ];
            } else {
                // Ruta configurada pero SSL pendiente
                $stmt = $pdo->prepare("
                    UPDATE tenants
                    SET caddy_status = 'active',
                        caddy_error_log = 'SSL pendiente de verificación. Let''s Encrypt puede tardar hasta 60s.'
                    WHERE id = ?
                ");
                $stmt->execute([$tenantId]);

                Logger::log("[DomainManager] Dominio configurado, SSL pendiente: {$domain}", 'WARNING');

                return [
                    'success' => true,
                    'route_id' => $result['route_id'],
                    'ssl_verified' => false,
                    'message' => 'Dominio configurado. El certificado SSL se está generando (puede tardar hasta 60s).'
                ];
            }
        } else {
            // Error al añadir ruta en Caddy
            $stmt = $pdo->prepare("
                UPDATE tenants
                SET caddy_status = 'error',
                    caddy_error_log = ?
                WHERE id = ?
            ");
            $stmt->execute([$result['error'], $tenantId]);

            Logger::log("[DomainManager] Error configurando dominio en Caddy: {$domain} - " . $result['error'], 'ERROR');

            return [
                'success' => false,
                'error' => $result['error'],
                'message' => 'Error al configurar en Caddy'
            ];
        }
    }

    /**
     * Obtiene un tenant por ID
     */
    private function getTenant(int $id): ?object
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        $tenant = $stmt->fetch(PDO::FETCH_OBJ);
        return $tenant ?: null;
    }

    /**
     * Valida los datos de entrada
     */
    private function validateInput(string $name, string $domain, string $email, string $adminName, string $password): array
    {
        $errors = [];

        if (empty($name) || mb_strlen($name) < 3) {
            $errors[] = 'El nombre debe tener al menos 3 caracteres.';
        }

        if (empty($domain)) {
            $errors[] = 'El dominio es obligatorio.';
        } elseif (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $errors[] = 'El formato del dominio no es válido.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email del administrador no es válido.';
        }

        if (empty($adminName)) {
            $errors[] = 'El nombre del administrador es obligatorio.';
        }

        if (empty($password) || mb_strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        return $errors;
    }

    /**
     * Sanitiza un dominio
     */
    private function sanitizeDomain(string $domain): string
    {
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];
        $domain = preg_replace('/^www\./', '', $domain);
        return strtolower(trim($domain));
    }

    /**
     * Genera un slug único
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $original = $slug;
        $counter = 1;

        $pdo = Database::connect();
        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = ?");
            $stmt->execute([$slug]);
            if (!$stmt->fetch()) {
                break;
            }
            $slug = $original . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Crea el admin del tenant
     */
    private function createTenantAdmin(PDO $pdo, int $tenantId, string $email, string $name, string $password): int
    {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO admins (tenant_id, email, name, password, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$tenantId, $email, $name, $hashedPassword]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Crea permisos y roles por defecto para el tenant
     * Evita duplicados verificando existencia antes de insertar
     */
    private function createDefaultPermissionsAndRoles(PDO $pdo, int $tenantId): void
    {
        // Permisos por defecto
        $permissions = [
            ['name' => 'admin.access', 'description' => 'Acceder al panel de administración', 'category' => 'admin'],
            ['name' => 'admin.dashboard', 'description' => 'Ver el dashboard', 'category' => 'admin'],
            ['name' => 'users.view', 'description' => 'Ver usuarios', 'category' => 'users'],
            ['name' => 'users.create', 'description' => 'Crear usuarios', 'category' => 'users'],
            ['name' => 'users.edit', 'description' => 'Editar usuarios', 'category' => 'users'],
            ['name' => 'users.delete', 'description' => 'Eliminar usuarios', 'category' => 'users'],
            ['name' => 'content.view', 'description' => 'Ver contenido', 'category' => 'content'],
            ['name' => 'content.create', 'description' => 'Crear contenido', 'category' => 'content'],
            ['name' => 'content.edit', 'description' => 'Editar contenido', 'category' => 'content'],
            ['name' => 'content.delete', 'description' => 'Eliminar contenido', 'category' => 'content'],
        ];

        // Preparar statements para evitar duplicados
        $checkPermStmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? AND tenant_id = ?");
        $insertPermStmt = $pdo->prepare("
            INSERT INTO permissions (name, description, category, tenant_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");

        foreach ($permissions as $perm) {
            $checkPermStmt->execute([$perm['name'], $tenantId]);
            if (!$checkPermStmt->fetch()) {
                $insertPermStmt->execute([$perm['name'], $perm['description'], $perm['category'], $tenantId]);
            }
        }

        // Roles por defecto
        $roles = [
            ['name' => 'admin', 'description' => 'Administrador con acceso completo'],
            ['name' => 'editor', 'description' => 'Editor de contenido'],
            ['name' => 'viewer', 'description' => 'Solo lectura'],
        ];

        // Preparar statements para evitar duplicados
        $checkRoleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? AND tenant_id = ?");
        $insertRoleStmt = $pdo->prepare("
            INSERT INTO roles (name, description, tenant_id, is_system, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");

        foreach ($roles as $role) {
            $checkRoleStmt->execute([$role['name'], $tenantId]);
            if (!$checkRoleStmt->fetch()) {
                $insertRoleStmt->execute([$role['name'], $role['description'], $tenantId]);
            }
        }
    }

    /**
     * Verifica si es una petición AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Envía email de bienvenida al administrador del nuevo tenant
     */
    private function sendWelcomeEmail(
        string $email,
        string $name,
        string $password,
        string $domain,
        string $tenantName
    ): bool {
        try {
            $appName = getenv('APP_NAME') ?: 'MuseDock CMS';
            $loginUrl = "https://{$domain}/admin/login";

            $subject = "Bienvenido a {$tenantName} - Datos de acceso";

            $htmlBody = $this->getWelcomeEmailTemplate($name, $email, $password, $domain, $tenantName, $loginUrl);
            $textBody = $this->getWelcomeEmailTextTemplate($name, $email, $password, $domain, $tenantName, $loginUrl);

            $sent = Mailer::send($email, $subject, $htmlBody, $textBody);

            if ($sent) {
                Logger::log("[DomainManager] Email de bienvenida enviado a: {$email}", 'INFO');
            } else {
                Logger::log("[DomainManager] Error enviando email de bienvenida a: {$email}", 'WARNING');
            }

            return $sent;
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Excepción enviando email: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Genera el HTML del email de bienvenida
     */
    private function getWelcomeEmailTemplate(
        string $name,
        string $email,
        string $password,
        string $domain,
        string $tenantName,
        string $loginUrl
    ): string {
        $appName = getenv('APP_NAME') ?: 'MuseDock CMS';
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a {$tenantName}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; max-width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                Bienvenido a {$tenantName}
                            </h1>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
                                Hola <strong>{$name}</strong>,
                            </p>

                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
                                Tu cuenta de administrador ha sido creada exitosamente. A continuación encontrarás tus datos de acceso:
                            </p>

                            <!-- Datos de acceso -->
                            <div style="margin: 30px 0; padding: 24px; background-color: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                                <h3 style="margin: 0 0 16px; font-size: 16px; color: #374151;">
                                    <strong>Datos de acceso</strong>
                                </h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 100px;">Dominio:</td>
                                        <td style="padding: 8px 0; color: #111827; font-size: 14px; font-weight: 600;">{$domain}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Email:</td>
                                        <td style="padding: 8px 0; color: #111827; font-size: 14px; font-weight: 600;">{$email}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Contraseña:</td>
                                        <td style="padding: 8px 0; color: #111827; font-size: 14px; font-weight: 600; font-family: monospace; background-color: #fef3c7; padding: 4px 8px; border-radius: 4px;">{$password}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Botón de acceso -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{$loginUrl}"
                                           style="display: inline-block; padding: 16px 32px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);">
                                            Acceder al Panel de Administración
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- URL alternativa -->
                            <p style="margin: 20px 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                Si el botón no funciona, copia y pega esta URL en tu navegador:
                            </p>
                            <p style="margin: 0 0 20px; font-size: 13px; line-height: 1.6; color: #10b981; word-break: break-all; background-color: #f9fafb; padding: 12px; border-radius: 4px; border: 1px solid #e5e7eb;">
                                {$loginUrl}
                            </p>

                            <!-- Advertencia de seguridad -->
                            <div style="margin: 30px 0; padding: 16px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
                                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #92400e;">
                                    <strong>Importante:</strong> Por seguridad, te recomendamos cambiar tu contraseña después del primer inicio de sesión.
                                </p>
                            </div>

                            <p style="margin: 20px 0 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                Si tienes alguna pregunta, no dudes en contactarnos.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f9fafb; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                Este correo fue enviado por <strong>{$appName}</strong><br>
                                <a href="https://{$domain}" style="color: #10b981; text-decoration: none;">{$domain}</a>
                            </p>
                            <p style="margin: 15px 0 0; font-size: 12px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                © {$year} {$tenantName}. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el texto plano del email de bienvenida
     */
    private function getWelcomeEmailTextTemplate(
        string $name,
        string $email,
        string $password,
        string $domain,
        string $tenantName,
        string $loginUrl
    ): string {
        $year = date('Y');

        return <<<TEXT
Bienvenido a {$tenantName}

Hola {$name},

Tu cuenta de administrador ha sido creada exitosamente.

DATOS DE ACCESO
---------------
Dominio: {$domain}
Email: {$email}
Contraseña: {$password}

URL de acceso: {$loginUrl}

IMPORTANTE: Por seguridad, te recomendamos cambiar tu contraseña después del primer inicio de sesión.

Si tienes alguna pregunta, no dudes en contactarnos.

---
© {$year} {$tenantName}
TEXT;
    }
}
