<?php

namespace Screenart\Musedock\Controllers\Frontend;

use Screenart\Musedock\Database;
use Screenart\Musedock\Mail\Mailer;
use Screenart\Musedock\Services\NewsletterCampaignService;
use Screenart\Musedock\View;

class NewsletterController
{
    public function subscribe()
    {
        $csrfToken = $_POST['_csrf'] ?? ($_POST['_token'] ?? '');
        if (!function_exists('validate_csrf') || !validate_csrf($csrfToken)) {
            flash('error', 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $email = trim((string)($_POST['email'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $consent = isset($_POST['newsletter_consent']) ? 1 : 0;
        $ip = $this->clientIp();

        // Antispam básico: limitar intentos por IP en ventana corta
        if ($this->tooManyAttemptsByIp($ip, 15, 10)) {
            flash('error', 'Demasiados intentos desde tu IP. Espera unos minutos e inténtalo de nuevo.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Email no válido.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        if ($consent !== 1) {
            flash('error', 'Debes aceptar la política de privacidad para suscribirte.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        $confirmToken = bin2hex(random_bytes(24));
        $unsubscribeToken = bin2hex(random_bytes(24));
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $sourceUrl = (string)($_POST['_page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '/'));
        $consentText = 'Acepto recibir comunicaciones por email y la política de privacidad.';
        $consentVersion = 'v1';

        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE tenant_id " . ($tenantId === null ? "IS NULL" : "=") . ($tenantId === null ? "" : " ?") . " AND email = ? LIMIT 1");
            $params = [];
            if ($tenantId !== null) $params[] = $tenantId;
            $params[] = $email;
            $stmt->execute($params);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } else {
            $stmt = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE " . ($tenantId === null ? "tenant_id IS NULL" : "tenant_id = ?") . " AND email = ? LIMIT 1");
            $params = [];
            if ($tenantId !== null) $params[] = $tenantId;
            $params[] = $email;
            $stmt->execute($params);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        if ($existing && ($existing['status'] ?? '') === 'active') {
            flash('success', 'Este email ya está suscrito y confirmado.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE newsletter_subscribers SET
                name = ?, status = 'pending', confirm_token = ?, unsubscribe_token = ?,
                consent_text = ?, consent_version = ?, consent_at = NOW(), consent_ip = ?, consent_user_agent = ?, source_url = ?,
                confirmed_at = NULL, unsubscribed_at = NULL, updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$name ?: null, $confirmToken, $unsubscribeToken, $consentText, $consentVersion, $ip, $ua, $sourceUrl, (int)$existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers
                (tenant_id, email, name, status, confirm_token, unsubscribe_token, consent_text, consent_version, consent_at, consent_ip, consent_user_agent, source_url, created_at, updated_at)
                VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, NOW(), ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$tenantId, $email, $name ?: null, $confirmToken, $unsubscribeToken, $consentText, $consentVersion, $ip, $ua, $sourceUrl]);
        }

        $confirmUrl = $this->absoluteUrl('/newsletter/confirm/' . urlencode($confirmToken));
        $unsubscribeUrl = $this->absoluteUrl('/newsletter/unsubscribe/' . urlencode($unsubscribeToken));
        $siteName = function_exists('site_setting') ? site_setting('site_name', 'MuseDock') : 'MuseDock';

        $html = "<h2>Confirma tu suscripción</h2>"
              . "<p>Hola" . ($name ? ' ' . htmlspecialchars($name) : '') . ",</p>"
              . "<p>Para completar tu alta en la newsletter de <strong>" . htmlspecialchars($siteName) . "</strong>, confirma tu email:</p>"
              . "<p><a href='" . htmlspecialchars($confirmUrl) . "' style='display:inline-block;background:#0d6efd;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;'>Confirmar suscripción</a></p>"
              . "<p>Si no fuiste tú, ignora este correo o date de baja aquí: <a href='" . htmlspecialchars($unsubscribeUrl) . "'>" . htmlspecialchars($unsubscribeUrl) . "</a></p>";

        $text = "Confirma tu suscripción: " . $confirmUrl . "\n"
              . "Baja directa: " . $unsubscribeUrl;

        $sent = Mailer::send($email, 'Confirma tu suscripción', $html, $text, null, null, $tenantId !== null ? (int)$tenantId : null);

        if ($sent) {
            flash('success', 'Revisa tu email para confirmar la suscripción (doble opt-in).');
        } else {
            flash('error', 'No se pudo enviar el email de confirmación. Revisa la configuración SMTP.');
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }

    public function confirm(string $token)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE confirm_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            echo $this->renderStatus(
                'error',
                'Enlace no válido',
                'Este enlace de confirmación no es válido o ya ha caducado. Si quieres suscribirte, vuelve a introducir tu email desde la página de inicio.',
                true
            );
            exit;
        }

        $already = ($row['status'] ?? '') === 'active';
        if (!$already) {
            $up = $pdo->prepare("UPDATE newsletter_subscribers SET status = 'active', confirmed_at = NOW(), updated_at = NOW() WHERE id = ?");
            $up->execute([(int)$row['id']]);
        }

        echo $this->renderStatus(
            'confirmed',
            $already ? '¡Ya estabas suscrito!' : '¡Suscripción confirmada!',
            $already
                ? 'Tu email ya estaba confirmado. Seguirás recibiendo nuestras novedades.'
                : 'Gracias por confirmar. A partir de ahora recibirás nuestras novedades por email. Puedes darte de baja cuando quieras desde cualquier correo.',
            false
        );
        exit;
    }

    public function unsubscribe(string $token)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE unsubscribe_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            echo $this->renderStatus(
                'error',
                'Enlace no válido',
                'Este enlace de baja no es válido o ya ha caducado. Si sigues recibiendo correos, contáctanos y lo resolvemos.',
                false
            );
            exit;
        }

        $already = ($row['status'] ?? '') === 'unsubscribed';
        if (!$already) {
            $up = $pdo->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW(), updated_at = NOW() WHERE id = ?");
            $up->execute([(int)$row['id']]);
        }

        echo $this->renderStatus(
            'unsubscribed',
            $already ? 'Ya estabas dado de baja' : 'Te has dado de baja',
            $already
                ? 'Este email ya no estaba suscrito. No recibirás más correos de nuestra newsletter.'
                : 'Hemos procesado tu baja. No volverás a recibir correos de nuestra newsletter. Sentimos verte marchar.',
            true
        );
        exit;
    }

    /**
     * Renderiza la página de estado del newsletter (confirmación / baja / error).
     */
    private function renderStatus(string $state, string $title, string $message, bool $showResubscribe): string
    {
        return View::renderTheme('newsletter-status', [
            'nl_state' => $state,
            'nl_title' => $title,
            'nl_message' => $message,
            'nl_showResubscribe' => $showResubscribe,
        ]);
    }

    public function trackOpen(string $token)
    {
        if ($token !== '') {
            NewsletterCampaignService::markOpenByToken($token);
        }

        // Pixel GIF transparente 1x1
        $gif = base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($gif));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $gif;
        exit;
    }

    public function trackClick(string $token)
    {
        if ($token !== '') {
            NewsletterCampaignService::markClickByToken($token);
        }

        $url = trim((string)($_GET['url'] ?? ''));
        $target = '/';
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url)) {
            $target = $url;
        }

        header('Location: ' . $target, true, 302);
        exit;
    }

    private function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? parse_url((string)(getenv('APP_URL') ?: ''), PHP_URL_HOST) ?: 'localhost';
        return $scheme . '://' . $host . $path;
    }

    private function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = (string)$_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Limita intentos por IP para reducir abuso/bots.
     */
    private function tooManyAttemptsByIp(string $ip, int $minutes, int $maxAttempts): bool
    {
        try {
            $pdo = Database::connect();
            $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE consent_ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)");
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE consent_ip = ? AND created_at >= (NOW() - INTERVAL '{$minutes} minutes')");
            }
            $stmt->execute([$ip]);
            return ((int)$stmt->fetchColumn()) >= $maxAttempts;
        } catch (\Throwable $e) {
            // Si hay error, no bloquear todo el flujo.
            return false;
        }
    }
}
