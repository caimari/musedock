<?php

namespace Blog\Controllers\Frontend;

use Blog\Models\BlogPost;
use Blog\Models\BlogComment;
use Screenart\Musedock\Security\Captcha;
use Screenart\Musedock\Security\IPHelper;
use Screenart\Musedock\Security\RateLimiter;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\NotificationService;
use Screenart\Musedock\Services\TenantManager;

class BlogCommentController
{
    public function captcha()
    {
        SessionSecurity::startSession();
        Captcha::generate('blog_comments');
    }

    public function store()
    {
        SessionSecurity::startSession();
        $data = $_POST;

        $postId = (int) ($data['post_id'] ?? 0);
        $tenantId = TenantManager::currentTenantId();

        if ($postId <= 0) {
            flash('error', 'No se pudo procesar el comentario.');
            header('Location: /blog');
            exit;
        }

        // Honeypot: responder como éxito para bots
        if (!empty($data['_comment_hp'])) {
            $post = BlogPost::find($postId);
            $target = $post ? (blog_url($post->slug) . '#comments') : '/blog';
            flash('success', 'Gracias. Tu comentario ha sido recibido y será revisado.');
            header('Location: ' . $target);
            exit;
        }

        $post = BlogPost::query()
            ->where('id', $postId)
            ->where('status', 'published')
            ->first();

        if (!$post) {
            flash('error', 'El post no existe o no está disponible.');
            header('Location: /blog');
            exit;
        }

        // Aislamiento por tenant
        if ($tenantId !== null) {
            if ((int) ($post->tenant_id ?? 0) !== (int) $tenantId) {
                flash('error', 'No se pudo procesar el comentario.');
                header('Location: /blog');
                exit;
            }
        } else {
            $postTenant = $post->tenant_id;
            if (!($postTenant === null || (int)$postTenant === 0)) {
                flash('error', 'No se pudo procesar el comentario.');
                header('Location: /blog');
                exit;
            }
        }

        $postUrl = blog_url($post->slug) . '#comments';

        if (!(bool) $post->allow_comments) {
            flash('error', 'Los comentarios están desactivados para este post.');
            header('Location: ' . $postUrl);
            exit;
        }

        $authorName = trim((string) ($data['author_name'] ?? ''));
        $authorEmail = trim((string) ($data['author_email'] ?? ''));
        $authorUrl = trim((string) ($data['author_url'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        $captchaInput = trim((string) ($data['comment_captcha'] ?? ''));
        $legalAccepted = (string)($data['comment_legal_accept'] ?? '') === '1';

        $errors = [];

        if ($authorName === '' || mb_strlen($authorName) < 2 || mb_strlen($authorName) > 120) {
            $errors[] = 'El nombre es obligatorio (2-120 caracteres).';
        }

        if ($authorEmail === '' || !filter_var($authorEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($authorEmail) > 190) {
            $errors[] = 'Introduce un email válido.';
        }

        if ($content === '' || mb_strlen($content) < 3 || mb_strlen($content) > 3000) {
            $errors[] = 'El comentario debe tener entre 3 y 3000 caracteres.';
        }

        if (!$legalAccepted) {
            $errors[] = 'Debes aceptar la Política de Privacidad y los Términos para publicar.';
        }

        if ($authorUrl !== '' && !filter_var($authorUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'La URL del sitio web no es válida.';
        }

        // Rate limit por IP y post
        $ip = IPHelper::getRealIP();
        $rlIdentifier = 'blog_comment|' . $post->id . '|' . $ip;

        if (!RateLimiter::check($rlIdentifier, 5, 15)) {
            flash('error', 'Has enviado demasiados comentarios. Inténtalo en unos minutos.');
            header('Location: ' . $postUrl);
            exit;
        }

        if (BlogComment::shouldRequireCaptcha($tenantId)) {
            if ($captchaInput === '' || !Captcha::verify($captchaInput, 'blog_comments')) {
                $errors[] = 'Código CAPTCHA inválido.';
            }
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            header('Location: ' . $postUrl);
            exit;
        }

        // Heurística anti-spam configurable
        $status = 'pending';
        $spamLinksThreshold = BlogComment::spamLinksThreshold();
        $linkCount = preg_match_all('/(https?:\/\/|www\.)/i', $content);

        if ($linkCount >= $spamLinksThreshold) {
            $status = 'spam';
        } elseif (BlogComment::shouldAutoApprove($authorEmail, $tenantId)) {
            $status = 'approved';
        }

        $comment = BlogComment::create([
            'tenant_id' => $post->tenant_id,
            'post_id' => $post->id,
            'author_name' => mb_substr(strip_tags($authorName), 0, 120),
            'author_email' => mb_substr($authorEmail, 0, 190),
            'author_url' => $authorUrl !== '' ? mb_substr($authorUrl, 0, 500) : null,
            'content' => $content,
            'status' => $status,
            'ip_address' => $ip,
            'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'legal_consent' => $legalAccepted ? 1 : 0,
            'legal_consent_at' => $legalAccepted ? date('Y-m-d H:i:s') : null,
            'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
        ]);

        RateLimiter::increment($rlIdentifier, 15);

        if ($comment && $status === 'pending') {
            try {
                NotificationService::notifyBlogCommentPending(
                    (int) $comment->id,
                    (int) $post->id,
                    (string) ($post->title ?? ''),
                    (string) ($authorName ?? ''),
                    $post->tenant_id !== null ? (int)$post->tenant_id : null
                );
            } catch (\Throwable $e) {
                error_log('BlogCommentController notify error: ' . $e->getMessage());
            }
        }

        if ($comment && $status === 'approved') {
            BlogComment::recalculatePostCommentCount((int) $post->id);
        }

        if ($status === 'approved') {
            flash('success', 'Gracias. Tu comentario se ha publicado.');
        } else {
            flash('success', 'Gracias. Tu comentario ha sido recibido y será revisado.');
        }
        header('Location: ' . $postUrl);
        exit;
    }
}
