<?php

namespace Modules\InstagramGallery\Controllers\Tenant;

use Modules\InstagramGallery\Models\InstagramConnection;
use Modules\InstagramGallery\Models\InstagramSetting;
use Modules\InstagramGallery\Services\InstagramApiService;
use Exception;

/**
 * Publica un post del blog en Instagram (foto + caption con título,
 * extracto y link al post). Endpoints AJAX para el listado del blog.
 */
class BlogShareController
{
    private $pdo;
    private $tenantId;
    private bool $isSuperadmin;

    public function __construct()
    {
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramConnection::setPdo($this->pdo);
        InstagramSetting::setPdo($this->pdo);
        $this->isSuperadmin = !empty($_SESSION['super_admin']);
        $this->tenantId = function_exists('tenant_id') ? (tenant_id() ?? 1) : 1;
    }

    /**
     * AJAX GET: devuelve el preview de lo que se publicaría + lista de
     * conexiones disponibles para el modal de compartir.
     */
    public function preview()
    {
        header('Content-Type: application/json; charset=utf-8');
        $postId = (int)($_GET['post_id'] ?? 0);
        if ($postId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Falta post_id']);
            return;
        }

        $post = $this->loadPost($postId);
        if (!$post) {
            echo json_encode(['ok' => false, 'message' => 'Post no encontrado o sin permisos']);
            return;
        }

        $imageUrl = $this->resolveImageUrl($post['featured_image'] ?? '', (int)($post['tenant_id'] ?? 0));
        if (!$imageUrl) {
            echo json_encode(['ok' => false, 'message' => 'El post no tiene imagen destacada (Instagram requiere una imagen para publicar).']);
            return;
        }

        $connections = InstagramConnection::getActiveByTenant($this->tenantId);
        $accounts = [];
        foreach ($connections as $c) {
            $presetList = $this->parsePreset((string)($c->hashtags_preset ?? ''));
            $hasFb = !empty($c->facebook_page_id) && !empty($c->facebook_page_token) && !empty($c->facebook_enabled);
            $accounts[] = [
                'id' => $c->id,
                'username' => $c->username,
                'expires_at' => $c->token_expires_at,
                'hashtags_preset' => $presetList,
                'facebook_enabled' => $hasFb,
                'facebook_page_name' => $hasFb ? ($c->facebook_page_name ?? '') : null,
            ];
        }
        if (empty($accounts)) {
            echo json_encode(['ok' => false, 'message' => 'No hay cuentas conectadas. Conecta una en Social Publisher.']);
            return;
        }

        // Caption base con los hashtags de la primera cuenta (la que sale
        // por defecto en el select). Si el usuario cambia de cuenta, el
        // cliente recalcula recombinando presets + dinámicos.
        $defaultPreset = $accounts[0]['hashtags_preset'] ?? [];
        $caption = $this->buildCaption($post, $defaultPreset);

        echo json_encode([
            'ok' => true,
            'image_url' => $imageUrl,
            'caption' => $caption,
            'caption_length' => mb_strlen($caption),
            'accounts' => $accounts,
            'dynamic_hashtags' => $this->buildDynamicHashtags((int)($post['id'] ?? 0)),
        ]);
    }

