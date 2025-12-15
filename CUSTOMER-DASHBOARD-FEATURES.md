# Customer Dashboard - Funcionalidades Implementadas

## 🎯 Resumen General

Sistema completo de gestión de dominios para customers, con soporte para:
- ✅ Subdominios FREE (.musedock.com)
- ✅ Dominios personalizados con Cloudflare
- ✅ Health checks automáticos
- ✅ Retry de configuraciones fallidas
- ✅ Email routing
- ✅ SSL automático con Cloudflare DNS-01

---

## 📦 Componentes Implementados

### 1. Dashboard del Customer (`/customer/dashboard`)

**Archivo**: [core/Views/Customer/dashboard.blade.php](core/Views/Customer/dashboard.blade.php)

**Funcionalidades**:
- ✅ Lista de todos los tenants del customer
- ✅ Health check status badge (Healthy, Degraded, Error)
- ✅ Botones de acción para cada tenant:
  - **Retry Provisioning**: Reintentar configuración de Cloudflare/Caddy si falló
  - **Health Check**: Ejecutar verificación manual de estado
- ✅ Dos botones principales:
  - **Solicitar Subdominio FREE**: Crear nuevo subdominio gratuito
  - **Solicitar Dominio Personalizado**: Incorporar dominio propio

**Ejemplo de UI**:
```
┌────────────────────────────────────────────────┐
│ Mis Sitios                                     │
├────────────────────────────────────────────────┤
│ 🌐 ejemplo.musedock.com                        │
│ Plan: FREE | ✅ Activo | ✅ Funcionando        │
│ [Acceder al Panel] [Retry] [Health Check]     │
├────────────────────────────────────────────────┤
│ [🎁 Solicitar Subdominio FREE]                 │
│ [👑 Solicitar Dominio Personalizado]           │
└────────────────────────────────────────────────┘
```

---

### 2. Solicitud de Subdominio FREE

**Ruta**: `/customer/request-free-subdomain`

**Archivos**:
- [Controllers/FreeSubdomainController.php](plugins/superadmin/caddy-domain-manager/Controllers/FreeSubdomainController.php)
- [Views/Customer/request-free-subdomain.blade.php](core/Views/Customer/request-free-subdomain.blade.php)

**Funcionalidades**:
- ✅ Formulario para solicitar subdominio `.musedock.com`
- ✅ Validación en tiempo real de disponibilidad (AJAX)
- ✅ Límite: 1 subdominio FREE por customer
- ✅ Validación de formato: 3-30 caracteres, solo minúsculas, números y guiones
- ✅ Palabras reservadas bloqueadas (www, mail, admin, etc.)
- ✅ Creación automática con ProvisioningService
- ✅ Email de bienvenida automático

**Flujo**:
```
1. Customer ingresa "mi-empresa"
2. Sistema verifica disponibilidad en tiempo real
3. Customer confirma
4. Sistema crea tenant en estado "pending"
5. Cloudflare configura DNS automáticamente
6. Caddy obtiene SSL automáticamente
7. Email enviado con credenciales de acceso
8. Health check automático después de 5 segundos
9. ¡Sitio activo en https://mi-empresa.musedock.com!
```

---

### 3. Solicitud de Dominio Personalizado

**Ruta**: `/customer/request-custom-domain`

**Archivos**:
- [Controllers/CustomDomainController.php](plugins/superadmin/caddy-domain-manager/Controllers/CustomDomainController.php)
- [Views/Customer/request-custom-domain.blade.php](core/Views/Customer/request-custom-domain.blade.php)
- [Services/CloudflareZoneService.php](plugins/superadmin/caddy-domain-manager/Services/CloudflareZoneService.php)

**Funcionalidades**:
- ✅ Formulario para incorporar dominio existente
- ✅ Añadir dominio a Cloudflare Account 2 (Full Setup) vía API
- ✅ Crear CNAMEs @ y www → mortadelo.musedock.com con proxy orange
- ✅ Habilitar Email Routing (opcional)
- ✅ Envío de instrucciones de cambio de NS por email
- ✅ Verificación automática de NS cada 30 minutos
- ✅ Activación automática cuando NS está activo
- ✅ Email de confirmación de activación

