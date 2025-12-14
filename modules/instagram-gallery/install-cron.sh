#!/bin/bash

# Instagram Gallery - Instalador de Cron Job
# Este script configura el auto-refresh de tokens de Instagram

echo "=================================================="
echo "Instagram Gallery - Instalación de Cron Job"
echo "=================================================="
echo ""

# Ruta del comando
COMMAND_PATH="/var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php"

# Verificar que el archivo existe
if [ ! -f "$COMMAND_PATH" ]; then
    echo "❌ ERROR: No se encuentra el archivo RefreshInstagramTokens.php"
    echo "   Ruta esperada: $COMMAND_PATH"
    exit 1
fi

# Hacer el archivo ejecutable
chmod +x "$COMMAND_PATH"
echo "✅ Permisos de ejecución configurados"

# Crear entrada de cron
CRON_ENTRY="0 2 * * * /usr/bin/php $COMMAND_PATH >> /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/logs/cron.log 2>&1"

# Verificar si ya existe la entrada
if crontab -l 2>/dev/null | grep -q "RefreshInstagramTokens.php"; then
    echo "⚠️  Ya existe una entrada de cron para RefreshInstagramTokens.php"
    echo ""
    echo "Entrada actual:"
    crontab -l | grep "RefreshInstagramTokens.php"
    echo ""
    read -p "¿Deseas reemplazarla? (s/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        echo "❌ Instalación cancelada"
        exit 0
    fi

    # Eliminar entrada anterior
    crontab -l | grep -v "RefreshInstagramTokens.php" | crontab -
fi

# Agregar nueva entrada
(crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -

echo ""
echo "✅ Cron job instalado exitosamente!"
echo ""
echo "=================================================="
echo "CONFIGURACIÓN:"
echo "=================================================="
echo "Comando:    $COMMAND_PATH"
echo "Frecuencia: Diariamente a las 2:00 AM"
echo "Logs:       /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/logs/"
echo ""
echo "=================================================="
echo "VERIFICACIÓN:"
echo "=================================================="
echo "Ver cron actual:  crontab -l"
echo "Ver logs:         tail -f /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/logs/cron.log"
echo "Probar comando:   php $COMMAND_PATH"
echo ""
echo "=================================================="
echo "¿QUÉ HACE?"
echo "=================================================="
echo "1. Busca tokens que expiran en 7 días o menos"
echo "2. Los renueva automáticamente (extiende 60 días más)"
echo "3. Guarda logs de cada renovación"
echo ""
echo "✅ ¡Listo! Tus tokens de Instagram se renovarán automáticamente."
echo ""
