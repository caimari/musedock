@extends('layouts.app')

@section('title', 'Editar Diapositiva')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
             <div class="breadcrumb">
                <a href="/{{ admin_path() }}/sliders">Sliders</a>
                <span class="mx-2">/</span>
                <a href="/{{ admin_path() }}/sliders/{{ $slide->slider_id }}/edit">{{ e($slide->slider->name ?? 'Slider ID '.$slide->slider_id) }}</a>
                 <span class="mx-2">/</span>
                <span>Editar Diapositiva</span>
            </div>
             <a href="/{{ admin_path() }}/sliders/{{ $slide->slider_id }}/edit" class="btn btn-sm btn-outline-secondary">Volver al Slider</a>
        </div>

         @include('partials.alerts-sweetalert2')

        <div class="card">
            <div class="card-header">
                Editando Diapositiva
            </div>
            <div class="card-body">
                <form method="POST" action="/{{ admin_path() }}/sliders/slides/{{ $slide->id }}/update">
                    @include('slides._form', ['slide' => $slide])
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
