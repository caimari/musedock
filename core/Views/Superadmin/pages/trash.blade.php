@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content"><div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-3">
<h2>{{ $title }}</h2>
<a href="/musedock/pages" class="btn btn-outline-secondary"><i class="bi bi-list"></i> {{ __('pages.back_to_pages_list') }}</a>
</div>
@php $flashSuccess = consume_flash('success'); @endphp
@if ($flashSuccess)
<script>
document.addEventListener('DOMContentLoaded', function() {
  Swal.fire({
    icon: 'success',
    title: {!! json_encode($flashSuccess) !!},
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  });
});
</script>
@endif
<div class="alert alert-warning"><i class="bi bi-info-circle"></i> {{ __('pages.trash_auto_delete_warning') }}</div>
<div class="card"><div class="card-body">
@if (empty($pages))
<p class="text-muted">{{ __('pages.no_pages_in_trash') }}</p>
@else
<table class="table table-hover">
<thead><tr><th>{{ __('pages.title_field') }}</th><th>{{ __('pages.deleted_at') }}</th><th>{{ __('pages.deleted_by') }}</th><th>{{ __('pages.auto_delete_at') }}</th><th style="width:200px;">{{ __('pages.actions') }}</th></tr></thead>
<tbody>
@foreach ($pages as $page)
@php $info = $trashInfo[$page->id] ?? null; @endphp
<tr>
<td><strong>{{ e($page->title) }}</strong><br><small class="text-muted">{{ e($page->slug) }}</small></td>
<td>{{ $info ? date('d/m/Y H:i', strtotime($info['deleted_at'])) : '-' }}</td>
<td>{{ e($info['deleted_by_name'] ?? __('common.unknown')) }}<br><small class="text-muted">{{ e($info['deleted_by_type'] ?? '') }}</small></td>
<td>@if($info && $info['scheduled_permanent_delete'])<span class="text-danger">{{ date('d/m/Y', strtotime($info['scheduled_permanent_delete'])) }}</span>@else-@endif</td>
<td>
<div class="btn-group btn-group-sm">
<form method="POST" action="/musedock/pages/{{ $page->id }}/restore" style="display:inline;">
<input type="hidden" name="_token" value="{{ csrf_token() }}">
<button type="submit" class="btn btn-success" title="{{ __('pages.restore_revision') }}"><i class="bi bi-arrow-counterclockwise"></i> {{ __('pages.restore_revision') }}</button>
</form>
<form method="POST" action="/musedock/pages/{{ $page->id }}/force-delete" style="display:inline;" onsubmit="return confirm('{{ __('pages.confirm_permanent_delete') }}');">
<input type="hidden" name="_token" value="{{ csrf_token() }}">
<input type="hidden" name="_method" value="DELETE">
<button type="submit" class="btn btn-danger" title="{{ __('pages.permanent_delete') }}"><i class="bi bi-trash"></i></button>
</form>
</div>
</td>
</tr>
@endforeach
</tbody>
</table>
@endif
</div></div>
</div></div>
@endsection
