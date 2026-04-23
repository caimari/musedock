<?php

namespace Modules\InstagramGallery\Controllers\Tenant;

use Modules\InstagramGallery\Models\InstagramSetting;
use Modules\InstagramGallery\Models\InstagramConnection;
use Screenart\Musedock\View;

class SettingsController
{
    private $pdo;
    private $tenantId;

    public function __construct()
    {
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramSetting::setPdo($this->pdo);

        $this->tenantId = function_exists('tenant_id') ? (tenant_id() ?? 1) : 1;
    }

    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __instagram('errors.permission_denied'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }
    }

    public function index()
    {
        $this->checkPermission('instagram.settings');

        $settings = InstagramSetting::getAll($this->tenantId);
        $layouts = InstagramSetting::getAvailableLayouts();

        InstagramConnection::setPdo($this->pdo);
        $connections = InstagramConnection::getActiveByTenant($this->tenantId);

        return View::renderModule('instagram-gallery', 'tenant.instagram.settings', [
            'settings' => $settings,
            'layouts' => $layouts,
            'tenantId' => $this->tenantId,
            'connections' => $connections,
        ]);
    }

    public function update()
    {
        $this->checkPermission('instagram.settings');

        try {
            $data = $_POST;

            // Mode
            if (isset($data['instagram_mode'])) {
                InstagramSetting::set('instagram_mode', $data['instagram_mode'], $this->tenantId, 'string');
            }

            // API Credentials
            if (isset($data['instagram_app_id'])) {
                InstagramSetting::set('instagram_app_id', $data['instagram_app_id'], $this->tenantId, 'string');
            }
            if (isset($data['instagram_app_secret'])) {
                InstagramSetting::set('instagram_app_secret', $data['instagram_app_secret'], $this->tenantId, 'string');
            }
            if (isset($data['instagram_redirect_uri'])) {
                InstagramSetting::set('instagram_redirect_uri', $data['instagram_redirect_uri'], $this->tenantId, 'string');
            }

            // Display settings
            if (isset($data['default_layout'])) {
                InstagramSetting::set('default_layout', $data['default_layout'], $this->tenantId, 'string');
            }
            if (isset($data['default_columns'])) {
                InstagramSetting::set('default_columns', (int) $data['default_columns'], $this->tenantId, 'int');
            }
            if (isset($data['default_gap'])) {
                InstagramSetting::set('default_gap', (int) $data['default_gap'], $this->tenantId, 'int');
            }
            if (isset($data['max_posts_per_gallery'])) {
                InstagramSetting::set('max_posts_per_gallery', (int) $data['max_posts_per_gallery'], $this->tenantId, 'int');
            }

            // Cache settings
            if (isset($data['cache_duration_hours'])) {
                InstagramSetting::set('cache_duration_hours', (int) $data['cache_duration_hours'], $this->tenantId, 'int');
            }
            InstagramSetting::set('auto_refresh_tokens', isset($data['auto_refresh_tokens']) ? 1 : 0, $this->tenantId, 'bool');

            if (isset($data['token_refresh_threshold_days'])) {
                InstagramSetting::set('token_refresh_threshold_days', (int) $data['token_refresh_threshold_days'], $this->tenantId, 'int');
            }

            // Display options
            InstagramSetting::set('show_captions', isset($data['show_captions']) ? 1 : 0, $this->tenantId, 'bool');

            if (isset($data['caption_max_length'])) {
                InstagramSetting::set('caption_max_length', (int) $data['caption_max_length'], $this->tenantId, 'int');
            }

            InstagramSetting::set('enable_lightbox', isset($data['enable_lightbox']) ? 1 : 0, $this->tenantId, 'bool');
            InstagramSetting::set('enable_lazy_loading', isset($data['enable_lazy_loading']) ? 1 : 0, $this->tenantId, 'bool');

            // Layout options
            if (isset($data['hover_effect'])) {
                InstagramSetting::set('hover_effect', $data['hover_effect'], $this->tenantId, 'string');
            }
            if (isset($data['border_radius'])) {
                InstagramSetting::set('border_radius', (int) $data['border_radius'], $this->tenantId, 'int');
            }
            if (isset($data['image_aspect_ratio'])) {
                InstagramSetting::set('image_aspect_ratio', $data['image_aspect_ratio'], $this->tenantId, 'string');
            }

            $_SESSION['success'] = __instagram('settings.settings_saved');

        } catch (\Exception $e) {
            error_log('Instagram settings error: ' . $e->getMessage());
            $_SESSION['error'] = __instagram('settings.settings_error') . ': ' . $e->getMessage();
        }

        redirect('/' . admin_path() . '/instagram/settings');
    }

    /**
     * AJAX: comprueba que el App ID de Instagram es válido y que el
     * Redirect URI configurado coincide con el guardado en la app de Meta.
     *
     * La nueva «Instagram API con login de empresa» NO tiene endpoint de
     * validación standalone (a diferencia de las apps de Facebook). El App
     * Secret sólo se puede validar de verdad durante el flujo OAuth real.
     * Lo que sí podemos validar sin login del usuario es:
     *   - Que el App ID existe (Instagram contesta con la página de OAuth).
     *   - Que el Redirect URI está registrado (Instagram valida el dominio).
     */
    public function testCredentials()
    {
        header('Content-Type: application/json; charset=utf-8');

        $appId = trim($_POST['instagram_app_id'] ?? '');
        $appSecret = trim($_POST['instagram_app_secret'] ?? '');
        $redirectUri = trim($_POST['instagram_redirect_uri'] ?? '');

        // Si los campos llegan vacíos del POST porque están bloqueados (disabled),
        // intentamos recuperarlos de los settings ya guardados.
        if ($appId === '' || $appSecret === '' || $redirectUri === '') {
            $settings = InstagramSetting::getAll($this->tenantId);
            $appId = $appId !== '' ? $appId : ($settings['instagram_app_id'] ?? '');
            $appSecret = $appSecret !== '' ? $appSecret : ($settings['instagram_app_secret'] ?? '');
            $redirectUri = $redirectUri !== '' ? $redirectUri : ($settings['instagram_redirect_uri'] ?? '');
        }

        if ($appId === '' || $appSecret === '') {
            echo json_encode(['ok' => false, 'message' => 'Faltan App ID o App Secret']);
            return;
        }
        if (!preg_match('/^[0-9]{6,}$/', $appId)) {
            echo json_encode(['ok' => false, 'message' => 'El App ID debe ser numérico (es el «Identificador de la aplicación de Instagram», no el App Secret).']);
            return;
        }
        if (strlen($appSecret) < 20) {
            echo json_encode(['ok' => false, 'message' => 'El App Secret parece demasiado corto. Debe ser la «Clave secreta de la aplicación de Instagram» (32 caracteres aprox.).']);
            return;
        }
        if ($redirectUri === '') {
            echo json_encode(['ok' => false, 'message' => 'Falta el Redirect URI']);
            return;
        }

        // Pedimos a Instagram la URL de autorización con un HEAD request:
        // si el App ID o el Redirect URI no son válidos, Instagram redirige
        // a una URL de error. Si son válidos, redirige a la página de login.
        $url = 'https://www.instagram.com/oauth/authorize?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => 'instagram_business_basic',
            'response_type' => 'code',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 MuseDock-IG-Test/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            echo json_encode(['ok' => false, 'message' => 'Error de red contactando con Instagram: ' . $curlErr]);
            return;
        }

        $body = (string) $response;
        $combined = ($redirectUrl ?: '') . ' ' . $body;

        // Patrones de error que devuelve Instagram cuando algo está mal
        $patterns = [
            '/Invalid platform app/i'                  => 'Instagram dice «Invalid platform app»: el App ID no existe o la app no tiene activado el producto «API con inicio de sesión para empresas de Instagram». Comprueba el App ID y vuelve a intentarlo.',
            '/redirect_uri.+(invalid|not.+valid|does.+not.+match|mismatch)/i'
                                                       => 'Instagram dice que el Redirect URI no coincide con el registrado en Meta. Pega exactamente la URL de OAuth Redirect que te muestra el panel en el campo «Valid OAuth Redirect URIs» de Meta y guarda.',
            '/Invalid redirect_uri/i'                  => 'Instagram rechaza el Redirect URI. Pega exactamente la URL que muestra el panel en «Valid OAuth Redirect URIs» de Meta.',
            '/error_message=([^&]+)/'                  => null, // capturamos abajo
        ];
        foreach ($patterns as $regex => $msg) {
            if (preg_match($regex, $combined, $m)) {
                if ($msg === null) {
                    $msg = 'Instagram devolvió un error: ' . urldecode($m[1]);
                }
                echo json_encode(['ok' => false, 'message' => $msg]);
                return;
            }
        }

        // 200 con HTML de login o 302 a instagram.com/accounts/login → App ID OK
        $looksOk = (
            $httpCode === 200
            || ($httpCode >= 300 && $httpCode < 400 && stripos((string)$redirectUrl, 'instagram.com') !== false)
        );
        if ($looksOk) {
            echo json_encode([
                'ok' => true,
                'message' => 'App ID válido. IMPORTANTE: el Redirect URI y el App Secret sólo se validan al pulsar «Conectar cuenta». Antes asegúrate de haber pegado este Redirect URI en «Valid OAuth Redirect URIs» dentro de Meta (Paso 2 del acordeón).'
            ]);
            return;
        }

        echo json_encode([
            'ok' => false,
            'message' => 'Respuesta inesperada de Instagram (HTTP ' . $httpCode . '). Comprueba el App ID y el Redirect URI.'
        ]);
    }
}
