<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\NewsletterCampaignService;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\View;

class NewsletterCampaignController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || ($auth['type'] ?? null) !== 'super_admin') {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $status = trim((string)($_GET['status'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));
        $tenantId = trim((string)($_GET['tenant_id'] ?? ''));

        $pdo = Database::connect();

        $sql = "SELECT c.*, t.name AS tenant_name
                FROM newsletter_campaigns c
                LEFT JOIN tenants t ON t.id = c.tenant_id
                WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }
        if ($tenantId !== '') {
            if ($tenantId === 'global') {
                $sql .= " AND c.tenant_id IS NULL";
            } else {
                $sql .= " AND c.tenant_id = ?";
                $params[] = (int)$tenantId;
            }
        }
        if ($q !== '') {
            $sql .= " AND (c.name LIKE ? OR c.subject LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql .= " ORDER BY c.created_at DESC LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $tenants = $pdo->query("SELECT id, name FROM tenants ORDER BY name ASC")->fetchAll(\PDO::FETCH_OBJ);

        return View::renderSuperadmin('newsletter/campaigns/index', [
            'title' => 'Campañas Newsletter',
            'campaigns' => $campaigns,
            'tenants' => $tenants,
            'status' => $status,
            'q' => $q,
            'tenantId' => $tenantId,
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || ($auth['type'] ?? null) !== 'super_admin') {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $pdo = Database::connect();
        $tenants = $pdo->query("SELECT id, name FROM tenants ORDER BY name ASC")->fetchAll(\PDO::FETCH_OBJ);

        return View::renderSuperadmin('newsletter/campaigns/create', [
            'title' => 'Nueva Campaña',
            'tenants' => $tenants,
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || ($auth['type'] ?? null) !== 'super_admin') {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $subject = trim((string)($_POST['subject'] ?? ''));
        if ($subject === '') {
            flash('error', 'El asunto es obligatorio.');
            header('Location: /musedock/newsletter/campaigns/create');
            exit;
        }

        $id = NewsletterCampaignService::createCampaign([
            'tenant_id' => ($_POST['tenant_id'] ?? '') === 'global' ? null : ($_POST['tenant_id'] ?? null),
            'name' => $_POST['name'] ?? '',
            'subject' => $subject,
            'preheader' => $_POST['preheader'] ?? '',
            'html_content' => $_POST['html_content'] ?? '',
            'text_content' => $_POST['text_content'] ?? '',
            'from_name' => $_POST['from_name'] ?? '',
            'from_email' => $_POST['from_email'] ?? '',
            'reply_to' => $_POST['reply_to'] ?? '',
            'scheduled_at' => $_POST['scheduled_at'] ?? '',
            'created_by_type' => 'super_admin',
            'created_by_id' => (int)($auth['id'] ?? 0),
        ]);

        flash('success', 'Campaña creada.');
        header('Location: /musedock/newsletter/campaigns/' . $id . '/edit');
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || ($auth['type'] ?? null) !== 'super_admin') {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $campaign = NewsletterCampaignService::findCampaign((int)$id, null);
        if (!$campaign) {
            flash('error', 'Campaña no encontrada.');
            header('Location: /musedock/newsletter/campaigns');
            exit;
        }

        $pdo = Database::connect();
        $tenants = $pdo->query("SELECT id, name FROM tenants ORDER BY name ASC")->fetchAll(\PDO::FETCH_OBJ);

        return View::renderSuperadmin('newsletter/campaigns/edit', [
            'title' => 'Editar Campaña',
            'campaign' => (object)$campaign,
            'tenants' => $tenants,
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || ($auth['type'] ?? null) !== 'super_admin') {
            http_response_code(403);
            exit('Acceso denegado');
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
        ], null);

        if ($ok) {
            flash('success', 'Campaña actualizada.');
        } else {
            flash('error', 'No se pudo actualizar la campaña.');
        }

        header('Location: /musedock/newsletter/campaigns/' . (int)$id . '/edit');
        exit;
    }

    public function queue($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || ($auth['type'] ?? null) !== 'super_admin') {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $result = NewsletterCampaignService::queueCampaign((int)$id, null);
        flash($result['success'] ? 'success' : 'error', $result['message'] ?? 'Operación finalizada');

        header('Location: /musedock/newsletter/campaigns');
        exit;
    }
}
