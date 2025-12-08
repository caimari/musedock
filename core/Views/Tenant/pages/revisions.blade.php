@extends('layouts.app')
@section('title', $title)
@section('styles')
<style>.revision-row{cursor:pointer;transition:background-color .2s}.revision-row:hover{background-color:#f8f9fa}.revision-type-badge{font-size:.75rem;padding:.25rem .5rem}.compare-checkbox{width:20px;height:20px;cursor:pointer}</style>
@endsection
@section('content')
<div class="app-content"><div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-3">
<h2>{{ $title }}</h2>
<div><a href="{{ route('tenant.pages.edit', $page->id) }}" class="btn btn-secondary me-2"><i class="bi bi-arrow-left"></i> Volver a editar</a><a href="{{ route('tenant.pages.index') }}" class="btn btn-outline-secondary"><i class="bi bi-list"></i> Lista de páginas</a></div>
</div>
@if (session('success'))<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'success',title:'Correcto',text:{!!json_encode(session('success'))!!},confirmButtonColor:'#3085d6'});});</script>@endif
<div class="card mb-3"><div class="card-body"><h5 class="card-title">{{ e($page->title) }}</h5><p class="card-text text-muted"><strong>Total de revisiones:</strong> {{ count($revisions) }}</p></div></div>
<div id="compare-button-container" class="mb-3" style="display:none;"><button type="button" class="btn btn-primary" onclick="compareSelected()"><i class="bi bi-arrow-left-right"></i> Comparar revisiones seleccionadas</button></div>
<div class="card"><div class="card-body">@if(empty($revisions))<p class="text-muted">No hay revisiones disponibles para esta página.</p>@else
<table class="table table-hover"><thead><tr><th style="width:40px;"></th><th>Fecha y Hora</th><th>Tipo</th><th>Usuario</th><th>Cambios</th><th style="width:200px;">Acciones</th></tr></thead><tbody>
@foreach ($revisions as $revision)
<tr class="revision-row">
<td><input type="checkbox" class="compare-checkbox" data-revision-id="{{ $revision->id }}" onchange="toggleCompareButton()"></td>
<td>{{ date('d/m/Y H:i:s', strtotime($revision->created_at)) }}</td>
<td>@php $badges=['initial'=>['success','Inicial'],'manual'=>['primary','Manual'],'autosave'=>['info','Autoguardado'],'published'=>['warning','Publicado'],'restored'=>['dark','Restaurado']]; [$class,$label]=$badges[$revision->revision_type]??['secondary',$revision->revision_type]; @endphp<span class="badge bg-{{ $class }} revision-type-badge">{{ $label }}</span></td>
<td>{{ e($revision->user_name ?? 'Sistema') }}<small class="text-muted d-block">{{ e($revision->user_type) }}</small></td>
<td>{!! e($revision->changes_summary)?:'<span class="text-muted">Sin descripción</span>' !!}</td>
<td><div class="btn-group btn-group-sm"><a href="{{ route('tenant.pages.revisions.preview', [$page->id, $revision->id]) }}" class="btn btn-outline-secondary" title="Vista previa"><i class="bi bi-eye"></i></a>
<form method="POST" action="{{ route('tenant.pages.revisions.restore', [$page->id, $revision->id]) }}" style="display:inline;" onsubmit="return confirm('¿Restaurar la página a esta versión?');"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-outline-primary" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button></form></div></td>
</tr>
@endforeach
</tbody></table>@endif</div></div>
</div></div>
<script>
let selectedRevisions=[];
function toggleCompareButton(){const checkboxes=document.querySelectorAll('.compare-checkbox:checked');selectedRevisions=Array.from(checkboxes).map(cb=>cb.getAttribute('data-revision-id'));document.getElementById('compare-button-container').style.display=selectedRevisions.length===2?'block':'none';if(selectedRevisions.length===2){document.querySelectorAll('.compare-checkbox:not(:checked)').forEach(cb=>cb.disabled=true);}else{document.querySelectorAll('.compare-checkbox').forEach(cb=>cb.disabled=false);}}
function compareSelected(){if(selectedRevisions.length===2){@php $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin'; @endphp window.location.href=`/{!!json_encode($adminPath)!!}/pages/{{ $page->id }}/revisions/${selectedRevisions[0]}/compare/${selectedRevisions[1]}`;}}
</script>
@endsection
