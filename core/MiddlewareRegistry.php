<?php
return [
    // middlewares genéricos
    'auth'           => \Screenart\Musedock\Middlewares\AuthMiddleware::class,
    'csrf'           => \Screenart\Musedock\Middlewares\CsrfMiddleware::class,
    'superadmin'     => \Screenart\Musedock\Middlewares\SuperAdminMiddleware::class,
    'tenantResolver' => \Screenart\Musedock\Middlewares\TenantResolver::class,
	'language' => \Screenart\Musedock\Middlewares\LanguageMiddleware::class,

    // alias "Laravel‐style"
    'AuthMiddleware' => \Screenart\Musedock\Middlewares\AuthMiddleware::class,
    'permission'     => \Screenart\Musedock\Middlewares\PermissionMiddleware::class,
    'role'           => \Screenart\Musedock\Middlewares\RoleMiddleware::class,

    //Middleware dinámico
    'dynamic.permission' => \Screenart\Musedock\Middlewares\DynamicPermissionMiddleware::class,

    // Customer auth (4to nivel de usuarios - Caddy Domain Manager plugin)
    'customer' => \CaddyDomainManager\Middlewares\CustomerAuthMiddleware::class,
];
