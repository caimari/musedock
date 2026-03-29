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
use Screenart\Musedock\Services\TenantCreationService;
use CaddyDomainManager\Services\CaddyService;
use Screenart\Musedock\Models\Language;
use Screenart\Musedock\TenantPluginManager;
use Screenart\Musedock\Models\ThemeOption;
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
        $domainTypeFilter = $_GET['domain_type'] ?? '';
        $orderStatusFilter = $_GET['order_status'] ?? '';
        $hostingTypeFilter = $_GET['hosting_type'] ?? '';

        // Base domain para detectar subdominios
        $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');

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

        if ($domainTypeFilter === 'musedock') {
            $sql .= " AND domain LIKE ?";
            $params[] = '%.' . $baseDomain;
        } elseif ($domainTypeFilter === 'custom') {
            $sql .= " AND domain NOT LIKE ?";
            $params[] = '%.' . $baseDomain;
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

        // Cargar email del admin root de cada tenant
        $tenantAdminEmails = [];
        try {
            $adminStmt = $pdo->query("
                SELECT DISTINCT ON (tenant_id) tenant_id, email, name
                FROM admins
                WHERE tenant_id IS NOT NULL
                ORDER BY tenant_id, id ASC
            ");
            foreach ($adminStmt->fetchAll(PDO::FETCH_OBJ) as $row) {
                $tenantAdminEmails[$row->tenant_id] = $row;
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Cargar conteo de aliases por tenant
        $aliasCounts = [];
        try {
            $aliasStmt = $pdo->query("SELECT tenant_id, COUNT(*) as cnt FROM domain_aliases GROUP BY tenant_id");
            foreach ($aliasStmt->fetchAll(PDO::FETCH_OBJ) as $row) {
                $aliasCounts[$row->tenant_id] = (int) $row->cnt;
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Dominios registrados por customers (domain_orders)
        $domainOrders = [];
        try {
            $orderSql = "
                SELECT
                    dord.*,
                    c.email AS customer_email,
                    c.name AS customer_name,
                    t.domain AS tenant_domain
                FROM domain_orders dord
                LEFT JOIN customers c ON c.id = dord.customer_id
                LEFT JOIN tenants t ON t.id = dord.tenant_id
                WHERE 1=1
            ";
            $orderParams = [];

            if (!empty($orderStatusFilter)) {
                $orderSql .= " AND dord.status = ?";
                $orderParams[] = $orderStatusFilter;
            }

            if (!empty($hostingTypeFilter)) {
                $orderSql .= " AND dord.hosting_type = ?";
                $orderParams[] = $hostingTypeFilter;
            }

            if (!empty($search)) {
                $orderSql .= " AND (
                    LOWER(dord.domain) LIKE LOWER(?) OR
                    LOWER(dord.extension) LIKE LOWER(?) OR
                    LOWER(CONCAT(dord.domain, '.', dord.extension)) LIKE LOWER(?) OR
                    LOWER(COALESCE(c.email, '')) LIKE LOWER(?) OR
                    LOWER(COALESCE(c.name, '')) LIKE LOWER(?)
                )";
                $like = "%{$search}%";
                $orderParams[] = $like;
                $orderParams[] = $like;
                $orderParams[] = $like;
                $orderParams[] = $like;
                $orderParams[] = $like;
            }

            $orderSql .= " ORDER BY dord.created_at DESC";

            $stmt = $pdo->prepare($orderSql);
            $stmt->execute($orderParams);
            $domainOrders = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        } catch (\Throwable $e) {
            // Tabla no existe aún o error de compatibilidad - no romper la vista
            Logger::warning("[DomainManager] Could not load domain_orders: " . $e->getMessage());
            $domainOrders = [];
        }

        // Verificar disponibilidad de Caddy API
        $caddyApiAvailable = $this->caddyService->isApiAvailable();

        // Cargar todos los alias (standalone listing)
        $allAliases = [];
        try {
            $stmt = $pdo->query("
                SELECT da.*, t.name AS tenant_name, t.domain AS tenant_domain
                FROM domain_aliases da
                LEFT JOIN tenants t ON t.id = da.tenant_id
                ORDER BY da.created_at DESC
            ");
            $allAliases = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Cargar redirects
        $domainRedirects = [];
        try {
            $stmt = $pdo->query("SELECT * FROM domain_redirects ORDER BY created_at DESC");
            $domainRedirects = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        } catch (\Exception $e) {
            // Table may not exist
        }

        return View::renderSuperadmin('plugins.caddy-domain-manager.index', [
            'title' => 'Domain Manager',
            'tenants' => $tenants,
            'domainOrders' => $domainOrders,
            'caddyApiAvailable' => $caddyApiAvailable,
            'aliasCounts' => $aliasCounts,
            'tenantAdminEmails' => $tenantAdminEmails,
            'allAliases' => $allAliases,
            'domainRedirects' => $domainRedirects,
            'baseDomain' => $baseDomain,
            'filters' => [
                'caddy_status' => $caddyStatusFilter,
                'status' => $statusFilter,
                'search' => $search,
                'domain_type' => $domainTypeFilter,
                'order_status' => $orderStatusFilter,
                'hosting_type' => $hostingTypeFilter
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
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/domain-manager/create');
            exit;
        }

        // Recoger datos
        $name = trim($_POST['name'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $includeWww = isset($_POST['include_www']);
        $configureInCaddy = isset($_POST['configure_caddy']);
        $configureCloudflare = isset($_POST['configure_cloudflare']);
        $skipCloudflare = isset($_POST['skip_cloudflare']);
        $enableEmailRouting = isset($_POST['enable_email_routing']);
        $emailRoutingDestination = trim($_POST['email_routing_destination'] ?? '');
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

        // Detectar si es subdominio de musedock.com (o la base configurada)
        $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');
        $isMusedockSubdomain = str_ends_with($domain, '.' . $baseDomain);

        // Si es subdominio de musedock.com: forzar Cloudflare CNAME (no zona), sin skip
        if ($isMusedockSubdomain) {
            $configureCloudflare = true; // Siempre crear CNAME
            $skipCloudflare = false;
        }

        // Si skip_cloudflare está marcado, desactivar cloudflare
        if ($skipCloudflare) {
            $configureCloudflare = false;
        }

        // Verificar dominio único (en tenants, domain_aliases y domain_orders)
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ? OR domain = ?");
        $stmt->execute([$domain, 'www.' . $domain]);

        if ($stmt->fetch()) {
            flash('error', "El dominio '{$domain}' ya está registrado.");
            header('Location: /musedock/domain-manager/create');
            exit;
        }

        try {
            $stmtAlias = $pdo->prepare("SELECT id FROM domain_aliases WHERE domain = ? OR domain = ?");
            $stmtAlias->execute([$domain, 'www.' . $domain]);
            if ($stmtAlias->fetch()) {
                flash('error', "El dominio '{$domain}' ya está registrado como alias de otro tenant.");
                header('Location: /musedock/domain-manager/create');
                exit;
            }
        } catch (\Exception $e) {
            // domain_aliases table may not exist
        }

        try {
            $stmtOrder = $pdo->prepare("SELECT id FROM domain_orders WHERE (domain = ? OR domain = ?) AND status NOT IN ('cancelled','failed')");
            $stmtOrder->execute([$domain, 'www.' . $domain]);
            if ($stmtOrder->fetch()) {
                flash('error', "El dominio '{$domain}' tiene un pedido de registro en proceso.");
                header('Location: /musedock/domain-manager/create');
                exit;
            }
        } catch (\Exception $e) {
            // domain_orders table may not exist
        }

        try {
            // Generar slug
            $slug = $this->generateSlug($name);

            // Determinar estado inicial de Caddy
            $caddyStatus = $configureInCaddy ? 'pending_dns' : 'not_configured';
            $caddyRouteId = $configureInCaddy ? $this->caddyService->generateRouteId($domain) : null;

            // Usar TenantCreationService como única fuente de verdad
            $tenantService = new TenantCreationService($pdo);

            $result = $tenantService->createTenant(
                [
                    'name' => $name,
                    'domain' => $domain,
                    'admin_path' => 'admin',
                    'status' => 'active'
                ],
                [
                    'email' => $adminEmail,
                    'name' => $adminName,
                    'password' => $adminPassword
                ]
            );

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Error desconocido al crear tenant');
            }

            $tenantId = $result['tenant_id'];

            // Actualizar campos específicos de Caddy (el Service no maneja esto)
            $stmt = $pdo->prepare("
                UPDATE tenants
                SET slug = ?, include_www = ?, is_subdomain = ?, caddy_status = ?, caddy_route_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$slug, $includeWww ? 1 : 0, $isMusedockSubdomain ? 1 : 0, $caddyStatus, $caddyRouteId, $tenantId]);

            // Variables para mensajes
            $caddyMessage = '';
            $cloudflareMessage = '';
            $emailRoutingMessage = '';
            $cloudflareZoneId = null;
            $cloudflareNameservers = [];

            // Para subdominios de musedock.com: crear CNAME en vez de zona
            if ($isMusedockSubdomain) {
                try {
                    $cloudflareService = new \CaddyDomainManager\Services\CloudflareService();
                    $subdomain = str_replace('.' . $baseDomain, '', $domain);

                    Logger::log("[DomainManager] Creating CNAME for subdomain {$subdomain}.{$baseDomain}", 'INFO');
                    $cnameResult = $cloudflareService->createSubdomainRecord($subdomain, true);

                    // Guardar record_id en BD
                    $stmt = $pdo->prepare("
                        UPDATE tenants
                        SET cloudflare_record_id = ?, status = 'active'
                        WHERE id = ?
                    ");
                    $stmt->execute([$cnameResult['record_id'] ?? null, $tenantId]);

                    $cloudflareMessage = " CNAME creado automáticamente en Cloudflare.";
                    Logger::log("[DomainManager] CNAME created for {$domain}", 'INFO');
                } catch (\Exception $e) {
                    Logger::log("[DomainManager] Error creando CNAME para {$domain}: " . $e->getMessage(), 'ERROR');
                    $cloudflareMessage = " Error al crear CNAME en Cloudflare: " . $e->getMessage();
                }

                // Configurar Caddy directamente (no hay que esperar NS)
                if ($configureInCaddy) {
                    $caddyResult = $this->configureDomainInCaddy($tenantId, $domain, $includeWww);
                    if ($caddyResult['success']) {
                        $caddyMessage = " Dominio configurado en Caddy con SSL.";
                    } else {
                        $caddyMessage = " Error al configurar Caddy: " . ($caddyResult['error'] ?? 'desconocido');
                    }
                }

                // Skip al bloque de Cloudflare zona y Caddy condicional
                $configureCloudflare = false;
                $configureInCaddy = false;
            }

            // Configurar en Cloudflare si se solicitó (solo dominios custom, no subdominios)
            if ($configureCloudflare) {
                try {
                    $cloudflareService = new \CaddyDomainManager\Services\CloudflareZoneService();

                    // 1. Añadir zona a Cloudflare
                    Logger::log("[DomainManager] Adding domain {$domain} to Cloudflare", 'INFO');
                    $zoneResult = $cloudflareService->addFullZone($domain);
                    $cloudflareZoneId = $zoneResult['zone_id'];
                    $cloudflareNameservers = $zoneResult['nameservers'];

                    // 2. Crear CNAMEs @ y www → mortadelo.musedock.com
                    Logger::log("[DomainManager] Creating CNAMEs for {$domain}", 'INFO');
                    $cloudflareService->createProxiedCNAME($cloudflareZoneId, '@', 'mortadelo.musedock.com', true);

                    if ($includeWww) {
                        $cloudflareService->createProxiedCNAME($cloudflareZoneId, 'www', 'mortadelo.musedock.com', true);
                    }

                    // 3. Guardar zone_id en BD
                    $stmt = $pdo->prepare("
                        UPDATE tenants
                        SET cloudflare_zone_id = ?,
                            cloudflare_nameservers = ?,
                            cloudflare_proxied = 1,
                            status = 'waiting_ns_change'
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $cloudflareZoneId,
                        json_encode($cloudflareNameservers),
                        $tenantId
                    ]);

                    // 4. Configurar Email Routing si se solicitó
                    if ($enableEmailRouting) {
                        // Usar email personalizado o admin_email como fallback
                        $emailDestination = !empty($emailRoutingDestination) ? $emailRoutingDestination : $adminEmail;

                        Logger::log("[DomainManager] Enabling Email Routing for {$domain} → {$emailDestination}", 'INFO');
                        $emailResult = $cloudflareService->enableEmailRouting($cloudflareZoneId, $emailDestination);

                        if ($emailResult['enabled']) {
                            $stmt = $pdo->prepare("UPDATE tenants SET email_routing_enabled = 1 WHERE id = ?");
                            $stmt->execute([$tenantId]);
                            $emailRoutingMessage = " Email Routing activado (catch-all → {$emailDestination}).";
                        } else {
                            $emailRoutingMessage = " Email Routing no pudo activarse: " . ($emailResult['error'] ?? 'Error desconocido');
                        }
                    }

                    $nsString = implode(', ', $cloudflareNameservers);
                    $cloudflareMessage = " Dominio añadido a Cloudflare. Nameservers: {$nsString}.";

                    Logger::log("[DomainManager] Cloudflare configured successfully for {$domain}", 'INFO');

                } catch (\Exception $e) {
                    Logger::log("[DomainManager] Error configurando Cloudflare: " . $e->getMessage(), 'ERROR');
                    $cloudflareMessage = " Error al configurar Cloudflare: " . $e->getMessage();
                }
            }

            // Configurar en Caddy si se solicitó (y no está en Cloudflare pending NS)
            if ($configureInCaddy && !$configureCloudflare) {
                $caddyResult = $this->configureDomainInCaddy($tenantId, $domain, $includeWww);

                if ($caddyResult['success']) {
                    $caddyMessage = " Dominio configurado en Caddy.";
                } else {
                    $caddyMessage = " Error al configurar Caddy: " . $caddyResult['error'];
                }
            } elseif ($configureInCaddy && $configureCloudflare) {
                $caddyMessage = " (Caddy se configurará automáticamente cuando verifiques los NS)";
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

            // Mensaje final completo
            $finalMessage = "Tenant '{$name}' creado correctamente.{$cloudflareMessage}{$emailRoutingMessage}{$caddyMessage}{$emailMessage}";
            flash('success', $finalMessage);

            header('Location: /musedock/domain-manager');
            exit;

        } catch (\Exception $e) {
            // Solo hacer rollBack si hay una transacción activa
            // (TenantCreationService maneja su propia transacción)
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
        if (($tenant->caddy_route_id ?? null) && $caddyApiAvailable) {
            $caddyRouteInfo = $this->caddyService->getRoute($tenant->caddy_route_id);
        }

        // Fetch tenant site settings and active languages
        $tenantSettings = $this->getTenantSiteSettings($tenant->id);
        $activeLanguages = [];
        try {
            $activeLanguages = Language::getActiveLanguages($tenant->id);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error loading languages for tenant {$tenant->id}: " . $e->getMessage(), 'ERROR');
        }

        // Obtener grupos editoriales para dropdown
        $domainGroups = [];
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("SELECT id, name FROM domain_groups ORDER BY name ASC");
            $domainGroups = $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // Tabla puede no existir aún
        }

        // Obtener plugins compartidos disponibles y su estado para este tenant
        $sharedPlugins = TenantPluginManager::getAvailableSharedPlugins();
        foreach ($sharedPlugins as &$sp) {
            $sp['active'] = TenantPluginManager::isPluginActive($tenant->id, $sp['slug']);
        }
        unset($sp);

        // Cargar alias de dominio del tenant
        $domainAliases = [];
        try {
            $stmt = $pdo->prepare("SELECT * FROM domain_aliases WHERE tenant_id = ? ORDER BY created_at");
            $stmt->execute([$tenant->id]);
            $domainAliases = $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        // Cargar admin root del tenant (para perfil de autor)
        $tenantRootAdmin = null;
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, avatar, bio, social_twitter, social_linkedin, social_github, social_website, author_page_enabled, author_slug FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
            $stmt->execute([$tenant->id]);
            $tenantRootAdmin = $stmt->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // Columns may not exist yet
        }

        // Cargar blog_layout desde theme_options del tenant
        $tenantBlogLayout = 'grid';
        $tenantBlogHeaderTicker = false;
        try {
            $themeSlug = $tenant->theme ?? 'default';
            $themeOpts = ThemeOption::getOptions($themeSlug, $tenant->id);
            $tenantBlogLayout = $themeOpts['blog']['blog_layout'] ?? 'grid';
            $tenantBlogHeaderTicker = !empty($themeOpts['blog']['blog_header_ticker']);
            // Default new toggles to legacy value for backward compat
            $legacyDefault = $tenantBlogHeaderTicker ? '1' : '0';
            $tenantBlogTickerTags = $themeOpts['blog']['blog_ticker_tags'] ?? $legacyDefault;
            $tenantBlogTickerLatest = $themeOpts['blog']['blog_ticker_latest'] ?? $legacyDefault;
            $tenantBlogTickerTagsPosition = $themeOpts['blog']['blog_ticker_tags_position'] ?? 'top';
            $tenantBlogTickerLatestPosition = $themeOpts['blog']['blog_ticker_latest_position'] ?? 'top';
            $tenantBlogTopbarClock = !empty($themeOpts['blog']['blog_topbar_clock']);
            $tenantBlogTickerClock = !empty($themeOpts['blog']['blog_ticker_clock']);
            $tenantBlogHeaderClockLocale = $themeOpts['blog']['blog_header_clock_locale'] ?? 'es';
            $tenantBlogHeaderClockTimezone = $themeOpts['blog']['blog_header_clock_timezone'] ?? 'Europe/Madrid';
            $tenantBlogSidebarRelatedPosts = $themeOpts['blog']['blog_sidebar_related_posts'] ?? '1';
            $tenantBlogSidebarRelatedPostsCount = $themeOpts['blog']['blog_sidebar_related_posts_count'] ?? '4';
            $tenantBlogSidebarTags = $themeOpts['blog']['blog_sidebar_tags'] ?? '1';
            $tenantBlogSidebarCategories = $themeOpts['blog']['blog_sidebar_categories'] ?? '1';
            $tenantBlogShowBriefs = $themeOpts['blog']['blog_show_briefs'] ?? '0';
            $tenantBlogBriefsCount = $themeOpts['blog']['blog_briefs_count'] ?? '10';
            $tenantScrollToTopEnabled = $themeOpts['scroll_to_top']['scroll_to_top_enabled'] ?? '1';
        } catch (\Exception $e) {
            // Fallback silencioso
        }

        return View::renderSuperadmin('plugins.caddy-domain-manager.edit', [
            'title' => 'Editar Dominio: ' . $tenant->domain,
            'tenant' => $tenant,
            'caddyApiAvailable' => $caddyApiAvailable,
            'caddyRouteInfo' => $caddyRouteInfo,
            'tenantSettings' => $tenantSettings,
            'activeLanguages' => $activeLanguages,
            'domainGroups' => $domainGroups,
            'sharedPlugins' => $sharedPlugins,
            'domainAliases' => $domainAliases,
            'tenantBlogLayout' => $tenantBlogLayout,
            'tenantBlogHeaderTicker' => $tenantBlogHeaderTicker,
            'tenantBlogTickerTags' => !empty($tenantBlogTickerTags),
            'tenantBlogTickerLatest' => !empty($tenantBlogTickerLatest),
            'tenantBlogTickerTagsPosition' => $tenantBlogTickerTagsPosition ?? 'top',
            'tenantBlogTickerLatestPosition' => $tenantBlogTickerLatestPosition ?? 'top',
            'tenantBlogTopbarClock' => $tenantBlogTopbarClock ?? false,
            'tenantBlogTickerClock' => $tenantBlogTickerClock ?? false,
            'tenantBlogHeaderClockLocale' => $tenantBlogHeaderClockLocale ?? 'es',
            'tenantBlogHeaderClockTimezone' => $tenantBlogHeaderClockTimezone ?? 'Europe/Madrid',
            'tenantBlogSidebarRelatedPosts' => !empty($tenantBlogSidebarRelatedPosts),
            'tenantBlogSidebarRelatedPostsCount' => $tenantBlogSidebarRelatedPostsCount ?? '4',
            'tenantBlogSidebarTags' => !empty($tenantBlogSidebarTags),
            'tenantBlogSidebarCategories' => !empty($tenantBlogSidebarCategories),
            'tenantScrollToTopEnabled' => !empty($tenantScrollToTopEnabled ?? '1'),
            'tenantRootAdmin' => $tenantRootAdmin,
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
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
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
        $configureCloudflare = isset($_POST['configure_cloudflare']);
        $enableEmailRouting = isset($_POST['enable_email_routing']);
        $emailRoutingDestination = trim($_POST['email_routing_destination'] ?? '');

        // Guardar estado anterior para detectar cambios
        $oldIncludeWww = (bool)($tenant->include_www ?? false);

        if (empty($name)) {
            flash('error', 'El nombre es obligatorio.');
            header("Location: /musedock/domain-manager/{$id}/edit");
            exit;
        }

        try {
            $pdo = Database::connect();
            // Obtener group_id del formulario
            $groupId = $_POST['group_id'] ?? null;
            $groupId = ($groupId === '' || $groupId === null) ? null : (int) $groupId;

            $stmt = $pdo->prepare("
                UPDATE tenants
                SET name = ?, status = ?, include_www = ?, caddy_status = ?, group_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $status, $newIncludeWww ? 1 : 0, $caddyStatus, $groupId, $id]);

            $cloudflareMessage = '';
            $emailRoutingMessage = '';

            // Configurar Cloudflare si se solicitó (solo si NO está configurado previamente)
            if ($configureCloudflare && empty($tenant->cloudflare_zone_id)) {
                try {
                    $cloudflareService = new \CaddyDomainManager\Services\CloudflareZoneService();

                    // 1. Añadir zona a Cloudflare
                    Logger::log("[DomainManager] Adding domain {$tenant->domain} to Cloudflare from EDIT", 'INFO');
                    $zoneResult = $cloudflareService->addFullZone($tenant->domain);
                    $cloudflareZoneId = $zoneResult['zone_id'];
                    $cloudflareNameservers = $zoneResult['nameservers'];

                    // 2. Crear CNAMEs @ y www → mortadelo.musedock.com
                    Logger::log("[DomainManager] Creating CNAMEs for {$tenant->domain}", 'INFO');
                    $cloudflareService->createProxiedCNAME($cloudflareZoneId, '@', 'mortadelo.musedock.com', true);

                    if ($newIncludeWww) {
                        $cloudflareService->createProxiedCNAME($cloudflareZoneId, 'www', 'mortadelo.musedock.com', true);
                    }

                    // 3. Guardar zone_id en BD
                    $stmt = $pdo->prepare("
                        UPDATE tenants
                        SET cloudflare_zone_id = ?,
                            cloudflare_nameservers = ?,
                            cloudflare_proxied = 1,
                            status = 'waiting_ns_change'
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $cloudflareZoneId,
                        json_encode($cloudflareNameservers),
                        $id
                    ]);

                    // 4. Configurar Email Routing si se solicitó
                    if ($enableEmailRouting && !empty($emailRoutingDestination)) {
                        Logger::log("[DomainManager] Enabling Email Routing for {$tenant->domain} → {$emailRoutingDestination}", 'INFO');
                        $emailResult = $cloudflareService->enableEmailRouting($cloudflareZoneId, $emailRoutingDestination);

                        if ($emailResult['enabled']) {
                            $stmt = $pdo->prepare("UPDATE tenants SET email_routing_enabled = 1 WHERE id = ?");
                            $stmt->execute([$id]);
                            $emailRoutingMessage = " Email Routing activado (catch-all → {$emailRoutingDestination}).";
                        } else {
                            $emailRoutingMessage = " Email Routing no pudo activarse: " . ($emailResult['error'] ?? 'Error desconocido');
                        }
                    }

                    $nsString = implode(', ', $cloudflareNameservers);
                    $cloudflareMessage = " Dominio añadido a Cloudflare. Nameservers: {$nsString}.";

                    Logger::log("[DomainManager] Cloudflare configured successfully for {$tenant->domain}", 'INFO');

                } catch (\Exception $e) {
                    Logger::log("[DomainManager] Error configurando Cloudflare desde EDIT: " . $e->getMessage(), 'ERROR');
                    $cloudflareMessage = " Error al configurar Cloudflare: " . $e->getMessage();
                }
            }

            // Si cambió include_www Y está configurado en Caddy, reconfigurar
            $needsCaddyUpdate = ($oldIncludeWww !== $newIncludeWww) &&
                               ($tenant->caddy_route_id ?? null) &&
                               in_array($tenant->caddy_status ?? 'not_configured', ['active', 'error']);

            if ($needsCaddyUpdate && $this->caddyService->isApiAvailable()) {
                // Reconfigurar con nuevo valor de www (sin downtime: upsert en lugar de delete+create)
                $result = $this->configureDomainInCaddy($id, $tenant->domain, $newIncludeWww);

                if ($result['success']) {
                    $finalMessage = "Tenant actualizado y reconfigurado en Caddy con " . ($newIncludeWww ? 'www incluido' : 'sin www') . ".{$cloudflareMessage}{$emailRoutingMessage}";
                    flash('success', $finalMessage);
                } else {
                    flash('warning', "Tenant actualizado, pero error al reconfigurar Caddy: " . $result['error']);
                }
            } else {
                $finalMessage = 'Tenant actualizado correctamente.' . $cloudflareMessage . $emailRoutingMessage;
                flash('success', $finalMessage);
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

        // Obtener opción de eliminar de Cloudflare (por defecto true)
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $deleteFromCloudflare = $input['deleteFromCloudflare'] ?? ($_POST['deleteFromCloudflare'] ?? true);

        $caddyWarning = null;
        $cloudflareWarning = null;

        try {
            // 1. Intentar eliminar de Cloudflare (solo si el usuario lo solicitó)
            if ($deleteFromCloudflare) {
                // A) Dominios personalizados: eliminar la zona completa
                if (!($tenant->is_subdomain ?? false) && ($tenant->cloudflare_zone_id ?? null)) {
                    try {
                        $cloudflareZoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
                        $cloudflareZoneService->deleteZone($tenant->cloudflare_zone_id);
                        Logger::log("[DomainManager] Zona de Cloudflare eliminada: {$tenant->cloudflare_zone_id} ({$tenant->domain})", 'INFO');
                    } catch (\Exception $e) {
                        $errorMsg = $e->getMessage();
                        // Caso especial: dominio registrado en Cloudflare Registrar
                        if (strpos($errorMsg, 'Cloudflare Registrar') !== false) {
                            $cloudflareWarning = "La zona permanece en Cloudflare porque el dominio está registrado con Cloudflare Registrar. " .
                                               "Para eliminarlo completamente, transfiere el dominio a otro registrador primero.";
                            Logger::log("[DomainManager] Dominio {$tenant->domain} usa Cloudflare Registrar - zona no eliminada", 'WARNING');
                        } else {
                            $cloudflareWarning = "No se pudo eliminar la zona de Cloudflare: " . $errorMsg;
                            Logger::log("[DomainManager] Warning eliminando zona de Cloudflare: " . $errorMsg, 'WARNING');
                        }
                    }
                }
                // B) Subdominios FREE: eliminar solo el registro DNS
                elseif (($tenant->is_subdomain ?? false) && ($tenant->cloudflare_record_id ?? null)) {
                    $cloudflareService = new \CaddyDomainManager\Services\CloudflareService();
                    $cloudflareResult = $cloudflareService->deleteRecord($tenant->cloudflare_record_id);

                    if (!$cloudflareResult['success']) {
                        $cloudflareWarning = "El registro DNS de Cloudflare podría seguir activo. Error: " . ($cloudflareResult['error'] ?? 'desconocido');
                        Logger::log("[DomainManager] Warning eliminando de Cloudflare: " . $cloudflareWarning, 'WARNING');
                    } else {
                        Logger::log("[DomainManager] Registro DNS eliminado de Cloudflare: {$tenant->cloudflare_record_id}", 'INFO');
                    }
                }
            } else {
                Logger::log("[DomainManager] Usuario eligió NO eliminar de Cloudflare: {$tenant->domain}", 'INFO');
            }

            // 2. Intentar eliminar de Caddy si existe configuración
            if ($tenant->caddy_route_id ?? null) {
                $caddyResult = $this->caddyService->removeDomain($tenant->caddy_route_id);
                if (!$caddyResult['success']) {
                    // Guardar warning pero continuar con eliminación de BD
                    $caddyWarning = "La ruta '{$tenant->caddy_route_id}' podría seguir en Caddy. Error: " . $caddyResult['error'];
                    Logger::log("[DomainManager] Warning eliminando de Caddy: " . $caddyWarning, 'WARNING');
                } else {
                    Logger::log("[DomainManager] Ruta eliminada de Caddy: {$tenant->caddy_route_id}", 'INFO');
                }
            }

            // 2b. Limpiar Cloudflare de aliases del tenant antes del DELETE CASCADE
            try {
                $aliasPdo = Database::connect();
                $aliasStmt = $aliasPdo->prepare("SELECT * FROM domain_aliases WHERE tenant_id = ?");
                $aliasStmt->execute([$id]);
                foreach ($aliasStmt->fetchAll(\PDO::FETCH_OBJ) as $alias) {
                    try {
                        $this->removeAliasCloudflare($alias);
                        Logger::log("[DomainManager] Alias Cloudflare limpiado: {$alias->domain}", 'INFO');
                    } catch (\Exception $e) {
                        Logger::log("[DomainManager] Warning limpiando alias Cloudflare {$alias->domain}: " . $e->getMessage(), 'WARNING');
                    }
                }
            } catch (\Exception $e) {
                // domain_aliases table may not exist
                Logger::log("[DomainManager] No se pudieron limpiar aliases: " . $e->getMessage(), 'WARNING');
            }

            // 3. Eliminar tenant y datos relacionados de la BD
            $pdo = Database::connect();
            $pdo->beginTransaction();

            try {
                // Eliminar en orden de dependencias
                $pdo->prepare("DELETE FROM domain_aliases WHERE tenant_id = ?")->execute([$id]);
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

            // Preparar mensaje según resultado de Cloudflare y Caddy
            $message = "Tenant '{$tenant->name}' eliminado correctamente.";
            $warnings = [];

            if ($cloudflareWarning) {
                $warnings[] = $cloudflareWarning;
            }
            if ($caddyWarning) {
                $warnings[] = $caddyWarning;
            }

            if (!empty($warnings)) {
                $message .= " Advertencias: " . implode(' | ', $warnings);
            }

            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'cloudflare_warning' => $cloudflareWarning,
                    'caddy_warning' => $caddyWarning
                ]);
                exit;
            }

            if (!empty($warnings)) {
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

        error_log("[DomainManager] reconfigure() called for tenant ID: {$id}");

        $tenant = $this->getTenant($id);

        if (!$tenant) {
            error_log("[DomainManager] Tenant not found: {$id}");
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Tenant no encontrado']);
                exit;
            }
            flash('error', 'Tenant no encontrado.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        error_log("[DomainManager] Tenant found: {$tenant->domain}, caddy_status: " . ($tenant->caddy_status ?? 'null'));

        // Validar que tenga sentido reconfigurar
        $caddyStatus = $tenant->caddy_status ?? 'not_configured';

        // Si está en estado 'configuring', no permitir otra configuración
        // (Quitada la verificación de 'already_active' para permitir forzar reconfiguración)

        // Si está en estado 'configuring', no permitir otra configuración
        if ($caddyStatus === 'configuring') {
            error_log("[DomainManager] Domain is currently configuring, rejecting");
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'El dominio está siendo configurado actualmente. Por favor espere.',
                    'status' => 'in_progress'
                ]);
                exit;
            }
            flash('warning', 'El dominio está siendo configurado actualmente. Por favor espere.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        // Proceder con la reconfiguración
        error_log("[DomainManager] Calling configureDomainInCaddy for: {$tenant->domain}");
        $result = $this->configureDomainInCaddy($id, $tenant->domain, (bool)($tenant->include_www ?? true));
        error_log("[DomainManager] configureDomainInCaddy result: " . json_encode($result));

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
        if ($tenant->caddy_route_id ?? null) {
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

        // Cargar aliases activos para incluirlos en la ruta de Caddy
        $aliases = [];
        try {
            $aliasStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = ? AND status IN ('pending','active')");
            $aliasStmt->execute([$tenantId]);
            $aliases = $aliasStmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        // Intentar crear/actualizar en Caddy (sin downtime) — incluye aliases
        $result = empty($aliases)
            ? $this->caddyService->upsertDomain($domain, $includeWww)
            : $this->caddyService->upsertDomainWithAliases($domain, $includeWww, $aliases);

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
     * Regenerar permisos del tenant (AJAX)
     *
     * Regenera los permisos del rol Admin del tenant según la configuración
     * de tenant_default_settings. NO afecta contraseñas ni usuarios.
     */
    public function regeneratePermissions($id)
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

        try {
            $tenantService = new TenantCreationService();
            $result = $tenantService->regeneratePermissions($tenant->id);

            if ($result['success']) {
                Logger::log("[DomainManager] Permisos regenerados para tenant {$tenant->id}: {$result['permissions_count']} permisos", 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => "Se han regenerado {$result['permissions_count']} permisos correctamente.",
                    'permissions_count' => $result['permissions_count']
                ]);
            } else {
                Logger::log("[DomainManager] Error regenerando permisos tenant {$tenant->id}: " . $result['error'], 'ERROR');
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al regenerar permisos: ' . $result['error']
                ]);
            }
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Excepción regenerando permisos: " . $e->getMessage(), 'ERROR');
            echo json_encode([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ]);
        }

        exit;
    }

    /**
     * Regenerar menús del tenant (AJAX)
     *
     * Elimina todos los menús del tenant y los recrea copiando
     * desde admin_menus según la configuración actual.
     */
    public function regenerateMenus($id)
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

        try {
            $tenantService = new TenantCreationService();
            $result = $tenantService->regenerateMenus($tenant->id);

            if ($result['success']) {
                Logger::log("[DomainManager] Menús regenerados para tenant {$tenant->id}: {$result['menus_count']} items", 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => "Se han regenerado {$result['menus_count']} items de menú correctamente.",
                    'menus_count' => $result['menus_count']
                ]);
            } else {
                Logger::log("[DomainManager] Error regenerando menús tenant {$tenant->id}: " . $result['error'], 'ERROR');
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al regenerar menús: ' . $result['error']
                ]);
            }
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Excepción regenerando menús: " . $e->getMessage(), 'ERROR');
            echo json_encode([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ]);
        }

        exit;
    }

    /**
     * Regenerar módulos del tenant desde defaults
     */
    public function regenerateModules($id)
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

        try {
            $tenantService = new TenantCreationService();
            $result = $tenantService->regenerateModules($tenant->id);

            if ($result['success']) {
                Logger::log("[DomainManager] Módulos regenerados para tenant {$tenant->id}: {$result['enabled_count']} activos de {$result['total_count']} total", 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => "Se han configurado {$result['enabled_count']} módulos activos de {$result['total_count']} disponibles.",
                    'enabled_count' => $result['enabled_count'],
                    'total_count' => $result['total_count']
                ]);
            } else {
                Logger::log("[DomainManager] Error regenerando módulos tenant {$tenant->id}: " . $result['error'], 'ERROR');
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al regenerar módulos: ' . $result['error']
                ]);
            }
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Excepción regenerando módulos: " . $e->getMessage(), 'ERROR');
            echo json_encode([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ]);
        }

        exit;
    }

    /**
     * Regenerar idiomas del tenant (AJAX)
     *
     * Elimina todos los idiomas del tenant y los recrea con los
     * idiomas por defecto (Español e Inglés).
     */
    public function regenerateLanguages($id)
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

        try {
            $tenantService = new TenantCreationService();
            $result = $tenantService->regenerateLanguages($tenant->id);

            if ($result['success']) {
                Logger::log("[DomainManager] Idiomas regenerados para tenant {$tenant->id}: {$result['languages_count']} idiomas", 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => "Se han regenerado {$result['languages_count']} idiomas correctamente.",
                    'languages_count' => $result['languages_count']
                ]);
            } else {
                Logger::log("[DomainManager] Error regenerando idiomas tenant {$tenant->id}: " . $result['error'], 'ERROR');
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al regenerar idiomas: ' . $result['error']
                ]);
            }
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Excepción regenerando idiomas: " . $e->getMessage(), 'ERROR');
            echo json_encode([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ]);
        }

        exit;
    }

    /**
     * Toggle plugin compartido para un tenant (AJAX)
     *
     * Activa o desactiva un plugin de plugins/tenant-shared/ para un tenant.
     */
    public function toggleTenantPlugin($id)
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

        // Leer datos del body JSON
        $input = json_decode(file_get_contents('php://input'), true);

        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
            exit;
        }

        $slug = $input['slug'] ?? '';
        $active = (bool) ($input['active'] ?? false);

        if (empty($slug)) {
            echo json_encode(['success' => false, 'message' => 'Slug del plugin requerido']);
            exit;
        }

        // Verificar que el plugin existe en tenant-shared
        $sharedPath = TenantPluginManager::getSharedPluginsPath() . '/' . $slug;
        if (!is_dir($sharedPath)) {
            echo json_encode(['success' => false, 'message' => 'Plugin no encontrado en plugins compartidos']);
            exit;
        }

        try {
            if ($active) {
                $result = TenantPluginManager::activateSharedPlugin($tenant->id, $slug);
                $message = "Plugin {$slug} activado para {$tenant->domain}";
            } else {
                $result = TenantPluginManager::deactivateSharedPlugin($tenant->id, $slug);
                $message = "Plugin {$slug} desactivado para {$tenant->domain}";
            }

            if ($result) {
                Logger::log("[DomainManager] {$message}", 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'active' => $active
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al cambiar estado del plugin'
                ]);
            }
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error toggle plugin: " . $e->getMessage(), 'ERROR');
            echo json_encode([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ]);
        }

        exit;
    }

    // ============================================
    // DOMAIN ALIASES MANAGEMENT
    // ============================================

    /**
     * Lista los alias de dominio de un tenant (AJAX)
     */
    public function getAliases($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM domain_aliases WHERE tenant_id = ? ORDER BY created_at ASC");
        $stmt->execute([$id]);
        $aliases = $stmt->fetchAll(PDO::FETCH_OBJ);

        echo json_encode(['success' => true, 'aliases' => $aliases]);
        exit;
    }

    /**
     * Añade un alias de dominio a un tenant (AJAX)
     */
    public function addAlias($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $aliasDomain = trim(strtolower($input['domain'] ?? ''));
        $includeWww = (bool) ($input['include_www'] ?? false);
        $skipCloudflare = (bool) ($input['skip_cloudflare'] ?? false);

        // Validar formato de dominio
        if (empty($aliasDomain)) {
            echo json_encode(['success' => false, 'message' => 'Dominio requerido.']);
            exit;
        }

        // Quitar www si lo tiene
        $aliasDomain = preg_replace('/^www\./', '', $aliasDomain);

        // Validar formato básico
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/', $aliasDomain)) {
            echo json_encode(['success' => false, 'message' => 'Formato de dominio inválido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        // Verificar que no sea el mismo dominio principal
        if ($aliasDomain === $tenant->domain) {
            echo json_encode(['success' => false, 'message' => 'El alias no puede ser igual al dominio principal.']);
            exit;
        }

        $pdo = Database::connect();

        // Verificar unicidad en tenants.domain
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ? OR domain = ?");
        $stmt->execute([$aliasDomain, 'www.' . $aliasDomain]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Este dominio ya está registrado como dominio principal de otro tenant.']);
            exit;
        }

        // Verificar unicidad en domain_aliases (incluir variante www)
        $stmt = $pdo->prepare("SELECT id FROM domain_aliases WHERE domain = ? OR domain = ?");
        $stmt->execute([$aliasDomain, 'www.' . $aliasDomain]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Este dominio ya está registrado como alias.']);
            exit;
        }

        // Verificar unicidad en domain_orders (pedidos pendientes/activos)
        try {
            $stmt = $pdo->prepare("SELECT id FROM domain_orders WHERE (domain = ? OR domain = ?) AND status NOT IN ('cancelled','failed')");
            $stmt->execute([$aliasDomain, 'www.' . $aliasDomain]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este dominio tiene un pedido de registro en proceso.']);
                exit;
            }
        } catch (\Exception $e) {
            // domain_orders table may not exist
        }

        // Determinar si es subdominio de la plataforma
        $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');
        $isSubdomain = str_ends_with($aliasDomain, '.' . $baseDomain);

        try {
            $pdo->beginTransaction();

            // Insertar alias
            $stmt = $pdo->prepare("
                INSERT INTO domain_aliases (tenant_id, domain, include_www, is_subdomain, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$id, $aliasDomain, $includeWww ? 1 : 0, $isSubdomain ? 1 : 0]);
            $aliasId = $pdo->lastInsertId();

            // Configurar Cloudflare DNS (a menos que el usuario lo omita)
            $cloudflareInfo = [];
            if (!$skipCloudflare) {
                $cloudflareInfo = $this->configureAliasCloudflare($aliasDomain, $isSubdomain, $includeWww);

                // Actualizar alias con info de Cloudflare
                if (!empty($cloudflareInfo)) {
                    $stmt = $pdo->prepare("
                        UPDATE domain_aliases
                        SET cloudflare_zone_id = ?,
                            cloudflare_record_id = ?,
                            cloudflare_nameservers = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $cloudflareInfo['zone_id'] ?? null,
                        $cloudflareInfo['record_id'] ?? null,
                        isset($cloudflareInfo['nameservers']) ? json_encode($cloudflareInfo['nameservers']) : null,
                        $aliasId
                    ]);
                }
            }

            // Reconstruir ruta Caddy con TODOS los aliases
            $aliasStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = ? AND status IN ('pending','active')");
            $aliasStmt->execute([$id]);
            $allAliases = $aliasStmt->fetchAll(PDO::FETCH_OBJ);

            $caddyResult = $this->caddyService->upsertDomainWithAliases(
                $tenant->domain,
                (bool) ($tenant->include_www ?? true),
                $allAliases
            );

            if ($caddyResult['success']) {
                $stmt = $pdo->prepare("UPDATE domain_aliases SET caddy_configured = 1, status = 'active' WHERE id = ?");
                $stmt->execute([$aliasId]);
            } else {
                $stmt = $pdo->prepare("UPDATE domain_aliases SET error_log = ?, status = 'error' WHERE id = ?");
                $stmt->execute([$caddyResult['error'], $aliasId]);
            }

            $pdo->commit();

            $responseData = [
                'success' => true,
                'message' => "Alias {$aliasDomain} añadido correctamente.",
                'alias_id' => $aliasId,
                'caddy_success' => $caddyResult['success'],
            ];

            if ($skipCloudflare) {
                $responseData['dns_info'] = 'Cloudflare omitido. Asegúrate de que el dominio apunte a este servidor.';
            } elseif ($isSubdomain) {
                $responseData['dns_info'] = 'CNAME creado automáticamente.';
            } elseif (!empty($cloudflareInfo['nameservers'])) {
                $responseData['nameservers'] = $cloudflareInfo['nameservers'];
                $responseData['dns_info'] = 'Zona creada en Cloudflare. Cambia los nameservers del dominio.';
            }

            Logger::log("[DomainManager] Alias añadido: {$aliasDomain} → tenant {$tenant->domain}", 'INFO');

            echo json_encode($responseData);
            exit;

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::log("[DomainManager] Error añadiendo alias: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error al añadir alias: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Elimina un alias de dominio de un tenant (AJAX)
     */
    public function removeAlias($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $aliasId = (int) ($input['alias_id'] ?? 0);
        $deleteFromCloudflare = (bool) ($input['deleteFromCloudflare'] ?? false);

        if (!$aliasId) {
            echo json_encode(['success' => false, 'message' => 'ID de alias no válido.']);
            exit;
        }

        $pdo = Database::connect();

        // Verificar que el alias pertenece a este tenant
        $stmt = $pdo->prepare("SELECT * FROM domain_aliases WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$aliasId, $id]);
        $alias = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$alias) {
            echo json_encode(['success' => false, 'message' => 'Alias no encontrado.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            // 1. Limpiar Cloudflare solo si el usuario lo pidió
            $cfAction = 'omitido';
            if ($deleteFromCloudflare) {
                $this->removeAliasCloudflare($alias);
                $cfAction = $alias->is_subdomain ? 'registro CNAME eliminado' : 'zona eliminada';
            }

            // 2. Eliminar alias de DB
            $stmt = $pdo->prepare("DELETE FROM domain_aliases WHERE id = ?");
            $stmt->execute([$aliasId]);

            // 3. Reconstruir ruta Caddy sin el alias eliminado
            $remainingStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = ? AND status IN ('pending','active')");
            $remainingStmt->execute([$id]);
            $remainingAliases = $remainingStmt->fetchAll(PDO::FETCH_OBJ);

            $this->caddyService->upsertDomainWithAliases(
                $tenant->domain,
                (bool) ($tenant->include_www ?? true),
                $remainingAliases
            );

            Logger::log("[DomainManager] Alias eliminado: {$alias->domain} de tenant {$tenant->domain} (CF: {$cfAction})", 'INFO');

            echo json_encode([
                'success' => true,
                'message' => "Alias {$alias->domain} eliminado correctamente.",
                'cloudflare' => $cfAction,
            ]);
            exit;

        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error eliminando alias: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error al eliminar alias: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Configura DNS en Cloudflare para un alias.
     * Subdominios: CNAME automático; Custom: zona nueva.
     */
    private function configureAliasCloudflare(string $domain, bool $isSubdomain, bool $includeWww): array
    {
        try {
            if ($isSubdomain) {
                $cloudflareService = new \CaddyDomainManager\Services\CloudflareService();
                $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');
                $subdomain = str_replace('.' . $baseDomain, '', $domain);
                $result = $cloudflareService->createSubdomainRecord($subdomain, true);
                return [
                    'record_id' => $result['record_id'] ?? null,
                    'zone_id' => null,
                    'nameservers' => null,
                ];
            } else {
                $cloudflareZoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
                $zoneResult = $cloudflareZoneService->addFullZone($domain);
                $zoneId = $zoneResult['zone_id'];

                $cloudflareZoneService->createProxiedCNAME($zoneId, '@', 'mortadelo.musedock.com', true);
                if ($includeWww) {
                    $cloudflareZoneService->createProxiedCNAME($zoneId, 'www', 'mortadelo.musedock.com', true);
                }

                return [
                    'zone_id' => $zoneId,
                    'record_id' => null,
                    'nameservers' => $zoneResult['nameservers'] ?? [],
                ];
            }
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error Cloudflare alias {$domain}: " . $e->getMessage(), 'WARNING');
            return [];
        }
    }

    /**
     * Limpia la configuración de Cloudflare de un alias.
     */
    private function removeAliasCloudflare(object $alias): void
    {
        try {
            if ($alias->is_subdomain && $alias->cloudflare_record_id) {
                $cloudflareService = new \CaddyDomainManager\Services\CloudflareService();
                $cloudflareService->deleteRecord($alias->cloudflare_record_id);
            } elseif (!$alias->is_subdomain && $alias->cloudflare_zone_id) {
                $cloudflareZoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
                $cloudflareZoneService->deleteZone($alias->cloudflare_zone_id);
            }
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Warning limpiando Cloudflare alias {$alias->domain}: " . $e->getMessage(), 'WARNING');
        }
    }

    // ============================================
    // SITE SETTINGS MANAGEMENT
    // ============================================

    private const BRANDING_DIR = 'branding';

    /**
     * Guarda los ajustes del sitio de un tenant (AJAX)
     */
    public function updateSiteSettings($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        // Validar CSRF
        $csrfToken = $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            $pdo = Database::connect();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $pdo->beginTransaction();

            $settingsKeys = [
                'site_name', 'site_subtitle', 'site_description', 'admin_email',
                'contact_phone', 'contact_email', 'contact_address', 'contact_whatsapp',
                'social_facebook', 'social_twitter', 'social_instagram',
                'social_linkedin', 'social_youtube', 'social_tiktok',
                'footer_copyright',
                'default_lang', 'force_lang',
            ];

            // Campos traducibles del footer
            $activeLanguages = Language::getActiveLanguages($tenant->id);
            foreach ($activeLanguages as $lang) {
                $settingsKeys[] = 'footer_short_description_' . $lang->code;
            }

            foreach ($settingsKeys as $key) {
                $value = $_POST[$key] ?? null;
                if ($value !== null) {
                    $this->saveTenantSiteSetting($pdo, $tenant->id, $key, $value, $driver);
                }
            }

            // Checkboxes
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'show_logo', isset($_POST['show_logo']) ? '1' : '0', $driver);
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'show_title', isset($_POST['show_title']) ? '1' : '0', $driver);
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'show_subtitle', isset($_POST['show_subtitle']) ? '1' : '0', $driver);
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'show_language_switcher', isset($_POST['show_language_switcher']) ? '1' : '0', $driver);

            // Logo upload
            if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                $url = $this->storeBrandingUploadFile($_FILES['site_logo'], "tenant-{$tenant->id}", 'logo');
                if ($url) {
                    $currentSettings = $this->getTenantSiteSettings($tenant->id);
                    $this->deleteBrandingFileFromPath($currentSettings['site_logo'] ?? '');
                    $this->saveTenantSiteSetting($pdo, $tenant->id, 'site_logo', $url, $driver);
                }
            }

            // Favicon upload
            if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
                $url = $this->storeBrandingUploadFile($_FILES['site_favicon'], "tenant-{$tenant->id}", 'favicon');
                if ($url) {
                    $currentSettings = $this->getTenantSiteSettings($tenant->id);
                    $this->deleteBrandingFileFromPath($currentSettings['site_favicon'] ?? '');
                    $this->saveTenantSiteSetting($pdo, $tenant->id, 'site_favicon', $url, $driver);
                }
            }

            $pdo->commit();

            Logger::log("[DomainManager] Site settings updated for tenant {$tenant->id} ({$tenant->domain})", 'INFO');
            echo json_encode(['success' => true, 'message' => 'Ajustes del sitio guardados correctamente.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::log("[DomainManager] Error saving site settings for tenant {$tenant->id}: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error al guardar los ajustes: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Elimina el logo de un tenant (AJAX)
     */
    public function deleteTenantLogo($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $csrfToken = $input['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            $settings = $this->getTenantSiteSettings($tenant->id);
            $this->deleteBrandingFileFromPath($settings['site_logo'] ?? '');

            $pdo = Database::connect();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'site_logo', '', $driver);

            Logger::log("[DomainManager] Logo deleted for tenant {$tenant->id}", 'INFO');
            echo json_encode(['success' => true, 'message' => 'Logo eliminado correctamente.']);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error deleting logo for tenant {$tenant->id}: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el logo.']);
        }
        exit;
    }

    /**
     * Elimina el favicon de un tenant (AJAX)
     */
    public function deleteTenantFavicon($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $csrfToken = $input['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            $settings = $this->getTenantSiteSettings($tenant->id);
            $this->deleteBrandingFileFromPath($settings['site_favicon'] ?? '');

            $pdo = Database::connect();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'site_favicon', '', $driver);

            Logger::log("[DomainManager] Favicon deleted for tenant {$tenant->id}", 'INFO');
            echo json_encode(['success' => true, 'message' => 'Favicon eliminado correctamente.']);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error deleting favicon for tenant {$tenant->id}: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el favicon.']);
        }
        exit;
    }

    /**
     * Obtiene todos los settings de un tenant
     */
    private function getTenantSiteSettings(int $tenantId): array
    {
        try {
            $pdo = Database::connect();
            $keyCol = Database::qi('key');
            $stmt = $pdo->prepare("SELECT {$keyCol}, value FROM tenant_settings WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error loading tenant settings for tenant {$tenantId}: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Guarda un setting del tenant (upsert)
     */
    private function saveTenantSiteSetting(PDO $pdo, int $tenantId, string $key, $value, string $driver): bool
    {
        $keyCol = Database::qi('key');

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT INTO tenant_settings (tenant_id, {$keyCol}, value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO tenant_settings (tenant_id, {$keyCol}, value)
                VALUES (?, ?, ?)
                ON CONFLICT (tenant_id, {$keyCol}) DO UPDATE SET value = EXCLUDED.value
            ");
        }

        return $stmt->execute([$tenantId, $key, $value]);
    }

    /**
     * Almacena un archivo de branding (logo/favicon)
     */
    private function storeBrandingUploadFile(array $file, string $scope, string $kind): ?string
    {
        $allowedTypes = $kind === 'favicon'
            ? ['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon']
            : ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        if (!in_array(($file['type'] ?? ''), $allowedTypes, true)) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = $kind === 'favicon' ? 'ico' : 'png';
        }

        $safeExt = preg_replace('/[^a-z0-9]+/', '', $ext);
        if ($safeExt === '') {
            $safeExt = $kind === 'favicon' ? 'ico' : 'png';
        }

        $filename = "{$kind}-" . time() . '-' . bin2hex(random_bytes(4)) . ".{$safeExt}";
        $relative = self::BRANDING_DIR . '/' . $scope . '/' . $filename;

        $destDir = APP_ROOT . '/storage/app/media/' . self::BRANDING_DIR . '/' . $scope;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $destPath = APP_ROOT . '/storage/app/media/' . $relative;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return null;
        }

        return '/media/file/' . $relative;
    }

    /**
     * Elimina un archivo de branding del disco
     */
    private function deleteBrandingFileFromPath(?string $storedPath): void
    {
        $storedPath = trim((string)$storedPath);
        if ($storedPath === '' || $storedPath === '0') {
            return;
        }

        if (str_starts_with($storedPath, '/media/file/')) {
            $relative = ltrim(substr($storedPath, strlen('/media/file/')), '/');
            $fullPath = APP_ROOT . '/storage/app/media/' . $relative;

            $real = realpath($fullPath);
            $root = realpath(APP_ROOT . '/storage/app/media');
            if ($real && $root && str_starts_with($real, $root) && is_file($real)) {
                @unlink($real);
            }
            return;
        }

        if (str_starts_with($storedPath, 'uploads/') || str_starts_with($storedPath, '/uploads/')) {
            $legacyPath = public_path(ltrim($storedPath, '/'));
            if (is_file($legacyPath)) {
                @unlink($legacyPath);
            }
            return;
        }

        if (str_starts_with($storedPath, 'assets/') || str_starts_with($storedPath, '/assets/')) {
            $legacyPath = public_path(ltrim($storedPath, '/'));
            if (is_file($legacyPath)) {
                @unlink($legacyPath);
            }
        }
    }

    /**
     * Guarda los ajustes SEO y Social de un tenant (AJAX)
     */
    public function updateSeoSettings($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $csrfToken = $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            $pdo = Database::connect();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $pdo->beginTransaction();

            $seoKeys = [
                'site_keywords', 'site_author', 'twitter_site',
                'social_facebook', 'social_twitter', 'social_instagram',
                'social_linkedin', 'social_youtube', 'social_pinterest',
            ];

            foreach ($seoKeys as $key) {
                $value = $_POST[$key] ?? '';
                $this->saveTenantSiteSetting($pdo, $tenant->id, $key, $value, $driver);
            }

            // OG image upload
            if (isset($_FILES['og_image']) && $_FILES['og_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['og_image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                if (!in_array($file['type'], $allowedTypes)) {
                    throw new \Exception('Tipo de archivo no permitido para la imagen OG.');
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'tenant_' . $tenant->id . '_og_' . time() . '.' . $ext;
                $uploadDir = APP_ROOT . '/public/uploads/tenants/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    // Eliminar imagen OG anterior
                    $currentSettings = $this->getTenantSiteSettings($tenant->id);
                    $currentOg = $currentSettings['og_image'] ?? '';
                    if ($currentOg && is_file(APP_ROOT . '/public/' . $currentOg)) {
                        @unlink(APP_ROOT . '/public/' . $currentOg);
                    }

                    $this->saveTenantSiteSetting($pdo, $tenant->id, 'og_image', 'uploads/tenants/' . $filename, $driver);
                }
            }

            $pdo->commit();

            Logger::log("[DomainManager] SEO settings updated for tenant {$tenant->id} ({$tenant->domain})", 'INFO');
            echo json_encode(['success' => true, 'message' => 'Ajustes SEO guardados correctamente.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::log("[DomainManager] Error saving SEO settings for tenant {$tenant->id}: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error al guardar los ajustes SEO: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Elimina la imagen OG de un tenant (AJAX)
     */
    public function deleteTenantOgImage($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $csrfToken = $input['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            $settings = $this->getTenantSiteSettings($tenant->id);
            $currentOg = $settings['og_image'] ?? '';
            if ($currentOg && is_file(APP_ROOT . '/public/' . $currentOg)) {
                @unlink(APP_ROOT . '/public/' . $currentOg);
            }

            $pdo = Database::connect();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'og_image', '', $driver);

            Logger::log("[DomainManager] OG image deleted for tenant {$tenant->id}", 'INFO');
            echo json_encode(['success' => true, 'message' => 'Imagen OG eliminada correctamente.']);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error deleting OG image for tenant {$tenant->id}: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error al eliminar la imagen OG.']);
        }
        exit;
    }

    /**
     * Guarda los ajustes del blog de un tenant (AJAX)
     */
    public function updateBlogSettings($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $csrfToken = $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            $pdo = Database::connect();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $pdo->beginTransaction();

            // Blog URL prefix
            $blogUrlMode = $_POST['blog_url_mode'] ?? 'prefix';
            if ($blogUrlMode === 'none') {
                $blogUrlPrefix = '';
            } else {
                $blogUrlPrefix = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['blog_url_prefix'] ?? 'blog')));
                if ($blogUrlPrefix === '') {
                    $blogUrlPrefix = 'blog';
                }
            }

            $this->saveTenantSiteSetting($pdo, $tenant->id, 'blog_url_prefix', $blogUrlPrefix, $driver);

            // Actualizar el prefix de todos los slugs de blog existentes
            $newSlugPrefix = $blogUrlPrefix !== '' ? $blogUrlPrefix : null;
            $stmt = $pdo->prepare("UPDATE slugs SET prefix = ? WHERE tenant_id = ? AND module = 'blog'");
            $stmt->execute([$newSlugPrefix, $tenant->id]);

            // Posts per page / RSS
            $postsPerPage = max(1, min(100, (int)($_POST['posts_per_page'] ?? 10)));
            $postsPerRss = max(1, min(50, (int)($_POST['posts_per_rss'] ?? 10)));
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'posts_per_page', (string)$postsPerPage, $driver);
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'posts_per_rss', (string)$postsPerRss, $driver);

            // Blog public (noindex)
            $blogPublic = isset($_POST['blog_noindex']) ? '0' : '1';
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'blog_public', $blogPublic, $driver);

            // Show views
            $blogShowViews = isset($_POST['blog_show_views']) ? '1' : '0';
            $this->saveTenantSiteSetting($pdo, $tenant->id, 'blog_show_views', $blogShowViews, $driver);

            $pdo->commit();

            // Blog layout (se guarda en theme_options, no en site_settings)
            $blogLayout = $_POST['blog_layout'] ?? 'grid';
            $allowedLayouts = ['grid', 'list', 'magazine', 'minimal', 'newspaper', 'fashion'];
            if (!in_array($blogLayout, $allowedLayouts)) {
                $blogLayout = 'grid';
            }
            $themeSlug = $tenant->theme ?? 'default';
            $currentOpts = ThemeOption::getOptions($themeSlug, $tenant->id);
            if (!isset($currentOpts['blog'])) {
                $currentOpts['blog'] = [];
            }
            $currentOpts['blog']['blog_layout'] = $blogLayout;
            // Individual ticker toggles
            $tickerTagsOn = isset($_POST['blog_ticker_tags']);
            $tickerLatestOn = isset($_POST['blog_ticker_latest']);
            $currentOpts['blog']['blog_ticker_tags'] = $tickerTagsOn ? '1' : '0';
            $currentOpts['blog']['blog_ticker_latest'] = $tickerLatestOn ? '1' : '0';
            $tickerTagsPos = $_POST['blog_ticker_tags_position'] ?? 'top';
            $currentOpts['blog']['blog_ticker_tags_position'] = in_array($tickerTagsPos, ['top', 'bottom']) ? $tickerTagsPos : 'top';
            $tickerLatestPos = $_POST['blog_ticker_latest_position'] ?? 'top';
            $currentOpts['blog']['blog_ticker_latest_position'] = in_array($tickerLatestPos, ['top', 'bottom']) ? $tickerLatestPos : 'top';
            // Legacy flag synced for backward compat
            $currentOpts['blog']['blog_header_ticker'] = ($tickerTagsOn || $tickerLatestOn) ? '1' : '0';
            $currentOpts['blog']['blog_topbar_clock'] = isset($_POST['blog_topbar_clock']) ? '1' : '0';
            $currentOpts['blog']['blog_ticker_clock'] = isset($_POST['blog_ticker_clock']) ? '1' : '0';
            $allowedClockLocales = ['es', 'en', 'fr', 'de', 'pt'];
            $clockLocale = $_POST['blog_header_clock_locale'] ?? 'es';
            $currentOpts['blog']['blog_header_clock_locale'] = in_array($clockLocale, $allowedClockLocales) ? $clockLocale : 'es';
            $clockTz = $_POST['blog_header_clock_timezone'] ?? 'Europe/Madrid';
            // Validate timezone
            try { new \DateTimeZone($clockTz); } catch (\Exception $e) { $clockTz = 'Europe/Madrid'; }
            $currentOpts['blog']['blog_header_clock_timezone'] = $clockTz;
            $currentOpts['blog']['blog_sidebar_related_posts'] = isset($_POST['blog_sidebar_related_posts']) ? '1' : '0';
            $currentOpts['blog']['blog_sidebar_related_posts_count'] = $_POST['blog_sidebar_related_posts_count'] ?? '4';
            $currentOpts['blog']['blog_sidebar_tags'] = isset($_POST['blog_sidebar_tags']) ? '1' : '0';
            $currentOpts['blog']['blog_sidebar_categories'] = isset($_POST['blog_sidebar_categories']) ? '1' : '0';
            $currentOpts['blog']['blog_show_briefs'] = isset($_POST['blog_show_briefs']) ? '1' : '0';
            $currentOpts['blog']['blog_briefs_count'] = $_POST['blog_briefs_count'] ?? '10';

            // Scroll to top
            if (!isset($currentOpts['scroll_to_top'])) {
                $currentOpts['scroll_to_top'] = [];
            }
            $currentOpts['scroll_to_top']['scroll_to_top_enabled'] = isset($_POST['scroll_to_top_enabled']) ? '1' : '0';

            ThemeOption::saveOptions($themeSlug, $tenant->id, $currentOpts);

            // Save layout restrictions
            $allowedLayouts = $_POST['allowed_layouts'] ?? [];
            $allLayouts = ['default','left','centered','logo-above','logo-above-left','tema1','aca','sidebar'];

            // Only save restrictions if not all are checked (= no restrictions)
            $pdo->prepare("DELETE FROM tenant_layout_restrictions WHERE tenant_id = ? AND layout_type = 'header_layout'")->execute([$tenant->id]);
            if (count($allowedLayouts) < count($allLayouts) && !empty($allowedLayouts)) {
                $insertStmt = $pdo->prepare("INSERT INTO tenant_layout_restrictions (tenant_id, layout_type, layout_value, is_allowed) VALUES (?, 'header_layout', ?, ?)");
                foreach ($allLayouts as $layout) {
                    $isAllowed = in_array($layout, $allowedLayouts) ? 1 : 0;
                    $insertStmt->execute([$tenant->id, $layout, $isAllowed]);
                }
            }

            Logger::log("[DomainManager] Blog settings updated for tenant {$tenant->id} ({$tenant->domain})", 'INFO');
            echo json_encode(['success' => true, 'message' => 'Configuracion del blog guardada correctamente.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::log("[DomainManager] Error saving blog settings for tenant {$tenant->id}: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error al guardar la configuracion del blog: ' . $e->getMessage()]);
        }
        exit;
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
     * @return int ID del rol admin creado
     */
    private function createDefaultPermissionsAndRoles(PDO $pdo, int $tenantId): int
    {
        // Permisos por defecto - todos los permisos que un admin necesita
        $permissions = [
            // Acceso general
            ['name' => 'admin.access', 'description' => 'Acceder al panel de administración', 'category' => 'admin'],
            ['name' => 'admin.dashboard', 'description' => 'Ver el dashboard', 'category' => 'admin'],
            ['name' => 'admin.settings', 'description' => 'Gestionar configuración', 'category' => 'admin'],
            // Usuarios
            ['name' => 'users.view', 'description' => 'Ver usuarios', 'category' => 'users'],
            ['name' => 'users.create', 'description' => 'Crear usuarios', 'category' => 'users'],
            ['name' => 'users.edit', 'description' => 'Editar usuarios', 'category' => 'users'],
            ['name' => 'users.delete', 'description' => 'Eliminar usuarios', 'category' => 'users'],
            // Contenido
            ['name' => 'content.view', 'description' => 'Ver contenido', 'category' => 'content'],
            ['name' => 'content.create', 'description' => 'Crear contenido', 'category' => 'content'],
            ['name' => 'content.edit', 'description' => 'Editar contenido', 'category' => 'content'],
            ['name' => 'content.delete', 'description' => 'Eliminar contenido', 'category' => 'content'],
            // Páginas
            ['name' => 'pages.view', 'description' => 'Ver páginas', 'category' => 'pages'],
            ['name' => 'pages.create', 'description' => 'Crear páginas', 'category' => 'pages'],
            ['name' => 'pages.edit', 'description' => 'Editar páginas', 'category' => 'pages'],
            ['name' => 'pages.delete', 'description' => 'Eliminar páginas', 'category' => 'pages'],
            // Menús
            ['name' => 'menus.view', 'description' => 'Ver menús', 'category' => 'menus'],
            ['name' => 'menus.manage', 'description' => 'Gestionar menús', 'category' => 'menus'],
            // Media
            ['name' => 'media.view', 'description' => 'Ver media', 'category' => 'media'],
            ['name' => 'media.upload', 'description' => 'Subir archivos', 'category' => 'media'],
            ['name' => 'media.delete', 'description' => 'Eliminar archivos', 'category' => 'media'],
            // Módulos
            ['name' => 'modules.view', 'description' => 'Ver módulos', 'category' => 'modules'],
            ['name' => 'modules.manage', 'description' => 'Gestionar módulos', 'category' => 'modules'],
            // Plugins
            ['name' => 'plugins.view', 'description' => 'Ver plugins', 'category' => 'plugins'],
            ['name' => 'plugins.manage', 'description' => 'Gestionar plugins', 'category' => 'plugins'],
            // Temas
            ['name' => 'themes.view', 'description' => 'Ver temas', 'category' => 'themes'],
            ['name' => 'themes.manage', 'description' => 'Gestionar temas', 'category' => 'themes'],
        ];

        // Insertar permisos y guardar IDs
        $permissionIds = [];
        $checkPermStmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? AND tenant_id = ?");
        $insertPermStmt = $pdo->prepare("
            INSERT INTO permissions (name, description, category, tenant_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");

        foreach ($permissions as $perm) {
            $checkPermStmt->execute([$perm['name'], $tenantId]);
            $existing = $checkPermStmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $permissionIds[] = $existing['id'];
            } else {
                $insertPermStmt->execute([$perm['name'], $perm['description'], $perm['category'], $tenantId]);
                $permissionIds[] = $pdo->lastInsertId();
            }
        }

        // Roles por defecto
        $roles = [
            ['name' => 'admin', 'description' => 'Administrador con acceso completo'],
            ['name' => 'editor', 'description' => 'Editor de contenido'],
            ['name' => 'viewer', 'description' => 'Solo lectura'],
        ];

        $adminRoleId = null;
        $checkRoleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? AND tenant_id = ?");
        $insertRoleStmt = $pdo->prepare("
            INSERT INTO roles (name, description, tenant_id, is_system, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");

        foreach ($roles as $role) {
            $checkRoleStmt->execute([$role['name'], $tenantId]);
            $existing = $checkRoleStmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $roleId = $existing['id'];
            } else {
                $insertRoleStmt->execute([$role['name'], $role['description'], $tenantId]);
                $roleId = $pdo->lastInsertId();
            }

            // Guardar ID del rol admin
            if ($role['name'] === 'admin') {
                $adminRoleId = $roleId;
            }
        }

        // Asignar TODOS los permisos al rol admin
        if ($adminRoleId && !empty($permissionIds)) {
            $checkRolePermStmt = $pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? AND tenant_id = ?");
            $insertRolePermStmt = $pdo->prepare("
                INSERT INTO role_permissions (role_id, permission_id, tenant_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");

            foreach ($permissionIds as $permId) {
                $checkRolePermStmt->execute([$adminRoleId, $permId, $tenantId]);
                if (!$checkRolePermStmt->fetch()) {
                    $insertRolePermStmt->execute([$adminRoleId, $permId, $tenantId]);
                }
            }
        }

        return (int)$adminRoleId;
    }

    /**
     * Asigna un rol a un admin del tenant
     */
    private function assignRoleToAdmin(PDO $pdo, int $adminId, int $roleId, int $tenantId): void
    {
        // Verificar si ya existe la asignación
        $checkStmt = $pdo->prepare("SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? AND tenant_id = ?");
        $checkStmt->execute([$adminId, $roleId, $tenantId]);

        if (!$checkStmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO user_roles (user_id, user_type, role_id, tenant_id, created_at)
                VALUES (?, 'admin', ?, ?, NOW())
            ");
            $stmt->execute([$adminId, $roleId, $tenantId]);
        }
    }

    /**
     * Crea los menús por defecto para el tenant copiando de admin_menus
     */
    private function createDefaultTenantMenus(PDO $pdo, int $tenantId): void
    {
        // Verificar si el tenant ya tiene menús
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenant_menus WHERE tenant_id = ?");
        $checkStmt->execute([$tenantId]);
        $result = $checkStmt->fetch(\PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            return; // Ya tiene menús
        }

        // Obtener todos los menús de admin_menus
        $stmt = $pdo->query("
            SELECT id, parent_id, module_id, title, slug, url, icon, icon_type,
                   order_position, permission, is_active
            FROM admin_menus
            ORDER BY parent_id IS NULL DESC, parent_id, order_position
        ");
        $adminMenus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($adminMenus)) {
            return; // No hay menús base
        }

        // Mapeo de IDs antiguos a nuevos para mantener la jerarquía
        $idMap = [];

        $insertStmt = $pdo->prepare("
            INSERT INTO tenant_menus
            (tenant_id, parent_id, module_id, title, slug, url, icon, icon_type,
             order_position, permission, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        // Primero insertar menús padres (parent_id IS NULL)
        foreach ($adminMenus as $menu) {
            if ($menu['parent_id'] === null) {
                $insertStmt->execute([
                    $tenantId,
                    null,
                    $menu['module_id'],
                    $menu['title'],
                    $menu['slug'],
                    $menu['url'],
                    $menu['icon'],
                    $menu['icon_type'],
                    $menu['order_position'],
                    $menu['permission'],
                    $menu['is_active']
                ]);
                $idMap[$menu['id']] = $pdo->lastInsertId();
            }
        }

        // Luego insertar menús hijos
        foreach ($adminMenus as $menu) {
            if ($menu['parent_id'] !== null) {
                $newParentId = $idMap[$menu['parent_id']] ?? null;
                if ($newParentId) {
                    $insertStmt->execute([
                        $tenantId,
                        $newParentId,
                        $menu['module_id'],
                        $menu['title'],
                        $menu['slug'],
                        $menu['url'],
                        $menu['icon'],
                        $menu['icon_type'],
                        $menu['order_position'],
                        $menu['permission'],
                        $menu['is_active']
                    ]);
                    $idMap[$menu['id']] = $pdo->lastInsertId();
                }
            }
        }

        Logger::log("[DomainManager] Menús creados para tenant {$tenantId}: " . count($idMap) . " items", 'INFO');
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
     * Eliminar tenant con verificación de contraseña (AJAX)
     */
    public function destroyWithPassword($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        // Obtener input JSON (puede venir del middleware CSRF o leerlo directamente)
        $input = $GLOBALS['_JSON_INPUT'] ?? json_decode(file_get_contents('php://input'), true) ?? [];

        // Validar CSRF
        $csrfToken = $input['_csrf'] ?? $_POST['_csrf'] ?? '';

        if (!validate_csrf($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $password = $input['password'] ?? $_POST['password'] ?? '';

        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña es requerida']);
            exit;
        }

        // Verificar contraseña del superadmin actual
        $auth = $_SESSION['super_admin'] ?? null;
        if (!$auth || empty($auth['id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
            exit;
        }

        // Obtener el hash de contraseña de la BD
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT password FROM super_admins WHERE id = ?");
        $stmt->execute([$auth['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }

        // Obtener opción de eliminar de Cloudflare (por defecto true)
        $deleteFromCloudflare = $input['deleteFromCloudflare'] ?? true;

        // Proceder con la eliminación
        $tenant = $this->getTenant($id);

        if (!$tenant) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado']);
            exit;
        }

        $caddyWarning = null;
        $cloudflareWarning = null;

        try {
            // 1. Intentar eliminar de Cloudflare (solo si el usuario lo solicitó)
            if ($deleteFromCloudflare) {
                // A) Dominios personalizados: eliminar la zona completa
                if (!($tenant->is_subdomain ?? false) && ($tenant->cloudflare_zone_id ?? null)) {
                    try {
                        $cloudflareZoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
                        $cloudflareZoneService->deleteZone($tenant->cloudflare_zone_id);
                        Logger::log("[DomainManager] Zona de Cloudflare eliminada: {$tenant->cloudflare_zone_id} ({$tenant->domain})", 'INFO');
                    } catch (\Exception $e) {
                        $errorMsg = $e->getMessage();
                        // Caso especial: dominio registrado en Cloudflare Registrar
                        if (strpos($errorMsg, 'Cloudflare Registrar') !== false) {
                            $cloudflareWarning = "La zona permanece en Cloudflare porque el dominio está registrado con Cloudflare Registrar. " .
                                               "Para eliminarlo completamente, transfiere el dominio a otro registrador primero.";
                            Logger::log("[DomainManager] Dominio {$tenant->domain} usa Cloudflare Registrar - zona no eliminada", 'WARNING');
                        } else {
                            $cloudflareWarning = "No se pudo eliminar la zona de Cloudflare: " . $errorMsg;
                            Logger::log("[DomainManager] Warning eliminando zona de Cloudflare: " . $errorMsg, 'WARNING');
                        }
                    }
                }
                // B) Subdominios FREE: eliminar solo el registro DNS
                elseif (($tenant->is_subdomain ?? false) && ($tenant->cloudflare_record_id ?? null)) {
                    $cloudflareService = new \CaddyDomainManager\Services\CloudflareService();
                    $cloudflareResult = $cloudflareService->deleteRecord($tenant->cloudflare_record_id);

                    if (!$cloudflareResult['success']) {
                        $cloudflareWarning = "El registro DNS de Cloudflare podría seguir activo.";
                        Logger::log("[DomainManager] Warning eliminando de Cloudflare: " . ($cloudflareResult['error'] ?? 'Error desconocido'), 'WARNING');
                    } else {
                        Logger::log("[DomainManager] Registro DNS eliminado de Cloudflare: {$tenant->cloudflare_record_id}", 'INFO');
                    }
                }
            } else {
                Logger::log("[DomainManager] Usuario eligió NO eliminar de Cloudflare: {$tenant->domain}", 'INFO');
            }

            // 2. Intentar eliminar de Caddy si existe configuración
            if ($tenant->caddy_route_id ?? null) {
                $caddyResult = $this->caddyService->removeDomain($tenant->caddy_route_id);
                if (!$caddyResult['success']) {
                    $caddyWarning = "La ruta '{$tenant->caddy_route_id}' podría seguir en Caddy.";
                    Logger::log("[DomainManager] Warning eliminando de Caddy: " . ($caddyResult['error'] ?? 'Error desconocido'), 'WARNING');
                } else {
                    Logger::log("[DomainManager] Ruta eliminada de Caddy: {$tenant->caddy_route_id}", 'INFO');
                }
            }

            // 3. Eliminar tenant y datos relacionados de la BD
            $pdo->beginTransaction();

            try {
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

            $message = "Tenant '{$tenant->name}' eliminado correctamente.";
            $warnings = [];

            if ($cloudflareWarning) {
                $warnings[] = $cloudflareWarning;
            }
            if ($caddyWarning) {
                $warnings[] = $caddyWarning;
            }

            if (!empty($warnings)) {
                $message .= " Advertencias: " . implode(' | ', $warnings);
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'cloudflare_warning' => $cloudflareWarning,
                'caddy_warning' => $caddyWarning
            ]);
            exit;

        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error eliminando tenant: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
            exit;
        }
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

    /**
     * Crea un subdominio FREE .musedock.com (manual desde superadmin)
     *
     * POST /musedock/domain-manager/create-free
     */
    public function createFreeSubdomain()
    {
        // Verificar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        // Verificar permisos (checkPermission lanza excepción si no tiene permisos)
        $this->checkPermission('tenants.manage');

        try {
            // Recoger datos
            $subdomain = trim(strtolower($_POST['subdomain'] ?? ''));
            $customerEmail = trim(strtolower($_POST['customer_email'] ?? ''));
            $customerName = trim($_POST['customer_name'] ?? '');
            $customerPassword = $_POST['customer_password'] ?? '';
            $sendWelcomeEmail = isset($_POST['send_welcome_email']) && $_POST['send_welcome_email'] === '1';
            $cfProxy = !isset($_POST['cf_proxy']) || $_POST['cf_proxy'] === '1'; // default: true (proxy naranja)

            // Credenciales del admin (las mismas que customer por defecto)
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPassword = $_POST['admin_password'] ?? '';

            // Validaciones
            if (empty($subdomain) || empty($customerEmail) || empty($customerName) || empty($customerPassword)) {
                $this->jsonResponse(['success' => false, 'error' => 'Todos los campos son obligatorios'], 400);
                return;
            }

            // Preparar credenciales personalizadas del admin si se proporcionaron
            $adminCredentials = null;
            if (!empty($adminEmail) && !empty($adminPassword)) {
                if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $this->jsonResponse(['success' => false, 'error' => 'El email del admin no es válido'], 400);
                    return;
                }
                if (strlen($adminPassword) < 8) {
                    $this->jsonResponse(['success' => false, 'error' => 'La contraseña del admin debe tener al menos 8 caracteres'], 400);
                    return;
                }
                $adminCredentials = [
                    'email' => $adminEmail,
                    'password' => $adminPassword
                ];
            }

            // Preparar datos de customer
            $customerData = [
                'name' => $customerName,
                'email' => $customerEmail,
                'password' => $customerPassword
            ];

            // Usar ProvisioningService para crear tenant FREE
            $provisioningService = new \CaddyDomainManager\Services\ProvisioningService();
            // Superadmin creando manualmente: usar español por defecto
            $result = $provisioningService->provisionFreeTenant($customerData, $subdomain, $sendWelcomeEmail, 'es', $adminCredentials, $cfProxy);

            if ($result['success']) {
                Logger::info("[DomainManagerController] FREE subdomain created by superadmin: {$result['domain']}");

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Subdominio FREE creado exitosamente',
                    'domain' => $result['domain'],
                    'tenant_id' => $result['tenant_id']
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error'] ?? 'Error al crear subdominio'
                ], 400);
            }

        } catch (\Exception $e) {
            Logger::error("[DomainManagerController] Error creating FREE subdomain: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al crear subdominio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vincular dominio existente de Cloudflare
     * Importa la configuración sin modificar nada en Cloudflare
     */
    public function linkCloudflareZone(int $id): void
    {
        // Headers para JSON
        header('Content-Type: application/json');

        try {
            $this->checkPermission('tenants.manage');

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
                return;
            }

            // Verificar CSRF
            $input = file_get_contents('php://input');
            $data = $input ? json_decode($input, true) : [];
            $csrfToken = $data['_csrf'] ?? '';

            if (!validate_csrf($csrfToken)) {
                Logger::log("[DomainManager] CSRF validation failed", 'ERROR');
                $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
                return;
            }
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$id]);
            $tenant = $stmt->fetchObject();

            if (!$tenant) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado'], 404);
                return;
            }

            // Verificar que NO tenga ya Cloudflare configurado
            if (!empty($tenant->cloudflare_zone_id)) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Este dominio ya tiene Cloudflare configurado'
                ], 400);
                return;
            }

            Logger::log("[DomainManager] Linking existing Cloudflare zone for: {$tenant->domain}", 'INFO');

            $cloudflareService = new \CaddyDomainManager\Services\CloudflareZoneService();

            // Buscar zona en Cloudflare
            $zone = $cloudflareService->findExistingZone($tenant->domain);

            if (!$zone) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => "El dominio {$tenant->domain} no existe en Cloudflare o no está en la cuenta configurada"
                ], 404);
                return;
            }

            // Intentar obtener configuración de Email Routing (puede fallar por permisos)
            $emailRouting = null;
            if ($zone['email_routing_enabled']) {
                try {
                    $emailRouting = $cloudflareService->getEmailRoutingConfig($zone['zone_id']);
                } catch (\Exception $e) {
                    Logger::log("[DomainManager] No se pudo obtener config de Email Routing (permisos insuficientes): " . $e->getMessage(), 'WARNING');
                    // No es crítico, continuamos sin esta info
                }
            }

            // Guardar información en la BD
            $stmt = $pdo->prepare("
                UPDATE tenants
                SET cloudflare_zone_id = ?,
                    cloudflare_nameservers = ?,
                    cloudflare_proxied = 1,
                    email_routing_enabled = ?,
                    status = 'active',
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $zone['zone_id'],
                json_encode($zone['nameservers']),
                $zone['email_routing_enabled'] ? 1 : 0,
                $id
            ]);

            Logger::log("[DomainManager] Cloudflare zone linked successfully: {$tenant->domain} (Zone ID: {$zone['zone_id']})", 'INFO');

            // Preparar respuesta con información detallada
            $response = [
                'success' => true,
                'message' => 'Dominio vinculado correctamente con Cloudflare',
                'zone_id' => $zone['zone_id'],
                'nameservers' => $zone['nameservers'],
                'email_routing_enabled' => $zone['email_routing_enabled']
            ];

            // Información sobre Email Routing
            if ($zone['email_routing_enabled'] && $emailRouting) {
                $catchAllEmail = null;
                if (!empty($emailRouting['catch_all']['actions'])) {
                    foreach ($emailRouting['catch_all']['actions'] as $action) {
                        if ($action['type'] === 'forward' && !empty($action['value'])) {
                            $catchAllEmail = is_array($action['value']) ? $action['value'][0] : $action['value'];
                            break;
                        }
                    }
                }

                $response['email_routing'] = [
                    'catch_all_destination' => $catchAllEmail,
                    'rules_count' => count($emailRouting['rules'] ?? []),
                    'verified_destinations_count' => count($emailRouting['destinations'] ?? [])
                ];
            }

            $this->jsonResponse($response);

        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error linking Cloudflare zone: " . $e->getMessage(), 'ERROR');
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar domain order (registro de dominio de customer) con verificación de contraseña (AJAX)
     *
     * IMPORTANTE: NO elimina el dominio de OpenProvider (los dominios solo pueden caducar).
     * Solo elimina:
     * - El registro de la base de datos local (domain_orders)
     * - Opcionalmente la zona de Cloudflare si existe y el usuario lo solicita
     */
    public function destroyDomainOrderWithPassword($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        // Obtener input JSON
        $input = $GLOBALS['_JSON_INPUT'] ?? json_decode(file_get_contents('php://input'), true) ?? [];

        // Validar CSRF
        $csrfToken = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $password = $input['password'] ?? $_POST['password'] ?? '';
        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña es requerida']);
            exit;
        }

        // Verificar contraseña del superadmin actual
        $auth = $_SESSION['super_admin'] ?? null;
        if (!$auth || empty($auth['id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT password FROM super_admins WHERE id = ?");
        $stmt->execute([$auth['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }

        // Obtener opción de eliminar de Cloudflare (por defecto true)
        $deleteFromCloudflare = $input['deleteFromCloudflare'] ?? true;

        // Obtener la orden del dominio
        $stmt = $pdo->prepare("SELECT * FROM domain_orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Orden de dominio no encontrada']);
            exit;
        }

        $fullDomain = trim(($order['domain'] ?? '') . '.' . ($order['extension'] ?? ''), '.');
        $cloudflareWarning = null;

        try {
            // 1. Eliminar zona de Cloudflare si existe y el usuario lo solicitó
            if ($deleteFromCloudflare && !empty($order['cloudflare_zone_id'])) {
                try {
                    $cloudflareZoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
                    $cloudflareZoneService->deleteZone($order['cloudflare_zone_id']);
                    Logger::log("[DomainManager] Zona de Cloudflare eliminada para domain order: {$order['cloudflare_zone_id']} ({$fullDomain})", 'INFO');
                } catch (\Exception $e) {
                    $errorMsg = $e->getMessage();
                    if (strpos($errorMsg, 'Cloudflare Registrar') !== false) {
                        $cloudflareWarning = "La zona permanece en Cloudflare porque el dominio está registrado con Cloudflare Registrar.";
                        Logger::log("[DomainManager] Dominio {$fullDomain} usa Cloudflare Registrar - zona no eliminada", 'WARNING');
                    } else {
                        $cloudflareWarning = "No se pudo eliminar la zona de Cloudflare: " . $errorMsg;
                        Logger::log("[DomainManager] Warning eliminando zona de Cloudflare para order: " . $errorMsg, 'WARNING');
                    }
                }
            } else if (!$deleteFromCloudflare && !empty($order['cloudflare_zone_id'])) {
                Logger::log("[DomainManager] Usuario eligió NO eliminar zona de Cloudflare para: {$fullDomain}", 'INFO');
            }

            // 2. Eliminar el registro de domain_orders de la BD
            // NOTA: NO eliminamos de OpenProvider - los dominios solo pueden expirar
            $stmt = $pdo->prepare("DELETE FROM domain_orders WHERE id = ?");
            $stmt->execute([$id]);

            Logger::log("[DomainManager] Domain order eliminado: {$fullDomain} (ID: {$id}) - OpenProvider no afectado (solo puede expirar)", 'INFO');

            $message = "Registro del dominio '{$fullDomain}' eliminado correctamente de la base de datos local.";

            if (!empty($order['openprovider_domain_id'])) {
                $message .= " El dominio permanece en OpenProvider hasta su fecha de expiración.";
            }

            if ($cloudflareWarning) {
                $message .= " Advertencia: " . $cloudflareWarning;
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'cloudflare_warning' => $cloudflareWarning,
                'openprovider_note' => 'El dominio no fue eliminado de OpenProvider (solo puede expirar)'
            ]);
            exit;

        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error eliminando domain order: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Helper: Envía respuesta JSON
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Actualiza el perfil de autor del admin root de un tenant
     */
    public function updateAuthorProfile($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $csrfToken = $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            $pdo = Database::connect();

            // Find root admin
            $stmt = $pdo->prepare("SELECT id, name, tenant_id, author_slug FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
            $stmt->execute([$tenant->id]);
            $admin = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$admin) {
                echo json_encode(['success' => false, 'message' => 'No se encontró el administrador principal del tenant.']);
                exit;
            }

            $bio = trim($_POST['bio'] ?? '');
            $socialTwitter = trim($_POST['social_twitter'] ?? '');
            $socialLinkedin = trim($_POST['social_linkedin'] ?? '');
            $socialGithub = trim($_POST['social_github'] ?? '');
            $socialWebsite = trim($_POST['social_website'] ?? '');
            $authorEnabled = isset($_POST['author_page_enabled']) ? 1 : 0;

            // Sanitize URLs
            foreach ([&$socialTwitter, &$socialLinkedin, &$socialGithub, &$socialWebsite] as &$url) {
                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
            }
            unset($url);

            // Generate slug if enabling and no slug exists
            $slug = $admin->author_slug;
            if ($authorEnabled && empty($slug)) {
                $slug = \Screenart\Musedock\Models\Admin::generateSlug($admin->name, $admin->tenant_id);
            }

            $stmt = $pdo->prepare("UPDATE admins SET bio = ?, social_twitter = ?, social_linkedin = ?, social_github = ?, social_website = ?, author_page_enabled = ?, author_slug = ? WHERE id = ?");
            $stmt->execute([
                $bio ?: null,
                $socialTwitter ?: null,
                $socialLinkedin ?: null,
                $socialGithub ?: null,
                $socialWebsite ?: null,
                $authorEnabled,
                $slug,
                $admin->id
            ]);

            echo json_encode(['success' => true, 'message' => 'Perfil de autor actualizado correctamente.']);
            exit;

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Update custom CSS/JS for a tenant (AJAX)
     */
    public function updateCustomCode($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $csrfToken = $_POST['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad invalido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        try {
            $themeSlug = $tenant->theme ?? 'default';
            $customCss = $_POST['custom_css'] ?? '';
            $customJs = $_POST['custom_js'] ?? '';

            // --- CSS ---
            $cssDir = APP_ROOT . "/public/assets/themes/tenant_{$tenant->id}/{$themeSlug}/css";
            $cssPath = $cssDir . '/custom.css';

            if (file_exists($cssPath)) {
                $fullCss = file_get_contents($cssPath);
                // Replace everything after the custom marker
                $marker = '/* --- CSS Personalizado --- */';
                $pos = strpos($fullCss, $marker);
                if ($pos !== false) {
                    $baseCss = substr($fullCss, 0, $pos + strlen($marker));
                } else {
                    $baseCss = $fullCss . "\n\n" . $marker;
                }
            } else {
                if (!is_dir($cssDir)) {
                    mkdir($cssDir, 0755, true);
                }
                $baseCss = '/* --- CSS Personalizado --- */';
            }

            $finalCss = $baseCss . "\n" . $customCss;
            file_put_contents($cssPath, $finalCss);
            file_put_contents($cssDir . '/custom.css.timestamp', time());

            // --- JS ---
            $jsDir = APP_ROOT . "/public/assets/themes/tenant_{$tenant->id}/{$themeSlug}/js";
            if (!is_dir($jsDir)) {
                mkdir($jsDir, 0755, true);
            }
            $jsPath = $jsDir . '/custom.js';

            if (!empty(trim($customJs))) {
                // Guardar tal cual — el layout lo inyecta directamente en el HTML
                file_put_contents($jsPath, $customJs);
                file_put_contents($jsDir . '/custom.js.timestamp', time());
            } else {
                // Remove JS file if empty
                if (file_exists($jsPath)) {
                    unlink($jsPath);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Codigo personalizado guardado correctamente.']);
            exit;

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Auto-categorizar y etiquetar posts de un tenant usando IA (AJAX)
     */
    public function autoTagPosts($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $csrfToken = $input['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad invalido.']);
            exit;
        }

        $tenant = $this->getTenant($id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        $dryRun = !empty($input['dry_run']);
        $scope = $input['scope'] ?? 'all';
        $postIds = [];

        // Si se envían sugerencias preseleccionadas desde el frontend, aplicarlas directamente
        if (!empty($input['suggestions']) && is_array($input['suggestions'])) {
            try {
                $result = \Screenart\Musedock\Services\AI\BlogAutoTagger::applyFiltered((int) $tenant->id, $input['suggestions']);
                echo json_encode($result);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
        }

        // Si scope es "untagged", filtrar posts con pocas categorías/tags
        if ($scope === 'untagged') {
            try {
                $pdo = Database::connect();
                $stmt = $pdo->prepare("
                    SELECT bp.id FROM blog_posts bp
                    LEFT JOIN (SELECT post_id, COUNT(*) as cnt FROM blog_post_categories GROUP BY post_id) pc ON bp.id = pc.post_id
                    LEFT JOIN (SELECT post_id, COUNT(*) as cnt FROM blog_post_tags GROUP BY post_id) pt ON bp.id = pt.post_id
                    WHERE bp.tenant_id = ? AND bp.status = 'published'
                    AND (COALESCE(pc.cnt, 0) + COALESCE(pt.cnt, 0)) < 4
                    ORDER BY bp.id
                ");
                $stmt->execute([$tenant->id]);
                $postIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                if (empty($postIds)) {
                    echo json_encode(['success' => true, 'dry_run' => $dryRun, 'suggestions' => [], 'message' => 'Todos los posts ya tienen al menos 4 categorias/tags.']);
                    exit;
                }
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al filtrar posts: ' . $e->getMessage()]);
                exit;
            }
        }

        try {
            $result = \Screenart\Musedock\Services\AI\BlogAutoTagger::analyze((int) $tenant->id, $postIds, $dryRun);
            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ====================================================================
    // STANDALONE ALIAS MANAGEMENT (form-based, not AJAX)
    // ====================================================================

    public function createAlias()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $pdo = Database::connect();
        $tenants = $pdo->query("SELECT id, name, domain FROM tenants ORDER BY name ASC")->fetchAll(PDO::FETCH_OBJ);
        $caddyApiAvailable = $this->caddyService->isApiAvailable();

        return View::renderSuperadmin('plugins.caddy-domain-manager.create-alias', [
            'title' => 'Nuevo Alias de Dominio',
            'tenants' => $tenants,
            'caddyApiAvailable' => $caddyApiAvailable,
        ]);
    }

    public function storeAlias()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/domain-manager/create-alias');
            exit;
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $aliasDomain = strtolower(trim($_POST['domain'] ?? ''));
        $includeWww = isset($_POST['include_www']);
        $skipCloudflare = isset($_POST['skip_cloudflare']);
        $configureCloudflare = isset($_POST['configure_cloudflare']);

        // Strip www
        $aliasDomain = preg_replace('/^www\./', '', $aliasDomain);
        // Strip protocol
        $aliasDomain = preg_replace('#^https?://#', '', $aliasDomain);
        $aliasDomain = rtrim($aliasDomain, '/');

        if (empty($aliasDomain) || $tenantId <= 0) {
            flash('error', 'Dominio y tenant son obligatorios.');
            header('Location: /musedock/domain-manager/create-alias');
            exit;
        }

        $tenant = $this->getTenant($tenantId);
        if (!$tenant) {
            flash('error', 'Tenant no encontrado.');
            header('Location: /musedock/domain-manager/create-alias');
            exit;
        }

        $pdo = Database::connect();

        // Check uniqueness
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
        $stmt->execute([$aliasDomain]);
        if ($stmt->fetch()) {
            flash('error', 'Este dominio ya es un dominio principal de un tenant.');
            header('Location: /musedock/domain-manager/create-alias');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM domain_aliases WHERE domain = ?");
        $stmt->execute([$aliasDomain]);
        if ($stmt->fetch()) {
            flash('error', 'Este dominio ya está registrado como alias.');
            header('Location: /musedock/domain-manager/create-alias');
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM domain_redirects WHERE domain = ?");
            $stmt->execute([$aliasDomain]);
            if ($stmt->fetch()) {
                flash('error', 'Este dominio ya está registrado como redirección.');
                header('Location: /musedock/domain-manager/create-alias');
                exit;
            }
        } catch (\Exception $e) {}

        $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');
        $isSubdomain = str_ends_with($aliasDomain, '.' . $baseDomain);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO domain_aliases (tenant_id, domain, include_www, is_subdomain, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$tenantId, $aliasDomain, $includeWww ? 1 : 0, $isSubdomain ? 1 : 0]);
            $aliasId = $pdo->lastInsertId();

            // Cloudflare
            if (!$skipCloudflare && $configureCloudflare) {
                $cloudflareInfo = $this->configureAliasCloudflare($aliasDomain, $isSubdomain, $includeWww);
                if (!empty($cloudflareInfo)) {
                    $stmt = $pdo->prepare("UPDATE domain_aliases SET cloudflare_zone_id = ?, cloudflare_record_id = ?, cloudflare_nameservers = ? WHERE id = ?");
                    $stmt->execute([
                        $cloudflareInfo['zone_id'] ?? null,
                        $cloudflareInfo['record_id'] ?? null,
                        isset($cloudflareInfo['nameservers']) ? json_encode($cloudflareInfo['nameservers']) : null,
                        $aliasId
                    ]);
                }
            }

            // Caddy - rebuild with all aliases
            $aliasStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = ? AND status IN ('pending','active')");
            $aliasStmt->execute([$tenantId]);
            $allAliases = $aliasStmt->fetchAll(PDO::FETCH_OBJ);

            $caddyResult = $this->caddyService->upsertDomainWithAliases(
                $tenant->domain,
                (bool) ($tenant->include_www ?? true),
                $allAliases
            );

            if ($caddyResult['success']) {
                $stmt = $pdo->prepare("UPDATE domain_aliases SET caddy_configured = 1, status = 'active' WHERE id = ?");
                $stmt->execute([$aliasId]);
            } else {
                $stmt = $pdo->prepare("UPDATE domain_aliases SET error_log = ?, status = 'error' WHERE id = ?");
                $stmt->execute([$caddyResult['error'] ?? 'Caddy error', $aliasId]);
            }

            $pdo->commit();

            Logger::log("[DomainManager] Alias creado: {$aliasDomain} → tenant {$tenant->domain}", 'INFO');
            flash('success', "Alias {$aliasDomain} creado correctamente para {$tenant->domain}.");

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Logger::log("[DomainManager] Error creando alias: " . $e->getMessage(), 'ERROR');
            flash('error', 'Error al crear alias: ' . $e->getMessage());
        }

        header('Location: /musedock/domain-manager');
        exit;
    }

    public function editAlias($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT da.*, t.name AS tenant_name, t.domain AS tenant_domain FROM domain_aliases da LEFT JOIN tenants t ON t.id = da.tenant_id WHERE da.id = ?");
        $stmt->execute([$id]);
        $alias = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$alias) {
            flash('error', 'Alias no encontrado.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        $tenants = $pdo->query("SELECT id, name, domain FROM tenants ORDER BY name ASC")->fetchAll(PDO::FETCH_OBJ);
        $caddyApiAvailable = $this->caddyService->isApiAvailable();

        return View::renderSuperadmin('plugins.caddy-domain-manager.edit-alias', [
            'title' => 'Editar Alias: ' . $alias->domain,
            'alias' => $alias,
            'tenants' => $tenants,
            'caddyApiAvailable' => $caddyApiAvailable,
        ]);
    }

    public function updateAlias($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header("Location: /musedock/domain-manager/alias/{$id}/edit");
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM domain_aliases WHERE id = ?");
        $stmt->execute([$id]);
        $alias = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$alias) {
            flash('error', 'Alias no encontrado.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? $alias->tenant_id);
        $includeWww = isset($_POST['include_www']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE domain_aliases SET tenant_id = ?, include_www = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$tenantId, $includeWww, $id]);

        // Rebuild Caddy for the tenant
        $tenant = $this->getTenant($tenantId);
        if ($tenant) {
            $aliasStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = ? AND status IN ('pending','active')");
            $aliasStmt->execute([$tenantId]);
            $allAliases = $aliasStmt->fetchAll(PDO::FETCH_OBJ);
            $this->caddyService->upsertDomainWithAliases($tenant->domain, (bool) ($tenant->include_www ?? true), $allAliases);
        }

        flash('success', "Alias {$alias->domain} actualizado correctamente.");
        header('Location: /musedock/domain-manager');
        exit;
    }

    /**
     * Recreate Caddy route for an alias (repair)
     */
    public function recreateAliasRoute($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT da.*, t.domain AS tenant_domain, t.include_www AS tenant_include_www FROM domain_aliases da LEFT JOIN tenants t ON t.id = da.tenant_id WHERE da.id = ?");
        $stmt->execute([$id]);
        $alias = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$alias) {
            echo json_encode(['success' => false, 'message' => 'Alias no encontrado.']);
            exit;
        }

        // Gather all aliases for this tenant
        $aliasStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = ? AND status IN ('pending','active')");
        $aliasStmt->execute([$alias->tenant_id]);
        $allAliases = $aliasStmt->fetchAll(\PDO::FETCH_OBJ);

        $result = $this->caddyService->upsertDomainWithAliases(
            $alias->tenant_domain,
            (bool) ($alias->tenant_include_www ?? true),
            $allAliases
        );

        if ($result['success']) {
            $stmt = $pdo->prepare("UPDATE domain_aliases SET caddy_configured = 1, status = 'active', error_log = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            Logger::log("[DomainManager] Ruta Caddy recreada para alias: {$alias->domain}", 'INFO');
            echo json_encode(['success' => true, 'message' => "Ruta Caddy recreada correctamente para {$alias->domain}.", 'route_id' => $result['route_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE domain_aliases SET caddy_configured = 0, error_log = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$result['error'], $id]);

            echo json_encode(['success' => false, 'message' => 'Error al recrear la ruta: ' . $result['error']]);
        }
        exit;
    }

    public function deleteAlias($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM domain_aliases WHERE id = ?");
        $stmt->execute([$id]);
        $alias = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$alias) {
            echo json_encode(['success' => false, 'message' => 'Alias no encontrado.']);
            exit;
        }

        $deleteFromCloudflare = (bool) ($input['deleteFromCloudflare'] ?? false);

        try {
            // Remove Cloudflare only if user confirmed
            $cfAction = 'omitido';
            if ($deleteFromCloudflare) {
                $this->removeAliasCloudflare($alias);
                $cfAction = $alias->is_subdomain ? 'registro CNAME eliminado' : 'zona eliminada';
            }

            // Delete from DB
            $stmt = $pdo->prepare("DELETE FROM domain_aliases WHERE id = ?");
            $stmt->execute([$id]);

            // Rebuild Caddy for the tenant
            $tenant = $this->getTenant($alias->tenant_id);
            if ($tenant) {
                $aliasStmt = $pdo->prepare("SELECT domain, include_www FROM domain_aliases WHERE tenant_id = ? AND status IN ('pending','active')");
                $aliasStmt->execute([$alias->tenant_id]);
                $allAliases = $aliasStmt->fetchAll(PDO::FETCH_OBJ);
                $this->caddyService->upsertDomainWithAliases($tenant->domain, (bool) ($tenant->include_www ?? true), $allAliases);
            }

            Logger::log("[DomainManager] Alias eliminado: {$alias->domain} (CF: {$cfAction})", 'INFO');
            echo json_encode(['success' => true, 'message' => "Alias {$alias->domain} eliminado.", 'cloudflare' => $cfAction]);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error eliminando alias: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ====================================================================
    // STANDALONE REDIRECT MANAGEMENT
    // ====================================================================

    public function createRedirect()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $caddyApiAvailable = $this->caddyService->isApiAvailable();

        return View::renderSuperadmin('plugins.caddy-domain-manager.create-redirect', [
            'title' => 'Nueva Redirección de Dominio',
            'caddyApiAvailable' => $caddyApiAvailable,
        ]);
    }

    public function storeRedirect()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/domain-manager/create-redirect');
            exit;
        }

        $domain = strtolower(trim($_POST['domain'] ?? ''));
        $redirectTo = trim($_POST['redirect_to'] ?? '');
        $redirectType = (int) ($_POST['redirect_type'] ?? 301);
        $includeWww = isset($_POST['include_www']);
        $preservePath = isset($_POST['preserve_path']);
        $skipCloudflare = isset($_POST['skip_cloudflare']);
        $configureCloudflare = isset($_POST['configure_cloudflare']);

        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('/^www\./', '', $domain);
        $domain = rtrim($domain, '/');

        if (!preg_match('#^https?://#', $redirectTo)) {
            $redirectTo = 'https://' . $redirectTo;
        }

        if (empty($domain) || empty($redirectTo)) {
            flash('error', 'Dominio y destino son obligatorios.');
            header('Location: /musedock/domain-manager/create-redirect');
            exit;
        }

        $pdo = Database::connect();

        // Check uniqueness across all tables
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
        $stmt->execute([$domain]);
        if ($stmt->fetch()) {
            flash('error', 'Este dominio ya es un dominio principal de un tenant.');
            header('Location: /musedock/domain-manager/create-redirect');
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM domain_aliases WHERE domain = ?");
            $stmt->execute([$domain]);
            if ($stmt->fetch()) {
                flash('error', 'Este dominio ya está registrado como alias.');
                header('Location: /musedock/domain-manager/create-redirect');
                exit;
            }
        } catch (\Exception $e) {}

        $stmt = $pdo->prepare("SELECT id FROM domain_redirects WHERE domain = ?");
        $stmt->execute([$domain]);
        if ($stmt->fetch()) {
            flash('error', 'Este dominio ya tiene una redirección configurada.');
            header('Location: /musedock/domain-manager/create-redirect');
            exit;
        }

        $baseDomain = \Screenart\Musedock\Env::get('TENANT_BASE_DOMAIN', 'musedock.com');
        $isSubdomain = str_ends_with($domain, '.' . $baseDomain);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO domain_redirects (domain, redirect_to, redirect_type, include_www, is_subdomain, preserve_path, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$domain, $redirectTo, $redirectType, $includeWww ? 1 : 0, $isSubdomain ? 1 : 0, $preservePath ? 1 : 0]);
            $redirectId = $pdo->lastInsertId();

            // Cloudflare
            if (!$skipCloudflare && $configureCloudflare) {
                $cloudflareInfo = $this->configureAliasCloudflare($domain, $isSubdomain, $includeWww);
                if (!empty($cloudflareInfo)) {
                    $stmt = $pdo->prepare("UPDATE domain_redirects SET cloudflare_zone_id = ?, cloudflare_record_id = ?, cloudflare_nameservers = ? WHERE id = ?");
                    $stmt->execute([
                        $cloudflareInfo['zone_id'] ?? null,
                        $cloudflareInfo['record_id'] ?? null,
                        isset($cloudflareInfo['nameservers']) ? json_encode($cloudflareInfo['nameservers']) : null,
                        $redirectId
                    ]);
                }
            }

            // Caddy redirect configuration
            $caddyResult = $this->caddyService->configureRedirect($domain, $redirectTo, $includeWww, $redirectType, $preservePath);

            if ($caddyResult['success'] ?? false) {
                $stmt = $pdo->prepare("UPDATE domain_redirects SET caddy_configured = 1, status = 'active' WHERE id = ?");
                $stmt->execute([$redirectId]);
            } else {
                $stmt = $pdo->prepare("UPDATE domain_redirects SET error_log = ?, status = 'error' WHERE id = ?");
                $stmt->execute([$caddyResult['error'] ?? 'Caddy error', $redirectId]);
            }

            Logger::log("[DomainManager] Redirect creado: {$domain} → {$redirectTo}", 'INFO');
            flash('success', "Redirección {$domain} → {$redirectTo} creada correctamente.");

        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error creando redirect: " . $e->getMessage(), 'ERROR');
            flash('error', 'Error al crear redirección: ' . $e->getMessage());
        }

        header('Location: /musedock/domain-manager');
        exit;
    }

    public function editRedirect($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM domain_redirects WHERE id = ?");
        $stmt->execute([$id]);
        $redirect = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$redirect) {
            flash('error', 'Redirección no encontrada.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        $caddyApiAvailable = $this->caddyService->isApiAvailable();

        return View::renderSuperadmin('plugins.caddy-domain-manager.edit-redirect', [
            'title' => 'Editar Redirección: ' . $redirect->domain,
            'redirect' => $redirect,
            'caddyApiAvailable' => $caddyApiAvailable,
        ]);
    }

    public function updateRedirect($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header("Location: /musedock/domain-manager/redirect/{$id}/edit");
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM domain_redirects WHERE id = ?");
        $stmt->execute([$id]);
        $redirect = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$redirect) {
            flash('error', 'Redirección no encontrada.');
            header('Location: /musedock/domain-manager');
            exit;
        }

        $redirectTo = trim($_POST['redirect_to'] ?? $redirect->redirect_to);
        $redirectType = (int) ($_POST['redirect_type'] ?? $redirect->redirect_type);
        $includeWww = isset($_POST['include_www']) ? 1 : 0;
        $preservePath = isset($_POST['preserve_path']) ? 1 : 0;

        if (!preg_match('#^https?://#', $redirectTo)) {
            $redirectTo = 'https://' . $redirectTo;
        }

        $stmt = $pdo->prepare("UPDATE domain_redirects SET redirect_to = ?, redirect_type = ?, include_www = ?, preserve_path = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$redirectTo, $redirectType, $includeWww, $preservePath, $id]);

        // Re-configure Caddy
        $caddyResult = $this->caddyService->configureRedirect($redirect->domain, $redirectTo, (bool) $includeWww, $redirectType, (bool) $preservePath);
        if ($caddyResult['success'] ?? false) {
            $stmt = $pdo->prepare("UPDATE domain_redirects SET caddy_configured = 1, status = 'active', error_log = NULL WHERE id = ?");
            $stmt->execute([$id]);
        }

        flash('success', "Redirección {$redirect->domain} actualizada correctamente.");
        header('Location: /musedock/domain-manager');
        exit;
    }

    public function deleteRedirect($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM domain_redirects WHERE id = ?");
        $stmt->execute([$id]);
        $redirect = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$redirect) {
            echo json_encode(['success' => false, 'message' => 'Redirección no encontrada.']);
            exit;
        }

        try {
            // Remove from Caddy
            $this->caddyService->removeRedirect($redirect->domain);

            // Remove Cloudflare if any
            if (!empty($redirect->cloudflare_zone_id)) {
                $this->removeAliasCloudflare($redirect);
            }

            $stmt = $pdo->prepare("DELETE FROM domain_redirects WHERE id = ?");
            $stmt->execute([$id]);

            Logger::log("[DomainManager] Redirect eliminado: {$redirect->domain}", 'INFO');
            echo json_encode(['success' => true, 'message' => "Redirección {$redirect->domain} eliminada."]);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error eliminando redirect: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Toggle Cloudflare Proxy (nube naranja ↔ gris) via AJAX
     */
    public function toggleCloudflareProxy($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json; charset=UTF-8');

        $tenant = $this->getTenant((int)$id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $proxied = (bool)($input['proxied'] ?? false);

        try {
            $pdo = Database::connect();

            if ($tenant->is_subdomain && !empty($tenant->cloudflare_record_id)) {
                // Subdomain: use CloudflareService (parent zone musedock.com)
                $cloudflareService = new \CaddyDomainManager\Services\CloudflareService();
                $result = $cloudflareService->updateProxyStatus($tenant->cloudflare_record_id, $proxied);

                if (!$result['success']) {
                    echo json_encode(['success' => false, 'message' => 'Error Cloudflare: ' . ($result['error'] ?? 'Unknown')]);
                    exit;
                }
            } elseif (!empty($tenant->cloudflare_zone_id)) {
                // Custom domain with own zone: find main A/CNAME record and update
                $zoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
                $records = $zoneService->listDNSRecords($tenant->cloudflare_zone_id);

                $mainRecord = null;
                $domain = $tenant->domain;
                foreach ($records as $record) {
                    if (in_array($record['type'], ['A', 'AAAA', 'CNAME']) && ($record['name'] === $domain || $record['name'] === $domain . '.')) {
                        $mainRecord = $record;
                        break;
                    }
                }

                if (!$mainRecord) {
                    echo json_encode(['success' => false, 'message' => "No se encontró registro DNS principal para {$domain}"]);
                    exit;
                }

                $zoneService->updateDNSRecord($tenant->cloudflare_zone_id, $mainRecord['id'], [
                    'type' => $mainRecord['type'],
                    'name' => $mainRecord['name'],
                    'content' => $mainRecord['content'],
                    'ttl' => $mainRecord['ttl'] ?? 1,
                    'proxied' => $proxied,
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Este tenant no tiene configuración de Cloudflare.']);
                exit;
            }

            // Update DB
            $stmt = $pdo->prepare("UPDATE tenants SET cloudflare_proxied = ? WHERE id = ?");
            $stmt->execute([$proxied ? 1 : 0, $id]);

            $statusLabel = $proxied ? 'Proxy activado (nube naranja)' : 'Solo DNS (nube gris)';
            Logger::log("[DomainManager] Cloudflare proxy toggle: {$tenant->domain} → {$statusLabel}", 'INFO');

            echo json_encode([
                'success' => true,
                'proxied' => $proxied,
                'message' => $statusLabel,
            ]);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error toggling Cloudflare proxy: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Toggle Cloudflare proxy for any domain by zone_id + record_id (AJAX)
     */
    public function toggleDomainProxy()
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $zoneId = $input['zone_id'] ?? '';
        $recordId = $input['record_id'] ?? '';
        $proxied = (bool)($input['proxied'] ?? false);
        $domain = $input['domain'] ?? '';

        if (!$zoneId || !$recordId) {
            echo json_encode(['success' => false, 'message' => 'Faltan zone_id o record_id.']);
            exit;
        }

        try {
            $cfToken = \Screenart\Musedock\Env::get('CLOUDFLARE_API_TOKEN', '');

            // Get current record
            $getResp = $this->cfApiRequest($cfToken, 'GET', "/zones/{$zoneId}/dns_records/{$recordId}");
            if (empty($getResp['result'])) {
                echo json_encode(['success' => false, 'message' => 'Registro DNS no encontrado en Cloudflare.']);
                exit;
            }

            $record = $getResp['result'];

            // Update proxy status
            $updateResp = $this->cfApiRequest($cfToken, 'PUT', "/zones/{$zoneId}/dns_records/{$recordId}", [
                'type' => $record['type'],
                'name' => $record['name'],
                'content' => $record['content'],
                'ttl' => $record['ttl'] ?? 1,
                'proxied' => $proxied,
            ]);

            if (!empty($updateResp['success'])) {
                $statusLabel = $proxied ? 'Proxy activado (nube naranja)' : 'Solo DNS (nube gris)';
                Logger::log("[DomainManager] CF proxy toggle: {$domain} → {$statusLabel}", 'INFO');
                echo json_encode(['success' => true, 'proxied' => $proxied, 'message' => $statusLabel]);
            } else {
                $err = $updateResp['errors'][0]['message'] ?? 'Error desconocido';
                echo json_encode(['success' => false, 'message' => "Error CF: {$err}"]);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Check real Cloudflare proxy status via API for tenant + aliases (AJAX)
     */
    public function checkProxyStatus($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json; charset=UTF-8');

        $tenant = $this->getTenant((int)$id);
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant no encontrado.']);
            exit;
        }

        $domains = [];
        $cfToken = \Screenart\Musedock\Env::get('CLOUDFLARE_API_TOKEN', '');

        try {
            // 1. Check main subdomain
            $mainProxied = null;
            if ($tenant->is_subdomain && !empty($tenant->cloudflare_record_id)) {
                $cloudflareService = new \CaddyDomainManager\Services\CloudflareService();
                $record = $cloudflareService->getRecord($tenant->cloudflare_record_id);
                $mainProxied = $record['proxied'] ?? null;

                // Sync DB
                $dbProxied = (bool)($tenant->cloudflare_proxied ?? false);
                if ($mainProxied !== null && $mainProxied !== $dbProxied) {
                    $pdo = Database::connect();
                    $stmt = $pdo->prepare("UPDATE tenants SET cloudflare_proxied = ? WHERE id = ?");
                    $stmt->execute([$mainProxied ? 1 : 0, $id]);
                }
            } elseif (!empty($tenant->cloudflare_zone_id)) {
                $zoneService = new \CaddyDomainManager\Services\CloudflareZoneService();
                $records = $zoneService->listDNSRecords($tenant->cloudflare_zone_id);
                foreach ($records as $record) {
                    if (in_array($record['type'], ['A', 'AAAA', 'CNAME']) && ($record['name'] === $tenant->domain || $record['name'] === $tenant->domain . '.')) {
                        $mainProxied = $record['proxied'] ?? null;
                        break;
                    }
                }
            }

            // Determine zone_id for principal domain
            $mainZoneId = $tenant->cloudflare_zone_id ?: null;
            $mainRecordId = $tenant->cloudflare_record_id ?: null;
            if (!$mainZoneId && $tenant->is_subdomain) {
                // Subdomain uses parent zone (musedock.com)
                $mainZoneId = \Screenart\Musedock\Env::get('CLOUDFLARE_ZONE_ID', '');
            }

            $domains[] = [
                'domain' => $tenant->domain,
                'type' => 'principal',
                'proxied' => $mainProxied,
                'zone_id' => $mainZoneId,
                'record_id' => $mainRecordId,
                'source' => 'db',
            ];

            // 2. Check aliases — query CF API directly by domain
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT id, domain, cloudflare_zone_id, cloudflare_record_id, is_subdomain FROM domain_aliases WHERE tenant_id = ? AND status IN ('pending','active')");
            $stmt->execute([$id]);
            $aliases = $stmt->fetchAll(\PDO::FETCH_OBJ);

            foreach ($aliases as $alias) {
                $aliasProxied = null;

                if ($alias->is_subdomain && !empty($alias->cloudflare_record_id)) {
                    try {
                        $cloudflareService = $cloudflareService ?? new \CaddyDomainManager\Services\CloudflareService();
                        $rec = $cloudflareService->getRecord($alias->cloudflare_record_id);
                        $aliasProxied = $rec['proxied'] ?? null;
                    } catch (\Exception $e) { /* skip */ }
                } elseif (!empty($alias->cloudflare_zone_id)) {
                    try {
                        $zoneService = $zoneService ?? new \CaddyDomainManager\Services\CloudflareZoneService();
                        $records = $zoneService->listDNSRecords($alias->cloudflare_zone_id);
                        foreach ($records as $rec) {
                            if (in_array($rec['type'], ['A', 'AAAA', 'CNAME']) && ($rec['name'] === $alias->domain || $rec['name'] === $alias->domain . '.')) {
                                $aliasProxied = $rec['proxied'] ?? null;
                                break;
                            }
                        }
                    } catch (\Exception $e) { /* skip */ }
                } else {
                    // No CF data in DB — try to find the zone via API by domain name
                    if ($cfToken) {
                        try {
                            $resp = $this->cfApiRequest($cfToken, 'GET', '/zones?name=' . urlencode($alias->domain));
                            if (!empty($resp['result'])) {
                                $zone = $resp['result'][0];
                                $aliasZoneId = $zone['id'];
                                $dnsResp = $this->cfApiRequest($cfToken, 'GET', "/zones/{$aliasZoneId}/dns_records?name=" . urlencode($alias->domain));
                                foreach ($dnsResp['result'] ?? [] as $rec) {
                                    if (in_array($rec['type'], ['A', 'AAAA', 'CNAME'])) {
                                        $aliasProxied = $rec['proxied'] ?? null;
                                        $aliasRecordId = $rec['id'];
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception $e) { /* skip */ }
                    }
                }

                $domains[] = [
                    'domain' => $alias->domain,
                    'type' => 'alias',
                    'proxied' => $aliasProxied,
                    'alias_id' => $alias->id,
                    'zone_id' => $alias->cloudflare_zone_id ?: ($aliasZoneId ?? null),
                    'record_id' => $alias->cloudflare_record_id ?: ($aliasRecordId ?? null),
                    'source' => (!empty($alias->cloudflare_zone_id) || !empty($alias->cloudflare_record_id)) ? 'db' : 'api',
                ];
                // Reset for next iteration
                $aliasZoneId = null;
                $aliasRecordId = null;
            }

            echo json_encode(['success' => true, 'domains' => $domains, 'proxied' => $mainProxied]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error CF: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Cambiar contraseña del admin root del tenant (AJAX)
     */
    public function changeAdminPassword($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $newPassword = trim($input['password'] ?? '');
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
            exit;
        }

        try {
            $pdo = Database::connect();
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE tenant_id = ? AND is_root_admin = 1");
            $stmt->execute([$hashedPassword, $id]);

            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'No se encontró admin root para este tenant.']);
                exit;
            }

            Logger::log("[DomainManager] Password cambiado para admin root del tenant #{$id}", 'INFO');
            echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente.']);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error cambiando password admin: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Actualizar email o nombre del admin root del tenant (AJAX)
     */
    public function updateAdminField($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $field = $input['field'] ?? '';
        $value = trim($input['value'] ?? '');

        if (!in_array($field, ['email', 'name'])) {
            echo json_encode(['success' => false, 'message' => 'Campo no permitido.']);
            exit;
        }

        if ($field === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Email no válido.']);
                exit;
            }
            // Verificar que no esté en uso por otro admin
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND tenant_id != ?");
            $stmt->execute([$value, $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este email ya está en uso por otro admin.']);
                exit;
            }
        }

        if ($field === 'name' && empty($value)) {
            echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']);
            exit;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE admins SET {$field} = ?, updated_at = NOW() WHERE tenant_id = ? AND is_root_admin = 1");
            $stmt->execute([$value, $id]);

            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'No se encontró admin root para este tenant.']);
                exit;
            }

            $fieldLabel = $field === 'email' ? 'Email' : 'Nombre';
            Logger::log("[DomainManager] {$fieldLabel} actualizado para admin root del tenant #{$id}: {$value}", 'INFO');
            echo json_encode(['success' => true, 'message' => "{$fieldLabel} actualizado correctamente."]);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error actualizando {$field} admin: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Guardar todos los campos del admin root del tenant (AJAX)
     */
    public function saveAdmin($id)
    {
        SessionSecurity::startSession();
        $this->checkMultitenancyEnabled();
        $this->checkPermission('tenants.manage');

        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
            exit;
        }

        $email = trim($input['email'] ?? '');
        $name = trim($input['name'] ?? '');
        $password = $input['password'] ?? null;

        // Validaciones
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email no válido.']);
            exit;
        }
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']);
            exit;
        }
        if ($password && strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
            exit;
        }

        try {
            $pdo = Database::connect();

            // Verificar email único
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND tenant_id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este email ya está en uso por otro admin.']);
                exit;
            }

            // Actualizar email y nombre
            $stmt = $pdo->prepare("UPDATE admins SET email = ?, name = ?, updated_at = NOW() WHERE tenant_id = ? AND is_root_admin = 1");
            $stmt->execute([$email, $name, $id]);

            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'No se encontró admin root para este tenant.']);
                exit;
            }

            // Actualizar contraseña si se proporcionó
            $changes = ['email', 'nombre'];
            if ($password) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE tenant_id = ? AND is_root_admin = 1");
                $stmt->execute([$hashedPassword, $id]);
                $changes[] = 'contraseña';
            }

            Logger::log("[DomainManager] Admin root del tenant #{$id} actualizado: " . implode(', ', $changes), 'INFO');
            echo json_encode(['success' => true, 'message' => 'Admin actualizado correctamente.']);
        } catch (\Exception $e) {
            Logger::log("[DomainManager] Error guardando admin: " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ============================================
    // CLOUDFLARE TOKEN MANAGEMENT
    // ============================================

    /**
     * Verifica un token de Cloudflare y devuelve sus permisos/zonas.
     */
    public function verifyCloudflareToken()
    {
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $token = trim($input['token'] ?? '');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token requerido.']);
            exit;
        }

        try {
            // Verificar token
            $verify = $this->cfApiRequest($token, 'GET', '/user/tokens/verify');
            if (!($verify['success'] ?? false)) {
                echo json_encode(['success' => false, 'message' => 'Token inválido o expirado.']);
                exit;
            }

            // Obtener zonas accesibles
            $zones = $this->cfApiRequest($token, 'GET', '/zones?per_page=50');
            $zoneList = [];
            foreach (($zones['result'] ?? []) as $z) {
                $zoneList[] = ['name' => $z['name'], 'status' => $z['status'], 'id' => $z['id']];
            }

            // Obtener cuenta
            $accounts = $this->cfApiRequest($token, 'GET', '/accounts');
            $accountName = ($accounts['result'][0]['name'] ?? 'Desconocida');

            echo json_encode([
                'success' => true,
                'status' => $verify['result']['status'] ?? 'unknown',
                'account' => $accountName,
                'zones_count' => count($zoneList),
                'zones' => $zoneList,
                'all_zones_access' => count($zoneList) > 1 || (count($zoneList) === 1 && $zoneList[0]['name'] !== 'musedock.com'),
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Guarda el token de Cloudflare en .env, /etc/default/caddy y reinicia Caddy.
     */
    public function saveCloudflareToken()
    {
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!validate_csrf($input['_csrf'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $token = trim($input['token'] ?? '');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token requerido.']);
            exit;
        }

        // Verificar que el token es válido antes de guardar
        $verify = $this->cfApiRequest($token, 'GET', '/user/tokens/verify');
        if (!($verify['success'] ?? false)) {
            echo json_encode(['success' => false, 'message' => 'Token inválido. No se ha guardado.']);
            exit;
        }

        $errors = [];
        $updated = [];

        // 1. Actualizar .env
        $envPath = defined('APP_ROOT') ? APP_ROOT . '/.env' : '/var/www/vhosts/musedock.com/httpdocs/.env';
        if (file_exists($envPath) && is_writable($envPath)) {
            $envContent = file_get_contents($envPath);
            $pattern = '/^CLOUDFLARE_API_TOKEN=.*/m';
            $replacement = 'CLOUDFLARE_API_TOKEN=' . $token;
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n" . $replacement;
            }
            file_put_contents($envPath, $envContent);
            $updated[] = '.env';
        } else {
            $errors[] = '.env no existe o no es escribible';
        }

        // 2. Actualizar /etc/default/caddy via script helper
        $helperScript = '/usr/local/bin/update-caddy-token.sh';
        if (file_exists($helperScript) && is_executable($helperScript)) {
            $escapedToken = escapeshellarg($token);
            $output = [];
            $code = 0;
            exec("sudo {$helperScript} {$escapedToken} 2>&1", $output, $code);
            $outputStr = implode("\n", $output);
            if ($code === 0) {
                $updated[] = '/etc/default/caddy';
                $updated[] = 'Caddy reiniciado';
            } else {
                $errors[] = 'Script helper: ' . $outputStr;
            }
        } else {
            // Fallback: intentar escribir directamente
            $caddyEnvPath = '/etc/default/caddy';
            if (file_exists($caddyEnvPath) && is_writable($caddyEnvPath)) {
                $caddyContent = file_get_contents($caddyEnvPath);
                $pattern = '/^CLOUDFLARE_API_TOKEN=.*/m';
                $replacement = 'CLOUDFLARE_API_TOKEN=' . $token;
                if (preg_match($pattern, $caddyContent)) {
                    $caddyContent = preg_replace($pattern, $replacement, $caddyContent);
                } else {
                    $caddyContent .= "\n" . $replacement;
                }
                file_put_contents($caddyEnvPath, $caddyContent);
                $updated[] = '/etc/default/caddy';
            } else {
                $errors[] = 'No se pudo actualizar /etc/default/caddy (sin permisos). Ejecuta manualmente: sudo sed -i "s|^CLOUDFLARE_API_TOKEN=.*|CLOUDFLARE_API_TOKEN=' . $token . '|" /etc/default/caddy && sudo systemctl restart caddy';
            }

            // Intentar reiniciar Caddy via API reload
            $ch = curl_init('http://localhost:2019/load');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        }

        // Limpiar cache de Env
        \Screenart\Musedock\Env::reload();

        Logger::log("[DomainManager] Cloudflare token actualizado. Updated: " . implode(', ', $updated) . ($errors ? " Errors: " . implode(', ', $errors) : ''), 'INFO');

        echo json_encode([
            'success' => empty($errors) || !empty($updated),
            'message' => 'Token actualizado en: ' . implode(', ', $updated) . ($errors ? '. Errores: ' . implode(', ', $errors) : ''),
            'updated' => $updated,
            'errors' => $errors,
        ]);
        exit;
    }

    /**
     * Helper para hacer requests a la API de Cloudflare con un token dado.
     */
    private function cfApiRequest(string $token, string $method, string $endpoint, ?array $body = null): array
    {
        $ch = curl_init();
        $url = 'https://api.cloudflare.com/client/v4' . $endpoint;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?: [];
    }

    /**
     * Quick helper to upsert a tenant setting
     */
    private function updateTenantSetting(\PDO $pdo, int $tenantId, string $key, string $value): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->saveTenantSiteSetting($pdo, $tenantId, $key, $value, $driver);
    }

    // ==================== PRESET MANAGEMENT ====================

    /**
     * Save current tenant theme options as a preset (AJAX)
     */
    public function savePresetForTenant($tenantId)
    {
        header('Content-Type: application/json');
        try {
            $tenantId = (int) $tenantId;
            $presetName = trim($_POST['preset_name'] ?? '');
            if (!$presetName) {
                echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
                return;
            }

            $pdo = Database::connect();

            // Get current options
            $stmt = $pdo->prepare("SELECT value FROM theme_options WHERE tenant_id = ? AND theme_slug = 'default' LIMIT 1");
            $stmt->execute([$tenantId]);
            $currentOptions = $stmt->fetchColumn();

            if (!$currentOptions) {
                echo json_encode(['success' => false, 'error' => 'Este tenant no tiene configuracion de tema guardada']);
                return;
            }

            // Generate slug
            $presetSlug = strtolower(trim($presetName));
            $presetSlug = preg_replace('/[^a-z0-9]+/', '-', $presetSlug);
            $presetSlug = trim($presetSlug, '-') ?: 'preset-' . uniqid();

            // Upsert preset
            $stmt = $pdo->prepare("
                INSERT INTO theme_presets (tenant_id, theme_slug, preset_slug, preset_name, options, created_at, updated_at)
                VALUES (?, 'default', ?, ?, ?, NOW(), NOW())
                ON CONFLICT (tenant_id, theme_slug, preset_slug) DO UPDATE SET
                    preset_name = EXCLUDED.preset_name,
                    options = EXCLUDED.options,
                    updated_at = NOW()
            ");
            $stmt->execute([$tenantId, $presetSlug, $presetName, $currentOptions]);

            echo json_encode(['success' => true, 'message' => "Preset '{$presetName}' guardado", 'slug' => $presetSlug]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Load a preset into tenant theme options (AJAX)
     */
    public function loadPresetForTenant($tenantId, $presetSlug)
    {
        header('Content-Type: application/json');
        try {
            $tenantId = (int) $tenantId;
            $pdo = Database::connect();

            // Get preset
            $stmt = $pdo->prepare("SELECT options FROM theme_presets WHERE tenant_id = ? AND theme_slug = 'default' AND preset_slug = ?");
            $stmt->execute([$tenantId, $presetSlug]);
            $presetOptions = $stmt->fetchColumn();

            if (!$presetOptions) {
                echo json_encode(['success' => false, 'error' => 'Preset no encontrado']);
                return;
            }

            // Apply to theme_options
            $stmt = $pdo->prepare("SELECT id FROM theme_options WHERE tenant_id = ? AND theme_slug = 'default'");
            $stmt->execute([$tenantId]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $pdo->prepare("UPDATE theme_options SET value = ? WHERE id = ?")->execute([$presetOptions, $existing['id']]);
            } else {
                $pdo->prepare("INSERT INTO theme_options (theme_slug, tenant_id, value) VALUES ('default', ?, ?)")->execute([$tenantId, $presetOptions]);
            }

            // Regenerate CSS
            try {
                $options = json_decode($presetOptions, true);
                \Screenart\Musedock\Models\ThemeOption::regenerateAssets('default', $tenantId, $options);

                // Update cookie colors
                $accent = $options['header']['header_link_hover_color'] ?? '#ff5e15';
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                $this->saveTenantSiteSetting($pdo, $tenantId, 'cookies_btn_accept_bg', $accent, $driver);
                $this->saveTenantSiteSetting($pdo, $tenantId, 'cookies_btn_reject_bg', '#6b7280', $driver);
            } catch (\Throwable $e) {
                // Non-fatal
            }

            echo json_encode(['success' => true, 'message' => 'Preset aplicado correctamente']);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete a tenant preset (AJAX)
     */
    public function deletePresetForTenant($tenantId, $presetSlug)
    {
        header('Content-Type: application/json');
        try {
            $tenantId = (int) $tenantId;
            $pdo = Database::connect();
            $stmt = $pdo->prepare("DELETE FROM theme_presets WHERE tenant_id = ? AND theme_slug = 'default' AND preset_slug = ?");
            $stmt->execute([$tenantId, $presetSlug]);

            echo json_encode(['success' => true, 'message' => 'Preset eliminado']);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ==================== SKIN MANAGEMENT ====================

    /**
     * Apply a skin to a tenant from the domain manager (AJAX)
     */
    public function applySkinToTenant()
    {
        header('Content-Type: application/json');

        try {
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $skinSlug = trim($_POST['skin_slug'] ?? '');

            if (!$tenantId || !$skinSlug) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                return;
            }

            $pdo = \Screenart\Musedock\Database::connect();

            // Get skin options
            $stmt = $pdo->prepare("SELECT id, options FROM theme_skins WHERE slug = ? AND is_global = TRUE AND is_active = TRUE LIMIT 1");
            $stmt->execute([$skinSlug]);
            $skin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$skin) {
                echo json_encode(['success' => false, 'error' => 'Skin no encontrado']);
                return;
            }

            $options = is_string($skin['options']) ? json_decode($skin['options'], true) : $skin['options'];
            if (!$options) {
                echo json_encode(['success' => false, 'error' => 'Opciones del skin invalidas']);
                return;
            }

            // Mark active skin
            $options['_active_skin'] = $skinSlug;

            // Save to theme_options
            $themeSlug = 'default';
            $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE);

            $stmt = $pdo->prepare("SELECT id FROM theme_options WHERE theme_slug = ? AND tenant_id = ?");
            $stmt->execute([$themeSlug, $tenantId]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $pdo->prepare("UPDATE theme_options SET value = ? WHERE id = ?");
                $stmt->execute([$optionsJson, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO theme_options (theme_slug, tenant_id, value) VALUES (?, ?, ?)");
                $stmt->execute([$themeSlug, $tenantId, $optionsJson]);
            }

            // Increment install count
            $pdo->prepare("UPDATE theme_skins SET install_count = install_count + 1 WHERE id = ?")->execute([$skin['id']]);

            // Update cookie banner colors to match skin accent
            $accent = $options['header']['header_link_hover_color']
                   ?? $options['scroll_to_top']['scroll_to_top_bg_color']
                   ?? '#ff5e15';
            $this->updateTenantSetting($pdo, $tenantId, 'cookies_btn_accept_bg', $accent);
            // Reject = darker shade or muted gray
            $rejectColor = '#6b7280';
            $this->updateTenantSetting($pdo, $tenantId, 'cookies_btn_reject_bg', $rejectColor);
            $this->updateTenantSetting($pdo, $tenantId, 'cookies_bg_color', '#ffffff');
            $this->updateTenantSetting($pdo, $tenantId, 'cookies_text_color', '#333333');

            // Clean internal _cookie_colors key before saving
            unset($options['_cookie_colors']);

            // Regenerate CSS
            try {
                \Screenart\Musedock\Models\ThemeOption::regenerateAssets($themeSlug, $tenantId, $options);
            } catch (\Throwable $e) {
                // Non-fatal
            }

            echo json_encode(['success' => true, 'message' => 'Skin aplicado correctamente']);

        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Toggle skin active/inactive (AJAX)
     */
    public function toggleSkin($slug)
    {
        header('Content-Type: application/json');

        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT id, is_active FROM theme_skins WHERE slug = ? AND tenant_id IS NULL LIMIT 1");
            $stmt->execute([$slug]);
            $skin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$skin) {
                echo json_encode(['success' => false, 'error' => 'Skin no encontrado']);
                return;
            }

            $newState = $skin['is_active'] ? false : true;
            $stmt = $pdo->prepare("UPDATE theme_skins SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newState, $skin['id']]);

            echo json_encode(['success' => true, 'is_active' => $newState, 'message' => $newState ? 'Skin activado' : 'Skin desactivado para tenants']);

        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete a global skin (AJAX)
     */
    public function deleteSkin($slug)
    {
        header('Content-Type: application/json');

        try {
            $pdo = \Screenart\Musedock\Database::connect();

            // Check how many tenants use this skin
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM theme_options WHERE value::text LIKE ?");
            $stmt->execute(['%"_active_skin":"' . $slug . '"%']);
            $usageCount = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM theme_skins WHERE slug = ? AND tenant_id IS NULL");
            $success = $stmt->execute([$slug]);

            $msg = 'Skin eliminado';
            if ($usageCount > 0) {
                $msg .= " ({$usageCount} tenant(s) lo tenian aplicado, seguiran con esos colores pero no podran re-aplicarlo)";
            }

            echo json_encode(['success' => $success, 'message' => $msg]);

        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
