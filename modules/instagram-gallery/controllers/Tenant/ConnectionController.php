<?php

namespace Modules\InstagramGallery\Controllers\Tenant;

use Modules\InstagramGallery\Models\InstagramConnection;
use Modules\InstagramGallery\Models\InstagramPost;
use Modules\InstagramGallery\Models\InstagramSetting;
use Modules\InstagramGallery\Services\InstagramApiService;
use Screenart\Musedock\View;
use Exception;

class ConnectionController
{
    protected $pdo;
    protected $tenantId;

    /**
     * Prefijo de URL del panel ("admin" para tenants, "musedock" para
     * el superadmin del CMS principal). El Superadmin controller lo
     * sobreescribe en su constructor.
     */
    protected string $basePath;

    /**
     * URL callback legacy ("/admin/instagram/callback" o
     * "/musedock/instagram/callback") — la registrada en Meta desde
     * versiones antiguas. La mantenemos para no romper OAuth.
     */
    protected string $legacyCallbackPath;

    public function __construct()
    {
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramConnection::setPdo($this->pdo);
        InstagramPost::setPdo($this->pdo);
        InstagramSetting::setPdo($this->pdo);

        // Por defecto: tenant. El Superadmin sobreescribe esto.
        $this->basePath = function_exists('admin_path') ? admin_path() : 'admin';
        $this->tenantId = function_exists('tenant_id') ? (tenant_id() ?? 1) : 1;
        $this->legacyCallbackPath = '/' . $this->basePath . '/instagram/callback';
    }

