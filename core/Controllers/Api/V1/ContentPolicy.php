<?php

namespace Screenart\Musedock\Controllers\Api\V1;

use Screenart\Musedock\Middlewares\ApiKeyAuth;
use Screenart\Musedock\SafeHtml;

/**
 * Reglas comunes para escritura de contenido vía API REST / MCP.
 *
 * - Todo el HTML entrante se sanitiza (la API la usan agentes de IA que pueden
 *   haber sido manipulados por prompt injection).
 * - Publicar es un permiso aparte ("{section}.publish"): sin él, lo creado queda
 *   en borrador y no se puede modificar contenido que ya está publicado.
 */
class ContentPolicy
{
    public static function html(?string $html): string
    {
        return SafeHtml::clean((string)$html);
    }

    public static function plain(?string $text): ?string
    {
        return $text === null ? null : SafeHtml::plain($text);
    }

    public static function canPublish(string $section): bool
    {
        $key = ApiKeyAuth::key();
        return $key !== null && $key->hasPermission($section . '.publish');
    }

    /**
     * Ajusta un estado solicitado según el permiso de publicación.
     * Devuelve [estado final, aviso|null].
     */
    public static function resolveStatus(string $section, string $requested): array
    {
        if (in_array($requested, ['published', 'scheduled'], true) && !self::canPublish($section)) {
            return ['draft', "Saved as draft: this key lacks the '{$section}.publish' permission. A site admin must publish it."];
        }
        return [$requested, null];
    }

    /**
     * Sin permiso de publicación no se puede tocar contenido que ya está en vivo.
     */
    public static function assertCanModifyLive(string $section, ?string $currentStatus): void
    {
        if (in_array($currentStatus, ['published', 'scheduled'], true) && !self::canPublish($section)) {
            ApiKeyAuth::respond(403, 'PUBLISH_PERMISSION_REQUIRED', "This item is published. Modifying live content requires the '{$section}.publish' permission.");
        }
    }
}