    /**
     * Parsea la cadena guardada en DB ("foo bar baz") en array ['foo','bar','baz'].
     */
    private function parsePreset(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/u', $raw) ?: [] as $t) {
            $t = trim((string)$t);
            if ($t !== '') $out[] = ltrim($t, '#');
        }
        return $out;
    }

    /**
     * AJAX POST: publica el post en Instagram y/o Facebook según los flags.
     * Parámetros:
     *   post_id            int
     *   connection_id      int (la conexión IG, que puede tener FB vinculado)
     *   caption            string (opcional — si vacío, se autogenera)
     *   publish_instagram  "1"/"0" (default 1)
     *   publish_facebook   "1"/"0" (default 0 — sólo si la conexión tiene FB on)
     */
    public function publish()
    {
        header('Content-Type: application/json; charset=utf-8');
        $postId = (int)($_POST['post_id'] ?? 0);
        $connectionId = (int)($_POST['connection_id'] ?? 0);
        $customCaption = trim((string)($_POST['caption'] ?? ''));
        $doIG = !isset($_POST['publish_instagram']) || $_POST['publish_instagram'] === '1';
        $doFB = isset($_POST['publish_facebook']) && $_POST['publish_facebook'] === '1';

        if ($postId <= 0 || $connectionId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Faltan parámetros']);
            return;
        }
        if (!$doIG && !$doFB) {
            echo json_encode(['ok' => false, 'message' => 'Tienes que elegir al menos una red (Instagram o Facebook).']);
            return;
        }

        $post = $this->loadPost($postId);
        if (!$post) {
            echo json_encode(['ok' => false, 'message' => 'Post no encontrado o sin permisos']);
            return;
        }

        $connection = InstagramConnection::find($connectionId);
        if (!$connection || ($connection->tenant_id !== null && $connection->tenant_id !== $this->tenantId)) {
            echo json_encode(['ok' => false, 'message' => 'Conexión no válida']);
            return;
        }
        if (empty($connection->app_id) || empty($connection->app_secret)) {
            echo json_encode(['ok' => false, 'message' => 'La conexión no tiene credenciales de Meta guardadas.']);
            return;
        }

        $imageUrl = $this->resolveImageUrl($post['featured_image'] ?? '', (int)($post['tenant_id'] ?? 0));
        if (!$imageUrl && $doIG) {
            echo json_encode(['ok' => false, 'message' => 'El post no tiene imagen destacada (Instagram la requiere).']);
            return;
        }
        if ($imageUrl && str_starts_with($imageUrl, 'http://')) {
            echo json_encode(['ok' => false, 'message' => 'Instagram requiere imagen HTTPS, no HTTP.']);
            return;
        }

        $preset = $this->parsePreset((string)($connection->hashtags_preset ?? ''));
        $caption = $customCaption !== '' ? $customCaption : $this->buildCaption($post, $preset);
        $postLink = $this->buildPublicPostUrl($post);

        $api = new InstagramApiService($connection->app_id, $connection->app_secret, $connection->redirect_uri ?: '');

        $igResult = null;
        $fbResult = null;
        $errors = [];

        // --- Instagram ---
        if ($doIG) {
            if (!$connection->is_active) {
                $errors[] = 'IG: conexión inactiva';
            } elseif (strtotime((string)$connection->token_expires_at) < time()) {
                $errors[] = 'IG: token caducado, reautoriza la cuenta';
            } else {
                try {
                    $igResult = $api->publishPhoto(
                        $connection->instagram_user_id,
                        $imageUrl,
                        $caption,
                        $connection->access_token
                    );
                    $this->saveTrackingInstagram($postId, $connectionId, $igResult['id'] ?? null, $igResult['permalink'] ?? null);
                } catch (Exception $e) {
                    error_log('IG publish error: ' . $e->getMessage());
                    $errors[] = 'IG: ' . $e->getMessage();
                }
            }
        }

        // --- Facebook ---
        if ($doFB) {
            if (empty($connection->facebook_page_id) || empty($connection->facebook_page_token) || empty($connection->facebook_enabled)) {
                $errors[] = 'FB: la conexión no tiene Página de Facebook vinculada (o está desactivada).';
            } else {
                try {
                    // Para FB el caption va sin el 🔗 (lo pondremos como link clicable aparte)
                    $fbMessage = $this->stripEmbeddedLinkFromCaption($caption, $postLink);
                    $fbResult = $api->publishToFacebookPage(
                        (string)$connection->facebook_page_id,
                        (string)$connection->facebook_page_token,
                        $fbMessage,
                        $postLink,
                        $imageUrl ?: null
                    );
                    $this->saveTrackingFacebook($postId, $fbResult['id'] ?? null, $fbResult['permalink'] ?? null);
                } catch (Exception $e) {
                    error_log('FB publish error: ' . $e->getMessage());
                    $errors[] = 'FB: ' . $e->getMessage();
                }
            }
        }

        // Respuesta: ok si al menos una red se publicó sin error.
        $anyOk = ($igResult !== null) || ($fbResult !== null);
        $msgParts = [];
        if ($igResult) $msgParts[] = 'Instagram OK';
        if ($fbResult) $msgParts[] = 'Facebook OK';
        if (!empty($errors)) $msgParts = array_merge($msgParts, $errors);

        echo json_encode([
            'ok' => $anyOk && empty($errors),
            'partial' => $anyOk && !empty($errors),
            'message' => implode(' · ', $msgParts) ?: 'No se pudo publicar',
            'instagram' => $igResult ? [
                'permalink' => $igResult['permalink'] ?? null,
                'id' => $igResult['id'] ?? null,
            ] : null,
            'facebook' => $fbResult ? [
                'permalink' => $fbResult['permalink'] ?? null,
                'id' => $fbResult['id'] ?? null,
            ] : null,
            'errors' => $errors,
        ]);
    }

    /**
     * Carga un post del blog asegurando que pertenece al tenant actual.
     */
    private function loadPost(int $postId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, title, slug, excerpt, content, featured_image, tenant_id, status FROM blog_posts WHERE id = ? LIMIT 1");
        $stmt->execute([$postId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;

        $postTenantId = (int)($row['tenant_id'] ?? 0);

        // Superadmin can access any tenant's posts — adjust tenantId to post's tenant
        if ($this->isSuperadmin) {
            $this->tenantId = $postTenantId;
            return $row;
        }

        // Tenant users only see their own tenant's posts
        if ($postTenantId !== (int)$this->tenantId) return null;
        return $row;
    }

    /**
     * Construye la URL pública del post usando el dominio y el prefijo
     * de blog del tenant al que pertenece, NO el del request actual
     * (el superadmin publica desde musedock.com, pero el link debe ir al
     * dominio real del tenant, ej. freenet.es).
     */
    private function buildPublicPostUrl(array $post): string
    {
        $slug = trim((string)($post['slug'] ?? ''));
        $tenantId = (int)($post['tenant_id'] ?? 0);

        $host = null;
        $prefix = 'blog'; // default

        if ($tenantId > 0) {
            try {
                $t = $this->pdo->prepare("SELECT domain FROM tenants WHERE id = ? LIMIT 1");
                $t->execute([$tenantId]);
                $host = $t->fetchColumn() ?: null;

                // Leer el blog_url_prefix de tenant_settings para ese tenant concreto.
                $p = $this->pdo->prepare(
                    "SELECT value FROM tenant_settings
                     WHERE tenant_id = ? AND key = 'blog_url_prefix'
                     LIMIT 1"
                );
                $p->execute([$tenantId]);
                $raw = $p->fetchColumn();
                if ($raw !== false) {
                    // El usuario puede haber desactivado el prefijo poniendo cadena vacía.
                    $prefix = trim((string)$raw);
                }
            } catch (\Throwable $e) {
                // Si algo falla, caemos al fallback con url() + blog_prefix() actuales.
            }
        }

        // Fallback: si no tenemos dominio propio del tenant, usar el del request
        // (puede ser musedock.com si lo publica un superadmin — no ideal, pero
        // sólo se da si el post no tiene tenant_id, que es el blog del CMS global).
        if (!$host) {
            $base = url('/');
            return rtrim($base, '/')
                . ($prefix !== '' ? '/' . trim($prefix, '/') : '')
                . '/' . ltrim($slug, '/');
        }

        $path = ($prefix !== '' ? '/' . trim($prefix, '/') : '') . '/' . ltrim($slug, '/');
        return 'https://' . $host . preg_replace('#/{2,}#', '/', $path);
    }

    /**
     * Convierte el path de la imagen en URL absoluta HTTPS.
     */
    private function resolveImageUrl(string $path, ?int $tenantId = null): ?string
    {
        $path = trim($path);
        if ($path === '') return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return str_replace('http://', 'https://', $path);
        }
        $relative = '/' . ltrim($path, '/');

        // Servir desde el dominio del tenant del post si es posible, no el del
        // request actual (que puede ser musedock.com cuando publica el superadmin).
        if ($tenantId !== null && $tenantId > 0) {
            try {
                $t = $this->pdo->prepare("SELECT domain FROM tenants WHERE id = ? LIMIT 1");
                $t->execute([$tenantId]);
                $host = $t->fetchColumn();
                if ($host) {
                    return 'https://' . $host . $relative;
                }
            } catch (\Throwable $e) {
                // fallback abajo
            }
        }

        $url = url($relative);
        return str_starts_with($url, 'http://') ? 'https://' . substr($url, 7) : $url;
    }

    /**
     * Extrae los textos de los <h2> del contenido HTML. Útil cuando el
     * post no tiene excerpt: devolvemos un índice de secciones como teaser.
     * @return string[]  Máximo 8 h2 (suficiente teaser, no desvela el cuerpo).
     */
    private function extractHeadings(string $html): array
    {
        if ($html === '') return [];
        $out = [];
        if (preg_match_all('#<h2\b[^>]*>(.*?)</h2>#is', $html, $m)) {
            foreach ($m[1] as $raw) {
                $clean = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($clean === '') continue;
                $out[] = $clean;
                if (count($out) >= 8) break;
            }
        }
        return $out;
    }

    /**
     * Devuelve los hashtags dinámicos (categorías + tags del post),
     * normalizados pero SIN '#'. Es el frontend / buildCaption quien
     * los combina con los preset de la cuenta.
     *
     * @return string[]  ej: ['desarrolloweb','seo']
     */
    private function buildDynamicHashtags(int $postId): array
    {
        if ($postId <= 0) return [];
        $names = [];

        $cats = $this->pdo->prepare(
            "SELECT c.name FROM blog_categories c
             INNER JOIN blog_post_categories pc ON pc.category_id = c.id
             WHERE pc.post_id = ?"
        );
        $cats->execute([$postId]);
        foreach ($cats->fetchAll(\PDO::FETCH_COLUMN) as $n) { $names[] = $n; }

        $tagsQ = $this->pdo->prepare(
            "SELECT t.name FROM blog_tags t
             INNER JOIN blog_post_tags pt ON pt.tag_id = t.id
             WHERE pt.post_id = ?"
        );
        $tagsQ->execute([$postId]);
        foreach ($tagsQ->fetchAll(\PDO::FETCH_COLUMN) as $n) { $names[] = $n; }

        $out = [];
        $seen = [];
        foreach ($names as $name) {
            $h = $this->toHashtag((string)$name);
            if ($h === '' || isset($seen[$h])) continue;
            $seen[$h] = true;
            $out[] = $h;
        }
        return $out;
    }

    /**
     * Merge inteligente de hashtags:
     *   1. Preset PRIMERO (prioridad máxima — son tu marca).
     *   2. Dinámicos DESPUÉS, sin duplicar.
     *   3. Cap a 30 (límite duro de Instagram): si sobra, se recortan los
     *      dinámicos (los preset no se tocan nunca).
     *
     * @param string[] $preset   Hashtags preset ya normalizados, sin '#'.
     * @param string[] $dynamic  Hashtags del post normalizados, sin '#'.
     * @return string[]          Array final CON '#' delante.
     */
    private function mergeHashtags(array $preset, array $dynamic): array
    {
        $out = [];
        $seen = [];
        foreach ($preset as $h) {
            if ($h === '' || isset($seen[$h])) continue;
            if (count($out) >= 30) break;
            $seen[$h] = true;
            $out[] = '#' . $h;
        }
        foreach ($dynamic as $h) {
            if ($h === '' || isset($seen[$h])) continue;
            if (count($out) >= 30) break;
            $seen[$h] = true;
            $out[] = '#' . $h;
        }
        return $out;
    }

    /**
     * Convierte "Desarrollo Web" → "desarrolloweb" apto para Instagram.
     */
    private function toHashtag(string $name): string
    {
        $s = trim($name);
        if ($s === '') return '';
        // Transliteración (quita tildes, ñ, etc.)
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s);
            if ($t !== false) $s = $t;
        } else {
            $s = mb_strtolower($s, 'UTF-8');
            $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        }
        // Sólo letras/números/guión bajo; el resto fuera
        $s = preg_replace('/[^a-z0-9_]+/u', '', $s) ?? '';
        // Instagram rechaza hashtags que empiezan por número — los saltamos
        if ($s === '' || preg_match('/^[0-9]/', $s)) return '';
        return $s;
    }

    /**
     * Caption: título + extracto + link al post + hashtags combinados
     * (preset de la cuenta + dinámicos del post, cap 30).
     * Instagram NO permite enlaces clicables — el link aparece como texto.
     *
     * @param string[] $preset  Hashtags preset ya normalizados, sin '#'.
     */
    private function buildCaption(array $post, array $preset = []): string
    {
        $title = trim((string)($post['title'] ?? ''));
        $excerpt = trim((string)($post['excerpt'] ?? ''));
        // Si no hay excerpt, construir un "teaser" a partir de los H2 del
        // contenido (índice de secciones). Así se anticipa de qué va sin
        // desvelar el cuerpo. Si tampoco hay H2, fallback a texto plano.
        if ($excerpt === '') {
            $headings = $this->extractHeadings((string)($post['content'] ?? ''));
            if (!empty($headings)) {
                $excerpt = "En el artículo:\n" . implode("\n", array_map(fn($h) => '• ' . $h, $headings));
            } else {
                $excerpt = trim(strip_tags((string)($post['content'] ?? '')));
            }
        }
        // Limitar excerpt para no pasar de 2200 chars (límite IG)
        $excerpt = mb_substr($excerpt, 0, 1500);
        if (mb_strlen($excerpt) === 1500) $excerpt .= '…';

        $link = $this->buildPublicPostUrl($post);

        $parts = [];
        if ($title !== '') $parts[] = $title;
        if ($excerpt !== '') $parts[] = $excerpt;
        $parts[] = '🔗 ' . $link;

        // Hashtags: preset de la cuenta (prioridad) + dinámicos del post.
        // Cap a 30 (límite de IG) recortando los dinámicos si hace falta.
        $dynamic = $this->buildDynamicHashtags((int)($post['id'] ?? 0));
        $hashtags = $this->mergeHashtags($preset, $dynamic);
        if (!empty($hashtags)) {
            $parts[] = implode(' ', $hashtags);
        }

        $caption = implode("\n\n", $parts);
        // Asegurar que no pasamos del límite duro de IG (2200)
        if (mb_strlen($caption) > 2200) {
            $caption = mb_substr($caption, 0, 2197) . '…';
        }
        return $caption;
    }

    /**
     * Save Instagram publish tracking info on the blog post.
     */
    private function saveTrackingInstagram(int $postId, int $connectionId, ?string $mediaId, ?string $permalink): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE blog_posts
                SET instagram_posted_at = NOW(),
                    instagram_post_id = ?,
                    instagram_permalink = ?,
                    instagram_connection_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$mediaId, $permalink, $connectionId, $postId]);
        } catch (\Exception $e) {
            error_log('Instagram saveTracking error: ' . $e->getMessage());
        }
    }

    /**
     * Save Facebook publish tracking info on the blog post.
     */
    private function saveTrackingFacebook(int $postId, ?string $postFbId, ?string $permalink): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE blog_posts
                SET facebook_posted_at = NOW(),
                    facebook_post_id = ?,
                    facebook_permalink = ?
                WHERE id = ?
            ");
            $stmt->execute([$postFbId, $permalink, $postId]);
        } catch (\Exception $e) {
            error_log('Facebook saveTracking error: ' . $e->getMessage());
        }
    }

    /**
     * Quita del caption la línea "🔗 URL" si contiene el mismo postLink,
     * para usarlo en Facebook donde el link va como campo `link=` aparte.
     */
    private function stripEmbeddedLinkFromCaption(string $caption, string $postLink): string
    {
        if ($postLink === '') return $caption;
        // Línea con el emoji 🔗 y la URL, con posible doble salto alrededor.
        $pattern = '/\n?\n?🔗\s*' . preg_quote($postLink, '/') . '\s*/u';
        $clean = preg_replace($pattern, '', $caption) ?? $caption;
        return trim($clean);
    }
}
