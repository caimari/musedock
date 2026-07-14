<?php

namespace Screenart\Musedock\Controllers\Frontend;

/**
 * Entrega el token CSRF de la sesión actual como JSON.
 *
 * Necesario porque la html-cache sirve páginas completas (incluido el
 * csrf_field del render original) a visitantes anónimos: el token embebido
 * pertenece a otra sesión y los formularios fallarían. Un pequeño JS en el
 * layout refresca los inputs _csrf/_token con el valor de este endpoint.
 */
class CsrfController
{
    public function token()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo json_encode(['token' => csrf_token()]);
        exit;
    }
}
