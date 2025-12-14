#!/bin/bash
###############################################################################
# Script: Configurar variables de entorno para Caddy
# Propósito: Añadir CLOUDFLARE_API_TOKEN al servicio systemd de Caddy
###############################################################################

set -e

echo "=================================================="
echo "Configurando variables de entorno para Caddy"
echo "=================================================="
echo ""

# Verificar que somos root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Este script debe ejecutarse como root o con sudo"
    exit 1
fi

# Leer API Token desde .env de MuseDock
CLOUDFLARE_API_TOKEN=$(grep -oP 'CLOUDFLARE_API_TOKEN=\K.*' /var/www/vhosts/musedock.com/httpdocs/.env || echo "")

if [ -z "$CLOUDFLARE_API_TOKEN" ]; then
    echo "⚠ No se encontró CLOUDFLARE_API_TOKEN en .env"
    echo "Por favor ingresa el token manualmente:"
    read -r CLOUDFLARE_API_TOKEN
fi

echo "✓ Token encontrado: ${CLOUDFLARE_API_TOKEN:0:20}..."

# Crear archivo de entorno para Caddy
echo ""
echo "📝 Creando archivo de entorno /etc/default/caddy..."

echo "CLOUDFLARE_API_TOKEN=$CLOUDFLARE_API_TOKEN" > /etc/default/caddy

echo "✓ Archivo de entorno creado"

# Crear override de systemd para usar EnvironmentFile
echo ""
echo "📝 Configurando systemd override..."

mkdir -p /etc/systemd/system/caddy.service.d/

cat > /etc/systemd/system/caddy.service.d/override.conf <<EOF
[Service]
EnvironmentFile=/etc/default/caddy
EOF

echo "✓ Override creado en /etc/systemd/system/caddy.service.d/override.conf"

# Recargar systemd
echo ""
echo "🔄 Recargando systemd daemon..."
systemctl daemon-reload
echo "✓ Daemon recargado"

echo ""
echo "=================================================="
echo "✅ Configuración completada"
echo "=================================================="
echo ""
echo "Variable de entorno CLOUDFLARE_API_TOKEN configurada"
echo ""
echo "Para verificar:"
echo "  systemctl cat caddy | grep CLOUDFLARE"
echo ""
