{{-- core/views/superadmin/slides/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Editar Diapositiva')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Navegación --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
             <div class="breadcrumb">
                <a href="{{ route('sliders.index') }}">Sliders</a>
                <span class="mx-2">/</span>
                 {{-- Enlace para volver al slider padre --}}
                <a href="{{ route('sliders.edit', ['id' => $slide->slider_id]) }}">{{ e($slide->slider->name ?? 'Slider ID '.$slide->slider_id) }}</a>
                 <span class="mx-2">/</span>
                <span>Editar Diapositiva</span>
            </div>
             <a href="{{ route('sliders.edit', ['id' => $slide->slider_id]) }}" class="btn btn-sm btn-outline-secondary">Volver al Slider</a>
        </div>

         {{-- Alertas SweetAlert2 --}}
        @include('partials.alerts-sweetalert2')

        <div class="card">
            <div class="card-header">
                Editando Diapositiva
            </div>
            <div class="card-body">
                 {{-- Ruta updateSlide necesita slideId --}}
<form method="POST" action="{{ route('slides.update', ['slideId' => $slide->id]) }}">
    <input type="hidden" name="_method" value="PUT">
                    @include('slides._form', ['slide' => $slide]) {{-- Pasamos la slide existente --}}
                </form>
            </div>
        </div>

    </div>
</div>
@endsection