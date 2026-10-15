<?php

namespace Modules\SearchConsole\Services;

use RuntimeException;

/**
 * Cliente mínimo de las APIs de Google con cuenta de servicio (sin google/apiclient).
 *
 * Firma un JWT RS256 con la clave privada de la cuenta de servicio, lo cambia por
 * un token de acceso (1 h) y reintenta con espera creciente ante 429/5xx.
 */
class GoogleClient
{
    public const SCOPES = [
        'https://www.googleapis.com/auth/webmasters',
        'https://www.googleapis.com/auth/siteverification',
    ];

    private const MAX_ATTEMPTS = 4;

    private array $key;
    private ?string $token = null;
    private int $tokenExpires = 0;

    /** @param array $serviceAccount JSON de la cuenta de servicio ya decodificado */
    public function __construct(array $serviceAccount)
    {
        self::assertServiceAccount($serviceAccount);
        $this->key = $serviceAccount;
    }

    /** Valida que el JSON sea una clave de cuenta de servicio utilizable. */
    public static function assertServiceAccount(array $json): void
    {
        if (($json['type'] ?? '') !== 'service_account') {
            throw new RuntimeException('El fichero no es una clave de cuenta de servicio de Google (type ≠ service_account).');
        }
        foreach (['client_email', 'private_key'] as $field) {
            if (empty($json[$field]) || !is_string($json[$field])) {
                throw new RuntimeException("Falta el campo «{$field}» en la clave JSON.");
            }
        }
        if (!filter_var($json['client_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El client_email de la clave no es válido.');
        }
        if (openssl_pkey_get_private($json['private_key']) === false) {
            throw new RuntimeException('La private_key de la clave no es válida.');
        }
    }

    public function clientEmail(): string
    {
        return $this->key['client_email'];
    }

    /**
     * Llamada a la API. Devuelve el JSON decodificado (array vacío si no hay cuerpo).
     *
     * @throws GoogleApiException
     */
    public function request(string $method, string $url, ?array $body = null): array
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            [$status, $response] = $this->send($method, $url, $body, ['Authorization: Bearer ' . $this->accessToken()]);

            if ($status >= 200 && $status < 300) {
                return $response === '' ? [] : (json_decode($response, true) ?? []);
            }
            if ($status === 401 && $attempt === 1) {
                $this->token = null; // token caducado o revocado: pedir otro una vez
                continue;
            }
            if (($status === 429 || $status >= 500 || $status === 0) && $attempt < self::MAX_ATTEMPTS) {
                usleep((int) (min(30, 2 ** $attempt) * 1_000_000 + random_int(0, 500_000)));
                continue;
            }

            throw GoogleApiException::fromResponse($status, $response);
        }
    }

    private function accessToken(): string
    {
        if ($this->token !== null && time() < $this->tokenExpires - 60) {
            return $this->token;
        }

        $now = time();
        $tokenUri = $this->key['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $segments = [
            self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::b64(json_encode([
                'iss' => $this->key['client_email'],
                'scope' => implode(' ', self::SCOPES),
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];
        $signature = '';
        if (!openssl_sign(implode('.', $segments), $signature, $this->key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar la petición con la clave de la cuenta de servicio.');
        }
        $segments[] = self::b64($signature);

        [$status, $response] = $this->send('POST', $tokenUri, null, [], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => implode('.', $segments),
        ]));
        $data = json_decode($response, true) ?? [];
        if ($status !== 200 || empty($data['access_token'])) {
            throw GoogleApiException::fromResponse($status, $response);
        }

        $this->token = $data['access_token'];
        $this->tokenExpires = $now + (int) ($data['expires_in'] ?? 3600);

        return $this->token;
    }

    /** @return array{0:int,1:string} */
    private function send(string $method, string $url, ?array $json, array $headers, ?string $form = null): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
        if ($form !== null) {
            $opts[CURLOPT_POSTFIELDS] = $form;
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif ($json !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        } elseif (in_array($method, ['POST', 'PUT'], true)) {
            $opts[CURLOPT_POSTFIELDS] = '';
            $headers[] = 'Content-Length: 0';
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$response === false ? 0 : $status, $response === false ? '' : (string) $response];
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
