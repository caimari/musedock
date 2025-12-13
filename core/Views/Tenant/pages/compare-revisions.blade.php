@extends('layouts.app')
@section('title', $title)
@section('styles')
<style>.diff-container{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.diff-column{border:1px solid #dee2e6;border-radius:.25rem;padding:1rem;background:#f8f9fa}.diff-added{background:#d4edda;border-left:3px solid #28a745}.diff-removed{background:#f8d7da;border-left:3px solid #dc3545}.content-preview{max-height:400px;overflow-y:auto;background:#fff;padding:1rem;border-radius:.25rem}</style>
@endsection
@section('content')
<div class="app-content"><div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-3"><h2>{{ $title }}</h2><a href="{{ admin_url('pages') }}/{{ $page->id }}/revisions" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver a revisiones</a></div>
<div class="diff-container">
<div class="diff-column"><h6>Revisión #{{ $revision1->id }}</h6><div><strong>Título:</strong><div class="content-preview @if($diff['title']) diff-removed @endif">{{ e($revision1->title) }}</div></div></div>
<div class="diff-column"><h6>Revisión #{{ $revision2->id }}</h6><div><strong>Título:</strong><div class="content-preview @if($diff['title']) diff-added @endif">{{ e($revision2->title) }}</div></div></div>
</div>
</div></div>
@endsection
