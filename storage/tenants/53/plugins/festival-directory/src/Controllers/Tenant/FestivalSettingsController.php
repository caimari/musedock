<?php

namespace FestivalDirectory\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class FestivalSettingsController
{
    private function getTenantId(): int
    {
        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            flash('error', 'Tenant no identificado.');
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }
        return $tenantId;
    }

    private function getSettings(int $tenantId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT settings FROM tenant_plugins WHERE tenant_id = ? AND slug = 'festival-directory'");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row && $row['settings'] ? json_decode($row['settings'], true) : [];
    }

    private function saveSettings(int $tenantId, array $settings): void
    {
        $pdo = Database::connect();
        $pdo->prepare("UPDATE tenant_plugins SET settings = ?, updated_at = NOW() WHERE tenant_id = ? AND slug = 'festival-directory'")
            ->execute([json_encode($settings), $tenantId]);
    }

    /**
     * Settings page.
     */
    public function index()
    {
        $tenantId = $this->getTenantId();
        $settings = $this->getSettings($tenantId);

        echo festival_render_admin('tenant.settings.index', [
            'title'    => 'Configuración — Festival Directory',
            'settings' => $settings,
        ]);
    }

    /**
     * Save settings.
     */
    public function save()
    {
        $tenantId = $this->getTenantId();
        $data = $_POST;
        unset($data['_token'], $data['_method']);

        $settings = $this->getSettings($tenantId);

        // Proxy
        $settings['scraper_proxy'] = trim($data['scraper_proxy'] ?? '');
        if (empty($settings['scraper_proxy'])) {
            unset($settings['scraper_proxy']);
        }

        // Scraper rate limit (seconds between requests)
        $settings['scraper_delay'] = max(1, (int)($data['scraper_delay'] ?? 2));

        // Default festival status on import
        $settings['import_default_status'] = in_array($data['import_default_status'] ?? '', ['draft', 'published']) ? $data['import_default_status'] : 'draft';

        $this->saveSettings($tenantId, $settings);

        flash('success', 'Configuración guardada correctamente.');
        header('Location: ' . festival_admin_url('settings'));
        exit;
    }

    /**
     * Test proxy connection (AJAX).
     */
    public function testProxy()
    {
        $this->getTenantId();

        header('Content-Type: application/json');

        $proxy = trim($_POST['proxy'] ?? '');

        if (empty($proxy)) {
            echo json_encode(['success' => false, 'message' => 'No se ha configurado ningún proxy.']);
            return;
        }

        // Validate format
        if (!preg_match('#^(https?|socks[45])://#i', $proxy)) {
            echo json_encode(['success' => false, 'message' => 'Formato inválido. Use: http://host:port, socks5://user:pass@host:port']);
            return;
        }

        // Test connection through proxy
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => 'https://httpbin.org/ip',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_PROXY          => $proxy,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (ProxyTest)',
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if (str_starts_with($proxy, 'socks5')) {
            $opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
        } elseif (str_starts_with($proxy, 'socks4')) {
            $opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS4;
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $totalTime = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 2);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $json = json_decode($response, true);
            $proxyIp = $json['origin'] ?? 'desconocida';

            // Get server's real IP for comparison
            $realCh = curl_init('https://httpbin.org/ip');
            curl_setopt_array($realCh, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
            $realResponse = curl_exec($realCh);
            curl_close($realCh);
            $realJson = json_decode($realResponse ?: '{}', true);
            $serverIp = $realJson['origin'] ?? 'desconocida';

            $isMasked = $proxyIp !== $serverIp;

            echo json_encode([
                'success'   => true,
                'message'   => 'Proxy funcionando correctamente.',
                'proxy_ip'  => $proxyIp,
                'server_ip' => $serverIp,
                'masked'    => $isMasked,
                'time'      => $totalTime . 's',
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al conectar: ' . ($error ?: "HTTP {$httpCode}"),
            ]);
        }
    }

    /**
     * Fetch free public proxies (AJAX) — server-side to avoid CORS.
     */
    public function freeProxies()
    {
        $this->getTenantId();

        header('Content-Type: application/json');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.proxyscrape.com/v4/free-proxy-list/get?request=display_proxies&proxy_format=protocolipport&format=text&protocol=http&timeout=5000&limit=10',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $proxies = array_filter(array_map('trim', explode("\n", trim($response))));
            echo json_encode(['success' => true, 'proxies' => array_values($proxies)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo obtener la lista de proxies públicos.']);
        }
    }
}
