<?php

namespace Modules\InstagramGallery\Services;

use Exception;

/**
 * Instagram API Service (Business Login)
 *
 * Habla con la API «Instagram con login de empresa» que sustituyó a la
 * antigua Instagram Basic Display (deprecada en dic-2024).
 * Documentación: https://developers.facebook.com/docs/instagram-platform
 *
 * Requisitos:
 * - La cuenta de Instagram tiene que ser Business o Creator.
 * - La app tiene que tener el producto «API con inicio de sesión para
 *   empresas de Instagram» configurado en Meta for Developers.
 */
class InstagramApiService
{
    private string $appId;
    private string $appSecret;
    private string $redirectUri;

    private const API_BASE_URL = 'https://api.instagram.com';
    private const GRAPH_API_URL = 'https://graph.instagram.com';
    private const OAUTH_AUTHORIZE_URL = 'https://www.instagram.com/oauth/authorize';
    private const OAUTH_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    private const GRAPH_VERSION = 'v21.0';

    /**
     * Scopes para Business Login:
     * - instagram_business_basic: leer perfil + media propios.
     * - instagram_business_content_publish: publicar fotos en el feed propio.
     * Para gestionar mensajes/comentarios harían falta scopes adicionales.
     */
    private const DEFAULT_SCOPES = 'instagram_business_basic,instagram_business_content_publish';

