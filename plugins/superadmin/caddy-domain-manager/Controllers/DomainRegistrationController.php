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

            // Obtener modo OpenProvider
            $openProviderMode = strtolower(\Screenart\Musedock\Env::get('OPENPROVIDER_MODE', 'sandbox'));

            echo View::renderTheme('Customer.register-domain', [
                'customer' => $_SESSION['customer'],
                'contacts' => $contacts,
                'recentOrders' => $recentOrders,
                'csrf_token' => csrf_token(),
                'openprovider_mode' => $openProviderMode
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainRegistration] Error loading search form: " . $e->getMessage());
            echo View::renderTheme('Customer.register-domain', [
                'customer' => $_SESSION['customer'],
                'contacts' => [],
                'recentOrders' => [],
                'csrf_token' => csrf_token(),
                'openprovider_mode' => 'sandbox',
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

            // Obtener códigos de teléfono
            $phoneCodes = OpenProviderService::getPhoneCountryCodes();

            echo View::renderTheme('Customer.domain-contact', [
                'customer' => $_SESSION['customer'],
                'contacts' => $contacts,
                'selectedDomain' => $selectedDomain,
                'selectedPrice' => $_SESSION['domain_registration']['price'] ?? 0,
                'selectedCurrency' => $_SESSION['domain_registration']['currency'] ?? 'EUR',
                'countries' => $countries,
                'phoneCodes' => $phoneCodes,
                'hosting_type' => $_SESSION['domain_registration']['hosting_type'] ?? 'musedock_hosting',
                'ns_type' => $_SESSION['domain_registration']['ns_type'] ?? 'cloudflare',
                'custom_ns' => isset($_SESSION['domain_registration']['custom_ns']) ? json_decode($_SESSION['domain_registration']['custom_ns'], true) : null,
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

            // Obtener modo OpenProvider
            $openProviderMode = strtolower(\Screenart\Musedock\Env::get('OPENPROVIDER_MODE', 'sandbox'));

            echo View::renderTheme('Customer.domain-checkout', [
                'customer' => $_SESSION['customer'],
                'domain' => $domainData['domain'],
                'price' => $domainData['price'] ?? 0,
                'currency' => $domainData['currency'] ?? 'EUR',
                'contact' => $contact,
                'handles' => $domainData['handles'] ?? [],
                'hosting_type' => $domainData['hosting_type'] ?? 'musedock_hosting',
                'ns_type' => $domainData['ns_type'] ?? 'cloudflare',
                'custom_ns' => $domainData['custom_ns'] ? json_decode($domainData['custom_ns'], true) : null,
                'openprovider_mode' => $openProviderMode,
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

            // Detectar si el usuario incluyó una extensión específica (ej: caracola.com)
            $query = strtolower(trim($query));
            $preferredExtension = null;

            // Verificar si tiene formato dominio.extension
            if (preg_match('/^([a-z0-9\-]+)\.([a-z]{2,10})$/i', $query, $matches)) {
                $domainName = $matches[1];
                $preferredExtension = $matches[2];
            } else {
                // Limpiar query (remover espacios, caracteres especiales excepto guiones)
                $domainName = preg_replace('/[^a-z0-9\-]/', '', $query);
            }

            $openProvider = new OpenProviderService();
            $results = $openProvider->searchDomain($domainName, $preferredExtension ? [$preferredExtension] : null);

            // Si hay extensión preferida, reordenar para que aparezca primero
            if ($preferredExtension) {
                usort($results, function($a, $b) use ($preferredExtension) {
                    $extA = pathinfo($a['domain'] ?? '', PATHINFO_EXTENSION);
                    $extB = pathinfo($b['domain'] ?? '', PATHINFO_EXTENSION);

                    if ($extA === $preferredExtension && $extB !== $preferredExtension) return -1;
                    if ($extB === $preferredExtension && $extA !== $preferredExtension) return 1;
                    return 0;
                });
            }

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
     * Guardar contacto y opciones de registro (AJAX)
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

            $pdo = Database::connect();
            $openProvider = new OpenProviderService();
            $handles = [];

            // Procesar contacto Owner
            $ownerExisting = $_POST['owner_existing'] ?? '';

            if ($ownerExisting) {
                // Usar contacto existente
                $stmt = $pdo->prepare("SELECT openprovider_handle FROM domain_contacts WHERE id = ? AND customer_id = ?");
                $stmt->execute([$ownerExisting, $customerId]);
                $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$contact) {
                    $this->jsonResponse(['success' => false, 'error' => 'Contacto no encontrado'], 404);
                    return;
                }
                $handles['owner'] = $contact['openprovider_handle'];
                $_SESSION['domain_registration']['contact_id'] = $ownerExisting;
            } else {
                // Validar campos requeridos para nuevo contacto (incluyendo numero)
                $required = ['owner_first_name', 'owner_last_name', 'owner_email', 'owner_phone', 'owner_street', 'owner_number', 'owner_city', 'owner_zipcode', 'owner_country'];
                $fieldLabels = [
                    'owner_first_name' => 'Nombre',
                    'owner_last_name' => 'Apellidos',
                    'owner_email' => 'Email',
                    'owner_phone' => 'Telefono',
                    'owner_street' => 'Direccion',
                    'owner_number' => 'Numero de direccion',
                    'owner_city' => 'Ciudad',
                    'owner_zipcode' => 'Codigo Postal',
                    'owner_country' => 'Pais'
                ];
                foreach ($required as $field) {
                    $value = trim($_POST[$field] ?? '');
                    if (empty($value)) {
                        $label = $fieldLabels[$field] ?? str_replace('owner_', '', $field);
                        $this->jsonResponse(['success' => false, 'error' => "El campo '{$label}' es requerido"], 400);
                        return;
                    }
                }

                // Validar que el telefono no sea el placeholder
                $ownerPhoneNumber = trim($_POST['owner_phone'] ?? '');
                if ($ownerPhoneNumber === '612345678' || strlen($ownerPhoneNumber) < 6) {
                    $this->jsonResponse(['success' => false, 'error' => "Por favor ingresa un numero de telefono valido"], 400);
                    return;
                }

                // Obtener código y número de teléfono por separado
                $ownerPhoneCode = $_POST['owner_phone_code'] ?? '34';
                $ownerPhoneNumber = trim($_POST['owner_phone'] ?? '');

                // Obtener datos de empresa y CIF
                $ownerCompany = trim($_POST['owner_company'] ?? '');
                $ownerCompanyRegNumber = trim($_POST['owner_company_reg_number'] ?? '');

                // Validar CIF obligatorio para .ES si hay empresa
                $domain = $_SESSION['domain_registration']['domain'] ?? '';
                $isEsDomain = str_ends_with(strtolower($domain), '.es');
                if ($isEsDomain && !empty($ownerCompany) && empty($ownerCompanyRegNumber)) {
                    $this->jsonResponse(['success' => false, 'error' => 'El CIF/NIF es obligatorio para dominios .ES cuando el titular es una empresa'], 400);
                    return;
                }

                // Obtener o crear contacto Owner en OpenProvider (reutiliza si ya existe)
                $handles['owner'] = $openProvider->getOrCreateContact([
                    'first_name' => $_POST['owner_first_name'],
                    'last_name' => $_POST['owner_last_name'],
                    'company' => $ownerCompany,
                    'company_reg_number' => $ownerCompanyRegNumber,
                    'email' => $_POST['owner_email'],
                    'phone' => $ownerPhoneNumber,
                    'phone_code' => $ownerPhoneCode,
                    'address' => $_POST['owner_street'],
                    'address_number' => $_POST['owner_number'] ?? '',
                    'city' => $_POST['owner_city'],
                    'state' => $_POST['owner_state'] ?? '',
                    'zipcode' => $_POST['owner_zipcode'],
                    'country' => $_POST['owner_country']
                ]);

                // Guardar contacto en BD
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM domain_contacts WHERE customer_id = ?");
                $stmtCount->execute([$customerId]);
                $isDefault = ($stmtCount->fetchColumn() == 0) ? 1 : 0;

                $stmt = $pdo->prepare("
                    INSERT INTO domain_contacts (
                        customer_id, openprovider_handle, type,
                        first_name, last_name, company, company_reg_number, email, phone,
                        address_street, address_number, address_city, address_state, address_zipcode, address_country,
                        is_default
                    ) VALUES (?, ?, 'owner', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $customerId,
                    $handles['owner'],
                    $_POST['owner_first_name'],
                    $_POST['owner_last_name'],
                    $ownerCompany ?: null,
                    $ownerCompanyRegNumber ?: null,
                    $_POST['owner_email'],
                    $_POST['owner_phone'],
                    $_POST['owner_street'],
                    $_POST['owner_number'] ?? null,
                    $_POST['owner_city'],
                    $_POST['owner_state'] ?? null,
                    $_POST['owner_zipcode'],
                    $_POST['owner_country'],
                    $isDefault
                ]);
                $_SESSION['domain_registration']['contact_id'] = $pdo->lastInsertId();
            }

            // Si usa el mismo contacto para todos
            $sameContactAll = isset($_POST['same_contact_all']) && $_POST['same_contact_all'] === '1';

            if ($sameContactAll) {
                $handles['admin'] = $handles['owner'];
                $handles['tech'] = $handles['owner'];
                $handles['billing'] = $handles['owner'];
            } else {
                // Crear o reutilizar contactos separados (Admin, Tech, Billing)
                foreach (['admin', 'tech', 'billing'] as $type) {
                    $firstName = trim($_POST["{$type}_first_name"] ?? '');
                    $lastName = trim($_POST["{$type}_last_name"] ?? '');
                    $email = trim($_POST["{$type}_email"] ?? '');
                    $phone = trim($_POST["{$type}_phone"] ?? '');
                    $phoneCode = $_POST["{$type}_phone_code"] ?? '34';
                    $number = trim($_POST["{$type}_number"] ?? '');

                    // Validar que el telefono no sea placeholder
                    if ($phone === '612345678') {
                        $phone = '';
                    }

                    if ($firstName && $lastName && $email && $phone && strlen($phone) >= 6 && $number) {
                        // Usar dirección propia si está disponible, si no usar la del owner
                        $street = !empty($_POST["{$type}_street"]) ? $_POST["{$type}_street"] : $_POST['owner_street'];
                        $city = !empty($_POST["{$type}_city"]) ? $_POST["{$type}_city"] : $_POST['owner_city'];
                        $zipcode = !empty($_POST["{$type}_zipcode"]) ? $_POST["{$type}_zipcode"] : $_POST['owner_zipcode'];
                        $country = !empty($_POST["{$type}_country"]) ? $_POST["{$type}_country"] : $_POST['owner_country'];
                        $number = !empty($_POST["{$type}_number"]) ? $_POST["{$type}_number"] : ($_POST['owner_number'] ?? '');
                        $state = !empty($_POST["{$type}_state"]) ? $_POST["{$type}_state"] : ($_POST['owner_state'] ?? '');

                        // Obtener o crear contacto (reutiliza si ya existe por email)
                        $handles[$type] = $openProvider->getOrCreateContact([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'email' => $email,
                            'phone' => $phone,
                            'phone_code' => $phoneCode,
                            'address' => $street,
                            'address_number' => $number,
                            'city' => $city,
                            'state' => $state,
                            'zipcode' => $zipcode,
                            'country' => $country
                        ]);
                    } else {
                        // Usar el mismo que owner
                        $handles[$type] = $handles['owner'];
                    }
                }
            }

            // Guardar opciones de hosting y nameservers
            $hostingType = $_POST['hosting_type'] ?? 'musedock_hosting';
            $nsType = $_POST['ns_type'] ?? 'cloudflare';
            $customNs = null;

            if ($nsType === 'custom') {
                $customNsArray = array_filter($_POST['custom_ns'] ?? [], fn($ns) => !empty(trim($ns)));
                if (count($customNsArray) >= 2) {
                    $customNs = json_encode(array_values($customNsArray));
                }
            }

            // Guardar todo en sesión
            $_SESSION['domain_registration']['handles'] = $handles;
            $_SESSION['domain_registration']['hosting_type'] = $hostingType;
            $_SESSION['domain_registration']['ns_type'] = $nsType;
            $_SESSION['domain_registration']['custom_ns'] = $customNs;
            $_SESSION['domain_registration']['use_cloudflare_ns'] = ($nsType === 'cloudflare');

            Logger::info("[DomainRegistration] Contacts saved. Owner: {$handles['owner']}, Hosting: {$hostingType}, NS: {$nsType}");

            $this->jsonResponse([
                'success' => true,
                'handles' => $handles,
                'redirect' => '/customer/register-domain/checkout'
            ]);

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
            $handles = $domainData['handles'] ?? [];
            $hostingType = $domainData['hosting_type'] ?? 'musedock_hosting';
            $nsType = $domainData['ns_type'] ?? 'cloudflare';
            $customNs = $domainData['custom_ns'] ?? null;
            $useCloudflareNs = $domainData['use_cloudflare_ns'] ?? true;

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

            // Usar handles de sesión o el del contacto como fallback
            $ownerHandle = $handles['owner'] ?? $contact['openprovider_handle'];
            $adminHandle = $handles['admin'] ?? $ownerHandle;
            $techHandle = $handles['tech'] ?? $ownerHandle;
            $billingHandle = $handles['billing'] ?? $ownerHandle;

            $pdo->beginTransaction();

            try {
                // 1. Crear orden en estado 'processing'
                $stmt = $pdo->prepare("
                    INSERT INTO domain_orders (
                        customer_id, domain, extension, openprovider_contact_handle,
                        owner_handle, admin_handle, tech_handle, billing_handle,
                        hosting_type, custom_nameservers, use_cloudflare_ns,
                        price_amount, price_currency, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processing')
                ");
                $stmt->execute([
                    $customerId,
                    $domainName,  // Solo el nombre sin extensión (ej: "caracola22" no "caracola22.com")
                    $extension,
                    $ownerHandle,
                    $ownerHandle,
                    $adminHandle,
                    $techHandle,
                    $billingHandle,
                    $hostingType,
                    $customNs,
                    $useCloudflareNs ? 1 : 0,
                    $price,
                    $currency
                ]);
                $orderId = $pdo->lastInsertId();

                Logger::info("[DomainRegistration] Order created. ID: {$orderId}, Domain: {$domain} (name: {$domainName}, ext: {$extension}), Hosting: {$hostingType}");

                // 2. Determinar nameservers
                $nameservers = [];
                if ($nsType === 'custom' && $customNs) {
                    $customNsArray = json_decode($customNs, true);
                    foreach ($customNsArray as $ns) {
                        $nameservers[] = ['name' => $ns];
                    }
                } else {
                    // Cloudflare nameservers - se actualizarán después de crear la zona
                    $nameservers = [
                        ['name' => 'aria.ns.cloudflare.com'],
                        ['name' => 'dom.ns.cloudflare.com']
                    ];
                }

                // 3. Registrar dominio en OpenProvider
                $openProvider = new OpenProviderService();

                $registrationResult = $openProvider->registerDomain(
                    $domainName,
                    $extension,
                    $ownerHandle,
                    $nameservers,
                    1,
                    [
                        'admin_handle' => $adminHandle,
                        'tech_handle' => $techHandle,
                        'billing_handle' => $billingHandle,
                        'autorenew' => 'on'
                    ]
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

                // 4. Configurar Cloudflare según tipo de hosting
                $cloudflareResult = null;
                $tenantId = null;
                $openproviderDomainId = $registrationResult['id'] ?? null;

                if ($useCloudflareNs) {
                    $cloudflareResult = $this->configureCloudflare($domain, $orderId, $pdo, $hostingType);

                    // 4.1 Actualizar nameservers en OpenProvider con los asignados por Cloudflare
                    if ($openproviderDomainId && !empty($cloudflareResult['nameservers'])) {
                        try {
                            $openProvider->updateDomainNameservers($openproviderDomainId, $cloudflareResult['nameservers']);
                            Logger::info("[DomainRegistration] Nameservers updated in OpenProvider: " . implode(', ', $cloudflareResult['nameservers']));
                        } catch (Exception $nsEx) {
                            // No es crítico, los NS se pueden actualizar luego manualmente
                            Logger::warning("[DomainRegistration] Could not update nameservers in OpenProvider: " . $nsEx->getMessage());
                        }
                    }
                }

                // 5. Crear tenant solo si es musedock_hosting
                if ($hostingType === 'musedock_hosting') {
                    $tenantId = $this->createTenant($customerId, $domain, $orderId, $pdo);

                    // Actualizar orden con tenant_id
                    $stmt = $pdo->prepare("UPDATE domain_orders SET tenant_id = ? WHERE id = ?");
                    $stmt->execute([$tenantId, $orderId]);
                }

                $pdo->commit();

                // Limpiar sesión de registro
                unset($_SESSION['domain_registration']);

                // Enviar email de confirmación
                $this->sendConfirmationEmail($customerId, $domain, $cloudflareResult['nameservers'] ?? [], $hostingType);

                Logger::info("[DomainRegistration] Registration complete. Order: {$orderId}, Tenant: " . ($tenantId ?? 'N/A'));

                $this->jsonResponse([
                    'success' => true,
                    'order_id' => $orderId,
                    'domain' => $domain,
                    'tenant_id' => $tenantId,
                    'hosting_type' => $hostingType,
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
     *
     * @param string $domain Nombre del dominio
     * @param int $orderId ID de la orden
     * @param PDO $pdo Conexión a base de datos
     * @param string $hostingType Tipo: 'musedock_hosting' o 'dns_only'
     */
    private function configureCloudflare(string $domain, int $orderId, PDO $pdo, string $hostingType = 'musedock_hosting'): array
    {
        Logger::info("[DomainRegistration] Configuring Cloudflare for {$domain}, hosting type: {$hostingType}");

        try {
            $cloudflare = new CloudflareZoneService();

            // Crear zona en Cloudflare
            $zoneResult = $cloudflare->addFullZone($domain);

            // Crear registros DNS solo si es musedock_hosting
            if ($hostingType === 'musedock_hosting') {
                // Crear CNAMEs apuntando al servidor de MuseDock
                $cloudflare->createCNAMEIfNotExists($zoneResult['zone_id'], '@', 'mortadelo.musedock.com', true);
                $cloudflare->createCNAMEIfNotExists($zoneResult['zone_id'], 'www', 'mortadelo.musedock.com', true);
                Logger::info("[DomainRegistration] DNS records created for {$domain} -> mortadelo.musedock.com");
            } else {
                // dns_only: Solo crear la zona sin registros
                Logger::info("[DomainRegistration] Zone created without DNS records (dns_only mode)");
            }

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
