<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\AcmeAssistantService;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\View;

class AcmeAssistantController
{
    use RequiresPermission;

    protected AcmeAssistantService $service;

    public function __construct(?AcmeAssistantService $service = null)
    {
        $this->service = $service ?: new AcmeAssistantService();
    }

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $firewall = $this->service->detectFirewallState();
        $providers = $this->service->listDnsProviders();
        $providerStatuses = [];
        foreach (($providers['available'] ?? []) as $provider) {
            $providerStatuses[$provider] = $this->service->providerCredentialStatus((string)$provider);
        }
        $acmeStatus = $this->service->getAcmeStatusSnapshot();

        return View::renderSuperadmin('settings.acme-assistant', [
            'title' => 'Firewall y Let\'s Encrypt',
            'firewall' => $firewall,
            'providers' => $providers,
            'providerStatuses' => $providerStatuses,
            'acmeStatus' => $acmeStatus,
            'defaultTtlSeconds' => $this->service->defaultTemporaryTtlSeconds(),
        ]);
    }

    public function status()
    {
        $this->jsonHeaders();

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.view');

            $firewall = $this->service->detectFirewallState();
            $providers = $this->service->listDnsProviders();
            $providerStatuses = [];
            foreach (($providers['available'] ?? []) as $provider) {
                $providerStatuses[$provider] = $this->service->providerCredentialStatus((string)$provider);
            }
            $acmeStatus = $this->service->getAcmeStatusSnapshot();

            echo json_encode([
                'success' => true,
                'firewall' => $firewall,
                'providers' => $providers,
                'provider_statuses' => $providerStatuses,
                'acme_status' => $acmeStatus,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function providerCheck()
    {
        $this->jsonHeaders();

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.view');

            $payload = $this->jsonInput();
            $provider = (string)($payload['provider'] ?? 'cloudflare');
            $check = $this->service->providerPostInstallCheck($provider);

            echo json_encode([
                'success' => true,
                'check' => $check,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function dryRun()
    {
        $this->jsonHeaders();

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.edit');

            $payload = $this->jsonInput();
            $result = $this->service->dryRun([
                'domain' => $payload['domain'] ?? '',
                'challenge' => $payload['challenge'] ?? 'auto',
                'provider' => $payload['provider'] ?? 'cloudflare',
            ]);
            $this->service->recordAssistantEvent(
                ($result['can_proceed'] ?? false) ? 'success' : 'warning',
                'Dry-run ACME',
                ($result['can_proceed'] ?? false)
                    ? 'Dry-run válido, listo para continuar.'
                    : 'Dry-run detectó bloqueos previos.',
                [
                    'domain' => $result['domain'] ?? '',
                    'challenge' => $result['challenge'] ?? '',
                    'provider' => $result['provider'] ?? '',
                    'actions' => $result['actions'] ?? [],
                    'reasons' => $result['reasons'] ?? [],
                ]
            );

            echo json_encode(['success' => true, 'result' => $result]);
        } catch (\Throwable $e) {
            http_response_code(500);
            $this->service->recordAssistantEvent('error', 'Dry-run ACME', 'Error ejecutando dry-run ACME.', [
                'error' => $e->getMessage(),
            ]);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function openTemporaryPorts()
    {
        $this->jsonHeaders();

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.edit');

            $payload = $this->jsonInput();
            $validated = $this->service->validateTemporaryPortRequest($payload, true);
            if (!($validated['ok'] ?? false)) {
                throw new \Exception((string)($validated['message'] ?? 'Solicitud inválida.'));
            }

            $this->assertSuperadminPassword((string)$validated['password']);
            $open = $this->service->openTemporaryPorts((int)$validated['ttl_seconds'], (string)$validated['ticket_id']);
            $state = $this->service->loadTemporaryRuleState((string)$validated['ticket_id']);

            if (!$open['success']) {
                http_response_code(422);
            }
            $this->service->recordAssistantEvent(
                $open['success'] ? 'success' : 'warning',
                'Apertura temporal ACME',
                (string)($open['message'] ?? 'Operación completada'),
                [
                    'ticket_id' => $validated['ticket_id'],
                    'opened_ports' => $open['opened_ports'] ?? [],
                    'errors' => $open['errors'] ?? [],
                    'expires_at' => $state['expires_at'] ?? ($open['expires_at'] ?? null),
                ]
            );

            echo json_encode([
                'success' => $open['success'],
                'message' => $open['message'],
                'opened_ports' => $open['opened_ports'] ?? [],
                'errors' => $open['errors'] ?? [],
                'expires_at' => $state['expires_at'] ?? ($open['expires_at'] ?? null),
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            $this->service->recordAssistantEvent('error', 'Apertura temporal ACME', 'Error al abrir puertos temporales.', [
                'error' => $e->getMessage(),
            ]);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function closeTemporaryPorts()
    {
        $this->jsonHeaders();

        try {
            SessionSecurity::startSession();
            $this->checkPermission('settings.edit');

            $payload = $this->jsonInput();
            $validated = $this->service->validateTemporaryPortRequest($payload, true);
            if (!($validated['ok'] ?? false)) {
                throw new \Exception((string)($validated['message'] ?? 'Solicitud inválida.'));
            }

            $this->assertSuperadminPassword((string)$validated['password']);
            $closed = $this->service->removeTemporaryPorts((string)$validated['ticket_id']);
            $this->service->recordAssistantEvent(
                'info',
                'Cierre temporal ACME',
                (string)($closed['message'] ?? 'Reglas temporales cerradas.'),
                [
                    'ticket_id' => $validated['ticket_id'],
                    'removed_ports' => $closed['removed_ports'] ?? [],
                ]
            );

            echo json_encode([
                'success' => $closed['success'],
                'message' => $closed['message'],
                'removed_ports' => $closed['removed_ports'] ?? [],
            ]);
        } catch (\Throwable $e) {
            http_response_code(422);
            $this->service->recordAssistantEvent('error', 'Cierre temporal ACME', 'Error al cerrar puertos temporales.', [
                'error' => $e->getMessage(),
            ]);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    protected function assertSuperadminPassword(string $password): void
    {
        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || ($auth['type'] ?? '') !== 'super_admin') {
            throw new \Exception('Solo un superadmin puede autorizar esta operación.');
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT password FROM super_admins WHERE id = ?');
        $stmt->execute([$auth['id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($password, (string)$hash)) {
            throw new \Exception('Contraseña de administrador incorrecta para asistencia ACME.');
        }
    }

    private function jsonInput(): array
    {
        $input = $GLOBALS['_JSON_INPUT'] ?? null;
        if (is_array($input)) {
            return $input;
        }

        $raw = file_get_contents('php://input');
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function jsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
}