**Flujo Completo**:
```
1. Customer solicita "miempresa.com"
   └─ Checkbox: Habilitar Email Routing ✓

2. Sistema añade dominio a Cloudflare Account 2
   └─ POST /zones (Full Setup)
   └─ Estado: waiting_ns_change

3. Sistema crea CNAMEs automáticamente:
   └─ @ → mortadelo.musedock.com (proxy orange)
   └─ www → mortadelo.musedock.com (proxy orange)

4. Sistema habilita Email Routing (si se solicitó)
   └─ Catch-all: *@miempresa.com → customer@email.com

5. Sistema envía email con instrucciones:
   ┌─────────────────────────────────────────┐
   │ 📧 Instrucciones de Nameservers         │
   ├─────────────────────────────────────────┤
   │ Nameserver 1: edna.ns.cloudflare.com   │
   │ Nameserver 2: frank.ns.cloudflare.com  │
   │                                         │
   │ Cambia estos NS en tu proveedor        │
   │ (GoDaddy, Namecheap, etc.)             │
   └─────────────────────────────────────────┘

6. Customer cambia NS en su proveedor
   └─ Tiempo de propagación: 2-48 horas

7. CRON job verifica cada 30 minutos
   └─ GET /zones/{id} → status: active

8. Cuando NS activo:
   └─ Configura Caddy
   └─ Obtiene SSL automáticamente
   └─ Aplica permisos y menús por defecto
   └─ Actualiza status a "active"
   └─ Ejecuta health check

9. Email de confirmación enviado
   └─ "¡Tu dominio está activo!"

10. ¡Sitio disponible en https://miempresa.com!
```

---

### 4. Health Check Service

**Archivo**: [Services/HealthCheckService.php](plugins/superadmin/caddy-domain-manager/Services/HealthCheckService.php)

**Verificaciones**:
- ✅ **DNS**: Resolución correcta, detección de Cloudflare
- ✅ **HTTP/HTTPS**: Servidor responde (códigos 2xx, 3xx, 4xx)
- ✅ **SSL**: Certificado válido, días restantes de expiración
- ✅ **Cloudflare Proxy**: Headers CF-Ray y Server: cloudflare

**Estados**:
- **Healthy** (✅): Todo funcionando correctamente
- **Degraded** (⚠️): Funcionando pero con advertencias (SSL próximo a expirar, etc.)
- **Error** (❌): DNS no resuelve o servidor no responde

**Ejemplo de resultado**:
```json
{
  "overall_status": "healthy",
  "checks": {
    "dns": {
      "passed": true,
      "message": "DNS resolviendo correctamente",
      "cloudflare_detected": true
    },
    "http": {
      "passed": true,
      "message": "Servidor respondiendo (HTTP 200)",
      "http_code": 200
    },
    "ssl": {
      "passed": true,
      "message": "Certificado SSL válido (87 días restantes)",
      "days_left": 87
    },
    "cloudflare": {
      "passed": true,
      "message": "✅ Protegido por Cloudflare"
    }
  }
}
```

---

### 5. Cloudflare Zone Service (Account 2)

**Archivo**: [Services/CloudflareZoneService.php](plugins/superadmin/caddy-domain-manager/Services/CloudflareZoneService.php)

**Métodos Principales**:

#### `addFullZone(string $domain)`
Añade dominio a Cloudflare con Full Setup (requiere cambio de NS)

```php
$result = $cloudflareService->addFullZone('ejemplo.com');
// Returns: ['zone_id' => '...', 'nameservers' => [...], 'status' => 'pending']
```

#### `createProxiedCNAME(string $zoneId, string $name, string $target, bool $proxied)`
Crea CNAME con proxy orange

