<?php

namespace Modules\SearchConsole\Services;

use RuntimeException;

/** Error devuelto por una API de Google (status 0 = sin conexión). */
class GoogleApiException extends RuntimeException
{
    public int $status;

    public static function fromResponse(int $status, string $body): self
    {
        $data = json_decode($body, true);
        $message = $data['error']['message'] ?? ($data['error_description'] ?? ($data['error'] ?? ''));
        if (!is_string($message) || $message === '') {
            $message = $status === 0 ? 'Sin conexión con Google' : "HTTP {$status}";
        }
        $e = new self(mb_substr($message, 0, 400), $status);
        $e->status = $status;

        return $e;
    }
}
