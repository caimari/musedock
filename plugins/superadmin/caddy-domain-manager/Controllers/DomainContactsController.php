<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use CaddyDomainManager\Services\OpenProviderService;
use PDO;
use Exception;

/**
 * DomainContactsController
 *
 * Gestiona la visualización y edición de contactos (handles) de dominios registrados.
 * Permite al cliente actualizar los datos de owner, admin, tech y billing contacts.
 *
 * @package CaddyDomainManager\Controllers
 */
class DomainContactsController
{
    /**
     * Mostrar contactos del dominio
     */
    public function show(int $id): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener orden del dominio
            $stmt = $pdo->prepare("
                SELECT do.*, t.domain as tenant_domain
                FROM domain_orders do
                LEFT JOIN tenants t ON t.id = do.tenant_id
                WHERE do.id = ? AND do.customer_id = ?
            ");
            $stmt->execute([$id, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $_SESSION['flash_error'] = 'Dominio no encontrado';
                header('Location: /customer/dashboard');
                exit;
            }

            // Obtener datos del dominio desde OpenProvider si está activo
            $domainInfo = null;
            $contactDetails = [];

            if ($order['status'] === 'active' && !empty($order['openprovider_domain_id'])) {
                try {
                    $openProvider = new OpenProviderService();
                    $domainInfo = $openProvider->getDomainDetails((int)$order['openprovider_domain_id']);

                    // Obtener detalles de cada handle
                    $handleTypes = ['owner_handle', 'admin_handle', 'tech_handle', 'billing_handle'];
                    foreach ($handleTypes as $handleType) {
                        $handle = $order[$handleType] ?? null;
                        if ($handle) {
                            try {
                                $contactDetails[$handleType] = $openProvider->getContact($handle);
                            } catch (Exception $e) {
                                Logger::warning("[DomainContacts] Could not fetch handle {$handle}: " . $e->getMessage());
                            }
                        }
                    }
                } catch (Exception $e) {
                    Logger::error("[DomainContacts] Error fetching domain info: " . $e->getMessage());
                }
            }

            // Obtener contactos locales del cliente
            $stmt = $pdo->prepare("
                SELECT * FROM domain_contacts
                WHERE customer_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$customerId]);
            $localContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener modo OpenProvider
            $openProviderMode = strtolower(\Screenart\Musedock\Env::get('OPENPROVIDER_MODE', 'sandbox'));

            echo View::renderTheme('Customer.domain-contacts', [
                'customer' => $_SESSION['customer'],
                'order' => $order,
                'domainInfo' => $domainInfo,
                'contactDetails' => $contactDetails,
                'localContacts' => $localContacts,
                'csrf_token' => csrf_token(),
                'openprovider_mode' => $openProviderMode
            ]);

        } catch (Exception $e) {
            Logger::error("[DomainContacts] Error: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al cargar contactos del dominio';
            header('Location: /customer/dashboard');
            exit;
        }
    }

    /**
     * Actualizar contactos del dominio (AJAX)
     */
    public function update(int $id): void
    {
        header('Content-Type: application/json');

        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            return;
        }

