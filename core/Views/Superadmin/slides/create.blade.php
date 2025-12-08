{{-- core/views/superadmin/slides/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Añadir Diapositiva a Slider')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Navegación --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
             <div class="breadcrumb">
                <a href="{{ route('sliders.index') }}">Sliders</a>
                <span class="mx-2">/</span>
                {{-- Enlace para volver al slider padre --}}
                <a href="{{ route('sliders.edit', ['id' => $sliderId]) }}">{{ e($sliderName ?? 'Slider ID '.$sliderId) }}</a>
                 <span class="mx-2">/</span>
                <span>Añadir Diapositiva</span>
            </div>
             <a href="{{ route('sliders.edit', ['id' => $sliderId]) }}" class="btn btn-sm btn-outline-secondary">Volver al Slider</a>
        </div>

        <div class="card">
            <div class="card-header">
                Nueva Diapositiva para "{{ e($sliderName ?? 'Slider ID '.$sliderId) }}"
            </div>
            <div class="card-body">
                {{-- Ruta storeSlide necesita sliderId --}}
                <form method="POST" action="{{ route('slides.store', ['sliderId' => $sliderId]) }}">
                     {{-- Pasamos $sliderId al parcial --}}
                    @include('slides._form', ['slide' => new \Screenart\Musedock\Models\Slide(), 'sliderId' => $sliderId])
                </form>
            </div>
        </div>

    </div>
</div>
@endsection