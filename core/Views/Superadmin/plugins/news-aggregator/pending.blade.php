@extends('layouts.app')
@section('title', 'News Aggregator')
@section('content')
@php $en = detectLanguage() === 'en'; $statusLabels = $en ? ['pending' => 'Pending', 'ready' => 'Ready', 'approved' => 'Approved'] : ['pending' => 'Pendiente', 'ready' => 'Lista', 'approved' => 'Aprobada']; $labels = $en ? ['approve' => 'Approve', 'reject' => 'Reject', 'delete' => 'Delete'] : ['approve' => 'Aprobar', 'reject' => 'Rechazar', 'delete' => 'Borrar']; @endphp
<div class="app-content"><div class="container-fluid">
@include('plugins.news-aggregator._nav')
<h3>{{ $en ? 'Global pending inbox' : 'Bandeja global de pendientes' }} ({{ $total }})</h3>
<form method="GET" class="d-flex gap-2 mb-3">
<label>{{ $en ? 'Publication' : 'Medio' }}
<select name="tenant" class="form-select">
<option value="0">{{ $en ? 'All publications' : 'Todos los medios' }}</option>
@foreach($tenants as $tenant)
<option value="{{ $tenant->id }}" {{ $tenantId == $tenant->id ? 'selected' : '' }}>{{ $tenant->domain }}</option>
@endforeach
</select></label>
<label>{{ $en ? 'Status' : 'Estado' }}
<select name="status" class="form-select">
<option value="">{{ $en ? 'All pending states' : 'Todos los estados pendientes' }}</option>
@foreach(['pending', 'ready', 'approved'] as $state)
<option {{ $status === $state ? 'selected' : '' }} value="{{ $state }}">{{ $statusLabels[$state] }}</option>
@endforeach
</select></label>
<button class="btn btn-primary">{{ $en ? 'Filter' : 'Filtrar' }}</button>
</form>
<form id="pending-bulk" method="POST" action="/musedock/news-aggregator/pending/bulk">
@csrf
<input type="hidden" name="filter_tenant" value="{{ $tenantId }}">
<input type="hidden" name="filter_status" value="{{ $status }}">
<input type="hidden" name="page" value="{{ $page }}">
<label><input type="checkbox" onchange="document.querySelectorAll('.pending-selection').forEach(box => box.checked = this.checked)"> {{ $en ? 'Select page' : 'Seleccionar página' }}</label>
@foreach($labels as $action => $label)
<button class="btn btn-outline-primary" name="action" value="{{ $action }}">{{ $label }}</button>
@endforeach
</form>
<div class="table-responsive"><table class="table">
<thead><tr><th></th><th>{{ $en ? 'Publication' : 'Medio' }}</th><th>{{ $en ? 'Title' : 'Título' }}</th><th>{{ $en ? 'Source' : 'Fuente' }}</th><th>{{ $en ? 'Date' : 'Fecha' }}</th><th>{{ $en ? 'Status' : 'Estado' }}</th><th>{{ $en ? 'Actions' : 'Acciones' }}</th></tr></thead>
<tbody>
@foreach($items as $item)
<tr>
<td><input aria-label="{{ $en ? 'Select item' : 'Seleccionar noticia' }}" class="pending-selection" form="pending-bulk" type="checkbox" name="selections[]" value="{{ $item->tenant_id }}:{{ $item->id }}"></td>
<td>{{ $item->domain }}</td>
<td><a href="/musedock/news-aggregator/items/{{ $item->id }}?tenant={{ $item->tenant_id }}">{{ $item->rewritten_title ?: $item->original_title }}</a></td>
<td>{{ $item->source_name }}</td><td>{{ $item->created_at }}</td><td>{{ $statusLabels[$item->status] }}</td>
<td><form method="POST" action="/musedock/news-aggregator/pending/bulk">
@csrf
<input type="hidden" name="selections[]" value="{{ $item->tenant_id }}:{{ $item->id }}">
<input type="hidden" name="filter_tenant" value="{{ $tenantId }}">
<input type="hidden" name="filter_status" value="{{ $status }}">
<input type="hidden" name="page" value="{{ $page }}">
@foreach($labels as $action => $label)
<button class="btn btn-sm btn-outline-primary" name="action" value="{{ $action }}">{{ $label }}</button>
@endforeach
</form></td></tr>
@endforeach
</tbody></table></div>
<nav class="d-flex gap-3">
@if($page > 1)
<a href="?{{ http_build_query(['tenant' => $tenantId, 'status' => $status, 'page' => $page - 1]) }}">{{ $en ? 'Previous' : 'Anterior' }}</a>
@endif
<span>{{ $page }} / {{ $totalPages }}</span>
@if($page < $totalPages)
<a href="?{{ http_build_query(['tenant' => $tenantId, 'status' => $status, 'page' => $page + 1]) }}">{{ $en ? 'Next' : 'Siguiente' }}</a>
@endif
</nav>
</div></div>
@endsection
