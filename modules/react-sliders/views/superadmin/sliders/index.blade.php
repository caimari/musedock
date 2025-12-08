@extends('layouts::app')

@section('title', __rs('slider.sliders'))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Botón Crear --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ __rs('slider.sliders') }}</h2>
            <a href="{{ route('react-sliders.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> {{ __rs('slider.add_slider') }}
            </a>
        </div>

        {{-- Mensajes de éxito --}}
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <div class="card">
            <div class="card-body table-responsive p-0">
                @if(count($sliders) > 0)
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ __rs('slider.name') }}</th>
                                <th>{{ __rs('slider.identifier') }}</th>
                                <th>{{ __rs('slider.engine') }}</th>
                                <th class="text-center">{{ __rs('slider.slides_count') }}</th>
                                <th class="text-center">{{ __rs('slider.status') }}</th>
                                <th class="text-end">{{ __rs('slider.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sliders as $slider)
                                <tr>
                                    <td>
                                        <a href="{{ route('react-sliders.edit', ['id' => $slider->id]) }}">
                                            <strong>{{ e($slider->name) }}</strong>
                                        </a>
                                    </td>
                                    <td>
                                        <code class="text-muted">{{ $slider->identifier }}</code>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ ucfirst($slider->engine) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info">{{ count($slider->slides()) }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if($slider->is_active)
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>{{ __rs('slider.active') }}
                                            </span>
                                        @else
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle me-1"></i>{{ __rs('slider.inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('react-sliders.edit', ['id' => $slider->id]) }}"
                                               class="btn btn-outline-primary"
                                               title="Editar Slider y Diapositivas">
                                                <i class="bi bi-pencil-square"></i> Editar
                                            </a>
                                            <button type="button"
                                                    class="btn btn-outline-danger"
                                                    title="Eliminar Slider"
                                                    onclick="confirmDelete({{ $slider->id }})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <form id="delete-form-{{ $slider->id }}"
                                              action="{{ route('react-sliders.destroy', ['id' => $slider->id]) }}"
                                              method="POST"
                                              style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-images" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="mt-3">{{ __rs('slider.no_sliders_found') }}.</p>
                        <a href="{{ route('react-sliders.create') }}" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-lg me-1"></i>{{ __rs('slider.add_first_slider') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmDelete(sliderId) {
    Swal.fire({
        title: '{{ __rs("slider.confirm_delete_title") }}',
        text: '{{ __rs("slider.confirm_delete_message") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-form-' + sliderId).submit();
        }
    });
}
</script>
@endpush
