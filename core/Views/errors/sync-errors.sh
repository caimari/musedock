#!/bin/bash
# Script para sincronizar las páginas de error a todos los temas
# Uso: bash core/Views/errors/sync-errors.sh

SOURCE_DIR="core/Views/errors"
THEMES_DIR="themes"

echo "🔄 Sincronizando páginas de error..."
echo ""

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Contador de temas actualizados
count=0

# Buscar todos los directorios de errores en temas
for error_dir in $(find $THEMES_DIR -type d -name "errors" | grep -E "views/errors$"); do
    theme_name=$(echo $error_dir | sed 's|themes/||' | sed 's|/views/errors||')

    echo -e "${BLUE}📁 Tema: $theme_name${NC}"

    # Copiar archivos
    cp -v $SOURCE_DIR/404.blade.php $error_dir/404.blade.php
    cp -v $SOURCE_DIR/403.blade.php $error_dir/403.blade.php
    cp -v $SOURCE_DIR/500.blade.php $error_dir/500.blade.php

    echo -e "${GREEN}✓ Actualizado${NC}"
    echo ""

    ((count++))
done

echo ""
echo -e "${GREEN}✅ Completado! $count temas actualizados${NC}"
echo ""
echo "Temas actualizados:"
find $THEMES_DIR -type d -name "errors" | grep -E "views/errors$" | sed 's|themes/||' | sed 's|/views/errors||' | sed 's/^/  - /'
