<?php

namespace Screenart\Musedock\Helpers;

/**
 * Botón (i) que abre una ventana explicando la diferencia entre «visitantes
 * únicos» y «cargas de página». Sirve en cualquier vista del panel (Blade o PHP
 * plano) y no depende de Bootstrap: la ventana se crea la primera vez que se pulsa.
 */
class VisitsHelp
{
    private static bool $assetsPrinted = false;

    public static function button(string $label = '¿Qué diferencia hay entre visitantes únicos y cargas de página?'): string
    {
        $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $html = '<button type="button" class="md-visits-help" onclick="event.preventDefault();event.stopPropagation();mdVisitsHelp()" title="' . $label . '" aria-label="' . $label . '">'
            . '<i class="bi bi-info-circle"></i></button>';
        if (!self::$assetsPrinted) {
            self::$assetsPrinted = true;
            $html .= self::assets();
        }

        return $html;
    }

    private static function assets(): string
    {
        return <<<'HTML'
<template id="md-visits-help-tpl">
<div class="md-vh-overlay" role="dialog" aria-modal="true" aria-labelledby="md-vh-title">
  <div class="md-vh-box">
    <div class="md-vh-head">
      <strong id="md-vh-title"><i class="bi bi-people"></i> Visitantes únicos y cargas de página</strong>
      <button type="button" class="md-vh-close" aria-label="Cerrar">&times;</button>
    </div>
    <div class="md-vh-body">
      <p><strong><i class="bi bi-person"></i> Visitantes únicos</strong>: personas distintas. Si alguien entra cinco veces el mismo día, o recarga la página, cuenta como <strong>1</strong>. Si vuelve otro día, cuenta otra vez.</p>
      <p><strong><i class="bi bi-arrow-repeat"></i> Cargas de página</strong>: cada vez que se abre la página, incluidas las recargas y las vueltas atrás. Si alguien entra cinco veces, son <strong>5</strong>.</p>
      <p class="md-vh-example">Ejemplo: 100 visitantes únicos y 180 cargas = 100 personas que, entre todas, abrieron la página 180 veces.</p>
      <ul>
        <li>No se usan cookies de seguimiento: a cada persona se la distingue por una huella anónima de su conexión que cambia cada día. Por eso dos personas en la misma red (una oficina, una casa) pueden contar como una.</li>
        <li>Los robots y buscadores no cuentan, solo personas.</li>
        <li>Desde el 17/10/2026 también cuentan las visitas servidas desde la caché (antes se perdían).</li>
        <li>Las dos cifras salen de la analítica del sitio, que existe desde el 13/03/2026. Al ordenar el blog por vistas se usa el contador propio de cada entrada, que hasta el 17/10/2026 también contaba robots: el orden puede no coincidir del todo con las cifras.</li>
        <li>Google Search Console cuenta otra cosa: solo los clics desde los resultados de Google. Por eso sus cifras son menores.</li>
      </ul>
    </div>
  </div>
</div>
</template>
<style>
.md-visits-help{border:0;background:none;padding:0 .15rem;color:#6c757d;cursor:pointer;line-height:1;vertical-align:baseline;font-size:.9em}
.md-visits-help:hover,.md-visits-help:focus-visible{color:#0d6efd}
.md-vh-overlay{position:fixed;inset:0;z-index:2000;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:16px}
.md-vh-box{background:#fff;color:#212529;border-radius:10px;max-width:560px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 50px rgba(0,0,0,.25);text-align:left;font-weight:normal;white-space:normal}
.md-vh-head{display:flex;justify-content:space-between;align-items:center;padding:.9rem 1.1rem;border-bottom:1px solid #e9ecef}
.md-vh-close{border:0;background:none;font-size:1.6rem;line-height:1;color:#6c757d;cursor:pointer}
.md-vh-body{padding:1rem 1.1rem;font-size:.9rem}
.md-vh-body ul{padding-left:1.1rem;margin-bottom:0;color:#495057;font-size:.84rem}
.md-vh-body li{margin-bottom:.35rem}
.md-vh-example{background:#f1f5f9;border-radius:6px;padding:.5rem .7rem;font-size:.85rem}
</style>
<script>
function mdVisitsHelp() {
  var tpl = document.getElementById('md-visits-help-tpl');
  if (!tpl) return;
  var node = tpl.content.firstElementChild.cloneNode(true);
  var close = function () { node.remove(); document.removeEventListener('keydown', onKey); };
  var onKey = function (e) { if (e.key === 'Escape') close(); };
  node.addEventListener('click', function (e) { if (e.target === node || e.target.closest('.md-vh-close')) close(); });
  document.addEventListener('keydown', onKey);
  document.body.appendChild(node);
  node.querySelector('.md-vh-close').focus();
}
</script>
HTML;
    }
}
