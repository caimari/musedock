<?php
namespace Screenart\Musedock\Mail;

use Screenart\Musedock\Database;

/** Central authorization, deliberately separate from tenant-editable settings. */
final class TenantSmtpPolicy
{
    public static function allowsGlobal(int $tenantId): bool
    {
        if ($tenantId < 1) return false;
        try {
            $q=Database::connect()->prepare('SELECT global_allowed FROM tenant_smtp_permissions WHERE tenant_id=?');
            $q->execute([$tenantId]);
            return (string)$q->fetchColumn()==='1';
        } catch (\Throwable $e) {
            // Missing migration or database error must never grant mail relay access.
            error_log('TenantSmtpPolicy: no se pudo comprobar permiso; SMTP global bloqueado para tenant '.$tenantId);
            return false;
        }
    }
}
