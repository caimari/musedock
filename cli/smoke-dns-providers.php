<?php
/**
 * Smoke tests para proveedores DNS del Domain Manager.
 *
 * Uso:
 *   php cli/smoke-dns-providers.php
 */

define('APP_ROOT', realpath(__DIR__ . '/..'));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/bootstrap.php';
require_once '/var/www/vhosts/musedock.com/private-plugins/superadmin/caddy-domain-manager/Services/DnsProviderService.php';
require_once '/var/www/vhosts/musedock.com/private-plugins/superadmin/caddy-domain-manager/Services/DnsProviderAccountService.php';
require_once '/var/www/vhosts/musedock.com/private-plugins/superadmin/caddy-domain-manager/Services/DnsRecordProvisioningService.php';

use CaddyDomainManager\Services\DnsProviderService;
use CaddyDomainManager\Services\DnsProviderAccountService;
use CaddyDomainManager\Services\DnsRecordProvisioningService;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

$ok = 0;
$fail = 0;

function assertDnsSmoke(bool $condition, string $label, int &$ok, int &$fail): void
{
    if ($condition) {
        echo "[OK] {$label}\n";
        $ok++;
        return;
    }

    echo "[FAIL] {$label}\n";
    $fail++;
}

$service = new DnsProviderService();
$providers = $service->providers();

assertDnsSmoke(isset($providers['cloudflare']), 'Cloudflare sigue registrado', $ok, $fail);
assertDnsSmoke(($providers['cloudflare']['managed_dns'] ?? false) === true, 'Cloudflare conserva DNS gestionado', $ok, $fail);
assertDnsSmoke(isset($providers['route53'], $providers['digitalocean'], $providers['rfc2136']), 'Proveedores DNS-01 principales disponibles', $ok, $fail);
assertDnsSmoke(($providers['manual']['managed_dns'] ?? true) === false, 'Manual no gestiona DNS', $ok, $fail);
assertDnsSmoke(($providers['digitalocean']['managed_dns'] ?? false) === true, 'DigitalOcean permite gestion DNS por API', $ok, $fail);
assertDnsSmoke(($providers['hetzner']['managed_dns'] ?? false) === true, 'Hetzner permite gestion DNS por API', $ok, $fail);
assertDnsSmoke(($providers['route53']['dns01'] ?? false) === true && ($providers['route53']['managed_dns'] ?? true) === false, 'Route53 queda preparado para DNS-01 sin tocar flujo Cloudflare', $ok, $fail);
assertDnsSmoke($service->normalizeProvider('Route53') === 'route53', 'Normaliza proveedor en mayúsculas/minúsculas', $ok, $fail);
assertDnsSmoke($service->normalizeProvider('proveedor-inexistente') === 'manual', 'Proveedor desconocido cae a manual', $ok, $fail);

$cloudflare = $service->resolveForRequest(['dns_provider' => 'cloudflare'], false, true, false);
assertDnsSmoke(($cloudflare['provider'] ?? '') === 'cloudflare', 'Dominio Cloudflare queda en Cloudflare', $ok, $fail);
assertDnsSmoke(($cloudflare['mode'] ?? '') === 'cloudflare_zone', 'Cloudflare crea zona cuando se solicita', $ok, $fail);
assertDnsSmoke(($cloudflare['configure_cloudflare'] ?? false) === true, 'Cloudflare no se desactiva por defecto', $ok, $fail);

$external = $service->resolveForRequest(['dns_provider' => 'hetzner'], false, true, false);
assertDnsSmoke(($external['provider'] ?? '') === 'hetzner', 'Proveedor externo queda guardado', $ok, $fail);
assertDnsSmoke(($external['skip_cloudflare'] ?? false) === true, 'Proveedor externo omite Cloudflare', $ok, $fail);
assertDnsSmoke(($external['managed_by_musedock'] ?? true) === false, 'Proveedor externo no se marca como DNS gestionado', $ok, $fail);

$platform = $service->resolveForRequest(['dns_provider' => 'manual'], true, false, true);
assertDnsSmoke(($platform['provider'] ?? '') === 'cloudflare', 'Subdominio de plataforma fuerza Cloudflare', $ok, $fail);
assertDnsSmoke(($platform['mode'] ?? '') === 'platform_subdomain', 'Subdominio de plataforma mantiene modo CNAME', $ok, $fail);

$accounts = new DnsProviderAccountService();
assertDnsSmoke(in_array('api_token', $accounts->credentialFields('digitalocean'), true), 'Campos de credenciales DigitalOcean disponibles', $ok, $fail);
assertDnsSmoke(in_array('secret_api_key', $accounts->credentialFields('porkbun'), true), 'Campos de credenciales Porkbun disponibles', $ok, $fail);
assertDnsSmoke(in_array('server', $accounts->credentialFields('rfc2136'), true), 'Campos de credenciales RFC2136 disponibles', $ok, $fail);

$provisioning = new DnsRecordProvisioningService($accounts);
assertDnsSmoke($provisioning->canProvision('digitalocean') === true, 'Provisioning automatico disponible para DigitalOcean', $ok, $fail);
assertDnsSmoke($provisioning->canProvision('powerdns') === true, 'Provisioning automatico disponible para PowerDNS', $ok, $fail);
assertDnsSmoke($provisioning->canProvision('route53') === false, 'Route53 no crea registros desde MuseDock en este release', $ok, $fail);

echo "\nResultado: {$ok} OK, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
