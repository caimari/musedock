<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Mail\Mailer;
use CaddyDomainManager\Services\OpenProviderService;
use CaddyDomainManager\Services\CloudflareZoneService;
use CaddyDomainManager\Services\ProvisioningService;
use PDO;
use Exception;

/**
 * DomainRegistrationController
 *
 * Gestiona el flujo completo de registro de dominios:
 * 1. Búsqueda de disponibilidad
 * 2. Gestión de contactos (owner)
 * 3. Registro en OpenProvider
 * 4. Configuración DNS en Cloudflare
 * 5. Activación del tenant
 *
 * @package CaddyDomainManager\Controllers
 */
class DomainRegistrationController
{
    // ============================================
    // VIEWS
    // ============================================

    /**
     * Mostrar formulario de búsqueda de dominios
     */
    public function showSearchForm(): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener contactos existentes del customer
            $stmt = $pdo->prepare("
                SELECT * FROM domain_contacts
                WHERE customer_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$customerId]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener órdenes recientes
            $stmt = $pdo->prepare("
                SELECT * FROM domain_orders
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$customerId]);
            $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo View::renderTheme('Customer.register-domain', [
                'customer' => $_SESSION['customer'],
                'contacts' => $contacts,
                'recentOrders' => $recentOrders,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Error loading search form: " . $e->getMessage());
            echo View::renderTheme('Customer.register-domain', [
                'customer' => $_SESSION['customer'],
                'contacts' => [],
                'recentOrders' => [],
                'csrf_token' => csrf_token(),
                'error' => 'Error al cargar la pagina. Intenta de nuevo.'
            ]);
        }
    }

    /**
     * Mostrar formulario de contacto
     */
    public function showContactForm(): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        // Obtener dominio de la sesión
        $selectedDomain = $_SESSION['domain_registration']['domain'] ?? null;

        if (!$selectedDomain) {
            header('Location: /customer/register-domain');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener contactos existentes
            $stmt = $pdo->prepare("
                SELECT * FROM domain_contacts
                WHERE customer_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$customerId]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Lista de países (ISO 3166-1 alpha-2)
            $countries = $this->getCountriesList();

            echo View::renderTheme('Customer.domain-contact', [
                'customer' => $_SESSION['customer'],
                'contacts' => $contacts,
                'selectedDomain' => $selectedDomain,
                'countries' => $countries,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Error loading contact form: " . $e->getMessage());
            header('Location: /customer/register-domain?error=1');
            exit;
        }
    }

    /**
     * Mostrar checkout/confirmación
     */
    public function showCheckout(): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        // Verificar datos en sesión
        $domainData = $_SESSION['domain_registration'] ?? null;

        if (!$domainData || empty($domainData['domain']) || empty($domainData['contact_id'])) {
            header('Location: /customer/register-domain');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener contacto seleccionado
            $stmt = $pdo->prepare("SELECT * FROM domain_contacts WHERE id = ? AND customer_id = ?");
            $stmt->execute([$domainData['contact_id'], $customerId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contact) {
                header('Location: /customer/register-domain/contact');
                exit;
            }

            echo View::renderTheme('Customer.domain-checkout', [
                'customer' => $_SESSION['customer'],
                'domain' => $domainData['domain'],
                'price' => $domainData['price'] ?? 0,
                'currency' => $domainData['currency'] ?? 'EUR',
                'contact' => $contact,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Error loading checkout: " . $e->getMessage());
            header('Location: /customer/register-domain?error=1');
            exit;
        }
    }

    // ============================================
    // AJAX ENDPOINTS
    // ============================================

    /**
     * Buscar disponibilidad de dominios (AJAX)
     */
    public function searchDomains(): void
    {
        header('Content-Type: application/json');

        try {
            // Validar CSRF
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad invalido'], 403);
                return;
            }

            $query = trim($_POST['query'] ?? '');

            if (empty($query) || strlen($query) < 2) {
                $this->jsonResponse(['success' => false, 'error' => 'Introduce al menos 2 caracteres'], 400);
                return;
            }

            // Limpiar query (remover espacios, caracteres especiales)
            $query = preg_replace('/[^a-zA-Z0-9\-]/', '', $query);
            $query = strtolower($query);

            $openProvider = new OpenProviderService();
            $results = $openProvider->searchDomain($query);

            // Formatear resultados para la vista
            $formattedResults = array_map(function ($result) {
                return [
                    'domain' => $result['domain'] ?? '',
                    'available' => ($result['status'] ?? '') === 'free',
                    'status' => $result['status'] ?? 'unknown',
                    'price' => $result['price']['reseller']['price'] ?? null,
                    'currency' => $result['price']['reseller']['currency'] ?? 'EUR',
                    'is_premium' => $result['is_premium'] ?? false,
                    'premium_price' => $result['premium']['price']['create'] ?? null
                ];
            }, $results);

            $this->jsonResponse([
                'success' => true,
                'query' => $query,
                'results' => $formattedResults,
                'count' => count($formattedResults)
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Search error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al buscar dominios. Intenta de nuevo.'
            ], 500);
        }
    }

    /**
     * Seleccionar dominio para registro (AJAX)
     */
    public function selectDomain(): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad invalido'], 403);
                return;
            }

            $domain = trim($_POST['domain'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $currency = $_POST['currency'] ?? 'EUR';

            if (empty($domain)) {
                $this->jsonResponse(['success' => false, 'error' => 'Dominio no valido'], 400);
                return;
            }

            // Guardar en sesión
            $_SESSION['domain_registration'] = [
                'domain' => $domain,
                'price' => $price,
                'currency' => $currency,
                'selected_at' => date('Y-m-d H:i:s')
            ];

            $this->jsonResponse([
                'success' => true,
                'domain' => $domain,
                'redirect' => '/customer/register-domain/contact'
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Select domain error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al seleccionar dominio'], 500);
        }
    }

    /**
     * Guardar contacto (AJAX)
     */
    public function saveContact(): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad invalido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;

            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesion expirada'], 401);
                return;
            }

            // Validar campos requeridos
            $required = ['first_name', 'last_name', 'email', 'phone', 'address_street', 'address_city', 'address_zipcode', 'address_country'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    $this->jsonResponse(['success' => false, 'error' => "El campo {$field} es requerido"], 400);
                    return;
                }
            }

            $pdo = Database::connect();
            $pdo->beginTransaction();

            try {
                // Crear contacto en OpenProvider
                $openProvider = new OpenProviderService();
                $handle = $openProvider->createContact([
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'company' => $_POST['company'] ?? '',
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone'],
                    'address' => $_POST['address_street'],
                    'address_number' => $_POST['address_number'] ?? '',
                    'city' => $_POST['address_city'],
                    'state' => $_POST['address_state'] ?? '',
                    'zipcode' => $_POST['address_zipcode'],
                    'country' => $_POST['address_country']
                ]);

                // Guardar en base de datos
                $stmt = $pdo->prepare("
                    INSERT INTO domain_contacts (
                        customer_id, openprovider_handle, type,
                        first_name, last_name, company, email, phone,
                        address_street, address_number, address_city, address_state, address_zipcode, address_country,
                        is_default
                    ) VALUES (?, ?, 'owner', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                // Si es el primer contacto, hacerlo default
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM domain_contacts WHERE customer_id = ?");
                $stmtCount->execute([$customerId]);
                $isDefault = ($stmtCount->fetchColumn() == 0) ? 1 : 0;

                $stmt->execute([
                    $customerId,
                    $handle,
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['company'] ?? null,
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['address_street'],
                    $_POST['address_number'] ?? null,
                    $_POST['address_city'],
                    $_POST['address_state'] ?? null,
                    $_POST['address_zipcode'],
                    $_POST['address_country'],
                    $isDefault
                ]);

                $contactId = $pdo->lastInsertId();

                $pdo->commit();

                // Guardar en sesión
                $_SESSION['domain_registration']['contact_id'] = $contactId;

                Logger::info("[DomainRegistration] Contact created. ID: {$contactId}, Handle: {$handle}");

                $this->jsonResponse([
                    'success' => true,
                    'contact_id' => $contactId,
                    'handle' => $handle,
                    'redirect' => '/customer/register-domain/checkout'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Save contact error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al guardar contacto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Seleccionar contacto existente (AJAX)
     */
    public function selectContact(): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad invalido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            $contactId = intval($_POST['contact_id'] ?? 0);

            if (!$customerId || !$contactId) {
                $this->jsonResponse(['success' => false, 'error' => 'Datos invalidos'], 400);
                return;
            }

            // Verificar que el contacto pertenece al customer
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT id FROM domain_contacts WHERE id = ? AND customer_id = ?");
            $stmt->execute([$contactId, $customerId]);

            if (!$stmt->fetch()) {
                $this->jsonResponse(['success' => false, 'error' => 'Contacto no encontrado'], 404);
                return;
            }

            // Guardar en sesión
            $_SESSION['domain_registration']['contact_id'] = $contactId;

            $this->jsonResponse([
                'success' => true,
                'contact_id' => $contactId,
                'redirect' => '/customer/register-domain/checkout'
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Select contact error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al seleccionar contacto'], 500);
        }
    }

    /**
     * Registrar dominio (AJAX) - Paso final
     */
    public function registerDomain(): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token de seguridad invalido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            $domainData = $_SESSION['domain_registration'] ?? null;

            if (!$customerId || !$domainData || empty($domainData['domain']) || empty($domainData['contact_id'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Datos de registro incompletos. Reinicia el proceso.'], 400);
                return;
            }

            $domain = $domainData['domain'];
            $contactId = $domainData['contact_id'];
            $price = $domainData['price'] ?? 0;
            $currency = $domainData['currency'] ?? 'EUR';

            // Separar nombre y extensión
            $parts = explode('.', $domain, 2);
            if (count($parts) !== 2) {
                $this->jsonResponse(['success' => false, 'error' => 'Formato de dominio invalido'], 400);
                return;
            }

            $domainName = $parts[0];
            $extension = $parts[1];

            $pdo = Database::connect();

            // Obtener contacto
            $stmt = $pdo->prepare("SELECT * FROM domain_contacts WHERE id = ? AND customer_id = ?");
            $stmt->execute([$contactId, $customerId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contact || empty($contact['openprovider_handle'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Contacto no valido'], 400);
                return;
            }

            $pdo->beginTransaction();

            try {
                // 1. Crear orden en estado 'processing'
                $stmt = $pdo->prepare("
                    INSERT INTO domain_orders (
                        customer_id, domain, extension, openprovider_contact_handle,
                        price_amount, price_currency, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'processing')
                ");
                $stmt->execute([
                    $customerId,
                    $domain,
                    $extension,
                    $contact['openprovider_handle'],
                    $price,
                    $currency
                ]);
                $orderId = $pdo->lastInsertId();

                Logger::info("[DomainRegistration] Order created. ID: {$orderId}, Domain: {$domain}");

                // 2. Registrar dominio en OpenProvider
                $openProvider = new OpenProviderService();

                // Obtener nameservers de Cloudflare (los obtendremos después de crear la zona)
                // Por ahora usamos los NS genéricos de Cloudflare que se actualizarán
                $tempNameservers = [
                    ['name' => 'ns1.musedock-dns.com'],
                    ['name' => 'ns2.musedock-dns.com']
                ];

                $registrationResult = $openProvider->registerDomain(
                    $domainName,
                    $extension,
                    $contact['openprovider_handle'],
                    $tempNameservers,
                    1,
                    ['autorenew' => 'on']
                );

                // Actualizar orden con datos de OpenProvider
                $stmt = $pdo->prepare("
                    UPDATE domain_orders SET
                        openprovider_domain_id = ?,
                        registered_at = NOW(),
                        expires_at = ?,
                        status = 'registered'
                    WHERE id = ?
                ");
                $stmt->execute([
                    $registrationResult['id'],
                    $registrationResult['expiration_date'],
                    $orderId
                ]);

                Logger::info("[DomainRegistration] Domain registered in OpenProvider. Domain ID: {$registrationResult['id']}");

                // 3. Configurar Cloudflare
                $cloudflareResult = $this->configureCloudflare($domain, $orderId, $pdo);

                // 4. Crear tenant
                $tenantId = $this->createTenant($customerId, $domain, $orderId, $pdo);

                // Actualizar orden con tenant_id
                $stmt = $pdo->prepare("UPDATE domain_orders SET tenant_id = ? WHERE id = ?");
                $stmt->execute([$tenantId, $orderId]);

                $pdo->commit();

                // Limpiar sesión de registro
                unset($_SESSION['domain_registration']);

                // Enviar email de confirmación
                $this->sendConfirmationEmail($customerId, $domain, $cloudflareResult['nameservers'] ?? []);

                Logger::info("[DomainRegistration] Registration complete. Order: {$orderId}, Tenant: {$tenantId}");

                $this->jsonResponse([
                    'success' => true,
                    'order_id' => $orderId,
                    'domain' => $domain,
                    'tenant_id' => $tenantId,
                    'nameservers' => $cloudflareResult['nameservers'] ?? [],
                    'message' => 'Dominio registrado exitosamente!',
                    'redirect' => '/customer/dashboard'
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();

                // Actualizar orden con error
                try {
                    $stmt = $pdo->prepare("UPDATE domain_orders SET status = 'failed', error_message = ? WHERE id = ?");
                    $stmt->execute([$e->getMessage(), $orderId ?? 0]);
                } catch (Exception $ex) {
                    // Ignorar error de actualización
                }

                throw $e;
            }

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Register error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al registrar dominio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estado de una orden (AJAX)
     */
    public function getOrderStatus(): void
    {
        header('Content-Type: application/json');

        $customerId = $_SESSION['customer']['id'] ?? null;
        $orderId = intval($_GET['id'] ?? 0);

        if (!$customerId || !$orderId) {
            $this->jsonResponse(['success' => false, 'error' => 'Datos invalidos'], 400);
            return;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT * FROM domain_orders
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$orderId, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->jsonResponse(['success' => false, 'error' => 'Orden no encontrada'], 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'order' => $order
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Get order status error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error al obtener estado'], 500);
        }
    }

    // ============================================
    // PRIVATE HELPERS
    // ============================================

    /**
     * Configurar Cloudflare para el dominio
     */
    private function configureCloudflare(string $domain, int $orderId, PDO $pdo): array
    {
        Logger::info("[DomainRegistration] Configuring Cloudflare for {$domain}");

        try {
            $cloudflare = new CloudflareZoneService();

            // Crear zona en Cloudflare
            $zoneResult = $cloudflare->addFullZone($domain);

            // Crear CNAMEs
            $cloudflare->createCNAMEIfNotExists($zoneResult['zone_id'], '@', 'mortadelo.musedock.com', true);
            $cloudflare->createCNAMEIfNotExists($zoneResult['zone_id'], 'www', 'mortadelo.musedock.com', true);

            // Actualizar orden
            $stmt = $pdo->prepare("
                UPDATE domain_orders SET
                    cloudflare_zone_id = ?,
                    cloudflare_configured = 1
                WHERE id = ?
            ");
            $stmt->execute([$zoneResult['zone_id'], $orderId]);

            Logger::info("[DomainRegistration] Cloudflare configured. Zone ID: {$zoneResult['zone_id']}");

            return $zoneResult;

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Cloudflare error: " . $e->getMessage());
            // No lanzar excepción, el dominio ya está registrado
            return ['zone_id' => null, 'nameservers' => []];
        }
    }

    /**
     * Crear tenant para el dominio
     */
    private function createTenant(int $customerId, string $domain, int $orderId, PDO $pdo): int
    {
        Logger::info("[DomainRegistration] Creating tenant for {$domain}");

        // Generar nombre a partir del dominio
        $domainParts = explode('.', $domain);
        $tenantName = ucfirst($domainParts[0]);

        // Generar slug único
        $baseSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $domainParts[0]));
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = ?");
            $stmt->execute([$slug]);
            if (!$stmt->fetch()) break;
            $slug = $baseSlug . '-' . $counter++;
        }

        // Crear tenant
        $stmt = $pdo->prepare("
            INSERT INTO tenants (
                customer_id, name, slug, domain, is_subdomain, plan, status,
                cloudflare_proxied, created_at
            ) VALUES (?, ?, ?, ?, 0, 'custom', 'pending', 1, NOW())
        ");
        $stmt->execute([
            $customerId,
            $tenantName,
            $slug,
            $domain
        ]);

        $tenantId = $pdo->lastInsertId();

        Logger::info("[DomainRegistration] Tenant created. ID: {$tenantId}");

        // Aplicar defaults (en background)
        try {
            $provisioning = new ProvisioningService();
            $provisioning->applyTenantDefaults($tenantId);
        } catch (Exception $e) {
            Logger::warning("[DomainRegistration] Could not apply tenant defaults: " . $e->getMessage());
        }

        return $tenantId;
    }

    /**
     * Enviar email de confirmación
     */
    private function sendConfirmationEmail(int $customerId, string $domain, array $nameservers): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT name, email FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) return;

            $subject = "Tu Dominio {$domain} Ha Sido Registrado";

            $nsHtml = '';
            if (!empty($nameservers)) {
                $nsHtml = '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">';
                $nsHtml .= '<h4 style="margin-top: 0;">Configura estos Nameservers:</h4>';
                foreach ($nameservers as $ns) {
                    $nsHtml .= "<p style='margin: 5px 0;'><code>{$ns}</code></p>";
                }
                $nsHtml .= '</div>';
            }

            $htmlBody = "
                <h2>Felicidades! Tu dominio ha sido registrado</h2>
                <p>Hola {$customer['name']},</p>
                <p>Tu dominio <strong>{$domain}</strong> ha sido registrado exitosamente.</p>
                {$nsHtml}
                <p>Una vez configures los nameservers, tu sitio estara disponible en:</p>
                <p><a href='https://{$domain}'>https://{$domain}</a></p>
                <p>Gracias por usar MuseDock!</p>
            ";

            Mailer::send($customer['email'], $subject, $htmlBody);

            Logger::info("[DomainRegistration] Confirmation email sent to {$customer['email']}");

        } catch (Exception $e) {
            Logger::warning("[DomainRegistration] Could not send confirmation email: " . $e->getMessage());
        }
    }

    /**
     * Lista de países (ISO 3166-1 alpha-2)
     */
    private function getCountriesList(): array
    {
        return [
            'ES' => 'Espana',
            'US' => 'Estados Unidos',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CO' => 'Colombia',
            'CL' => 'Chile',
            'PE' => 'Peru',
            'VE' => 'Venezuela',
            'EC' => 'Ecuador',
            'GT' => 'Guatemala',
            'CU' => 'Cuba',
            'DO' => 'Republica Dominicana',
            'HN' => 'Honduras',
            'SV' => 'El Salvador',
            'NI' => 'Nicaragua',
            'CR' => 'Costa Rica',
            'PA' => 'Panama',
            'UY' => 'Uruguay',
            'PY' => 'Paraguay',
            'BO' => 'Bolivia',
            'DE' => 'Alemania',
            'FR' => 'Francia',
            'IT' => 'Italia',
            'PT' => 'Portugal',
            'GB' => 'Reino Unido',
            'NL' => 'Paises Bajos',
            'BE' => 'Belgica',
            'AT' => 'Austria',
            'CH' => 'Suiza',
            'PL' => 'Polonia',
            'SE' => 'Suecia',
            'NO' => 'Noruega',
            'DK' => 'Dinamarca',
            'FI' => 'Finlandia',
            'IE' => 'Irlanda',
            'BR' => 'Brasil',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'NZ' => 'Nueva Zelanda',
            'JP' => 'Japon',
            'CN' => 'China',
            'KR' => 'Corea del Sur',
            'IN' => 'India',
            'RU' => 'Rusia',
            'ZA' => 'Sudafrica'
        ];
    }

    /**
     * Respuesta JSON
     */
    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
