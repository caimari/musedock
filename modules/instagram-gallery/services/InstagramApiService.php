<?php

namespace Modules\InstagramGallery\Services;

use Exception;

/**
 * Instagram Basic Display API Service
 *
 * Handles OAuth flow and API communication with Instagram Basic Display API
 * Documentation: https://developers.facebook.com/docs/instagram-basic-display-api
 */
class InstagramApiService
{
    private string $appId;
    private string $appSecret;
    private string $redirectUri;

    private const API_BASE_URL = 'https://api.instagram.com';
    private const GRAPH_API_URL = 'https://graph.instagram.com';
    private const OAUTH_URL = 'https://api.instagram.com/oauth';

    public function __construct(string $appId, string $appSecret, string $redirectUri)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->redirectUri = $redirectUri;
    }

    /**
     * Step 1: Get authorization URL for OAuth flow
     */
    public function getAuthorizationUrl(string $state = null): string
    {
        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'user_profile,user_media',
            'response_type' => 'code'
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return self::OAUTH_URL . '/authorize?' . http_build_query($params);
    }

    /**
     * Step 2: Exchange authorization code for access token
     */
    public function getAccessToken(string $code): array
    {
        $url = self::OAUTH_URL . '/access_token';

        $data = [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
            'code' => $code
        ];

        $response = $this->makePostRequest($url, $data);

        if (!isset($response['access_token'])) {
            throw new Exception('Failed to get access token: ' . ($response['error_message'] ?? 'Unknown error'));
        }

        // Exchange short-lived token for long-lived token (60 days)
        return $this->getLongLivedToken($response['access_token']);
    }

    /**
     * Step 3: Exchange short-lived token for long-lived token (60 days)
     */
    public function getLongLivedToken(string $shortLivedToken): array
    {
        $url = self::GRAPH_API_URL . '/access_token';

        $params = [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->appSecret,
            'access_token' => $shortLivedToken
        ];

        $response = $this->makeGetRequest($url . '?' . http_build_query($params));

        if (!isset($response['access_token'])) {
            throw new Exception('Failed to get long-lived token: ' . ($response['error_message'] ?? 'Unknown error'));
        }

        return [
            'access_token' => $response['access_token'],
            'token_type' => $response['token_type'] ?? 'bearer',
            'expires_in' => $response['expires_in'] ?? 5184000 // 60 days default
        ];
    }

    /**
     * Refresh long-lived token (extends expiration by 60 days)
     */
    public function refreshToken(string $accessToken): array
    {
        $url = self::GRAPH_API_URL . '/refresh_access_token';

        $params = [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $accessToken
        ];

        $response = $this->makeGetRequest($url . '?' . http_build_query($params));

        if (!isset($response['access_token'])) {
            throw new Exception('Failed to refresh token: ' . ($response['error_message'] ?? 'Unknown error'));
        }

        return [
            'access_token' => $response['access_token'],
            'token_type' => $response['token_type'] ?? 'bearer',
            'expires_in' => $response['expires_in'] ?? 5184000
        ];
    }

    /**
     * Get user profile information
     */
    public function getUserProfile(string $accessToken): array
    {
        $url = self::GRAPH_API_URL . '/me';

        $params = [
            'fields' => 'id,username,account_type,media_count',
            'access_token' => $accessToken
        ];

        $response = $this->makeGetRequest($url . '?' . http_build_query($params));

        if (isset($response['error'])) {
            throw new Exception('Failed to get user profile: ' . ($response['error']['message'] ?? 'Unknown error'));
        }

        return $response;
    }

    /**
     * Get user's media (posts)
     */
    public function getUserMedia(string $accessToken, int $limit = 25, ?string $after = null): array
    {
        $url = self::GRAPH_API_URL . '/me/media';

        $params = [
            'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,username',
            'access_token' => $accessToken,
            'limit' => min($limit, 100) // Max 100 per request
        ];

        if ($after) {
            $params['after'] = $after;
        }

        $response = $this->makeGetRequest($url . '?' . http_build_query($params));

        if (isset($response['error'])) {
            throw new Exception('Failed to get user media: ' . ($response['error']['message'] ?? 'Unknown error'));
        }

        return $response;
    }

    /**
     * Get all user media (handles pagination)
     */
    public function getAllUserMedia(string $accessToken, int $maxPosts = 50): array
    {
        $allMedia = [];
        $after = null;
        $remaining = $maxPosts;

        do {
            $batchSize = min($remaining, 100);
            $response = $this->getUserMedia($accessToken, $batchSize, $after);

            if (isset($response['data'])) {
                $allMedia = array_merge($allMedia, $response['data']);
                $remaining -= count($response['data']);
            }

            // Check for next page
            $after = $response['paging']['cursors']['after'] ?? null;

            // Stop if we've reached the limit or there's no more data
        } while ($after && $remaining > 0 && isset($response['data']) && count($response['data']) > 0);

        return array_slice($allMedia, 0, $maxPosts);
    }

    /**
     * Get specific media details
     */
    public function getMedia(string $mediaId, string $accessToken): array
    {
        $url = self::GRAPH_API_URL . '/' . $mediaId;

        $params = [
            'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,username',
            'access_token' => $accessToken
        ];

        $response = $this->makeGetRequest($url . '?' . http_build_query($params));

        if (isset($response['error'])) {
            throw new Exception('Failed to get media: ' . ($response['error']['message'] ?? 'Unknown error'));
        }

        return $response;
    }

    /**
     * Validate access token
     */
    public function validateToken(string $accessToken): bool
    {
        try {
            $this->getUserProfile($accessToken);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Make GET request
     */
    private function makeGetRequest(string $url): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: MuseDock-Instagram-Gallery/1.0'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception('HTTP error ' . $httpCode . ': ' . $response);
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Make POST request
     */
    private function makePostRequest(string $url, array $data): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: MuseDock-Instagram-Gallery/1.0'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception('HTTP error ' . $httpCode . ': ' . $response);
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $responseData;
    }

    /**
     * Calculate token expiration date
     */
    public static function calculateExpirationDate(int $expiresIn): string
    {
        return date('Y-m-d H:i:s', time() + $expiresIn);
    }

    /**
     * Generate random state for OAuth security
     */
    public static function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Validate OAuth state
     */
    public static function validateState(string $receivedState, string $expectedState): bool
    {
        return hash_equals($expectedState, $receivedState);
    }
}
