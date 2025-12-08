@extends('layouts.app')
@section('title', $title)
@section('styles')
<style>.diff-container{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.diff-column{border:1px solid #dee2e6;border-radius:0.25rem;padding:1rem;background:#f8f9fa}.diff-added{background:#d4edda;border-left:3px solid #28a745}.diff-removed{background:#f8d7da;border-left:3px solid #dc3545}.content-preview{max-height:400px;overflow-y:auto;background:#fff;padding:1rem;border-radius:0.25rem}</style>
@endsection
@section('content')
<div class="app-content"><div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-3">
<h2>{{ $title }}</h2>
<div><a href="/musedock/blog/posts/{{ $post->id }}/revisions" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver a revisiones</a></div>
</div>
<div class="card mb-3"><div class="card-body"><h5>{{ e($post->title) }}</h5></div></div>
<div class="diff-container">
<div class="diff-column">
<h6 class="mb-3">Revisión #{{ $revision1->id }}<small class="text-muted ms-2">{{ date('d/m/Y H:i', strtotime($revision1->created_at)) }}</small></h6>
<div><strong>Título:</strong><div class="content-preview @if($diff['title']) diff-removed @endif">{{ e($revision1->title) }}</div></div>
<div class="mt-3"><strong>Contenido:</strong><div class="content-preview @if($diff['content']) diff-removed @endif">{!! nl2br(e(substr($revision1->content, 0, 500))) !!}...</div></div>
@if($revision1->excerpt)<div class="mt-3"><strong>Extracto:</strong><div class="content-preview @if($diff['excerpt']) diff-removed @endif">{{ e($revision1->excerpt) }}</div></div>@endif
<div class="mt-3"><small class="text-muted">Longitud contenido: {{ strlen($revision1->content) }} caracteres</small></div>
</div>
<div class="diff-column">
<h6 class="mb-3">Revisión #{{ $revision2->id }}<small class="text-muted ms-2">{{ date('d/m/Y H:i', strtotime($revision2->created_at)) }}</small></h6>
<div><strong>Título:</strong><div class="content-preview @if($diff['title']) diff-added @endif">{{ e($revision2->title) }}</div></div>
<div class="mt-3"><strong>Contenido:</strong><div class="content-preview @if($diff['content']) diff-added @endif">{!! nl2br(e(substr($revision2->content, 0, 500))) !!}...</div></div>
@if($revision2->excerpt)<div class="mt-3"><strong>Extracto:</strong><div class="content-preview @if($diff['excerpt']) diff-added @endif">{{ e($revision2->excerpt) }}</div></div>@endif
<div class="mt-3"><small class="text-muted">Longitud contenido: {{ strlen($revision2->content) }} caracteres</small></div>
</div>
</div>
<div class="card mt-3"><div class="card-body">
<h6>Resumen de cambios:</h6>
<ul>
@if($diff['title'])<li>El título cambió</li>@endif
@if($diff['content'])<li>El contenido cambió ({{ $diff['content_length_diff'] > 0 ? '+' : '' }}{{ $diff['content_length_diff'] }} caracteres)</li>@endif
@if($diff['excerpt'])<li>El extracto cambió</li>@endif
@if($diff['featured_image'])<li>La imagen destacada cambió</li>@endif
@if($diff['status'])<li>El status cambió</li>@endif
@if(!$diff['title'] && !$diff['content'] && !$diff['excerpt'] && !$diff['featured_image'] && !$diff['status'])<li class="text-muted">Sin cambios detectados</li>@endif
</ul>
</div></div>
</div></div>
@endsection
