# CLI Scripts - MuseDock CMS

Scripts de línea de comandos para tareas administrativas y programadas.

## 🔒 Seguridad

Este directorio está protegido con `.htaccess` y **NO debe ser accesible vía web**.

Los scripts solo pueden ejecutarse desde la línea de comandos (CLI).

---

## 📋 Script: `cron.php`

Ejecuta las tareas programadas del sistema (limpieza de papelera, limpieza de revisiones, etc.)

### Configuración Previa

1. **Editar `.env`** y configurar:
   ```env
   # Modo: pseudo | real | disabled
   CRON_MODE=real

   # Limpieza de papelera
   TRASH_AUTO_DELETE_ENABLED=true
   TRASH_RETENTION_DAYS=30

   # Limpieza de revisiones
   REVISION_CLEANUP_ENABLED=true
   REVISION_KEEP_RECENT=5
   REVISION_KEEP_MONTHLY=12
   REVISION_KEEP_YEARLY=3
   ```

2. **Ejecutar migración** para crear tabla `scheduled_tasks`:
   ```bash
   php /ruta/a/musedock/database/migrations/2025_11_14_020000_create_scheduled_tasks_table.php
   ```

### Uso Manual

```bash
# Ejecutar una vez (para testing)
php /ruta/a/musedock/cli/cron.php

# Ver output en pantalla
/usr/bin/php /ruta/a/musedock/cli/cron.php
```

### Configurar Crontab (Ejecución Automática)

#### 1. Editar crontab

```bash
crontab -e
```

#### 2. Agregar línea (elegir UNA de las siguientes)

**Ejecutar cada hora:**
```cron
0 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cli/cron.php >> /var/log/musedock-cron.log 2>&1
```

**Ejecutar cada 6 horas:**
```cron
0 */6 * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cli/cron.php >> /var/log/musedock-cron.log 2>&1
```

**Ejecutar una vez al día (2 AM):**
```cron
0 2 * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cli/cron.php >> /var/log/musedock-cron.log 2>&1
```

**Para testing (cada 5 minutos):**
```cron
*/5 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cli/cron.php >> /tmp/musedock-cron.log 2>&1
```

#### 3. Verificar que funciona

```bash
# Ver log
tail -f /var/log/musedock-cron.log

# O si usaste /tmp
tail -f /tmp/musedock-cron.log
```

---

## 🔄 Modos de Ejecución

### Modo `real` (Cron del Sistema)

**Configuración en `.env`:**
```env
CRON_MODE=real
```

**Características:**
- ✅ Más eficiente (no se ejecuta en cada request)
- ✅ Más preciso (se ejecuta exactamente cuando lo programas)
- ✅ Mejor rendimiento del sitio web
- ❌ Requiere acceso al crontab del servidor
- ❌ No funciona en hosting compartido sin cron

**Uso:** Configurar crontab como se indica arriba

---

### Modo `pseudo` (Pseudo-Cron)

**Configuración en `.env`:**
```env
CRON_MODE=pseudo
PSEUDO_CRON_INTERVAL=3600  # segundos (1 hora)
```

**Características:**
- ✅ Funciona en hosting compartido sin cron
- ✅ No requiere configuración del servidor
- ✅ Fácil de implementar
- ❌ Depende del tráfico del sitio
- ❌ Puede ejecutarse con retraso si no hay visitas
- ❌ Ligero overhead en cada request

**Uso:** No hacer nada, se ejecuta automáticamente en cada request (con throttle)

**Seguridad:**
- Throttle basado en tiempo (no se ejecuta más de 1 vez cada X segundos)
- Lock para evitar ejecuciones concurrentes
- NO expone endpoint público

---

### Modo `disabled` (Desactivado)

**Configuración en `.env`:**
```env
CRON_MODE=disabled
```

**Características:**
- Desactiva completamente las tareas programadas
- Útil para entornos de desarrollo o testing

---

## 📊 Monitoreo

### Ver estado de las tareas

```sql
SELECT * FROM scheduled_tasks;
```

Campos importantes:
- `last_run`: Última ejecución
- `next_run`: Próxima ejecución programada
- `status`: Estado actual (idle/running/failed)
- `run_count`: Número total de ejecuciones
- `success_count`: Ejecuciones exitosas
- `fail_count`: Ejecuciones fallidas
- `last_error`: Último error ocurrido

### Ver logs

```bash
# Logs del sistema
tail -f /var/www/vhosts/musedock.net/httpdocs/storage/logs/error.log

# Logs de cron (si configuraste el log)
tail -f /var/log/musedock-cron.log
```

---

## 🛠️ Troubleshooting

### El cron no se ejecuta

1. **Verificar permisos:**
   ```bash
   chmod +x /var/www/vhosts/musedock.net/httpdocs/cli/cron.php
   ```

2. **Verificar ruta de PHP:**
   ```bash
   which php
   # Usar la ruta completa en crontab
   ```

3. **Verificar sintaxis de crontab:**
   ```bash
   crontab -l  # Ver crontab actual
   ```

4. **Verificar logs:**
   ```bash
   tail -f /var/log/syslog | grep CRON
   ```

### Las tareas no eliminan nada

1. **Verificar configuración en `.env`:**
   ```env
   TRASH_AUTO_DELETE_ENABLED=true
   REVISION_CLEANUP_ENABLED=true
   ```

2. **Verificar que hay items para eliminar:**
   ```sql
   -- Papelera
   SELECT COUNT(*) FROM pages_trash WHERE scheduled_permanent_delete <= NOW();
   SELECT COUNT(*) FROM blog_posts_trash WHERE scheduled_permanent_delete <= NOW();

   -- Revisiones
   SELECT COUNT(*) FROM page_revisions;
   SELECT COUNT(*) FROM blog_post_revisions;
   ```

3. **Ejecutar manualmente para ver errores:**
   ```bash
   php /ruta/a/musedock/cli/cron.php
   ```

---

## 📝 Ejemplo de Output

```
╔══════════════════════════════════════════════════════════╗
║  MUSEDOCK CMS - Tareas Programadas (Cron)              ║
║  2025-11-14 02:00:00                                    ║
╚══════════════════════════════════════════════════════════╝

📋 Registrando tareas...
✓ Tareas registradas

🚀 Ejecutando tareas...
  ├─ Ejecutando: Limpieza de papelera
  │  └─ Eliminados: 3 items (Pages: 1, Posts: 2)
  ├─ Ejecutando: Limpieza de revisiones
  │  └─ Eliminadas: 47 revisiones (Pages: 25, Posts: 22)

✅ COMPLETADO

📊 RESUMEN:
  • cleanup_trash: success (2.34s)
  • cleanup_revisions: success (5.12s)
```

---

## 🔗 Ver también

- [Documentación de Cron](https://crontab.guru/) - Generador de expresiones cron
- [Configuración `.env`](../.env.example) - Variables de entorno
