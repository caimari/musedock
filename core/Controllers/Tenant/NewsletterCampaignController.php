<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\NewsletterCampaignService;
use Screenart\Musedock\View;

class NewsletterCampaignController
{
    public function index()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $tenantId = tenant_id();
        if (!$tenantId) {
            http_response_code(400);
            exit('Tenant no detectado');
        }

        $status = trim((string)($_GET['status'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));

        $pdo = Database::connect();

        $sql = "SELECT * FROM newsletter_campaigns WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($status !== '') {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        if ($q !== '') {
            $sql .= " AND (name LIKE ? OR subject LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql .= " ORDER BY created_at DESC LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderTenantAdmin('newsletter/campaigns/index', [
            'title' => 'Campañas Newsletter',
            'campaigns' => $campaigns,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        return View::renderTenantAdmin('newsletter/campaigns/create', [
            'title' => 'Nueva Campaña',
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $tenantId = tenant_id();
        if (!$tenantId) {
            http_response_code(400);
            exit('Tenant no detectado');
        }

        $subject = trim((string)($_POST['subject'] ?? ''));
        if ($subject === '') {
            flash('error', 'El asunto es obligatorio.');
            header('Location: /' . admin_path() . '/newsletter/campaigns/create');
            exit;
        }

        $userId = null;
        if (isset($_SESSION['admin']['id'])) {
            $userId = (int)$_SESSION['admin']['id'];
        } elseif (isset($_SESSION['user']['id'])) {
            $userId = (int)$_SESSION['user']['id'];
        }

        $id = NewsletterCampaignService::createCampaign([
            'name' => $_POST['name'] ?? '',
            'subject' => $subject,
            'preheader' => $_POST['preheader'] ?? '',
            'html_content' => $_POST['html_content'] ?? '',
            'text_content' => $_POST['text_content'] ?? '',
            'from_name' => $_POST['from_name'] ?? '',
            'from_email' => $_POST['from_email'] ?? '',
            'reply_to' => $_POST['reply_to'] ?? '',
            'scheduled_at' => $_POST['scheduled_at'] ?? '',
            'created_by_type' => 'tenant_admin',
            'created_by_id' => $userId,
        ], (int)$tenantId);

        flash('success', 'Campaña creada.');
        header('Location: /' . admin_path() . '/newsletter/campaigns/' . $id . '/edit');
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $tenantId = tenant_id();
        if (!$tenantId) {
            http_response_code(400);
            exit('Tenant no detectado');
        }

        $campaign = NewsletterCampaignService::findCampaign((int)$id, (int)$tenantId);
        if (!$campaign) {
            flash('error', 'Campaña no encontrada.');
            header('Location: /' . admin_path() . '/newsletter/campaigns');
            exit;
        }

        return View::renderTenantAdmin('newsletter/campaigns/edit', [
            'title' => 'Editar Campaña',
            'campaign' => (object)$campaign,
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $tenantId = tenant_id();
        if (!$tenantId) {
            http_response_code(400);
            exit('Tenant no detectado');
        }

        $ok = NewsletterCampaignService::updateCampaign((int)$id, [
            'name' => $_POST['name'] ?? '',
            'subject' => $_POST['subject'] ?? '',
            'preheader' => $_POST['preheader'] ?? '',
            'html_content' => $_POST['html_content'] ?? '',
            'text_content' => $_POST['text_content'] ?? '',
            'from_name' => $_POST['from_name'] ?? '',
            'from_email' => $_POST['from_email'] ?? '',
            'reply_to' => $_POST['reply_to'] ?? '',
            'scheduled_at' => $_POST['scheduled_at'] ?? '',
        ], (int)$tenantId);

        if ($ok) {
            flash('success', 'Campaña actualizada.');
        } else {
            flash('error', 'No se pudo actualizar la campaña.');
        }

        header('Location: /' . admin_path() . '/newsletter/campaigns/' . (int)$id . '/edit');
        exit;
    }

    public function queue($id)
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $tenantId = tenant_id();
        if (!$tenantId) {
            http_response_code(400);
            exit('Tenant no detectado');
        }

        $result = NewsletterCampaignService::queueCampaign((int)$id, (int)$tenantId);
        flash($result['success'] ? 'success' : 'error', $result['message'] ?? 'Operación finalizada');

        header('Location: /' . admin_path() . '/newsletter/campaigns');
        exit;
    }
}
