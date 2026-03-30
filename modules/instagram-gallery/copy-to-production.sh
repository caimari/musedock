#!/bin/bash

# Script para copiar el módulo Instagram Gallery de desarrollo (.net) a producción (.com)

SOURCE_DIR="/var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery"
DEST_DIR="/var/www/vhosts/musedock.com/httpdocs/modules/instagram-gallery"

echo "=================================================="
echo "Instagram Gallery - Copiar a Producción"
echo "=================================================="
echo ""
echo "Origen:  $SOURCE_DIR"
echo "Destino: $DEST_DIR"
echo ""

# Verificar que existe el directorio de origen
if [ ! -d "$SOURCE_DIR" ]; then
    echo "❌ ERROR: No existe el directorio de origen"
    exit 1
fi

# Crear directorio de destino si no existe
if [ ! -d "/var/www/vhosts/musedock.com/httpdocs/modules" ]; then
    echo "📁 Creando directorio de módulos en producción..."
    sudo mkdir -p "/var/www/vhosts/musedock.com/httpdocs/modules"
fi

# Copiar todo el módulo
echo "📦 Copiando módulo..."
sudo cp -r "$SOURCE_DIR" "$DEST_DIR"

# Ajustar permisos
echo "🔒 Ajustando permisos..."
sudo chown -R musedockcomcalamar:psaserv "$DEST_DIR"
sudo chmod -R 755 "$DEST_DIR"
sudo chmod +x "$DEST_DIR/install-cron.sh"
sudo chmod +x "$DEST_DIR/commands/RefreshInstagramTokens.php"

# Crear directorio de logs
sudo mkdir -p "$DEST_DIR/logs"
sudo chown -R musedockcomcalamar:psaserv "$DEST_DIR/logs"
sudo chmod 755 "$DEST_DIR/logs"

echo ""
echo "✅ Módulo copiado exitosamente a producción!"
echo ""
echo "=================================================="
echo "SIGUIENTE PASO:"
echo "=================================================="
echo "1. Ir al directorio:"
echo "   cd /var/www/vhosts/musedock.com/httpdocs/modules/instagram-gallery"
echo ""
echo "2. Instalar cron:"
echo "   bash install-cron.sh"
echo ""
echo "3. Configurar en el panel:"
echo "   https://musedock.com/musedock/instagram/settings"
echo ""
