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
# Preferimos el .env junto a este script (misma instalación) y dejamos un fallback legacy.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_ENV_FILE="${SCRIPT_DIR}/.env"
LEGACY_ENV_FILE="/var/www/vhosts/musedock.com/httpdocs/.env"

ENV_FILE="${MUSEDOCK_ENV_FILE:-$DEFAULT_ENV_FILE}"
if [ ! -f "$ENV_FILE" ] && [ -f "$LEGACY_ENV_FILE" ]; then
    ENV_FILE="$LEGACY_ENV_FILE"
fi

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ No se encontró .env (probé: $DEFAULT_ENV_FILE y $LEGACY_ENV_FILE)"
    echo "   Puedes indicar la ruta con: MUSEDOCK_ENV_FILE=/ruta/a/.env $0"
    exit 1
fi

CLOUDFLARE_API_TOKEN="$(grep -oP 'CLOUDFLARE_API_TOKEN=\\K.*' "$ENV_FILE" || echo "")"

if [ -z "$CLOUDFLARE_API_TOKEN" ]; then
    echo "⚠ No se encontró CLOUDFLARE_API_TOKEN en .env"
    echo "Por favor ingresa el token manualmente:"
    read -r CLOUDFLARE_API_TOKEN
fi

if [ -z "$CLOUDFLARE_API_TOKEN" ]; then
    echo "❌ Token vacío. Abortando para no dejar /etc/default/caddy sin credenciales."
    exit 1
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
