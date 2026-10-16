<?php

namespace Screenart\Musedock\Services\Dns;

/**
 * Operaciones mínimas que el core necesita de un proveedor DNS (verificaciones TXT, etc.).
 * Hoy solo hay cliente para Cloudflare; añadir otro proveedor es implementar esta interfaz y
 * registrarlo en DnsZones::clientFor().
 */
interface DnsZoneClient
{
    /** Busca la zona exacta de un dominio. Devuelve ['id' => ..., 'name' => ...] o null. */
    public function findZone(string $domain): ?array;

    /** Garantiza un registro TXT (no duplica si ya existe con el mismo contenido). */
    public function ensureTxt(string $zoneId, string $name, string $content): void;

    /** Comprueba que las credenciales valen. @return array{ok:bool, message:string} */
    public function verify(): array;
}
