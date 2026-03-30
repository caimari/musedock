<?php
/**
 * MuseDock Module Front Views Generator
 * 
 * Uso: php commands/module_front_views.php ModuleName
 * 
 * Este script crea las vistas del frontend en el tema default para un módulo existente.
 * 
 * Ejemplo: 
 *   php commands/module_front_views.php Blog
 */

// Verificar argumentos
if ($argc < 2) {
    echo "Error: Debes especificar un nombre para el módulo.\n";
    echo "Uso: php commands/module_front_views.php ModuleName\n";
    exit(1);
}

// Obtener el nombre del módulo
$moduleName = $argv[1];
$moduleLower = strtolower($moduleName);
$moduleUpper = ucfirst($moduleName);

// Definir rutas base
$baseDir = __DIR__ . '/../';
$themeDir = $baseDir . 'themes/default/views/' . $moduleLower;

// Crear directorio en el tema default
if (!is_dir($themeDir)) {
    if (mkdir($themeDir, 0755, true)) {
        echo "Creado directorio: $themeDir\n";
    } else {
        echo "Error: No se pudo crear el directorio $themeDir\n";
        exit(1);
    }
}

// Función para crear archivos con plantillas
function createFile($path, $content) {
    if (!file_exists($path)) {
        file_put_contents($path, $content);
        echo "  Creado: $path\n";
    } else {
        echo "  Ya existe: $path (no modificado)\n";
    }
}

// 1. Crear vista index.blade.php
$indexContent = <<<PHP
@extends('layouts.master')
@section('title', \$title ?? '$moduleUpper')
@section('content')
<div class="container mt-5">
    <h1 class="display-4">$moduleUpper</h1>
    
    @if(count(\$items ?? []) > 0)
        <div class="row">
            @foreach(\$items as \$item)
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">{{ \$item['title'] }}</h5>
                            <p class="card-text text-muted small">
                                <span>{{ \$item['created_at'] }}</span>
                            </p>
                            <div class="card-text mb-3">
                                {{ \$item['description'] ?? substr(strip_tags(\$item['content']), 0, 150) . '...' }}
                            </div>
                            <a href="/$moduleLower/{{ \$item['slug'] }}" class="btn btn-primary btn-sm">Ver más</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-info">
            No hay elementos disponibles todavía.
        </div>
    @endif
</div>
@endsection
PHP;

createFile($themeDir . '/index.blade.php', $indexContent);

// 2. Crear vista show.blade.php
$showContent = <<<PHP
@extends('layouts.master')
@section('title', \$title ?? \$item['title'] ?? '$moduleUpper')
@section('content')
<div class="container mt-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Inicio</a></li>
            <li class="breadcrumb-item"><a href="/$moduleLower">$moduleUpper</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ \$item['title'] ?? 'Detalle' }}</li>
        </ol>
    </nav>
    
    <article>
        <h1 class="display-4 mb-4">{{ \$item['title'] ?? 'Elemento' }}</h1>
        
        <div class="d-flex align-items-center text-muted small mb-4">
            <span class="me-3">
                <i class="fas fa-calendar"></i> {{ \$item['created_at'] ?? date('Y-m-d') }}
            </span>
        </div>
        
        <div class="content-body">
            {!! \$item['content'] ?? 'No hay contenido disponible.' !!}
        </div>
        
        <div class="mt-4">
            <a href="/$moduleLower" class="btn btn-primary">Volver a $moduleUpper</a>
        </div>
    </article>
</div>
@endsection
PHP;

createFile($themeDir . '/show.blade.php', $showContent);

// Si el módulo es blog, crear también vistas para categorías
if ($moduleLower == 'blog') {
    // 3. Crear vista category.blade.php
    $categoryContent = <<<PHP
@extends('layouts.master')
@section('title', \$title ?? \$category['name'] ?? 'Categoría')
@section('content')
<div class="container mt-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Inicio</a></li>
            <li class="breadcrumb-item"><a href="/blog">Blog</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ \$category['name'] ?? 'Categoría' }}</li>
        </ol>
    </nav>
    
    <h1 class="display-4 mb-4">{{ \$category['name'] ?? 'Categoría' }}</h1>
    
    @if(\$category['description'] ?? false)
        <div class="lead mb-4">
            {{ \$category['description'] }}
        </div>
    @endif
    
    @if(count(\$posts ?? []) > 0)
        <div class="row">
            @foreach(\$posts as \$post)
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">{{ \$post['title'] }}</h5>
                            <p class="card-text text-muted small">
                                <span>{{ \$post['created_at'] }}</span>
                            </p>
                            <div class="card-text mb-3">
                                {{ \$post['excerpt'] ?? substr(strip_tags(\$post['content']), 0, 150) . '...' }}
                            </div>
                            <a href="/blog/{{ \$post['slug'] }}" class="btn btn-primary btn-sm">Leer más</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-info">
            No hay entradas en esta categoría.
        </div>
    @endif
</div>
@endsection
PHP;

    createFile($themeDir . '/category.blade.php', $categoryContent);
    
    echo "\nCreadas vistas adicionales para el módulo de blog.\n";
}

echo "\n¡Vistas del frontend creadas con éxito para el módulo '$moduleUpper'!\n";
echo "Las vistas se han creado en: $themeDir\n";
echo "\nEstas vistas están listas para funcionar con el controlador que ya tienes en el módulo.\n";