```php
$cloudflareService->createProxiedCNAME($zoneId, '@', 'mortadelo.musedock.com', true);
$cloudflareService->createProxiedCNAME($zoneId, 'www', 'mortadelo.musedock.com', true);
```

#### `enableEmailRouting(string $zoneId, string $destinationEmail)`
Habilita Email Routing con catch-all

```php
$result = $cloudflareService->enableEmailRouting($zoneId, 'customer@example.com');
// Todos los emails de @dominio.com → customer@example.com
```

#### `verifyNameservers(string $zoneId)`
Verifica si los NS han sido cambiados

```php
$status = $cloudflareService->verifyNameservers($zoneId);
// Returns: ['ns_changed' => true/false, 'status' => 'active'/'pending', ...]
```

#### Métodos DNS Management

```php
// Listar todos los DNS records
$records = $cloudflareService->listDNSRecords($zoneId);

// Crear DNS record
$cloudflareService->createDNSRecord($zoneId, 'A', 'blog', '192.168.1.1', false, 3600);

// Actualizar DNS record
$cloudflareService->updateDNSRecord($zoneId, $recordId, ['content' => '192.168.1.2']);

// Eliminar DNS record
$cloudflareService->deleteDNSRecord($zoneId, $recordId);
```

---

### 6. CRON Job de Verificación de Nameservers

**Archivo**: [cron/verify-nameservers.php](cron/verify-nameservers.php)

**Configuración**:
```bash
# Ejecutar cada 30 minutos
0,30 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cron/verify-nameservers.php
```

**Proceso**:
1. Busca tenants con `status = 'waiting_ns_change'`
2. Para cada tenant:
   - Verifica estado de NS en Cloudflare vía API
   - Si `status = 'active'`:
     - Configura Caddy
     - Obtiene SSL automáticamente
     - Aplica tenant defaults
     - Actualiza tenant a `active`
     - Ejecuta health check
     - Envía email de activación
3. Si hay error, marca tenant como `error`

**Logs**:
```bash
tail -f /var/www/vhosts/musedock.net/logs/app.log | grep CRON
```

---

### 7. Base de Datos - Campos Añadidos

**Tabla**: `tenants`

```sql
-- Nuevos campos
cloudflare_zone_id VARCHAR(255) NULL          -- ID de zona en Cloudflare Account 2
cloudflare_nameservers JSON NULL              -- NSs de Cloudflare
email_routing_enabled BOOLEAN DEFAULT FALSE   -- Email routing activo

-- Status actualizado
status ENUM('active', 'suspended', 'pending', 'waiting_ns_change', 'error')
```

**Estados del Tenant**:
- `pending`: Creado, configuración en proceso
- `waiting_ns_change`: Añadido a Cloudflare, esperando NS
- `active`: NS cambiado, Caddy configurado, SSL activo
- `error`: Error en algún paso
- `suspended`: Suspendido por el admin

---

## 🔧 Configuración Requerida

### 1. Variables de Entorno (.env)

```bash
# CLOUDFLARE CUENTA 2: Dominios Personalizados
CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN=tu_api_token_aqui
CLOUDFLARE_CUSTOM_DOMAINS_ACCOUNT_ID=tu_account_id_aqui
CLOUDFLARE_CUSTOM_DOMAINS_SSL_MODE=full
```

### 2. CRON Job

```bash
crontab -e
```

Añadir:
```bash
0,30 * * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/cron/verify-nameservers.php
```

### 3. Verificar Configuración

```bash
# Test Cloudflare API
php -r "require 'vendor/autoload.php'; require 'core/bootstrap.php';
$s = new CaddyDomainManager\Services\CloudflareZoneService();
echo 'Cloudflare connected!';"

# Test CRON manually
php cron/verify-nameservers.php
```

---

## 📊 Resumen de Archivos Creados/Modificados

### ✅ Controladores
- `Controllers/FreeSubdomainController.php` (NUEVO)
- `Controllers/CustomDomainController.php` (NUEVO)
- `Controllers/CustomerController.php` (MODIFICADO - añadido retry y health check)

