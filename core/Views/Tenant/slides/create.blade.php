@extends('layouts.app')

@section('title', 'Añadir Diapositiva a Slider')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
             <div class="breadcrumb">
                <a href="/{{ admin_path() }}/sliders">Sliders</a>
                <span class="mx-2">/</span>
                <a href="/{{ admin_path() }}/sliders/{{ $sliderId }}/edit">{{ e($sliderName ?? 'Slider ID '.$sliderId) }}</a>
                 <span class="mx-2">/</span>
                <span>Añadir Diapositiva</span>
            </div>
             <a href="/{{ admin_path() }}/sliders/{{ $sliderId }}/edit" class="btn btn-sm btn-outline-secondary">Volver al Slider</a>
        </div>

        <div class="card">
            <div class="card-header">
                Nueva Diapositiva para "{{ e($sliderName ?? 'Slider ID '.$sliderId) }}"
            </div>
            <div class="card-body">
                <form method="POST" action="/{{ admin_path() }}/sliders/{{ $sliderId }}/slides">
                    @include('slides._form', ['slide' => new \Screenart\Musedock\Models\Slide(), 'sliderId' => $sliderId])
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
