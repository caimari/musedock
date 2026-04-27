<?php

use Screenart\Musedock\Database;

class SeedDnsMultiproviderDocs_2026_04_27_230001
{
    public function up()
    {
        $pdo = Database::connect();
        $now = date('Y-m-d H:i:s');

        $panelId = $this->findCategoryId($pdo, 'panel');
        if (!$panelId) {
            $docsId = $this->findCategoryId($pdo, 'docs');
            $panelId = $this->upsertCategory($pdo, 'MuseDock Panel', 'panel', 'Documentacion del panel de hosting.', $docsId, 2, $now);
        }

        $sectionId = $this->upsertCategory(
            $pdo,
            'Certificados y DNS',
            'certificados-dns',
            'Panel TLS, DNS-01, proveedores DNS y gestion de certificados.',
            $panelId,
            1,
            $now
        );

        $slug = 'dns-multiproveedor-certificados-panel';
        $title = 'DNS multi-proveedor y certificados del panel';
        $excerpt = 'Guia practica para configurar el dominio del panel, DNS-01, HTTP-01, Cloudflare, cuentas DNS multi-proveedor y renovacion de certificados.';
        $content = <<<'HTML'
<p>Esta guia explica como funciona el sistema actual de DNS y certificados del panel. Usa ejemplos genericos como <code>panel.midominio.com</code> y <code>midominio.com</code>; adapta los nombres a tu instalacion.</p>

<h2>Objetivo</h2>
<p>El panel puede funcionar con acceso por IP y tambien con un dominio o subdominio propio. Cuando usas un dominio publico, el certificado debe ser valido para evitar errores de navegador, especialmente si el dominio tiene HSTS o esta detras de un proxy.</p>
<ul>
    <li><strong>Acceso directo por IP:</strong> util para primera instalacion o emergencia. Normalmente usa certificado interno/autofirmado.</li>
    <li><strong>Dominio directo al servidor:</strong> por ejemplo <code>panel.midominio.com:8444</code>, con certificado publico emitido por Let's Encrypt.</li>
    <li><strong>Dominio detras de proxy DNS/CDN:</strong> por ejemplo proxy naranja de Cloudflare. En este caso se recomienda DNS-01.</li>
</ul>

<h2>Donde se configura</h2>
<ul>
    <li><strong>Dominio del panel:</strong> Ajustes del servidor/panel.</li>
    <li><strong>Asistente ACME:</strong> <code>/musedock/settings/acme-assistant</code>. Diagnostica puertos, proveedor DNS, metodo ACME y errores de emision.</li>
    <li><strong>Cuentas Cloudflare:</strong> <code>/musedock/plugins/caddy-domain-manager/cloudflare-accounts</code>. Mantiene el flujo Cloudflare existente.</li>
    <li><strong>Cuentas DNS:</strong> <code>/musedock/plugins/caddy-domain-manager/dns-accounts</code>. Guarda credenciales cifradas de otros proveedores DNS.</li>
    <li><strong>Domain Manager:</strong> permite elegir proveedor DNS global y por tenant, alias o redireccion.</li>
</ul>

<h2>Metodos ACME disponibles</h2>
<h3>HTTP-01</h3>
<p>Let's Encrypt entra por el puerto <code>80</code> del servidor para comprobar que controlas el dominio. Requiere que el DNS apunte al servidor y que el puerto 80 sea accesible desde Internet durante emision y renovacion.</p>
<h3>TLS-ALPN-01</h3>
<p>Let's Encrypt entra por el puerto <code>443</code>. Tambien requiere que el puerto 443 sea accesible publicamente.</p>
<h3>DNS-01</h3>
<p>El servidor no necesita abrir 80/443 al mundo. Caddy crea temporalmente un registro TXT en tu DNS:</p>
<pre><code>_acme-challenge.panel.midominio.com = token_temporal</code></pre>
<p>Let's Encrypt valida ese TXT y emite el certificado. Es el metodo recomendado para paneles cerrados por firewall o dominios detras de proxy.</p>

<h2>Apertura temporal de firewall</h2>
<p>Si guardas el dominio del panel y el sistema detecta que <code>80</code> o <code>443</code> no estan abiertos, el panel puede pedir confirmacion y password de administrador para abrirlos temporalmente. Esto permite emitir el certificado por HTTP-01/TLS-ALPN-01 sin dejar el servidor abierto permanentemente.</p>
<p>Despues de la ventana temporal, el sistema debe cerrar solo las reglas que abrio como asistencia ACME. Si un administrador abre manualmente 80/443 de forma permanente, esas reglas no deben confundirse con reglas temporales del asistente.</p>

<h2>Cloudflare sigue separado</h2>
<p>Cloudflare mantiene su flujo propio para no romper hostings existentes:</p>
<ul>
    <li>Cuentas Cloudflare en pantalla separada.</li>
    <li>Creacion de zonas cuando se usa el flujo Cloudflare gestionado.</li>
    <li>CNAMEs automaticos.</li>
    <li>Proxy naranja/gris.</li>
    <li>Email Routing.</li>
    <li>Certificados via DNS-01 cuando el proxy impide HTTP-01.</li>
</ul>
<p>Los hostings ya creados con Cloudflare no cambian por activar otros proveedores DNS.</p>

<h2>Cuentas DNS multi-proveedor</h2>
<p>La pantalla <strong>Cuentas DNS</strong> permite guardar credenciales cifradas para proveedores no Cloudflare. Puedes crear varias cuentas, probar la conexion y marcar una como predeterminada por proveedor.</p>
<p>Al crear o editar un tenant, alias o redireccion, el proveedor DNS queda guardado en ese registro. Esto permite que un hosting use un proveedor y otro hosting use otro distinto.</p>

<h2>Proveedores soportados</h2>
<table>
    <thead>
        <tr><th>Proveedor</th><th>Estado actual</th><th>Uso recomendado</th></tr>
    </thead>
    <tbody>
        <tr><td>Cloudflare</td><td>Gestion completa en pantalla propia</td><td>Zonas, proxy, CNAMEs, Email Routing y DNS-01</td></tr>
        <tr><td>DigitalOcean</td><td>Registros DNS automaticos</td><td>Crear/actualizar A, CNAME y soporte DNS-01</td></tr>
        <tr><td>Hetzner DNS</td><td>Registros DNS automaticos</td><td>Crear/actualizar A, CNAME y soporte DNS-01</td></tr>
        <tr><td>Vultr DNS</td><td>Registros DNS automaticos</td><td>Crear/actualizar A, CNAME y soporte DNS-01</td></tr>
        <tr><td>Linode DNS</td><td>Registros DNS automaticos</td><td>Crear/actualizar A, CNAME y soporte DNS-01</td></tr>
        <tr><td>Porkbun</td><td>Registros DNS automaticos si la zona existe</td><td>Dominios gestionados en Porkbun</td></tr>
        <tr><td>PowerDNS</td><td>Registros DNS automaticos en zona existente</td><td>Infraestructura DNS propia</td></tr>
        <tr><td>Route53</td><td>Credenciales y diagnostico DNS-01</td><td>Preparado para DNS-01; sin creacion automatica de zona/registros en este release</td></tr>
        <tr><td>OVH</td><td>Credenciales y diagnostico DNS-01</td><td>Preparado para DNS-01; sin creacion automatica de zona/registros en este release</td></tr>
        <tr><td>Namecheap</td><td>Credenciales y diagnostico DNS-01</td><td>Preparado para DNS-01; sin creacion automatica de zona/registros en este release</td></tr>
        <tr><td>Gandi</td><td>Credenciales y diagnostico DNS-01</td><td>Preparado para DNS-01; sin creacion automatica de zona/registros en este release</td></tr>
        <tr><td>RFC2136 / BIND</td><td>Credenciales y diagnostico DNS-01</td><td>Preparado para DNS-01 con TSIG; sin creacion automatica de zona/registros en este release</td></tr>
    </tbody>
</table>

<h2>Flujo recomendado para un dominio del panel</h2>
<ol>
    <li>Crea un registro DNS para <code>panel.midominio.com</code> apuntando al servidor.</li>
    <li>En Ajustes del panel, guarda el dominio del panel.</li>
    <li>Si usas DNS directo, permite HTTP-01/TLS-ALPN-01 abriendo 80/443 o usando la asistencia temporal.</li>
    <li>Si usas proxy o quieres firewall cerrado, configura una cuenta DNS y usa DNS-01.</li>
    <li>Comprueba el certificado con el navegador y con el asistente ACME.</li>
</ol>

<h2>Flujo recomendado para hostings</h2>
<ul>
    <li><strong>Cloudflare:</strong> usa el flujo existente.</li>
    <li><strong>Manual / externo:</strong> MuseDock no toca DNS; tu configuras A/CNAME fuera.</li>
    <li><strong>Proveedor DNS con cuenta:</strong> MuseDock guarda el proveedor y, si hay cuenta activa compatible, crea o actualiza registros automaticamente.</li>
</ul>
<p>Para crear registros <code>A</code> automaticamente es recomendable definir <code>DNS_WEB_TARGET_IP</code> o <code>SERVER_PUBLIC_IP</code> en el entorno del servidor.</p>

<h2>Que ocurre si falta una cuenta o token</h2>
<p>Si eliges un proveedor sin cuenta activa o con credenciales incompletas, el panel no debe romper el alta. Guarda el proveedor, muestra el aviso correspondiente y deja claro el siguiente paso: crear la cuenta DNS, corregir token, abrir puertos temporalmente o usar modo manual.</p>

<h2>Resumen rapido</h2>
<ul>
    <li>Cloudflare sigue siendo el flujo mas completo y separado.</li>
    <li>DNS-01 es la mejor opcion para paneles cerrados por firewall o dominios con proxy.</li>
    <li>DigitalOcean, Hetzner, Vultr, Linode, Porkbun y PowerDNS ya tienen gestion automatica de registros DNS.</li>
    <li>Route53, OVH, Namecheap, Gandi y RFC2136 quedan listos para credenciales/diagnostico DNS-01, con automatizacion de registros pendiente de ampliar.</li>
    <li>Cada hosting puede usar un proveedor DNS distinto.</li>
</ul>
HTML;

        $postId = $this->upsertPost($pdo, $title, $slug, $excerpt, $content, $now);
        $this->syncPostCategory($pdo, $postId, $sectionId, $now);
        $this->syncSlug($pdo, $postId, $slug);

        echo "✓ DNS multi-provider docs seeded: /docs/{$slug}\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE tenant_id IS NULL AND slug = ? LIMIT 1");
        $stmt->execute(['dns-multiproveedor-certificados-panel']);
        $postId = (int)$stmt->fetchColumn();
        if ($postId > 0) {
            $pdo->prepare("DELETE FROM slugs WHERE module = 'blog' AND reference_id = ? AND tenant_id IS NULL")->execute([$postId]);
            $pdo->prepare("DELETE FROM blog_post_categories WHERE post_id = ?")->execute([$postId]);
            $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$postId]);
        }
    }

    private function findCategoryId(\PDO $pdo, string $slug): ?int
    {
        $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE tenant_id IS NULL AND slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function upsertCategory(\PDO $pdo, string $name, string $slug, string $description, ?int $parentId, int $order, string $now): int
    {
        $existing = $this->findCategoryId($pdo, $slug);
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE blog_categories SET parent_id = ?, name = ?, description = ?, "order" = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$parentId, $name, $description, $order, $now, $existing]);
            return $existing;
        }

        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare('INSERT INTO blog_categories (tenant_id, parent_id, name, slug, description, `order`, post_count, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, ?, 0, ?, ?)');
            $stmt->execute([$parentId, $name, $slug, $description, $order, $now, $now]);
            return (int)$pdo->lastInsertId();
        }

        $stmt = $pdo->prepare('INSERT INTO blog_categories (tenant_id, parent_id, name, slug, description, "order", post_count, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, ?, 0, ?, ?) RETURNING id');
        $stmt->execute([$parentId, $name, $slug, $description, $order, $now, $now]);
        return (int)$stmt->fetchColumn();
    }

    private function upsertPost(\PDO $pdo, string $title, string $slug, string $excerpt, string $content, string $now): int
    {
        $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE tenant_id IS NULL AND slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $existing = (int)$stmt->fetchColumn();
        if ($existing > 0) {
            $stmt = $pdo->prepare('UPDATE blog_posts SET title = ?, excerpt = ?, content = ?, status = ?, post_type = ?, visibility = ?, published_at = COALESCE(published_at, ?), updated_at = ?, seo_title = ?, seo_description = ?, robots_directive = ? WHERE id = ?');
            $stmt->execute([$title, $excerpt, $content, 'published', 'docs', 'public', $now, $now, $title, $excerpt, 'index,follow', $existing]);
            return $existing;
        }

        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare('INSERT INTO blog_posts (tenant_id, user_id, user_type, title, slug, excerpt, content, status, visibility, published_at, base_locale, allow_comments, featured, seo_title, seo_description, robots_directive, hide_title, post_type, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([2, 'superadmin', $title, $slug, $excerpt, $content, 'published', 'public', $now, 'es', 0, 0, $title, $excerpt, 'index,follow', 0, 'docs', $now, $now]);
            return (int)$pdo->lastInsertId();
        }

        $stmt = $pdo->prepare('INSERT INTO blog_posts (tenant_id, user_id, user_type, title, slug, excerpt, content, status, visibility, published_at, base_locale, allow_comments, featured, seo_title, seo_description, robots_directive, hide_title, post_type, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id');
        $stmt->execute([2, 'superadmin', $title, $slug, $excerpt, $content, 'published', 'public', $now, 'es', 0, 0, $title, $excerpt, 'index,follow', 0, 'docs', $now, $now]);
        return (int)$stmt->fetchColumn();
    }

    private function syncPostCategory(\PDO $pdo, int $postId, int $categoryId, string $now): void
    {
        $pdo->prepare("DELETE FROM blog_post_categories WHERE post_id = ?")->execute([$postId]);
        $pdo->prepare("INSERT INTO blog_post_categories (post_id, category_id, created_at) VALUES (?, ?, ?)")->execute([$postId, $categoryId, $now]);
    }

    private function syncSlug(\PDO $pdo, int $postId, string $slug): void
    {
        $pdo->prepare("DELETE FROM slugs WHERE module = 'blog' AND reference_id = ? AND tenant_id IS NULL")->execute([$postId]);
        $pdo->prepare("INSERT INTO slugs (tenant_id, module, reference_id, slug, prefix, locale) VALUES (NULL, ?, ?, ?, ?, NULL)")->execute(['blog', $postId, $slug, 'docs']);
    }
}
