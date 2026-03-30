#!/bin/bash
###############################################################################
# Script: Instalar Caddy con plugin DNS de Cloudflare (DNS-01 Challenge)
# Propósito: Permitir certificados SSL con Cloudflare proxy naranja activo
# Autor: MuseDock Team
# Fecha: 2025-01-14
###############################################################################

set -e  # Exit on error

echo "=================================================="
echo "Instalando Caddy con plugin DNS de Cloudflare"
echo "=================================================="
echo ""

# Verificar que somos root o tenemos sudo
if [ "$EUID" -ne 0 ]; then
    echo "❌ Este script debe ejecutarse como root o con sudo"
    exit 1
fi

# Backup del binario actual de Caddy
echo "📦 Haciendo backup de Caddy actual..."
if [ -f /usr/bin/caddy ]; then
    cp /usr/bin/caddy /usr/bin/caddy.backup.$(date +%Y%m%d_%H%M%S)
    echo "✓ Backup creado"
fi

# Instalar xcaddy si no está instalado
echo ""
echo "📥 Instalando xcaddy (herramienta para compilar Caddy con plugins)..."
if ! command -v xcaddy &> /dev/null; then
    apt-get update
    apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/xcaddy/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-xcaddy-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/xcaddy/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-xcaddy.list
    apt-get update
    apt-get install -y xcaddy
    echo "✓ xcaddy instalado"
else
    echo "✓ xcaddy ya está instalado"
fi

# Compilar Caddy con plugin DNS de Cloudflare
echo ""
echo "🔨 Compilando Caddy con plugin DNS de Cloudflare..."
echo "   Esto puede tardar 2-3 minutos..."
cd /tmp
xcaddy build \
    --with github.com/caddy-dns/cloudflare

if [ ! -f ./caddy ]; then
    echo "❌ Error: No se pudo compilar Caddy"
    exit 1
fi

echo "✓ Caddy compilado exitosamente"

# Detener Caddy antes de reemplazar el binario
echo ""
echo "⏸ Deteniendo servicio Caddy..."
systemctl stop caddy

# Reemplazar binario
echo "📝 Reemplazando binario de Caddy..."
mv ./caddy /usr/bin/caddy
chmod +x /usr/bin/caddy
setcap 'cap_net_bind_service=+ep' /usr/bin/caddy

echo "✓ Binario reemplazado"

# Verificar que el plugin está instalado
echo ""
echo "🔍 Verificando instalación del plugin..."
if /usr/bin/caddy list-modules | grep -q "dns.providers.cloudflare"; then
    echo "✓ Plugin DNS de Cloudflare instalado correctamente"
else
    echo "⚠ Advertencia: No se detectó el plugin DNS de Cloudflare"
fi

# Mostrar versión y módulos
echo ""
echo "📊 Información de Caddy:"
/usr/bin/caddy version
echo ""
echo "Módulos DNS instalados:"
/usr/bin/caddy list-modules | grep dns

echo ""
echo "=================================================="
echo "✅ Instalación completada"
echo "=================================================="
echo ""
echo "Siguiente paso:"
echo "1. Configurar variable de entorno CLOUDFLARE_API_TOKEN"
echo "2. Actualizar /etc/caddy/Caddyfile para usar DNS-01 challenge"
echo "3. Reiniciar Caddy: sudo systemctl start caddy"
echo ""
