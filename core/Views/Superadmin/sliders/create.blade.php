@extends('layouts.app')

@section('title', $title ?? 'Crear Nuevo Slider')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Navegación --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
             <div class="breadcrumb">
                <a href="{{ route('sliders.index') }}">Sliders</a>
                <span class="mx-2">/</span>
                <span>Crear Nuevo</span>
            </div>
             <a href="{{ route('sliders.index') }}" class="btn btn-sm btn-outline-secondary">Volver al Listado</a>
        </div>

        <div class="card">
            <div class="card-header">
                Nuevo Slider
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('sliders.store') }}">
                    @include('sliders._form') {{-- Incluir el parcial del formulario --}}
                </form>
            </div>
        </div>

    </div>
</div>
@endsection