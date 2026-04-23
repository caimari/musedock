<?php

namespace Blog\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;
use Blog\Models\BlogComment;

class BlogCommentController
{
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __('blog.post.error_no_permission'));
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    public function index()
    {
        $this->checkPermission('blog.view');

        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: ' . admin_url('dashboard'));
            exit;
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? 'pending'));
        $perPage = (int) ($_GET['perPage'] ?? 20);
        $currentPage = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        $allowedStatuses = ['pending', 'approved', 'spam', 'rejected', 'all'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $query = BlogComment::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'DESC');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->whereRaw('(author_name LIKE ? OR author_email LIKE ? OR content LIKE ?)', [$term, $term, $term]);
        }

        $pagination = $query->paginate($perPage, $currentPage);
        $comments = $pagination['items'] ?? [];

        $postIds = [];
        foreach ($comments as $comment) {
            $postIds[] = (int) $comment->post_id;
        }
        $postIds = array_values(array_unique(array_filter($postIds)));

        $postMap = [];
        if (!empty($postIds)) {
            $pdo = Database::connect();
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $stmt = $pdo->prepare("SELECT id, title, slug FROM blog_posts WHERE id IN ({$placeholders})");
            $stmt->execute($postIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $post) {
                $postMap[(int) $post->id] = $post;
            }
        }

        foreach ($comments as $comment) {
            $post = $postMap[(int) $comment->post_id] ?? null;
            $comment->post_title = $post->title ?? 'Post eliminado';
            $comment->post_slug = $post->slug ?? null;
        }

        return View::renderTenant('blog.comments.index', [
            'title' => 'Comentarios del Blog',
            'comments' => $comments,
            'pagination' => $pagination,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function approve($id)
    {
        $this->checkPermission('blog.edit');
        $comment = $this->findComment((int) $id);

        if (!$comment) {
            flash('error', 'Comentario no encontrado o sin permisos.');
            header('Location: ' . admin_url('blog/comments'));
            exit;
        }

        $comment->status = 'approved';
        $comment->approved_at = date('Y-m-d H:i:s');
        $comment->approved_by = $_SESSION['admin']['id'] ?? null;
        $comment->approved_by_type = 'admin';
        $comment->save();

        BlogComment::recalculatePostCommentCount((int) $comment->post_id);

        flash('success', 'Comentario aprobado.');
        $this->back();
    }

    public function spam($id)
    {
        $this->checkPermission('blog.edit');
        $comment = $this->findComment((int) $id);

        if (!$comment) {
            flash('error', 'Comentario no encontrado o sin permisos.');
            header('Location: ' . admin_url('blog/comments'));
            exit;
        }

        $comment->status = 'spam';
        $comment->save();

        BlogComment::recalculatePostCommentCount((int) $comment->post_id);

        flash('success', 'Comentario marcado como spam.');
        $this->back();
    }

    public function destroy($id)
    {
        $this->checkPermission('blog.edit');
        $comment = $this->findComment((int) $id);

        if (!$comment) {
            flash('error', 'Comentario no encontrado o sin permisos.');
            header('Location: ' . admin_url('blog/comments'));
            exit;
        }

        $postId = (int) $comment->post_id;
        $comment->delete();

        BlogComment::recalculatePostCommentCount($postId);

        flash('success', 'Comentario eliminado.');
        $this->back();
    }

    private function findComment(int $id): ?BlogComment
    {
        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            return null;
        }

        $comment = BlogComment::query()
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        return $comment instanceof BlogComment ? $comment : null;
    }

    private function back(): void
    {
        $fallback = admin_url('blog/comments');
        $back = $_SERVER['HTTP_REFERER'] ?? $fallback;
        header('Location: ' . $back);
        exit;
    }
}
