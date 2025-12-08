@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')


<div class="app-content">
    <div class="container-fluid">

        <div class="card">
            <div class="card-body">
                <h5>{{ __('dashboard.welcome', ['name' => $_SESSION['super_admin']['email'] ?? 'Admin']) }}</h5>
                <p>{{ __('dashboard.welcome_message') }}</p>
                <a href="/musedock/logout" class="btn btn-danger mt-2">{{ __('auth.logout') }}</a>
            </div>
        </div>

        {{-- Aquí podrías cargar estadísticas, resumen de uso, widgets, etc. --}}
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card text-bg-light">
                    <div class="card-header">{{ __('dashboard.active_tenants') }}</div>
                    <div class="card-body">
                        <p class="card-text">{{ __('dashboard.add_stats') }}</p>
                        <a href="/musedock/tenants" class="btn btn-outline-primary">{{ __('tenants.view_all') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-bg-light">
                    <div class="card-header">{{ __('dashboard.available_modules') }}</div>
                    <div class="card-body">
                        <p class="card-text">{{ __('dashboard.manage_modules_desc') }}</p>
                        <a href="/musedock/modules" class="btn btn-outline-secondary">{{ __('modules.manage') }}</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
