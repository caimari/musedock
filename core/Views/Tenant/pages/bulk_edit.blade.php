@extends('layouts.app')
@section('title', 'Edición en lote')
@section('content')
<div class="app-content">
  <div class="container">
    <h2 class="mb-4">Edición en lote</h2>
    
    <form method="POST" action="{{ route('pages.bulk.update') }}">
      {!! csrf_field() !!}
      
      @foreach ($selectedIds as $id)
        <input type="hidden" name="selected[]" value="{{ $id }}">
      @endforeach
      
      <div class="card mb-4">
        <div class="card-header">Opciones comunes</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="status" class="form-select">
              <option value="">— No cambiar —</option>
              <option value="published">Publicado</option>
              <option value="draft">Borrador</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Visibilidad</label>
            <select name="visibility" class="form-select">
              <option value="">— No cambiar —</option>
              <option value="public">Público - Todos los visitantes</option>
              <option value="private">Privado - Solo el creador</option>
              <option value="members">Miembros - Usuarios registrados</option>
            </select>
            <small class="form-text text-muted">Controla quién puede ver estas páginas.</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Fecha de publicación</label>
            <input type="datetime-local" name="published_at" class="form-control">
            <small class="form-text text-muted">Dejar en blanco para no modificar.</small>
          </div>
        </div>
      </div>
      
      <div class="card mb-4">
        <div class="card-header">Páginas seleccionadas ({{ count($selectedPages) }})</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Título</th>
                  <th>Estado actual</th>
                  <th>Visibilidad</th>
                  <th>Fecha publicación</th>
                  <th>Actualizado</th>
                </tr>
              </thead>
              <tbody>
                @foreach($selectedPages as $page)
                <tr>
                  <td>{{ $page->id }}</td>
                  <td>
                    <a href="{{ route('pages.edit', ['id' => $page->id]) }}" target="_blank">
                      {{ $page->title }}
                    </a>
                  </td>
                  <td>
                    @if($page->status === 'published')
                      <span class="badge bg-success">Publicada</span>
                    @else
                      <span class="badge bg-secondary">Borrador</span>
                    @endif
                  </td>
                  <td>
                    @if($page->visibility === 'private')
                      <span class="badge bg-danger">Privada</span>
                    @elseif($page->visibility === 'members')
                      <span class="badge bg-info">Miembros</span>
                    @else
                      <span class="badge bg-light text-dark">Pública</span>
                    @endif
                  </td>
                  <td>
                    @if($page->published_at)
                      @if(is_string($page->published_at))
                        {{ date('d/m/Y H:i', strtotime($page->published_at)) }}
                      @else
                        {{ $page->published_at->format('d/m/Y H:i') }}
                      @endif
                    @else
                      <span class="text-muted">No definida</span>
                    @endif
                  </td>
                  <td>
                    @if($page->updated_at)
                      @if(is_string($page->updated_at))
                        {{ date('d/m/Y H:i', strtotime($page->updated_at)) }}
                      @else
                        {{ $page->updated_at->format('d/m/Y H:i') }}
                      @endif
                    @endif
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      <div class="d-flex justify-content-between">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-1"></i> Aplicar cambios a todas
        </button>
        <a href="{{ route('pages.index') }}" class="btn btn-secondary">
          <i class="fas fa-times me-1"></i> Cancelar
        </a>
      </div>
    </form>
  </div>
</div>
@endsection