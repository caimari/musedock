# Instalación DNS-01 Challenge para SSL con Cloudflare Proxy

## Resumen

Este procedimiento permite obtener certificados SSL de Let's Encrypt mientras mantienes el **proxy naranja de Cloudflare activo** (IP oculta + protección DDoS).

## ¿Por qué DNS-01 en lugar de HTTP-01?

- **HTTP-01**: Requiere conexión directa al servidor → NO funciona con proxy naranja
- **DNS-01**: Valida mediante registros DNS TXT → ✅ Funciona con proxy naranja

## Paso 1: Instalar Caddy con plugin DNS de Cloudflare

```bash
cd /var/www/vhosts/musedock.com/httpdocs

# Dar permisos de ejecución
chmod +x install-caddy-with-cloudflare-dns.sh

# Ejecutar instalación (requiere sudo)
sudo ./install-caddy-with-cloudflare-dns.sh
```

**Tiempo estimado**: 2-3 minutos

**Qué hace**:
1. Hace backup del Caddy actual
2. Instala `xcaddy` (herramienta de compilación)
3. Compila Caddy con el plugin `github.com/caddy-dns/cloudflare`
4. Reemplaza el binario de Caddy
5. Verifica que el plugin está instalado

## Paso 2: Configurar variable de entorno CLOUDFLARE_API_TOKEN

```bash
# Dar permisos de ejecución
chmod +x configure-caddy-env.sh

# Ejecutar configuración (requiere sudo)
sudo ./configure-caddy-env.sh
```

**Qué hace**:
1. Lee `CLOUDFLARE_API_TOKEN` del archivo `.env`
2. Crea archivo de entorno `/etc/default/caddy` con el token
3. Crea override de systemd en `/etc/systemd/system/caddy.service.d/override.conf`
4. Configura systemd para usar `EnvironmentFile` (método correcto, sin problemas de comillas)
5. Recarga systemd daemon

**Verificar**:
```bash
# Ver el archivo de entorno
cat /etc/default/caddy

# Ver configuración de systemd
systemctl cat caddy | grep EnvironmentFile
```

Deberías ver:
```
CLOUDFLARE_API_TOKEN=RoWaHrupEzaA-Y3qrg9S...
EnvironmentFile=/etc/default/caddy
```

## Paso 3: Hacer backup de Caddyfile actual

```bash
sudo cp /etc/caddy/Caddyfile /etc/caddy/Caddyfile.backup.$(date +%Y%m%d_%H%M%S)
```

## Paso 4: Copiar nueva configuración de Caddy

```bash
sudo cp Caddyfile.wildcard-ssl /etc/caddy/Caddyfile
```

## Paso 5: Validar configuración de Caddy

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
```

Si hay errores, revisar la sintaxis del Caddyfile.

## Paso 6: Reiniciar Caddy

```bash
sudo systemctl restart caddy
```

## Paso 7: Verificar logs

```bash
sudo journalctl -u caddy -f
```

**Lo que debes ver**:
```
✅ obtaining certificate for *.musedock.com
✅ dns-01 challenge started
✅ creating DNS TXT record _acme-challenge.musedock.com
✅ certificate obtained successfully
```

**Si ves errores**:
- `unauthorized`: Verifica que el CLOUDFLARE_API_TOKEN es correcto
- `not found`: Verifica que el Zone ID en `.env` es correcto
- `timeout`: Puede tardar 1-2 minutos, espera

## Paso 8: Probar subdominio existente

```bash
curl -I https://gregorioabdon.musedock.com
```

Deberías ver `HTTP/2 200` sin errores SSL.

## Paso 9: Verificar certificado

```bash
sudo ls -la /var/lib/caddy/.local/share/caddy/certificates/acme-v02.api.letsencrypt.org-directory/wildcard_.musedock.com/
```

Deberías ver:
- `wildcard_.musedock.com.crt` (certificado)
- `wildcard_.musedock.com.key` (clave privada)

## Verificación Final

1. **Cloudflare Dashboard**:
   - Ir a DNS → Ver registros
   - ✅ `gregorioabdon` debe tener nube naranja (proxy activo)

2. **SSL Labs Test**:
   ```bash
   https://www.ssllabs.com/ssltest/analyze.html?d=gregorioabdon.musedock.com
   ```
   Debe mostrar grado A/A+ con certificado Let's Encrypt

3. **Verificar IP oculta**:
   ```bash
   dig gregorioabdon.musedock.com +short
   ```
   Debe mostrar IP de Cloudflare (NO tu IP real del servidor)

## Renovación Automática

Caddy renovará automáticamente los certificados **60 días antes de expirar** usando el mismo DNS-01 challenge. No requiere acción manual.

## Troubleshooting

### Error: "dns: cloudflare: API error" o "API token '' appears invalid"
```bash
# Verificar token en .env
grep CLOUDFLARE_API_TOKEN /var/www/vhosts/musedock.com/httpdocs/.env

# Verificar archivo de entorno
cat /etc/default/caddy

# Verificar que systemd lo carga
systemctl cat caddy | grep EnvironmentFile

# Si no aparece o está vacío, repetir Paso 2
sudo ./configure-caddy-env.sh
sudo systemctl daemon-reload
sudo systemctl restart caddy
```

### Error: "acme: error presenting token"
```bash
# Verificar permisos de Cloudflare API Token
# Debe tener: Zone → DNS → Edit
```

### Certificado no se renueva automáticamente
```bash
# Ver estado de certificados
sudo caddy list-modules | grep tls

# Forzar renovación manual
sudo caddy renew
```

## Rollback (si algo sale mal)

```bash
# Restaurar Caddy anterior
sudo systemctl stop caddy
sudo cp /usr/bin/caddy.backup.YYYYMMDD_HHMMSS /usr/bin/caddy
sudo systemctl start caddy

# Restaurar Caddyfile anterior
sudo cp /etc/caddy/Caddyfile.backup.YYYYMMDD_HHMMSS /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

## Resumen de Beneficios

✅ **IP del servidor oculta** (proxy naranja activo)
✅ **Protección DDoS de Cloudflare**
✅ **Certificados SSL Let's Encrypt válidos**
✅ **Renovación automática** cada 60 días
✅ **Sin configuración manual** de certificados
✅ **Soporta wildcard** (`*.musedock.com`)
✅ **Gratis** (Let's Encrypt + Cloudflare Free)

## Siguientes Pasos

Una vez verificado que funciona:

1. ✅ **Subir código a repositorio** - Todos los cambios están listos
2. ✅ **El script `fix_cloudflare_proxy.php` ya no es necesario** - Puedes mantenerlo por si acaso, pero no lo necesitarás
3. ✅ **Nuevos tenants FREE se crearán automáticamente con proxy naranja** - ProvisioningService ya está configurado
4. ✅ **Los certificados SSL se obtienen automáticamente vía DNS-01** - Sin intervención manual
5. ✅ **Renovación automática** - Caddy renovará los certificados cada 60-90 días
6. ✅ **IP del servidor oculta** - Cloudflare proxy activo en todo momento

## Soporte

Si encuentras problemas:
1. Revisar logs: `sudo journalctl -u caddy -n 100`
2. Verificar configuración: `sudo caddy validate`
3. Verificar DNS: `dig _acme-challenge.musedock.com TXT +short`