    public function __construct(string $appId, string $appSecret, string $redirectUri)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->redirectUri = $redirectUri;
    }

    /**
     * Paso 1: URL de autorización OAuth (Business Login).
     * Lleva al usuario a instagram.com para que conceda permisos.
     */
    public function getAuthorizationUrl(string $state = null): string
    {
        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'scope' => self::DEFAULT_SCOPES,
            'response_type' => 'code',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return self::OAUTH_AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * Paso 2: cambia el code por un short-lived token y a continuación
     * lo intercambia por uno long-lived (60 días).
     */
    public function getAccessToken(string $code): array
    {
        $data = [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ];

        $response = $this->makePostRequest(self::OAUTH_TOKEN_URL, $data);

        if (!isset($response['access_token'])) {
            $msg = $response['error_message']
                ?? $response['error']['message']
                ?? ($response['error_type'] ?? 'Unknown error');
            throw new Exception('Failed to get access token: ' . $msg);
        }

        // Guardar el ig user id que devuelve junto con el short-lived token
        // para no tener que pedirlo aparte luego.
        $shortToken = $response['access_token'];
        $userId = $response['user_id'] ?? null;

        $longLived = $this->getLongLivedToken($shortToken);
        if ($userId !== null) {
            $longLived['user_id'] = $userId;
        }
        return $longLived;
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
        $url = self::GRAPH_API_URL . '/' . self::GRAPH_VERSION . '/me';

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
        $url = self::GRAPH_API_URL . '/' . self::GRAPH_VERSION . '/me/media';

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
        $url = self::GRAPH_API_URL . '/' . self::GRAPH_VERSION . '/' . $mediaId;

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
     * Publica una foto en el feed de Instagram (flujo en 2 pasos):
     *   1. POST /{ig-user-id}/media       → devuelve creation_id (container)
     *   2. POST /{ig-user-id}/media_publish → publica el container
     *
     * Requiere scope instagram_business_content_publish.
     * Instagram NO permite enlaces clicables en el caption; el link aparece
     * como texto plano. La image_url tiene que ser pública y JPEG/PNG.
     *
     * @return array{id:string,permalink:?string}
     */
    public function publishPhoto(string $igUserId, string $imageUrl, string $caption, string $accessToken): array
    {
        // Paso 1: crear el container
        $containerUrl = self::GRAPH_API_URL . '/' . self::GRAPH_VERSION . '/' . $igUserId . '/media';
        $containerResp = $this->makePostRequest($containerUrl, [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);
        if (empty($containerResp['id'])) {
            $err = $containerResp['error']['message'] ?? json_encode($containerResp);
            throw new Exception('No se pudo crear el contenedor en Instagram: ' . $err);
        }
        $creationId = $containerResp['id'];

        // Paso 2: publicar el container
        $publishUrl = self::GRAPH_API_URL . '/' . self::GRAPH_VERSION . '/' . $igUserId . '/media_publish';
        $publishResp = $this->makePostRequest($publishUrl, [
            'creation_id' => $creationId,
            'access_token' => $accessToken,
        ]);
        if (empty($publishResp['id'])) {
            $err = $publishResp['error']['message'] ?? json_encode($publishResp);
            throw new Exception('No se pudo publicar el contenedor en Instagram: ' . $err);
        }

        // Recuperar permalink del post recién publicado
        $permalink = null;
        try {
            $media = $this->getMedia($publishResp['id'], $accessToken);
            $permalink = $media['permalink'] ?? null;
        } catch (Exception $e) {
            // No es crítico: el post se ha publicado igual
        }

        return [
            'id' => $publishResp['id'],
            'permalink' => $permalink,
        ];
    }

    /**
     * Validate access token
     */
    // ========================================================================
    // FACEBOOK (Login for Business + Pages API)
    //
    // La API de IG con Business Login sólo publica en Instagram. Para publicar
    // en la Página de Facebook asociada hace falta un OAuth DISTINTO con el
    // login de Facebook y los scopes de Pages.
    //
    // Flujo:
    //   1. getFacebookAuthorizationUrl() → el usuario autoriza en FB.
    //   2. exchangeFacebookCode($code)   → devuelve un user_access_token.
    //   3. getFacebookLongLivedUserToken → lo cambiamos por uno long-lived.
    //   4. listFacebookPages($userToken) → lista de páginas que administra.
    //   5. El usuario elige una → guardamos su page_access_token (no caduca).
    //   6. publishToFacebookPage() publica con el page_access_token.
    // ========================================================================

    private const FB_OAUTH_DIALOG_URL = 'https://www.facebook.com/v21.0/dialog/oauth';
    private const FB_GRAPH_URL = 'https://graph.facebook.com/v21.0';
    private const FB_DEFAULT_SCOPES = 'pages_show_list,pages_manage_posts,pages_read_engagement,business_management';

    /**
     * URL para iniciar OAuth de Facebook Login.
     */
    public function getFacebookAuthorizationUrl(string $redirectUri, ?string $state = null): string
    {
        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $redirectUri,
            'scope' => self::FB_DEFAULT_SCOPES,
            'response_type' => 'code',
        ];
        if ($state) $params['state'] = $state;
        return self::FB_OAUTH_DIALOG_URL . '?' . http_build_query($params);
    }

    /**
     * Cambia el code OAuth de Facebook por un access_token del USUARIO.
     * Devuelve ['access_token' => ..., 'token_type' => ..., 'expires_in' => ?].
     */
    public function exchangeFacebookCode(string $code, string $redirectUri): array
    {
        $url = self::FB_GRAPH_URL . '/oauth/access_token?' . http_build_query([
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        $resp = $this->makeGetRequest($url);
        if (empty($resp['access_token'])) {
            $err = $resp['error']['message'] ?? json_encode($resp);
            throw new Exception('Facebook exchangeCode: ' . $err);
        }
        return $resp;
    }

    /**
     * Cambia un user_access_token short-lived por uno long-lived (~60 días).
     */
    public function getFacebookLongLivedUserToken(string $shortToken): array
    {
        $url = self::FB_GRAPH_URL . '/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortToken,
        ]);
        $resp = $this->makeGetRequest($url);
        if (empty($resp['access_token'])) {
            throw new Exception('Facebook long-lived user token: ' . json_encode($resp));
        }
        return $resp;
    }

    /**
     * Lista las páginas de Facebook que administra el usuario autenticado.
     * Devuelve array de ['id','name','access_token','category'].
     * El access_token devuelto por página NO caduca (usando un user token long-lived).
     */
    public function listFacebookPages(string $userAccessToken): array
    {
        $url = self::FB_GRAPH_URL . '/me/accounts?' . http_build_query([
            'fields' => 'id,name,access_token,category',
            'access_token' => $userAccessToken,
        ]);
        $resp = $this->makeGetRequest($url);
        if (isset($resp['error'])) {
            throw new Exception('Facebook listPages: ' . ($resp['error']['message'] ?? 'unknown'));
        }
        return $resp['data'] ?? [];
    }

    /**
     * Publica en la Página de Facebook con un Page Access Token.
     * Instagram NO acepta enlaces clicables en caption — Facebook SÍ, mediante
     * el parámetro `link`. Facebook genera preview automático (OG).
     *
     * Si $imageUrl viene, se publica como foto (/{page-id}/photos). Si no,
     * como post de texto + link (/{page-id}/feed).
     */
    public function publishToFacebookPage(string $pageId, string $pageAccessToken, string $message, ?string $link = null, ?string $imageUrl = null): array
    {
        if ($imageUrl) {
            $url = self::FB_GRAPH_URL . '/' . $pageId . '/photos';
            $payload = [
                'url' => $imageUrl,
                'caption' => $message . ($link ? "\n\n" . $link : ''),
                'access_token' => $pageAccessToken,
            ];
        } else {
            $url = self::FB_GRAPH_URL . '/' . $pageId . '/feed';
            $payload = [
                'message' => $message,
                'access_token' => $pageAccessToken,
            ];
            if ($link) $payload['link'] = $link;
        }

        $resp = $this->makePostRequest($url, $payload);
        if (empty($resp['id'])) {
            $err = $resp['error']['message'] ?? json_encode($resp);
            throw new Exception('Facebook publish: ' . $err);
        }

        // Intentar recuperar permalink del post
        $permalink = null;
        try {
            $postId = $resp['post_id'] ?? $resp['id']; // en /photos devuelve 'post_id'
            $get = self::FB_GRAPH_URL . '/' . $postId . '?' . http_build_query([
                'fields' => 'permalink_url',
                'access_token' => $pageAccessToken,
            ]);
            $info = $this->makeGetRequest($get);
            $permalink = $info['permalink_url'] ?? null;
        } catch (Exception $e) {
            // no crítico
        }

        return [
            'id' => $resp['post_id'] ?? $resp['id'],
            'permalink' => $permalink,
        ];
    }

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
