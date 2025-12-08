<?php

namespace Screenart\Musedock\Middlewares;

interface MiddlewareInterface {
    public function handle(callable $next);
}
