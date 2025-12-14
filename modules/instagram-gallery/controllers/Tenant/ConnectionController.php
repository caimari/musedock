<?php

namespace Modules\InstagramGallery\Controllers\Tenant;

use Modules\InstagramGallery\Models\InstagramConnection;
use Modules\InstagramGallery\Models\InstagramPost;
use Modules\InstagramGallery\Models\InstagramSetting;
use Modules\InstagramGallery\Services\InstagramApiService;
use Exception;

class ConnectionController
{
    private $pdo;
    private $tenantId;

    public function __construct()
    {
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramConnection::setPdo($this->pdo);
        InstagramPost::setPdo($this->pdo);
        InstagramSetting::setPdo($this->pdo);

        // Get tenant ID
        $this->tenantId = class_exists('TenantManager') ? TenantManager::currentTenantId() : 1;
    }

    /**
     * List all Instagram connections for Tenant
     */
    public function index()
    {
        // Get tenant connections (includes global)
        $connections = InstagramConnection::getByTenant($this->tenantId, true);

        // Get API configuration status
        $appId = InstagramSetting::get('instagram_app_id', $this->tenantId);
        $appSecret = InstagramSetting::get('instagram_app_secret', $this->tenantId);
        $apiConfigured = !empty($appId) && !empty($appSecret);

        render('modules/instagram-gallery/views/tenant/instagram/index.blade.php', [
            'connections' => $connections,
            'apiConfigured' => $apiConfigured,
            'tenantId' => $this->tenantId
        ]);
    }

    /**
     * Redirect to Instagram OAuth
     */
    public function connect()
    {
        // Get API credentials
        $appId = InstagramSetting::get('instagram_app_id', $this->tenantId);
        $appSecret = InstagramSetting::get('instagram_app_secret', $this->tenantId);
        $redirectUri = InstagramSetting::get('instagram_redirect_uri', $this->tenantId);

        if (!$appId || !$appSecret) {
            $_SESSION['error'] = __instagram('connection.api_not_configured');
            redirect('/admin/instagram');
            return;
        }

        // Generate and store state for CSRF protection
        $state = InstagramApiService::generateState();
        $_SESSION['instagram_oauth_state'] = $state;
        $_SESSION['instagram_oauth_context'] = 'tenant';
        $_SESSION['instagram_oauth_tenant_id'] = $this->tenantId;

        // Initialize API service
        $api = new InstagramApiService($appId, $appSecret, $redirectUri);

        // Redirect to Instagram authorization
        $authUrl = $api->getAuthorizationUrl($state);
        redirect($authUrl);
    }

    /**
     * Handle OAuth callback from Instagram
     */
    public function callback()
    {
        // Validate state
        $receivedState = $_GET['state'] ?? '';
        $expectedState = $_SESSION['instagram_oauth_state'] ?? '';

        if (!InstagramApiService::validateState($receivedState, $expectedState)) {
            $_SESSION['error'] = __instagram('connection.invalid_state');
            redirect('/admin/instagram');
            return;
        }

        // Get tenant ID from session
        $tenantId = $_SESSION['instagram_oauth_tenant_id'] ?? $this->tenantId;

        // Clear state
        unset($_SESSION['instagram_oauth_state']);
        unset($_SESSION['instagram_oauth_context']);
        unset($_SESSION['instagram_oauth_tenant_id']);

        // Check for error
        if (isset($_GET['error'])) {
            $errorDescription = $_GET['error_description'] ?? $_GET['error'];
            $_SESSION['error'] = __instagram('connection.oauth_error') . ': ' . $errorDescription;
            redirect('/admin/instagram');
            return;
        }

        // Get authorization code
        $code = $_GET['code'] ?? '';
        if (!$code) {
            $_SESSION['error'] = __instagram('connection.oauth_error');
            redirect('/admin/instagram');
            return;
        }

        try {
            // Get API credentials
            $appId = InstagramSetting::get('instagram_app_id', $tenantId);
            $appSecret = InstagramSetting::get('instagram_app_secret', $tenantId);
            $redirectUri = InstagramSetting::get('instagram_redirect_uri', $tenantId);

            // Initialize API service
            $api = new InstagramApiService($appId, $appSecret, $redirectUri);

            // Exchange code for access token
            $tokenData = $api->getAccessToken($code);

            // Get user profile
            $profile = $api->getUserProfile($tokenData['access_token']);

            // Calculate token expiration
            $expiresAt = InstagramApiService::calculateExpirationDate($tokenData['expires_in']);

            // Check if connection already exists
            $existing = InstagramConnection::findByInstagramUserId($profile['id'], $tenantId);

            if ($existing) {
                // Update existing connection
                $existing->update([
                    'username' => $profile['username'],
                    'access_token' => $tokenData['access_token'],
                    'token_expires_at' => $expiresAt,
                    'is_active' => 1,
                    'last_error' => null
                ]);

                $_SESSION['success'] = __instagram('connection.connection_created') . ' (@' . $profile['username'] . ')';
            } else {
                // Create new connection
                InstagramConnection::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'instagram_user_id' => $profile['id'],
                    'username' => $profile['username'],
                    'profile_picture' => null,
                    'access_token' => $tokenData['access_token'],
                    'token_expires_at' => $expiresAt,
                    'is_active' => 1
                ]);

                $_SESSION['success'] = __instagram('connection.connection_created') . ' (@' . $profile['username'] . ')';
            }

        } catch (Exception $e) {
            error_log('Instagram OAuth error: ' . $e->getMessage());
            $_SESSION['error'] = __instagram('connection.connection_error') . ': ' . $e->getMessage();
        }

        redirect('/admin/instagram');
    }

    /**
     * Sync posts from Instagram
     */
    public function sync($id)
    {
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
        try {
            $connection = InstagramConnection::find((int) $id);

            if (!$connection) {
                $_SESSION['error'] = __instagram('connection.not_found');
                redirect('/admin/instagram');
                return;
            }

            // Check if tenant owns this connection
            if ($connection->tenant_id !== $this->tenantId) {
                $_SESSION['error'] = __instagram('errors.permission_denied');
                redirect('/admin/instagram');
                return;
            }

            // Delete connection and all posts
            $connection->delete();

            $_SESSION['success'] = __instagram('connection.disconnected_success');

        } catch (Exception $e) {
            error_log('Instagram disconnect error: ' . $e->getMessage());
            $_SESSION['error'] = __instagram('errors.unknown_error') . ': ' . $e->getMessage();
        }

        redirect('/admin/instagram');
    }

    /**
     * View posts for a connection
     */
    public function posts($id)
    {
        $connection = InstagramConnection::find((int) $id);

        if (!$connection) {
            $_SESSION['error'] = __instagram('connection.not_found');
            redirect('/admin/instagram');
            return;
        }

        // Check if tenant can access this connection (own or global)
        if ($connection->tenant_id !== null && $connection->tenant_id !== $this->tenantId) {
            $_SESSION['error'] = __instagram('errors.permission_denied');
            redirect('/admin/instagram');
            return;
        }

        // Get posts
        $posts = $connection->posts();

        render('modules/instagram-gallery/views/tenant/instagram/posts.blade.php', [
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
}