    /**
     * Verificar si el usuario actual tiene un permiso específico
     * Si no lo tiene, redirige con mensaje de error
     */
    protected function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __instagram('errors.permission_denied'));
            header('Location: /' . $this->basePath . '/dashboard');
            exit;
        }
    }

    /**
     * Devuelve true si la conexión pertenece al scope actual.
     * - Tenant: la conexión es suya (tenant_id === $this->tenantId) O global (null).
     * - Superadmin: la conexión es global (tenant_id === null) EXCLUSIVAMENTE.
     */
    protected function connectionBelongsToScope(InstagramConnection $conn): bool
    {
        if ($this->tenantId === null) {
            // Superadmin: sólo cuentas globales.
            return $conn->tenant_id === null;
        }
        // Tenant: cuentas propias o globales compartidas.
        return $conn->tenant_id === null || $conn->tenant_id === $this->tenantId;
    }

    /**
     * Redirect de /admin/social-publisher/settings → /admin/social-publisher
     * (la página antigua desapareció al fundir todo en una sola).
     */
    public function settingsRedirect()
    {
        redirect('/' . $this->basePath . '/social-publisher');
    }

    /**
     * Redirect de rutas antiguas (prefijo /admin/instagram) al nuevo
     * prefijo /admin/social-publisher. Mantiene bookmarks viejos.
     */
    public function legacyRedirect()
    {
        redirect('/' . $this->basePath . '/social-publisher');
    }

    /**
     * Página única del módulo: lista de cuentas con sus credenciales y
     * acciones integradas. Sustituye a la antigua pantalla de Settings.
     */
    public function index()
    {
        $this->checkPermission('instagram.view');
        $connections = InstagramConnection::getByTenant($this->tenantId, true);

        return View::renderModule('instagram-gallery', 'tenant.instagram.index', [
            'connections' => $connections,
            'tenantId' => $this->tenantId,
            'basePath' => $this->basePath,
        ]);
    }

    /**
     * Guardar (crear o actualizar) credenciales de una conexión.
     * Si no existe todavía la conexión (wizard "nueva cuenta"), crea un
     * borrador pendiente de OAuth. Si ya existe, actualiza sus credenciales.
     */
    public function saveCredentials()
    {
        $this->checkPermission('instagram.edit');
        header('Content-Type: application/json; charset=utf-8');

        $connectionId = (int)($_POST['connection_id'] ?? 0);
        $appId = trim($_POST['app_id'] ?? '');
        $appSecret = trim($_POST['app_secret'] ?? '');
        $redirectUri = trim($_POST['redirect_uri'] ?? '');

        if ($redirectUri === '') {
            $redirectUri = url($this->legacyCallbackPath);
        }
        if (!preg_match('/^[0-9]{6,}$/', $appId)) {
            echo json_encode(['ok' => false, 'message' => 'El App ID debe ser numérico.']);
            return;
        }
        if (strlen($appSecret) < 20) {
            echo json_encode(['ok' => false, 'message' => 'El App Secret parece demasiado corto.']);
            return;
        }

        if ($connectionId > 0) {
            $conn = InstagramConnection::find($connectionId);
            if (!$conn || !$this->connectionBelongsToScope($conn)) {
                echo json_encode(['ok' => false, 'message' => 'Conexión no encontrada']);
                return;
            }
            $conn->update([
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'redirect_uri' => $redirectUri,
            ]);
            echo json_encode(['ok' => true, 'message' => 'Credenciales guardadas.', 'connection_id' => $conn->id]);
            return;
        }

        // Crear borrador sin OAuth todavía (placeholder — se rellenará en el callback).
        $draftIgUserId = 'draft-' . bin2hex(random_bytes(6));
        $new = InstagramConnection::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $_SESSION['user_id'] ?? null,
            'app_id' => $appId,
            'app_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'instagram_user_id' => $draftIgUserId,
            'username' => 'pendiente',
            'access_token' => '',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 60), // se sobrescribe en callback
            'is_active' => 0,
        ]);
        echo json_encode([
            'ok' => true,
            'message' => 'Credenciales guardadas. Ahora pulsa «Conectar con Instagram» para autorizar.',
            'connection_id' => $new->id,
        ]);
    }

    /**
     * Redirigir a Instagram OAuth. Siempre en el contexto de una conexión
     * concreta (id en la URL), para que el callback sepa a cuál pertenece.
     */
    public function connect()
    {
        $this->checkPermission('instagram.create');

        $connectionId = isset($_GET['connection_id']) ? (int)$_GET['connection_id'] : 0;
        if ($connectionId <= 0) {
            $_SESSION['error'] = 'Falta el identificador de conexión. Guarda primero las credenciales.';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        $conn = InstagramConnection::find($connectionId);
        if (!$conn || !$this->connectionBelongsToScope($conn)) {
            $_SESSION['error'] = 'Conexión no encontrada';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }
        if (empty($conn->app_id) || empty($conn->app_secret)) {
            $_SESSION['error'] = 'Esta conexión no tiene credenciales guardadas todavía.';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        $redirectUri = $conn->redirect_uri ?: url($this->legacyCallbackPath);

        $state = InstagramApiService::generateState();
        $_SESSION['instagram_oauth_state'] = $state;
        $_SESSION['instagram_oauth_tenant_id'] = $this->tenantId;
        $_SESSION['instagram_oauth_connection_id'] = $conn->id;

        $api = new InstagramApiService($conn->app_id, $conn->app_secret, $redirectUri);
        redirect($api->getAuthorizationUrl($state));
    }

    /**
     * Handle OAuth callback from Instagram
     */
    public function callback()
    {
        $this->checkPermission('instagram.create');
        // Validate state
        $receivedState = $_GET['state'] ?? '';
        $expectedState = $_SESSION['instagram_oauth_state'] ?? '';

        if (!InstagramApiService::validateState($receivedState, $expectedState)) {
            $_SESSION['error'] = __instagram('connection.invalid_state');
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        $tenantId = $_SESSION['instagram_oauth_tenant_id'] ?? $this->tenantId;
        $connectionId = (int)($_SESSION['instagram_oauth_connection_id'] ?? 0);
        unset($_SESSION['instagram_oauth_state']);
        unset($_SESSION['instagram_oauth_tenant_id']);
        unset($_SESSION['instagram_oauth_connection_id']);

        if (isset($_GET['error'])) {
            $errorDescription = $_GET['error_description'] ?? $_GET['error'];
            $_SESSION['error'] = 'OAuth cancelado o rechazado: ' . $errorDescription;
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        $code = $_GET['code'] ?? '';
        if (!$code) {
            $_SESSION['error'] = 'Instagram no devolvió ningún código de autorización.';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        $conn = $connectionId > 0 ? InstagramConnection::find($connectionId) : null;
        if (!$conn) {
            $_SESSION['error'] = 'No se encontró la conexión en curso. Vuelve a empezar el proceso.';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }
        if (empty($conn->app_id) || empty($conn->app_secret)) {
            $_SESSION['error'] = 'La conexión no tiene credenciales guardadas.';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        try {
            $redirectUri = $conn->redirect_uri ?: url($this->legacyCallbackPath);
            $api = new InstagramApiService($conn->app_id, $conn->app_secret, $redirectUri);

            $tokenData = $api->getAccessToken($code);
            $profile = $api->getUserProfile($tokenData['access_token']);
            $expiresAt = InstagramApiService::calculateExpirationDate($tokenData['expires_in']);

            // ¿El id de Instagram ya pertenece a otra conexión distinta?
            // Si sí (y no es la actual), unificamos: mantenemos la conexión
            // actual con los datos nuevos y borramos la duplicada.
            $existing = InstagramConnection::findByInstagramUserId($profile['id'], $tenantId);
            if ($existing && $existing->id !== $conn->id) {
                $existing->delete();
            }

            $conn->update([
                'instagram_user_id' => $profile['id'],
                'username' => $profile['username'],
                'access_token' => $tokenData['access_token'],
                'token_expires_at' => $expiresAt,
                'is_active' => 1,
                'last_error' => null,
            ]);

            $_SESSION['success'] = 'Cuenta @' . $profile['username'] . ' autorizada correctamente.';
        } catch (Exception $e) {
            error_log('Instagram OAuth error: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al completar OAuth: ' . $e->getMessage();
        }

        redirect('/' . $this->basePath . '/social-publisher');
    }

    /**
     * AJAX: guarda los hashtags predefinidos de una conexión.
     * Se normalizan igual que al publicar para que el usuario vea
     * exactamente lo que acabará en el caption.
     */
    public function saveHashtags()
    {
        $this->checkPermission('instagram.edit');
        header('Content-Type: application/json; charset=utf-8');

        $connectionId = (int)($_POST['connection_id'] ?? 0);
        $raw = (string)($_POST['hashtags'] ?? '');

        $conn = InstagramConnection::find($connectionId);
        if (!$conn || !$this->connectionBelongsToScope($conn)) {
            echo json_encode(['ok' => false, 'message' => 'Conexión no encontrada']);
            return;
        }

        // Normalizar cada token: dividir por espacios/saltos/comas, quitar #
        // iniciales duplicados, normalizar tildes/ñ/mayúsculas, dejar sólo
        // los válidos y dedupe. Guardamos sin '#' (lo añadimos al publicar).
        $normalized = [];
        $seen = [];
        foreach (preg_split('/[\s,]+/u', $raw) ?: [] as $tok) {
            $tok = ltrim($tok, '#');
            if ($tok === '') continue;
            $h = $this->normalizeHashtag($tok);
            if ($h === '' || isset($seen[$h])) continue;
            $seen[$h] = true;
            $normalized[] = $h;
            if (count($normalized) >= 30) break;
        }
        $stored = implode(' ', $normalized);

        $conn->update(['hashtags_preset' => $stored !== '' ? $stored : null]);

        echo json_encode([
            'ok' => true,
            'hashtags' => $normalized,
            'display' => $stored !== '' ? ('#' . implode(' #', $normalized)) : '',
            'count' => count($normalized),
            'message' => 'Hashtags guardados (' . count($normalized) . ').',
        ]);
    }

    /**
     * Normaliza un hashtag: minúsculas, sin tildes/ñ/espacios, solo
     * letras/números/guión_bajo, descarta los que empiezan por número.
     */
    private function normalizeHashtag(string $name): string
    {
        $s = trim($name);
        if ($s === '') return '';
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s);
            if ($t !== false) $s = $t;
        } else {
            $s = mb_strtolower($s, 'UTF-8');
            $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        }
        $s = preg_replace('/[^a-z0-9_]+/u', '', $s) ?? '';
        if ($s === '' || preg_match('/^[0-9]/', $s)) return '';
        return $s;
    }

    /**
     * AJAX: valida contra Instagram que el App ID y el Redirect URI son
     * aceptados. El App Secret no se puede validar standalone (sólo al
     * completar OAuth real), así que lo avisamos.
     */
    public function testCredentials()
    {
        $this->checkPermission('instagram.edit');
        header('Content-Type: application/json; charset=utf-8');

        $appId = trim($_POST['app_id'] ?? '');
        $appSecret = trim($_POST['app_secret'] ?? '');
        $redirectUri = trim($_POST['redirect_uri'] ?? '');
        $connectionId = (int)($_POST['connection_id'] ?? 0);

        // Si algún campo viene vacío y hay connection_id, rellenar desde BD.
        if ($connectionId > 0 && ($appId === '' || $appSecret === '' || $redirectUri === '')) {
            $c = InstagramConnection::find($connectionId);
            if ($c && $c->tenant_id === $this->tenantId) {
                $appId = $appId !== '' ? $appId : ($c->app_id ?? '');
                $appSecret = $appSecret !== '' ? $appSecret : ($c->app_secret ?? '');
                $redirectUri = $redirectUri !== '' ? $redirectUri : ($c->redirect_uri ?? '');
            }
        }
        if ($redirectUri === '') {
            $redirectUri = url($this->legacyCallbackPath);
        }

        if ($appId === '' || $appSecret === '') {
            echo json_encode(['ok' => false, 'message' => 'Faltan App ID o App Secret']);
            return;
        }
        if (!preg_match('/^[0-9]{6,}$/', $appId)) {
            echo json_encode(['ok' => false, 'message' => 'El App ID debe ser numérico']);
            return;
        }

        $url = 'https://www.instagram.com/oauth/authorize?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => 'instagram_business_basic,instagram_business_content_publish',
            'response_type' => 'code',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 MuseDock-IG-Test/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            echo json_encode(['ok' => false, 'message' => 'Error de red: ' . $curlErr]);
            return;
        }
        $combined = ($redirectUrl ?: '') . ' ' . (string) $response;

        $patterns = [
            '/Invalid platform app/i' => 'Instagram rechaza el App ID. Comprueba que es el «Identificador de la aplicación de Instagram» (Paso 2) y no el App ID general.',
            '/redirect_uri.+(invalid|not.+valid|does.+not.+match|mismatch)/i' => 'Instagram dice que el Redirect URI no coincide con el registrado en Meta. Pega la URL exacta que aparece en este panel en «Valid OAuth Redirect URIs» de Meta.',
            '/Invalid redirect_uri/i' => 'Instagram rechaza el Redirect URI. Comprueba que lo pegaste literal en Meta → "Configura el inicio de sesión como negocio de Instagram" → "Valid OAuth Redirect URIs".',
        ];
        foreach ($patterns as $regex => $msg) {
            if (preg_match($regex, $combined)) {
                echo json_encode(['ok' => false, 'message' => $msg]);
                return;
            }
        }

        $looksOk = (
            $httpCode === 200
            || ($httpCode >= 300 && $httpCode < 400 && stripos((string)$redirectUrl, 'instagram.com') !== false)
        );
        if ($looksOk) {
            echo json_encode([
                'ok' => true,
                'message' => 'App ID válido y Redirect URI aceptado por Instagram. El App Secret se verificará al guardar y completar el OAuth.',
            ]);
            return;
        }
        echo json_encode([
            'ok' => false,
            'message' => 'Respuesta inesperada de Instagram (HTTP ' . $httpCode . ').',
        ]);
    }

    /**
     * Sync posts from Instagram
     */
    public function sync($id)
    {
        $this->checkPermission('instagram.edit');
        header('Content-Type: application/json');

        try {
            $connection = InstagramConnection::find((int) $id);

            if (!$connection) {
                echo json_encode([
                    'success' => false,
                    'message' => __instagram('connection.not_found')
                ]);
                return;
            }

            // Check if tenant owns this connection or it's global
            if ($connection->tenant_id !== null && $connection->tenant_id !== $this->tenantId) {
                echo json_encode([
                    'success' => false,
                    'message' => __instagram('errors.permission_denied')
                ]);
                return;
            }

            // Sync posts
            $result = sync_instagram_posts($connection->id);

            echo json_encode([
                'success' => true,
                'message' => __instagram('connection.sync_success', ['count' => $result['synced_count']]),
                'synced_count' => $result['synced_count'],
                'errors' => $result['errors']
            ]);

        } catch (Exception $e) {
            error_log('Instagram sync error: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => __instagram('connection.sync_error', ['error' => $e->getMessage()])
            ]);
        }
    }

    /**
     * Disconnect Instagram account
     */
    public function disconnect($id)
    {
        $this->checkPermission('instagram.delete');
        try {
            $connection = InstagramConnection::find((int) $id);

            if (!$connection) {
                $_SESSION['error'] = __instagram('connection.not_found');
                redirect('/' . $this->basePath . '/social-publisher');
                return;
            }

            // Check if tenant owns this connection
            if ($connection->tenant_id !== $this->tenantId) {
                $_SESSION['error'] = __instagram('errors.permission_denied');
                redirect('/' . $this->basePath . '/social-publisher');
                return;
            }

            // Delete connection and all posts
            $connection->delete();

            $_SESSION['success'] = __instagram('connection.disconnected_success');

        } catch (Exception $e) {
            error_log('Instagram disconnect error: ' . $e->getMessage());
            $_SESSION['error'] = __instagram('errors.unknown_error') . ': ' . $e->getMessage();
        }

        redirect('/' . $this->basePath . '/social-publisher');
    }

    /**
     * View posts for a connection
     */
    public function posts($id)
    {
        $this->checkPermission('instagram.view');
        $connection = InstagramConnection::find((int) $id);

        if (!$connection) {
            $_SESSION['error'] = __instagram('connection.not_found');
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        // Check if tenant can access this connection (own or global)
        if ($connection->tenant_id !== null && $connection->tenant_id !== $this->tenantId) {
            $_SESSION['error'] = __instagram('errors.permission_denied');
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        // Get posts
        $posts = $connection->posts();

        return View::renderModule('instagram-gallery', 'tenant.instagram.posts', [
            'connection' => $connection,
            'posts' => $posts
        ]);
    }

    /**
     * AJAX selector for editor
     */
    public function selector()
    {
        header('Content-Type: application/json');

        $connections = InstagramConnection::getActiveByTenant($this->tenantId);

        $data = [];
        foreach ($connections as $connection) {
            $data[] = [
                'id' => $connection->id,
                'username' => $connection->username,
                'profile_picture' => $connection->profile_picture,
                'shortcode' => '[instagram connection=' . $connection->id . ']',
                'is_global' => $connection->tenant_id === null
            ];
        }

        echo json_encode($data);
    }

    // ========================================================================
    // FACEBOOK PAGE LINKING
    // ========================================================================

    /**
     * GET /admin/social-publisher/facebook/connect?connection_id=N
     * Inicia OAuth de Facebook para vincular una Página a la conexión IG N.
     */
    public function facebookConnect()
    {
        $this->checkPermission('instagram.create');

        $connectionId = (int)($_GET['connection_id'] ?? 0);
        $conn = $connectionId > 0 ? InstagramConnection::find($connectionId) : null;
        if (!$conn || !$this->connectionBelongsToScope($conn)) {
            $_SESSION['error'] = 'Conexión no válida';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }
        if (empty($conn->app_id) || empty($conn->app_secret)) {
            $_SESSION['error'] = 'La conexión no tiene credenciales de Meta guardadas.';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        $redirectUri = url('/' . $this->basePath . '/social-publisher/facebook/callback');
        $state = InstagramApiService::generateState();
        $_SESSION['fb_oauth_state'] = $state;
        $_SESSION['fb_oauth_connection_id'] = $conn->id;
        $_SESSION['fb_oauth_redirect_uri'] = $redirectUri;

        $api = new InstagramApiService($conn->app_id, $conn->app_secret, $conn->redirect_uri ?: '');
        redirect($api->getFacebookAuthorizationUrl($redirectUri, $state));
    }

    /**
     * GET /admin/social-publisher/facebook/callback
     * Meta redirige aquí tras la autorización. Guardamos el user_token
     * long-lived y mostramos la lista de páginas para que el usuario elija.
     */
    public function facebookCallback()
    {
        $this->checkPermission('instagram.create');

        $state = $_GET['state'] ?? '';
        $expected = $_SESSION['fb_oauth_state'] ?? '';
        $connectionId = (int)($_SESSION['fb_oauth_connection_id'] ?? 0);
        $redirectUri = (string)($_SESSION['fb_oauth_redirect_uri'] ?? url('/' . $this->basePath . '/social-publisher/facebook/callback'));

        unset($_SESSION['fb_oauth_state'], $_SESSION['fb_oauth_connection_id'], $_SESSION['fb_oauth_redirect_uri']);

        if (!InstagramApiService::validateState($state, $expected)) {
            $_SESSION['error'] = 'State OAuth inválido. Vuelve a intentarlo.';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }
        if (isset($_GET['error'])) {
            $_SESSION['error'] = 'Facebook rechazó la autorización: ' . ($_GET['error_description'] ?? $_GET['error']);
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        $code = $_GET['code'] ?? '';
        $conn = $connectionId > 0 ? InstagramConnection::find($connectionId) : null;
        if (!$code || !$conn) {
            $_SESSION['error'] = 'No se pudo completar el OAuth de Facebook.';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }

        try {
            $api = new InstagramApiService($conn->app_id, $conn->app_secret, $conn->redirect_uri ?: '');
            $short = $api->exchangeFacebookCode($code, $redirectUri);
            $long = $api->getFacebookLongLivedUserToken($short['access_token']);
            $userToken = $long['access_token'];
            $pages = $api->listFacebookPages($userToken);

            if (empty($pages)) {
                $_SESSION['error'] = 'Tu cuenta de Facebook no administra ninguna Página. Tienes que ser admin de al menos una Página vinculada a Instagram Business.';
                redirect('/' . $this->basePath . '/social-publisher');
                return;
            }

            // Guardamos el user_token y las páginas candidatas en sesión,
            // y redirigimos a una pantalla (o modal) de selección.
            $_SESSION['fb_pending'] = [
                'connection_id' => $conn->id,
                'user_token' => $userToken,
                'pages' => array_map(fn($p) => [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'access_token' => $p['access_token'],
                    'category' => $p['category'] ?? null,
                ], $pages),
            ];

            // Si sólo hay una, la vinculamos directamente.
            if (count($pages) === 1) {
                $this->linkFacebookPage($conn, $pages[0], $userToken);
                $_SESSION['success'] = 'Página «' . $pages[0]['name'] . '» vinculada correctamente.';
                unset($_SESSION['fb_pending']);
            } else {
                $_SESSION['fb_pick_page_connection_id'] = $conn->id;
                $_SESSION['success'] = 'Selecciona qué Página de Facebook quieres vincular a @' . $conn->username . '.';
            }
            redirect('/' . $this->basePath . '/social-publisher?fb_pick=' . $conn->id);
        } catch (\Exception $e) {
            error_log('Facebook OAuth error: ' . $e->getMessage());
            $_SESSION['error'] = 'Error Facebook OAuth: ' . $e->getMessage();
            redirect('/' . $this->basePath . '/social-publisher');
        }
    }

    /**
     * POST /admin/social-publisher/facebook/select-page
     * Cuando el usuario tiene varias páginas, elige una aquí (AJAX).
     */
    public function facebookSelectPage()
    {
        $this->checkPermission('instagram.edit');
        header('Content-Type: application/json; charset=utf-8');

        $connectionId = (int)($_POST['connection_id'] ?? 0);
        $pageId = trim((string)($_POST['page_id'] ?? ''));

        $pending = $_SESSION['fb_pending'] ?? null;
        if (!$pending || (int)($pending['connection_id'] ?? 0) !== $connectionId) {
            echo json_encode(['ok' => false, 'message' => 'Sesión expirada, vuelve a conectar Facebook.']);
            return;
        }

        $conn = InstagramConnection::find($connectionId);
        if (!$conn || !$this->connectionBelongsToScope($conn)) {
            echo json_encode(['ok' => false, 'message' => 'Conexión no válida']);
            return;
        }

        $picked = null;
        foreach ($pending['pages'] as $p) {
            if ((string)$p['id'] === $pageId) { $picked = $p; break; }
        }
        if (!$picked) {
            echo json_encode(['ok' => false, 'message' => 'Página no encontrada en la lista autorizada.']);
            return;
        }

        $this->linkFacebookPage($conn, $picked, (string)($pending['user_token'] ?? ''));
        unset($_SESSION['fb_pending'], $_SESSION['fb_pick_page_connection_id']);

        echo json_encode(['ok' => true, 'message' => 'Página «' . $picked['name'] . '» vinculada.']);
    }

    /**
     * POST /admin/social-publisher/{id}/facebook/disconnect
     */
    public function facebookDisconnect($id)
    {
        $this->checkPermission('instagram.delete');
        $conn = InstagramConnection::find((int)$id);
        if (!$conn || !$this->connectionBelongsToScope($conn)) {
            $_SESSION['error'] = 'Conexión no válida';
            redirect('/' . $this->basePath . '/social-publisher');
            return;
        }
        $conn->update([
            'facebook_page_id' => null,
            'facebook_page_name' => null,
            'facebook_page_token' => null,
            'facebook_user_token' => null,
            'facebook_enabled' => 0,
        ]);
        $_SESSION['success'] = 'Página de Facebook desvinculada de @' . $conn->username . '.';
        redirect('/' . $this->basePath . '/social-publisher');
    }

    /**
     * Helper interno: guarda la página elegida en la conexión IG.
     */
    private function linkFacebookPage(InstagramConnection $conn, array $page, string $userToken): void
    {
        $conn->update([
            'facebook_page_id' => $page['id'],
            'facebook_page_name' => $page['name'] ?? null,
            'facebook_page_token' => $page['access_token'] ?? null,
            'facebook_user_token' => $userToken,
            'facebook_enabled' => 1,
        ]);
    }
}
