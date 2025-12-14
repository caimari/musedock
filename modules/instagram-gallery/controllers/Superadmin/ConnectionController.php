<?php

namespace Modules\InstagramGallery\Controllers\Superadmin;

use Modules\InstagramGallery\Models\InstagramConnection;
use Modules\InstagramGallery\Models\InstagramPost;
use Modules\InstagramGallery\Models\InstagramSetting;
use Modules\InstagramGallery\Services\InstagramApiService;
use Exception;

class ConnectionController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramConnection::setPdo($this->pdo);
        InstagramPost::setPdo($this->pdo);
        InstagramSetting::setPdo($this->pdo);
    }

    /**
     * List all Instagram connections for SuperAdmin
     */
    public function index()
    {
        // Get all global connections (tenant_id = NULL)
        $connections = InstagramConnection::getByTenant(null, false);

        // Get API configuration status
        $appId = InstagramSetting::get('instagram_app_id', null);
        $appSecret = InstagramSetting::get('instagram_app_secret', null);
        $apiConfigured = !empty($appId) && !empty($appSecret);

        render('modules/instagram-gallery/views/superadmin/instagram/index.blade.php', [
            'connections' => $connections,
            'apiConfigured' => $apiConfigured
        ]);
    }

    /**
     * Redirect to Instagram OAuth
     */
    public function connect()
    {
        // Get API credentials
        $appId = InstagramSetting::get('instagram_app_id', null);
        $appSecret = InstagramSetting::get('instagram_app_secret', null);
        $redirectUri = InstagramSetting::get('instagram_redirect_uri', null);

        if (!$appId || !$appSecret) {
            $_SESSION['error'] = __instagram('connection.api_not_configured');
            redirect('/musedock/instagram');
            return;
        }

        // Generate and store state for CSRF protection
        $state = InstagramApiService::generateState();
        $_SESSION['instagram_oauth_state'] = $state;
        $_SESSION['instagram_oauth_context'] = 'superadmin';

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
            redirect('/musedock/instagram');
            return;
        }

        // Clear state
        unset($_SESSION['instagram_oauth_state']);

        // Check for error
        if (isset($_GET['error'])) {
            $errorDescription = $_GET['error_description'] ?? $_GET['error'];
            $_SESSION['error'] = __instagram('connection.oauth_error') . ': ' . $errorDescription;
            redirect('/musedock/instagram');
            return;
        }

        // Get authorization code
        $code = $_GET['code'] ?? '';
        if (!$code) {
            $_SESSION['error'] = __instagram('connection.oauth_error');
            redirect('/musedock/instagram');
            return;
        }

        try {
            // Get API credentials
            $appId = InstagramSetting::get('instagram_app_id', null);
            $appSecret = InstagramSetting::get('instagram_app_secret', null);
            $redirectUri = InstagramSetting::get('instagram_redirect_uri', null);

            // Initialize API service
            $api = new InstagramApiService($appId, $appSecret, $redirectUri);

            // Exchange code for access token
            $tokenData = $api->getAccessToken($code);

            // Get user profile
            $profile = $api->getUserProfile($tokenData['access_token']);

            // Calculate token expiration
            $expiresAt = InstagramApiService::calculateExpirationDate($tokenData['expires_in']);

            // Check if connection already exists
            $existing = InstagramConnection::findByInstagramUserId($profile['id'], null);

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
                    'tenant_id' => null, // SuperAdmin = NULL
                    'user_id' => null,
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

        redirect('/musedock/instagram');
    }

    /**
     * Sync posts from Instagram
     */
    public function sync($id)
    {
        header('Content-Type: application/json');

        try {
            $connection = InstagramConnection::find((int) $id);

            if (!$connection || $connection->tenant_id !== null) {
                echo json_encode([
                    'success' => false,
                    'message' => __instagram('connection.not_found')
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

            if (!$connection || $connection->tenant_id !== null) {
                $_SESSION['error'] = __instagram('connection.not_found');
                redirect('/musedock/instagram');
                return;
            }

            // Delete connection and all posts
            $connection->delete();

            $_SESSION['success'] = __instagram('connection.disconnected_success');

        } catch (Exception $e) {
            error_log('Instagram disconnect error: ' . $e->getMessage());
            $_SESSION['error'] = __instagram('errors.unknown_error') . ': ' . $e->getMessage();
        }

        redirect('/musedock/instagram');
    }

    /**
     * View posts for a connection
     */
    public function posts($id)
    {
        $connection = InstagramConnection::find((int) $id);

        if (!$connection || $connection->tenant_id !== null) {
            $_SESSION['error'] = __instagram('connection.not_found');
            redirect('/musedock/instagram');
            return;
        }

        // Get posts
        $posts = $connection->posts();

        render('modules/instagram-gallery/views/superadmin/instagram/posts.blade.php', [
            'connection' => $connection,
            'posts' => $posts
        ]);
    }
}
