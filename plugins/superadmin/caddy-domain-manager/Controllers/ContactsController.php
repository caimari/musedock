<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use CaddyDomainManager\Services\OpenProviderService;
use PDO;
use Exception;

/**
 * ContactsController
 *
 * Gestiona el directorio de contactos del cliente.
 * Permite ver, editar y eliminar contactos (handles) de OpenProvider.
 * Muestra cuantos dominios estan asociados a cada handle.
 *
 * @package CaddyDomainManager\Controllers
 */
class ContactsController
{
    /**
     * Listar todos los contactos del cliente
     *
     * GET /customer/contacts
     */
    public function index(): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener todos los contactos del cliente con conteo de dominios asociados
            $stmt = $pdo->prepare("
                SELECT
                    dc.*,
                    (
                        SELECT COUNT(*)
                        FROM domain_orders dord
                        WHERE dord.customer_id = dc.customer_id
                          AND dord.status IN ('active', 'registered', 'processing')
                          AND (
                              dord.owner_handle = dc.openprovider_handle
                              OR dord.admin_handle = dc.openprovider_handle
                              OR dord.tech_handle = dc.openprovider_handle
                              OR dord.billing_handle = dc.openprovider_handle
                          )
                    ) as domains_count
                FROM domain_contacts dc
                WHERE dc.customer_id = ?
                ORDER BY dc.is_default DESC, dc.created_at DESC
            ");
            $stmt->execute([$customerId]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener detalles de dominios asociados para cada contacto
            foreach ($contacts as &$contact) {
                if ($contact['domains_count'] > 0) {
                    $stmt = $pdo->prepare("
                        SELECT
                            dord.id,
                            CASE
                                WHEN dord.domain LIKE CONCAT('%.',  dord.extension)
                                THEN dord.domain
                                ELSE CONCAT(dord.domain, '.', dord.extension)
                            END as full_domain,
                            CASE
                                WHEN dord.owner_handle = ? THEN 'owner'
                                WHEN dord.admin_handle = ? THEN 'admin'
                                WHEN dord.tech_handle = ? THEN 'tech'
                                WHEN dord.billing_handle = ? THEN 'billing'
                                ELSE 'unknown'
                            END as role
                        FROM domain_orders dord
                        WHERE dord.customer_id = ?
                          AND dord.status IN ('active', 'registered', 'processing')
                          AND (
                              dord.owner_handle = ?
                              OR dord.admin_handle = ?
                              OR dord.tech_handle = ?
                              OR dord.billing_handle = ?
                          )
                        ORDER BY dord.created_at DESC
                        LIMIT 10
                    ");
                    $handle = $contact['openprovider_handle'];
                    $stmt->execute([
                        $handle, $handle, $handle, $handle,
                        $customerId,
                        $handle, $handle, $handle, $handle
                    ]);
                    $contact['associated_domains'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $contact['associated_domains'] = [];
                }
            }

            // Lista de países y códigos de teléfono
            $countries = $this->getCountriesList();
            $phoneCodes = OpenProviderService::getPhoneCountryCodes();

            echo View::renderTheme('Customer.contacts', [
                'customer' => $_SESSION['customer'],
                'contacts' => $contacts,
                'countries' => $countries,
                'phoneCodes' => $phoneCodes,
                'page_title' => 'Mis Contactos - MuseDock',
                'current_page' => 'contacts',
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[ContactsController] Error listing contacts: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al cargar contactos';
            header('Location: /customer/dashboard');
            exit;
        }
    }

    /**
     * Obtener datos de un contacto para edicion (AJAX)
     *
     * GET /customer/contacts/{id}
     */
    public function get(int $id): void
    {
        header('Content-Type: application/json');

        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            return;
        }

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("
                SELECT * FROM domain_contacts
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$id, $customerId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contact) {
                echo json_encode(['success' => false, 'error' => 'Contacto no encontrado']);
                return;
            }

            echo json_encode(['success' => true, 'contact' => $contact]);

        } catch (Exception $e) {
            Logger::error("[ContactsController] Error getting contact: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al obtener contacto']);
        }
    }

    /**
     * Actualizar contacto (AJAX)
     *
     * POST /customer/contacts/{id}/update
     */
    public function update(int $id): void
    {
        header('Content-Type: application/json');

        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            return;
        }

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF invalido']);
            return;
        }

        try {
            $pdo = Database::connect();

            // Obtener contacto actual
            $stmt = $pdo->prepare("
                SELECT * FROM domain_contacts
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$id, $customerId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contact) {
                echo json_encode(['success' => false, 'error' => 'Contacto no encontrado']);
                return;
            }

            $handle = $contact['openprovider_handle'];

            // Contar dominios asociados
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM domain_orders
                WHERE customer_id = ?
                  AND status IN ('active', 'registered', 'processing')
                  AND (
                      owner_handle = ?
                      OR admin_handle = ?
                      OR tech_handle = ?
                      OR billing_handle = ?
                  )
            ");
            $stmt->execute([$customerId, $handle, $handle, $handle, $handle]);
            $domainsCount = (int)$stmt->fetchColumn();

            // Validar campos requeridos
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $street = trim($_POST['address_street'] ?? '');
            $city = trim($_POST['address_city'] ?? '');
            $zipcode = trim($_POST['address_zipcode'] ?? '');
            $country = trim($_POST['address_country'] ?? '');

            if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) ||
                empty($street) || empty($city) || empty($zipcode) || empty($country)) {
                echo json_encode(['success' => false, 'error' => 'Todos los campos obligatorios deben estar completos']);
                return;
            }

            // Actualizar en OpenProvider
            $openProvider = new OpenProviderService();

            $phoneCode = $_POST['phone_code'] ?? '34';
            $phoneNumber = preg_replace('/[^\d]/', '', $phone);

            // OpenProvider requiere area_code - extraer del numero
            $areaCode = '';
            if (strlen($phoneNumber) >= 6) {
                $areaCode = substr($phoneNumber, 0, 1);
                $phoneNumber = substr($phoneNumber, 1);
            }

            $contactData = [
                'name' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'initials' => strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1))
                ],
                'company_name' => trim($_POST['company'] ?? ''),
                'email' => $email,
                'phone' => [
                    'country_code' => '+' . preg_replace('/[^\d]/', '', $phoneCode),
                    'area_code' => $areaCode,
                    'subscriber_number' => $phoneNumber
                ],
                'address' => [
                    'street' => $street,
                    'number' => trim($_POST['address_number'] ?? ''),
                    'zipcode' => $zipcode,
                    'city' => $city,
                    'state' => trim($_POST['address_state'] ?? ''),
                    'country' => strtoupper($country)
                ]
            ];

            // Añadir VAT si existe
            $companyRegNumber = trim($_POST['company_reg_number'] ?? '');
            if (!empty($companyRegNumber)) {
                $contactData['vat'] = $companyRegNumber;
            }

            // Actualizar en OpenProvider
            $result = $openProvider->updateContact($handle, $contactData);

            if (!$result) {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar en OpenProvider']);
                return;
            }

            // Actualizar en base de datos local
            $stmt = $pdo->prepare("
                UPDATE domain_contacts SET
                    first_name = ?,
                    last_name = ?,
                    company = ?,
                    company_reg_number = ?,
                    email = ?,
                    phone = ?,
                    address_street = ?,
                    address_number = ?,
                    address_city = ?,
                    address_state = ?,
                    address_zipcode = ?,
                    address_country = ?,
                    updated_at = NOW()
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([
                $firstName,
                $lastName,
                trim($_POST['company'] ?? '') ?: null,
                $companyRegNumber ?: null,
                $email,
                $phone,
                $street,
                trim($_POST['address_number'] ?? '') ?: null,
                $city,
                trim($_POST['address_state'] ?? '') ?: null,
                $zipcode,
                $country,
                $id,
                $customerId
            ]);

            Logger::info("[ContactsController] Contact {$id} (handle: {$handle}) updated by customer {$customerId}. Affects {$domainsCount} domains.");

            echo json_encode([
                'success' => true,
                'message' => $domainsCount > 1
                    ? "Contacto actualizado correctamente. Los cambios afectan a {$domainsCount} dominios."
                    : 'Contacto actualizado correctamente'
            ]);

        } catch (Exception $e) {
            Logger::error("[ContactsController] Error updating contact: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Eliminar contacto (AJAX)
     *
     * POST /customer/contacts/{id}/delete
     */
    public function delete(int $id): void
    {
        header('Content-Type: application/json');

        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            return;
        }

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF invalido']);
            return;
        }

        try {
            $pdo = Database::connect();

            // Obtener contacto
            $stmt = $pdo->prepare("
                SELECT * FROM domain_contacts
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$id, $customerId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contact) {
                echo json_encode(['success' => false, 'error' => 'Contacto no encontrado']);
                return;
            }

            $handle = $contact['openprovider_handle'];

            // Verificar que no tenga dominios asociados
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM domain_orders
                WHERE customer_id = ?
                  AND status IN ('active', 'registered', 'processing')
                  AND (
                      owner_handle = ?
                      OR admin_handle = ?
                      OR tech_handle = ?
                      OR billing_handle = ?
                  )
            ");
            $stmt->execute([$customerId, $handle, $handle, $handle, $handle]);
            $domainsCount = (int)$stmt->fetchColumn();

            if ($domainsCount > 0) {
                echo json_encode([
                    'success' => false,
                    'error' => "No puedes eliminar este contacto porque esta asociado a {$domainsCount} dominio(s). Primero cambia el contacto en esos dominios."
                ]);
                return;
            }

            // Eliminar contacto local (no eliminamos en OpenProvider, solo localmente)
            $stmt = $pdo->prepare("DELETE FROM domain_contacts WHERE id = ? AND customer_id = ?");
            $stmt->execute([$id, $customerId]);

            Logger::info("[ContactsController] Contact {$id} (handle: {$handle}) deleted by customer {$customerId}");

            echo json_encode(['success' => true, 'message' => 'Contacto eliminado correctamente']);

        } catch (Exception $e) {
            Logger::error("[ContactsController] Error deleting contact: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al eliminar contacto']);
        }
    }

    /**
     * Establecer contacto como predeterminado (AJAX)
     *
     * POST /customer/contacts/{id}/set-default
     */
    public function setDefault(int $id): void
    {
        header('Content-Type: application/json');

        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            return;
        }

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF invalido']);
            return;
        }

        try {
            $pdo = Database::connect();

            // Verificar que el contacto existe y pertenece al cliente
            $stmt = $pdo->prepare("
                SELECT id FROM domain_contacts
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$id, $customerId]);

            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Contacto no encontrado']);
                return;
            }

            // Quitar default de todos los contactos del cliente
            $stmt = $pdo->prepare("
                UPDATE domain_contacts SET is_default = 0
                WHERE customer_id = ?
            ");
            $stmt->execute([$customerId]);

            // Establecer este como default
            $stmt = $pdo->prepare("
                UPDATE domain_contacts SET is_default = 1
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$id, $customerId]);

            Logger::info("[ContactsController] Contact {$id} set as default by customer {$customerId}");

            echo json_encode(['success' => true, 'message' => 'Contacto establecido como predeterminado']);

        } catch (Exception $e) {
            Logger::error("[ContactsController] Error setting default contact: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al establecer contacto predeterminado']);
        }
    }

    /**
     * Lista de paises (ISO 3166-1 alpha-2)
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
}
