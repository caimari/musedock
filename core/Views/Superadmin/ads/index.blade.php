@extends('layouts.app')

@section('title', $title ?? 'Anuncios')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2>{{ $title ?? 'Anuncios' }}</h2>
                <p class="text-muted mb-0">Gestiona los anuncios y banners globales del CMS.</p>
            </div>
            <a href="/musedock/ads/create" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Crear anuncio
            </a>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="card">
            <div class="card-body table-responsive p-0">
                @if($ads && count($ads) > 0)
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Espacio</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th class="text-center">Impresiones</th>
                                <th class="text-center">Clics</th>
                                <th>Programaci&oacute;n</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ads as $ad)
                                <tr>
                                    <td>
                                        <a href="/musedock/ads/{{ $ad->id }}/edit">
                                            <strong>{{ e($ad->name) }}</strong>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ e($ad->slot_name ?? $ad->slot_slug) }}</span>
                                    </td>
                                    <td>
                                        @if($ad->ad_type === 'image')
                                            <span class="badge bg-info">Imagen</span>
                                        @else
                                            <span class="badge text-white" style="background-color: #6f42c1;">HTML</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ad->is_active)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ number_format($ad->impressions ?? 0) }}</td>
                                    <td class="text-center">{{ number_format($ad->clicks ?? 0) }}</td>
                                    <td>
                                        @if(!$ad->starts_at && !$ad->ends_at)
                                            <span class="text-muted">Siempre</span>
                                        @else
                                            <small>
                                                @if($ad->starts_at){{ date('d/m/Y', strtotime($ad->starts_at)) }}@else ...@endif
                                                &mdash;
                                                @if($ad->ends_at){{ date('d/m/Y', strtotime($ad->ends_at)) }}@else ...@endif
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="/musedock/ads/{{ $ad->id }}/edit" class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <form action="/musedock/ads/{{ $ad->id }}/toggle" method="POST" class="d-inline">
                                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                <button type="submit" class="btn {{ $ad->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $ad->is_active ? 'Desactivar' : 'Activar' }}">
                                                    <i class="bi {{ $ad->is_active ? 'bi-pause-fill' : 'bi-play-fill' }}"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                    onclick="confirmDeleteAd({{ $ad->id }})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <form id="delete-ad-form-{{ $ad->id }}" action="/musedock/ads/{{ $ad->id }}/delete" method="POST" style="display: none;">
                                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-megaphone fs-1 d-block mb-2"></i>
                        Sin anuncios. <a href="/musedock/ads/create">Crea el primero</a>.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    function confirmDeleteAd(adId) {
        Swal.fire({
            title: '¿Eliminar anuncio?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-ad-form-' + adId).submit();
            }
        });
    }
</script>
@endpush