        try {
            $pdo = Database::connect();

            // Obtener orden del dominio
            $stmt = $pdo->prepare("
                SELECT * FROM domain_orders
                WHERE id = ? AND customer_id = ? AND status = 'active'
            ");
            $stmt->execute([$id, $customerId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Dominio no encontrado o no activo']);
                return;
            }

            if (empty($order['openprovider_domain_id'])) {
                echo json_encode(['success' => false, 'message' => 'El dominio no tiene ID de OpenProvider']);
                return;
            }

            $openProvider = new OpenProviderService();

            // Recoger los handles a actualizar
            $handles = [];
            $handleTypes = ['owner_handle', 'admin_handle', 'tech_handle', 'billing_handle'];

            foreach ($handleTypes as $handleType) {
                if (!empty($_POST[$handleType])) {
                    $handles[$handleType] = trim($_POST[$handleType]);
                }
            }

            if (empty($handles)) {
                echo json_encode(['success' => false, 'message' => 'No se especificaron handles a actualizar']);
                return;
            }

            // Si se envían datos de nuevo contacto, crear el handle primero
            if (!empty($_POST['create_new_contact']) && $_POST['create_new_contact'] === 'true') {
                $contactType = $_POST['new_contact_type'] ?? 'owner_handle';

                // Validar datos del contacto
                $requiredFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'country'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST['new_' . $field])) {
                        echo json_encode(['success' => false, 'message' => "Campo requerido: {$field}"]);
                        return;
                    }
                }

                // Crear o reutilizar handle
                $newHandle = $openProvider->getOrCreateContact([
                    'first_name' => $_POST['new_first_name'],
                    'last_name' => $_POST['new_last_name'],
                    'company_name' => $_POST['new_company_name'] ?? '',
                    'email' => $_POST['new_email'],
                    'phone' => [
                        'country_code' => $_POST['new_phone_country'] ?? '+34',
                        'area_code' => '',
                        'subscriber_number' => preg_replace('/[^0-9]/', '', $_POST['new_phone'])
                    ],
                    'address' => [
                        'street' => $_POST['new_address'],
                        'number' => $_POST['new_address_number'] ?? '',
                        'zipcode' => $_POST['new_zipcode'] ?? '',
                        'city' => $_POST['new_city'],
                        'country' => $_POST['new_country']
                    ],
                    'vat' => $_POST['new_vat'] ?? null
                ]);

                $handles[$contactType] = $newHandle;

                // Guardar en contactos locales
                $this->saveLocalContact($pdo, $customerId, $newHandle, $_POST);
            }

            // Actualizar en OpenProvider
            $result = $openProvider->updateDomainContacts(
                (int)$order['openprovider_domain_id'],
                $handles
            );

            if ($result) {
                // Actualizar en base de datos local
                $updateFields = [];
                $updateValues = [];

                foreach ($handles as $type => $handle) {
                    $updateFields[] = "{$type} = ?";
                    $updateValues[] = $handle;
                }

                $updateValues[] = $id;

                $stmt = $pdo->prepare("
                    UPDATE domain_orders
                    SET " . implode(', ', $updateFields) . ", updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute($updateValues);

                Logger::info("[DomainContacts] Updated contacts for order {$id}");

                echo json_encode([
                    'success' => true,
                    'message' => 'Contactos actualizados correctamente',
                    'handles' => $handles
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar en OpenProvider']);
            }

        } catch (Exception $e) {
            Logger::error("[DomainContacts] Update error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Obtener detalles de un handle (AJAX)
     */
    public function getContactDetails(string $handle): void
    {
        header('Content-Type: application/json');

        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            return;
        }

        try {
            $openProvider = new OpenProviderService();
            $contact = $openProvider->getContact($handle);

            if ($contact) {
                echo json_encode([
                    'success' => true,
                    'contact' => $contact
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Contacto no encontrado']);
            }

        } catch (Exception $e) {
            Logger::error("[DomainContacts] Error fetching handle {$handle}: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Guardar contacto en base de datos local
     */
    private function saveLocalContact(PDO $pdo, int $customerId, string $handle, array $data): void
    {
        try {
            // Verificar si ya existe
            $stmt = $pdo->prepare("
                SELECT id FROM domain_contacts
                WHERE customer_id = ? AND openprovider_handle = ?
            ");
            $stmt->execute([$customerId, $handle]);

            if ($stmt->fetch()) {
                return; // Ya existe
            }

            // Insertar nuevo
            $stmt = $pdo->prepare("
                INSERT INTO domain_contacts (
                    customer_id, first_name, last_name, company_name, email, phone,
                    address, city, state, zipcode, country, vat_number,
                    openprovider_handle, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $customerId,
                $data['new_first_name'] ?? '',
                $data['new_last_name'] ?? '',
                $data['new_company_name'] ?? null,
                $data['new_email'] ?? '',
                $data['new_phone'] ?? '',
                $data['new_address'] ?? '',
                $data['new_city'] ?? '',
                $data['new_state'] ?? null,
                $data['new_zipcode'] ?? '',
                $data['new_country'] ?? '',
                $data['new_vat'] ?? null,
                $handle
            ]);

        } catch (Exception $e) {
            Logger::warning("[DomainContacts] Could not save local contact: " . $e->getMessage());
        }
    }
}