### ✅ Servicios
- `Services/CloudflareZoneService.php` (NUEVO)
- `Services/HealthCheckService.php` (NUEVO)
- `Services/ProvisioningService.php` (MODIFICADO - añadido health check y defaults)

### ✅ Vistas
- `Views/Customer/request-free-subdomain.blade.php` (NUEVO)
- `Views/Customer/request-custom-domain.blade.php` (NUEVO)
- `Views/Customer/dashboard.blade.php` (MODIFICADO - añadido health checks y botones)

### ✅ Modelos
- `Models/Customer.php` (MODIFICADO - añadido `getTenantsWithHealthCheck()`)

### ✅ Rutas
- `routes.php` (MODIFICADO - añadidas rutas de FREE y custom domains)

### ✅ CRON
- `cron/verify-nameservers.php` (NUEVO)

### ✅ Migraciones
- `database/migrations/2025_01_01_150000_add_custom_domain_fields_to_tenants.php` (NUEVO)

### ✅ Documentación
- `SETUP-CUSTOM-DOMAINS.md` (NUEVO)
- `CUSTOMER-DASHBOARD-FEATURES.md` (NUEVO - este archivo)

---

## 🎯 Casos de Uso

### Caso 1: Customer registra nuevo tenant FREE

```
1. Customer se registra en /register
2. Recibe email de bienvenida
3. Accede a /customer/dashboard
4. Ve su tenant FREE en la lista
5. Health check muestra: ✅ Funcionando
```

### Caso 2: Customer solicita otro subdominio FREE

```
1. Customer borra su tenant anterior
2. En dashboard, clic "Solicitar Subdominio FREE"
3. Ingresa "nueva-empresa"
4. Sistema verifica disponibilidad en tiempo real
5. Confirma y crea tenant automáticamente
6. Recibe email cuando esté listo (2 min)
7. Accede a https://nueva-empresa.musedock.com/admin
```

### Caso 3: Customer incorpora dominio personalizado

```
1. Customer compró "miempresa.com" en GoDaddy
2. En dashboard, clic "Solicitar Dominio Personalizado"
3. Ingresa "miempresa.com"
4. Activa Email Routing ✓
5. Confirma solicitud
6. Recibe email con NS de Cloudflare
7. Cambia NS en GoDaddy
8. Espera 2-48 horas
9. CRON detecta cambio automáticamente
10. Recibe email de confirmación
11. Accede a https://miempresa.com/admin
12. Emails a info@miempresa.com llegan a su inbox
```

### Caso 4: Configuración falla (retry)

```
1. Cloudflare configuró OK pero Caddy falló
2. Dashboard muestra: ⚠️ Degradado
3. Botón "Retry" visible
4. Customer hace clic en "Retry"
5. Sistema reintenta solo la parte fallida
6. SweetAlert muestra: ✅ Éxito
7. Health check actualizado: ✅ Funcionando
```

---

## 🔍 Logs y Debugging

### Ver logs del CRON
```bash
tail -f logs/app.log | grep "\[CRON\]"
```

### Ver logs de Health Check
```bash
tail -f logs/app.log | grep "\[HealthCheck\]"
```

### Ver logs de Cloudflare Zone
```bash
tail -f logs/app.log | grep "\[CloudflareZone\]"
```

### Ver logs de Provisioning
```bash
tail -f logs/app.log | grep "\[ProvisioningService\]"
```

---

## 🚀 Próximas Funcionalidades (Futuro)

- [ ] Panel de gestión DNS para customers (añadir/editar/eliminar records)
- [ ] Soporte para múltiples dominios personalizados por customer
- [ ] Integración con OpenProvider para registro de dominios
- [ ] Facturación automática para planes CUSTOM
- [ ] Estadísticas de tráfico por dominio
- [ ] Backups automáticos por tenant
- [ ] Multi-región (replicar tenants en múltiples servidores)

---

**Versión:** 1.0
**Última actualización:** 2025-12-15
**Autor:** Claude Sonnet 4.5
