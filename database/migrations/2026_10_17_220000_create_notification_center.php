<?php
use Screenart\Musedock\Services\Notifications\NotificationCenter;

/** Ajustes → Avisos: tablas de destinatarios, tipos y registro, y super_admins.locale (las crea el propio servicio). */
class CreateNotificationCenter_2026_10_17_220000
{
    public function up(): void { NotificationCenter::types(); NotificationCenter::recipients(); NotificationCenter::importLegacy(); }
    public function down(): void { /* Se conservan destinatarios y registro. */ }
}
