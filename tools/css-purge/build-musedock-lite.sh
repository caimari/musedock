#!/bin/bash
# Regenera public/assets/themes/musedock/css/styles-lite.css a partir de styles(1).css,
# conservando solo las reglas que usan las páginas, plantillas y JS del tema musedock.
#
# Uso:  tools/css-purge/build-musedock-lite.sh [https://musedock.com]
#
# Cuándo: tras añadir o cambiar plantillas del tema musedock (o vistas del panel de
# clientes) que usen clases de styles(1).css. Después, purgar cachés:
#   storage/cache/themes/musedock/*.bladec  y  storage/html-cache/_master/
# Verificación: comparar la web con ?css=full (original) y sin parámetro (lite).
set -euo pipefail

BASE="${1:-https://musedock.com}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

echo "→ Descargando páginas públicas desde el sitemap de $BASE"
mkdir -p "$WORK/html"
{ curl -s "$BASE/sitemap.xml" | grep -o '<loc>[^<]*</loc>' | sed 's#</\?loc>##g'
  for p in / /plans /register /customer/login /docs /blog /soporte /contacto /p/privacy /p/terms-and-conditions /no-existe-404; do
    echo "$BASE$p"; done; } | sort -u > "$WORK/urls.txt"
i=0; while read -r u; do i=$((i+1)); curl -s -m 20 "$u" -o "$WORK/html/p$i.html" || true; done < "$WORK/urls.txt"
echo "  $(ls "$WORK/html" | wc -l) páginas"

CSS="$ROOT/public/assets/themes/musedock/css"
cat > "$WORK/purgecss.config.cjs" <<EOF
module.exports = {
  content: [
    '$WORK/html/*.html',
    '$ROOT/themes/musedock/views/**/*.blade.php',
    '$ROOT/public/assets/themes/musedock/js/*.js',
    '$ROOT/public/assets/js/*.js',
    '$ROOT/public/assets/vendor/nice-select2/*.js',
    '$ROOT/../private-plugins/superadmin/musedock-cloud/views/**/*.php',
  ],
  css: ['$CSS/styles(1).css'],
  output: '$WORK/out',
  keyframes: false, fontFace: false, variables: false,
  safelist: {
    // Clases que añade JavaScript en tiempo de ejecución (menús, sticky, carruseles, modales, cookies)
    standard: ['active','open','show','in','fade','collapse','collapsing','sticky','fixed','is-sticky','scrolled','current','current-menu-item','selected','disabled','hidden','visible','focus','hover','wow','animated','body','html'],
    deep: [/^owl-/, /^slimmenu/, /^nice-select/, /^modal/, /^dropdown/, /^navbar/, /^nav-/, /^cookie/, /^simple-slide/, /^sticky/, /^menu-item/, /^sub-menu/, /^ziph-/, /^zipprich/, /^swal2/, /^alert/, /^tooltip/, /^popover/, /^has-/, /^is-/, /^fa-/, /^col-/, /^animat/, /^fadeIn/, /^slideIn/, /^zoomIn/, /^bounce/],
    greedy: [/menu/, /header/, /footer/, /widget/, /slider/, /carousel/],
  },
};
EOF

echo "→ PurgeCSS + csso (sin reestructurar: mantiene el orden de la cascada)"
mkdir -p "$WORK/out"
npx -y purgecss@6 --config "$WORK/purgecss.config.cjs" >/dev/null
npx -y csso-cli@4 "$WORK/out/styles(1).css" --no-restructure -o "$CSS/styles-lite.css"

printf "  styles(1).css   %7s B\n  styles-lite.css %7s B\n" "$(stat -c %s "$CSS/styles(1).css")" "$(stat -c %s "$CSS/styles-lite.css")"
echo "✓ Hecho. Purga las cachés y compara con ?css=full."
