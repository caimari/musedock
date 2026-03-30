@extends('layouts.app')

@section('title', $title ?? 'Centro de Desarrolladores')

@section('content')
@include('partials.alerts-sweetalert2')

<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.marketplace.index') }}">Marketplace</a></li>
                    <li class="breadcrumb-item active">Desarrolladores</li>
                </ol>
            </nav>
            <h1 class="h3 mb-1">
                <i class="bi bi-code-slash me-2"></i>
                Centro de Desarrolladores
            </h1>
            <p class="text-muted mb-0">Crea y publica módulos, plugins y temas para el marketplace de MuseDock</p>
        </div>
    </div>

    {{-- Banner principal --}}
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm overflow-hidden" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
                <div class="card-body py-5 text-white">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h2 class="mb-3">
                                <i class="bi bi-rocket-takeoff me-2"></i>
                                Comparte tus creaciones con la comunidad
                            </h2>
                            <p class="lead mb-4 opacity-75">
                                Publica tus módulos, plugins y temas en el marketplace de MuseDock.
                                Llega a miles de usuarios y monetiza tu trabajo.
                            </p>
                            <div class="d-flex gap-3 flex-wrap">
                                <a href="https://docs.musedock.org/developers" target="_blank" class="btn btn-light btn-lg">
                                    <i class="bi bi-book me-2"></i> Documentación
                                </a>
                                <a href="https://musedock.org/developers/register" target="_blank" class="btn btn-outline-light btn-lg">
                                    <i class="bi bi-person-plus me-2"></i> Crear cuenta de desarrollador
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center d-none d-lg-block">
                            <i class="bi bi-code-square display-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pasos para publicar --}}
    <div class="row mb-5">
        <div class="col-12">
            <h4 class="mb-4">
                <i class="bi bi-list-ol me-2"></i>
                ¿Cómo publicar en el Marketplace?
            </h4>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <span class="fs-4 fw-bold text-primary">1</span>
                            </div>
                            <h5>Crea tu cuenta</h5>
                            <p class="text-muted small mb-0">
                                Regístrate como desarrollador en musedock.org para obtener tu API Key.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <span class="fs-4 fw-bold text-primary">2</span>
                            </div>
                            <h5>Desarrolla</h5>
                            <p class="text-muted small mb-0">
                                Crea tu módulo, plugin o tema siguiendo las guías de desarrollo.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <span class="fs-4 fw-bold text-primary">3</span>
                            </div>
                            <h5>Empaqueta</h5>
                            <p class="text-muted small mb-0">
                                Crea un archivo ZIP con la estructura correcta y los archivos requeridos.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <span class="fs-4 fw-bold text-primary">4</span>
                            </div>
                            <h5>Publica</h5>
                            <p class="text-muted small mb-0">
                                Sube tu paquete al marketplace y completa la información.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Estructura de archivos --}}
    <div class="row mb-5">
        <div class="col-lg-4 mb-4 mb-lg-0">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-puzzle me-2"></i> Estructura de Módulo</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-dark text-light p-3 rounded small mb-0"><code>mi-modulo/
├── module.json        <span class="text-success">← Requerido</span>
├── routes.php         <span class="text-success">← Requerido</span>
├── controllers/
│   └── MiController.php
├── models/
│   └── MiModelo.php
├── views/
│   ├── superadmin/
│   └── tenant/
├── migrations/
│   └── 001_create_table.sql
├── assets/
│   ├── css/
│   └── js/
└── lang/
    ├── en.json
    └── es.json</code></pre>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4 mb-lg-0">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="bi bi-plug me-2"></i> Estructura de Plugin</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-dark text-light p-3 rounded small mb-0"><code>mi-plugin/
├── plugin.json        <span class="text-success">← Requerido</span>
├── MiPlugin.php       <span class="text-success">← Clase principal</span>
├── install.php        <span class="text-muted">← Opcional</span>
├── uninstall.php      <span class="text-muted">← Opcional</span>
├── assets/
│   ├── css/
│   └── js/
└── views/
    └── settings.blade.php</code></pre>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-palette me-2"></i> Estructura de Tema</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-dark text-light p-3 rounded small mb-0"><code>mi-tema/
├── theme.json         <span class="text-success">← Requerido</span>
├── views/
│   ├── layouts/
│   │   └── main.blade.php <span class="text-success">← Req.</span>
│   ├── home.blade.php
│   ├── page.blade.php
│   └── partials/
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
└── screenshot.png</code></pre>
                </div>
            </div>
        </div>
    </div>

    {{-- Archivos de configuración --}}
    <div class="row mb-5">
        <div class="col-12">
            <h4 class="mb-4">
                <i class="bi bi-file-earmark-code me-2"></i>
                Archivos de Configuración
            </h4>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">module.json</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded small mb-0"><code>{
  "name": "Mi Módulo",
  "slug": "mi-modulo",
  "version": "1.0.0",
  "description": "Descripción...",
  "author": "Tu Nombre",
  "author_url": "https://...",
  "min_version": "2.0.0",
  "permissions": [
    "mi-modulo.view",
    "mi-modulo.manage"
  ]
}</code></pre>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">plugin.json</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded small mb-0"><code>{
  "name": "Mi Plugin",
  "slug": "mi-plugin",
  "version": "1.0.0",
  "description": "Descripción...",
  "author": "Tu Nombre",
  "main_class": "MiPlugin",
  "hooks": [
    "after_page_render",
    "before_save_post"
  ]
}</code></pre>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">theme.json</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded small mb-0"><code>{
  "name": "Mi Tema",
  "slug": "mi-tema",
  "version": "1.0.0",
  "description": "Descripción...",
  "author": "Tu Nombre",
  "screenshot": "screenshot.png",
  "customizable": true,
  "bootstrap": true
}</code></pre>
                </div>
            </div>
        </div>
    </div>

    {{-- Recursos --}}
    <div class="row">
        <div class="col-12">
            <h4 class="mb-4">
                <i class="bi bi-link-45deg me-2"></i>
                Recursos Útiles
            </h4>
            <div class="row g-4">
                <div class="col-md-4">
                    <a href="https://docs.musedock.org/modules" target="_blank" class="card border-0 shadow-sm h-100 text-decoration-none resource-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-book text-primary fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Guía de Módulos</h6>
                                    <small class="text-muted">Aprende a crear módulos</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="https://docs.musedock.org/plugins" target="_blank" class="card border-0 shadow-sm h-100 text-decoration-none resource-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-plug text-success fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Guía de Plugins</h6>
                                    <small class="text-muted">Hooks y extensiones</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="https://docs.musedock.org/themes" target="_blank" class="card border-0 shadow-sm h-100 text-decoration-none resource-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-palette text-info fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Guía de Temas</h6>
                                    <small class="text-muted">Crea temas personalizados</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="https://github.com/musedock/examples" target="_blank" class="card border-0 shadow-sm h-100 text-decoration-none resource-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-dark bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-github text-dark fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Ejemplos en GitHub</h6>
                                    <small class="text-muted">Código de ejemplo</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="https://docs.musedock.org/api" target="_blank" class="card border-0 shadow-sm h-100 text-decoration-none resource-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-braces text-warning fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Referencia de API</h6>
                                    <small class="text-muted">Funciones disponibles</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="https://discord.gg/musedock" target="_blank" class="card border-0 shadow-sm h-100 text-decoration-none resource-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-discord text-primary fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Comunidad Discord</h6>
                                    <small class="text-muted">Soporte y discusión</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.resource-card {
    transition: all 0.3s ease;
}
.resource-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;
}
</style>
@endsection
