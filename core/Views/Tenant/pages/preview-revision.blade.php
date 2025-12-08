@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content"><div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-3"><h2>{{ $title }}</h2>
<div><a href="{{ route('tenant.pages.revisions', $page->id) }}" class="btn btn-secondary me-2"><i class="bi bi-arrow-left"></i> Volver a revisiones</a>
<form method="POST" action="{{ route('tenant.pages.revisions.restore', [$page->id, $revision->id]) }}" style="display:inline;" onsubmit="return confirm('¿Restaurar a esta versión?');">
<input type="hidden" name="_token" value="{{ csrf_token() }}">
<button type="submit" class="btn btn-primary"><i class="bi bi-arrow-counterclockwise"></i> Restaurar esta versión</button>
</form></div>
</div>
<div class="card"><div class="card-body"><h5>{{ e($revision->title) }}</h5><hr><div>{!! nl2br(e($revision->content)) !!}</div></div></div>
</div></div>
@endsection
