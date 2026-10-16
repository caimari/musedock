<?php

namespace Screenart\Musedock\Services\Dns;

use Screenart\Musedock\Services\DnsAccounts;

/**
 * Localiza en qué cuenta del registro DNS está el DNS de un dominio y permite publicar registros.
 * Usa SOLO el registro único (DnsAccounts): no hay copias de credenciales en otros sitios.
 */
class DnsZones
{
    /** @var callable|null fn(array $account, array $credentials): DnsZoneClient  (inyectable en pruebas) */
    private $factory;

    public function __construct(private DnsAccounts $accounts, ?callable $factory = null)
    {
        $this->factory = $factory;
    }

    /**
     * Busca el dominio (o su dominio padre) en las cuentas habilitadas con cliente disponible.
     * @return array{account:array, client:DnsZoneClient, zone:array}|null
     */
    public function locate(string $domain): ?array
    {
        $labels = explode('.', strtolower(trim($domain)));
        $candidates = [];
        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidates[] = implode('.', array_slice($labels, $i));
        }
        foreach ($this->accounts->all(null, true) as $account) {
            try {
                $client = $this->clientFor($account);
            } catch (\Throwable $e) {
                continue;
            }
            if ($client === null) {
                continue;
            }
            foreach ($candidates as $candidate) {
                try {
                    $zone = $client->findZone($candidate);
                } catch (\Throwable $e) {
                    continue 2; // cuenta con token roto: se pasa a la siguiente sin tumbar el resto
                }
                if ($zone) {
                    return ['account' => $account, 'client' => $client, 'zone' => $zone];
                }
            }
        }

        return null;
    }

    public function clientFor(array $account): ?DnsZoneClient
    {
        $creds = $this->accounts->credentials((int) $account['id']);
        if ($this->factory) {
            return ($this->factory)($account, $creds);
        }

        return match ($account['provider']) {
            'cloudflare' => new CloudflareClient((string) ($creds['api_token'] ?? ''), $account['account_ref'] ?: null),
            default => null, // resto de proveedores: aún sin cliente
        };
    }

    /** Prueba una cuenta y guarda el resultado (para el botón «Probar» y la salud). @return array{ok:bool,message:string} */
    public function test(int $id): array
    {
        $account = $this->accounts->find($id);
        if (!$account) {
            return ['ok' => false, 'message' => 'Cuenta no encontrada.'];
        }
        $client = $this->clientFor($account);
        if ($client === null) {
            $this->accounts->recordTest($id, 'sin-cliente', 'Credenciales guardadas; este proveedor aún no tiene prueba automática.');

            return ['ok' => true, 'message' => 'Credenciales guardadas (sin prueba automática para este proveedor).'];
        }
        $r = $client->verify();
        $msg = $r['message'];
        if ($r['ok'] && $client instanceof CloudflareClient) {
            try {
                $n = $client->zoneCount();
                $msg .= $n !== null ? " Ve {$n} zonas." : '';
            } catch (\Throwable $e) {
            }
        }
        $this->accounts->recordTest($id, $r['ok'] ? 'ok' : 'error', $msg);

        return ['ok' => $r['ok'], 'message' => $msg];
    }
}
