# Configuración de Dominios Personalizados

Esta guía explica cómo configurar el sistema de dominios personalizados con Cloudflare.

## 📋 Requisitos Previos

1. **Cuenta de Cloudflare Account 2** para dominios personalizados
2. **API Token de Cloudflare** con permisos:
   - Zone Settings: Edit
   - Zone: Edit
   - DNS: Edit
   - Email Routing Rules: Edit
3. **Account ID de Cloudflare**

## 🔧 Configuración Inicial

### 1. Configurar Variables de Entorno

Edita el archivo `.env` y configura las credenciales de Cloudflare Account 2:

```bash
# CLOUDFLARE CUENTA 2: Dominios Personalizados (Full NS Setup)
CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN=tu_api_token_aqui
CLOUDFLARE_CUSTOM_DOMAINS_ACCOUNT_ID=tu_account_id_aqui
CLOUDFLARE_CUSTOM_DOMAINS_SSL_MODE=full
```

**Importante:**
- `CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN`: Token de API con permisos de zona y DNS
- `CLOUDFLARE_CUSTOM_DOMAINS_ACCOUNT_ID`: ID de la cuenta de Cloudflare
- `CLOUDFLARE_CUSTOM_DOMAINS_SSL_MODE`: Modo SSL (opciones: `off`, `flexible`, `full`, `full_strict`)

### 2. Configurar el CRON Job

El sistema necesita verificar automáticamente cada 30 minutos si los customers han cambiado los nameservers de sus dominios.

#### Opción A: Cron de Plesk/cPanel

Añade esta línea a tu crontab:

```bash
*/30 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cron/verify-nameservers.php
```

#### Opción B: Configurar manualmente

```bash
crontab -e
```

Añade:

```bash
# Verificación de nameservers cada 30 minutos
*/30 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cron/verify-nameservers.php
```

#### Verificar que el CRON está configurado:

```bash
crontab -l | grep verify-nameservers
```

### 3. Verificar Logs

Los logs del CRON job se escriben en el log estándar del sistema:

```bash
tail -f /var/www/vhosts/musedock.net/logs/app.log | grep CRON
```

## 🚀 Cómo Funciona

### Flujo Completo de Incorporación de Dominio Personalizado

```
1. Customer solicita dominio personalizado desde el dashboard
   ↓
2. Sistema añade dominio a Cloudflare Account 2 (Full Setup)
   ↓
3. Sistema crea CNAMEs @ y www → mortadelo.musedock.com (proxy orange)
   ↓
4. Sistema habilita Email Routing (si se solicitó)
   ↓
5. Sistema envía email al customer con instrucciones de NS
   ↓
6. Customer cambia NS en su proveedor (GoDaddy, Namecheap, etc.)
   ↓
7. CRON job verifica cada 30 min si NS cambió
   ↓
8. Cuando NS activo → Sistema configura Caddy + SSL + activa tenant
   ↓
9. Sistema envía email de confirmación al customer
   ↓
10. ¡Sitio web activo! 🎉
```

### Estados del Tenant

- **`pending`**: Tenant creado, esperando configuración inicial
- **`waiting_ns_change`**: Añadido a Cloudflare, esperando cambio de NS por parte del customer
- **`active`**: NS cambiado, Caddy configurado, SSL obtenido, sitio activo
- **`error`**: Error en algún paso del proceso

## 🔍 Verificación Manual

### Verificar que un dominio está en Cloudflare:

```bash
curl -X GET "https://api.cloudflare.com/client/v4/zones?name=ejemplo.com" \
  -H "Authorization: Bearer TU_API_TOKEN" \
  -H "Content-Type: application/json"
```

### Verificar estado de nameservers:

```bash
dig ejemplo.com NS +short
```

Los NS deben apuntar a Cloudflare (ej: `edna.ns.cloudflare.com`)

### Verificar CNAME:

```bash
dig ejemplo.com +short
```

Debe retornar IPs de Cloudflare (proxy orange activo)

## 🛠️ Troubleshooting

### El CRON no está ejecutándose

1. Verificar que el CRON está configurado:
   ```bash
   crontab -l
   ```

2. Verificar permisos del script:
   ```bash
   chmod +x /var/www/vhosts/musedock.net/httpdocs/cron/verify-nameservers.php
   ```

3. Ejecutar manualmente para ver errores:
   ```bash
   php /var/www/vhosts/musedock.net/httpdocs/cron/verify-nameservers.php
   ```

### El tenant se queda en "waiting_ns_change"

1. Verificar que el customer cambió los NS correctamente
2. Verificar propagación DNS:
   ```bash
   dig ejemplo.com NS +short
   ```
3. Forzar verificación manual:
   ```bash
   php /var/www/vhosts/musedock.net/httpdocs/cron/verify-nameservers.php
   ```

### Error de Cloudflare API

Verificar en los logs:

```bash
tail -100 /var/www/vhosts/musedock.net/logs/app.log | grep CloudflareZone
```

Posibles causas:
- API Token inválido o sin permisos
- Account ID incorrecto
- Rate limiting de Cloudflare

### No se obtiene SSL automáticamente

1. Verificar que Caddy está configurado para el dominio:
   ```bash
   curl http://localhost:2019/config/ | jq '.apps.http.servers'
   ```

2. Verificar logs de Caddy:
   ```bash
   journalctl -u caddy -n 50
   ```

3. Verificar que el dominio apunta correctamente a mortadelo.musedock.com

## 📧 Email Routing

Si el customer habilitó Email Routing:

### Verificar configuración:

```bash
curl -X GET "https://api.cloudflare.com/client/v4/zones/ZONE_ID/email/routing" \
  -H "Authorization: Bearer TU_API_TOKEN"
```

### Probar envío de email:

```bash
echo "Test email" | mail -s "Test" contact@ejemplo.com
```

El email debe llegar al email del customer configurado.

## 🎯 Ejemplo de Uso

```bash
# 1. Customer solicita dominio "miempresa.com"
# 2. Sistema envía email con NS: edna.ns.cloudflare.com y frank.ns.cloudflare.com
# 3. Customer cambia NS en GoDaddy
# 4. Esperar 2-48 horas para propagación
# 5. CRON detecta cambio automáticamente
# 6. Sistema activa el sitio y envía email de confirmación
# 7. Sitio disponible en https://miempresa.com
```

## 📚 Referencias

- [Cloudflare API Documentation](https://developers.cloudflare.com/api/)
- [Email Routing Documentation](https://developers.cloudflare.com/email-routing/)
- [Caddy DNS-01 Challenge](https://caddyserver.com/docs/automatic-https#dns-challenge)

## 🔒 Seguridad

- **NUNCA** commitear el `.env` con tokens reales
- Usar API Tokens con permisos mínimos necesarios
- Rotar tokens periódicamente
- Monitorear logs para detectar uso indebido

## 💡 Tips

1. **Tiempo de propagación**: Avisar a los customers que el cambio de NS puede tardar entre 2 y 48 horas
2. **Email de confirmación**: El sistema envía email automáticamente cuando el sitio está activo
3. **Health check**: Después de la activación, el sistema ejecuta un health check automático
4. **DNS Management**: Los customers pueden gestionar sus registros DNS desde el dashboard (próximamente)

## 🆘 Soporte

Si encuentras problemas, revisa:

1. Logs del sistema: `/var/www/vhosts/musedock.net/logs/app.log`
2. Logs de Caddy: `journalctl -u caddy`
3. Base de datos: tabla `tenants`, columnas `status`, `cloudflare_zone_id`, `cloudflare_nameservers`

---

**Versión:** 1.0
**Última actualización:** 2025-12-15
