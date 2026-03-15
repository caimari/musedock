@extends('layouts.app')

@section('title', $title ?? 'Sliders')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ $title ?? 'Sliders' }}</h2>
            <a href="/{{ admin_path() }}/sliders/create" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Crear Nuevo Slider
            </a>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="card">
            <div class="card-body table-responsive p-0">
                @if($sliders && count($sliders) > 0)
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Shortcode</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sliders as $slider)
                                <tr>
                                    <td>
                                        <a href="/{{ admin_path() }}/sliders/{{ $slider->id }}/edit">
                                            <strong>{{ e($slider->name) }}</strong>
                                        </a>
                                    </td>
                                    <td>
                                        <input type="text" readonly class="form-control form-control-sm d-inline-block" style="width: auto;"
                                               value="[slider id={{ $slider->id }}]" id="shortcode-{{ $slider->id }}">
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1"
                                                onclick="copyShortcode({{ $slider->id }})" title="Copiar Shortcode">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="/{{ admin_path() }}/sliders/{{ $slider->id }}/edit" class="btn btn-outline-primary" title="Editar Slider y Diapositivas">
                                                <i class="bi bi-pencil-square"></i> Editar
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" title="Eliminar Slider"
                                                    onclick="confirmDeleteSlider({{ $slider->id }})">
                                                <i class="bi bi-trash"></i>
                                            </button>

                                            <form id="delete-slider-form-{{ $slider->id }}" action="/{{ admin_path() }}/sliders/{{ $slider->id }}/destroy" method="POST" style="display: none;">
                                                @csrf
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-3 text-center text-muted">
                        No se encontraron sliders. <a href="/{{ admin_path() }}/sliders/create">Crea el primero</a>.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    function copyShortcode(sliderId) {
        const input = document.getElementById('shortcode-' + sliderId);
        input.select();
        input.setSelectionRange(0, 99999);
        try {
            document.execCommand('copy');
            input.classList.add('is-valid');
            setTimeout(() => input.classList.remove('is-valid'), 1500);
        } catch (err) {
            console.error('Error al copiar shortcode:', err);
            Swal.fire('Error', 'No se pudo copiar el shortcode.', 'error');
        }
    }

    function confirmDeleteSlider(sliderId) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡Eliminarás este slider y todas sus diapositivas! Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-slider-form-' + sliderId).submit();
            }
        });
    }
</script>
@endpush
