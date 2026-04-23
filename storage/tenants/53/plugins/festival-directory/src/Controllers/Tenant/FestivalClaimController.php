<?php

namespace FestivalDirectory\Controllers\Tenant;

use FestivalDirectory\Models\Festival;
use FestivalDirectory\Models\FestivalClaim;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Services\AuditLogger;

class FestivalClaimController
{
    private function getTenantId(): int
    {
        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            flash('error', 'Tenant no identificado.');
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }
        return $tenantId;
    }

    public function index()
    {
        $tenantId = $this->getTenantId();

        $statusFilter = $_GET['status'] ?? '';

        $query = FestivalClaim::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'DESC');

        if (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        $claims = $query->get();

        // Batch load festival names
        $festivalNames = [];
        if (!empty($claims)) {
            $fIds = array_unique(array_map(fn($c) => $c->festival_id, $claims));
            $placeholders = implode(',', array_fill(0, count($fIds), '?'));
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT id, name, slug FROM festivals WHERE id IN ({$placeholders})");
            $stmt->execute(array_values($fIds));
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $festivalNames[$row['id']] = $row;
            }
        }

        // Count by status
        $pdo = Database::connect();
        $countStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM festival_claims WHERE tenant_id = ? GROUP BY status");
        $countStmt->execute([$tenantId]);
        $statusCounts = [];
        foreach ($countStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $statusCounts[$row['status']] = (int)$row['cnt'];
        }

        echo festival_render_admin('tenant.claims.index', [
            'title'         => 'Claims',
            'claims'        => $claims,
            'festivalNames' => $festivalNames,
            'statusFilter'  => $statusFilter,
            'statusCounts'  => $statusCounts,
        ]);
    }

    public function show($id)
    {
        $tenantId = $this->getTenantId();

        $claim = FestivalClaim::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$claim) {
            flash('error', 'No encontrado.');
            header('Location: ' . festival_admin_url('claims'));
            exit;
        }

        $festival = Festival::find($claim->festival_id);

        echo festival_render_admin('tenant.claims.show', [
            'title'    => 'Claim' . ' #' . $id,
            'claim'    => $claim,
            'festival' => $festival,
        ]);
    }

    public function approve($id)
    {
        $tenantId = $this->getTenantId();

        $claim = FestivalClaim::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$claim) {
            flash('error', 'No encontrado.');
            header('Location: ' . festival_admin_url('claims'));
            exit;
        }

        $pdo = Database::connect();
        $adminNotes = $_POST['admin_notes'] ?? '';
        $adminId = $_SESSION['admin']['id'] ?? null;

        // Update claim
        $pdo->prepare("UPDATE festival_claims SET status = 'approved', admin_notes = ?, resolved_by = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$adminNotes, $adminId, $id]);

        // Update festival status to 'claimed' and set contact email
        $pdo->prepare("UPDATE festivals SET status = 'claimed', claimed_by = ?, claimed_at = NOW(), contact_email = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$adminId, $claim->user_email, $claim->festival_id]);

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('festival_claim.approved', 'festival_claim', $id, [
                'festival_id' => $claim->festival_id,
                'user_email'  => $claim->user_email,
            ]);
        }

        flash('success', 'Claim aprobado correctamente.');
        header('Location: ' . festival_admin_url('claims'));
        exit;
    }

    public function reject($id)
    {
        $tenantId = $this->getTenantId();

        $claim = FestivalClaim::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$claim) {
            flash('error', 'No encontrado.');
            header('Location: ' . festival_admin_url('claims'));
            exit;
        }

        $adminNotes = $_POST['admin_notes'] ?? '';
        $adminId = $_SESSION['admin']['id'] ?? null;

        $pdo = Database::connect();
        $pdo->prepare("UPDATE festival_claims SET status = 'rejected', admin_notes = ?, resolved_by = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$adminNotes, $adminId, $id]);

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('festival_claim.rejected', 'festival_claim', $id, [
                'festival_id' => $claim->festival_id,
            ]);
        }

        flash('success', 'Claim rechazado.');
        header('Location: ' . festival_admin_url('claims'));
        exit;
    }
}
