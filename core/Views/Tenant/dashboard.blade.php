@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-welcome">
                    <div class="card-body">
                        <h2 class="mb-2">👋 {{ __('dashboard.welcome', ['name' => $email]) }}</h2>
                        <p class="mb-0 subtitle">{{ __('dashboard.admin_panel_subtitle') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row align-items-stretch">
            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card dashboard-stat h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span class="stat-icon" aria-hidden="true">📄</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1">{{ __('pages.title') }}</h5>
                                <h3 class="mb-0">{{ $stats['pages'] ?? 0 }}</h3>
                            </div>
                        </div>
                        <div class="mt-auto pt-3">
                            <a href="{{ admin_url('pages') }}" class="btn btn-sm btn-soft-primary">{{ __('pages.view_all') }} →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card dashboard-stat h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span class="stat-icon" aria-hidden="true">📰</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1">Posts</h5>
                                <h3 class="mb-0">{{ $stats['posts'] ?? 0 }}</h3>
                            </div>
                        </div>
                        <div class="mt-auto pt-3">
                            <a href="{{ admin_url('blog/posts') }}" class="btn btn-sm btn-soft-primary">Gestionar →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card dashboard-stat h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span class="stat-icon" aria-hidden="true">📋</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1">{{ __('menus.title') }}</h5>
                                <h3 class="mb-0">{{ $stats['menus'] ?? 0 }}</h3>
                            </div>
                        </div>
                        <div class="mt-auto pt-3">
                            <a href="{{ admin_url('menus') }}" class="btn btn-sm btn-soft-primary">{{ __('menus.manage') }} →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card dashboard-stat h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span class="stat-icon" aria-hidden="true">🧩</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1">{{ __('modules.title') }}</h5>
                                <h3 class="mb-0">{{ $stats['modules_enabled'] ?? 0 }}</h3>
                                <div class="small text-muted">{{ ($stats['modules_available'] ?? 0) }} disponibles</div>
                            </div>
                        </div>
                        <div class="mt-auto pt-3">
                            <a href="{{ admin_url('modules') }}" class="btn btn-sm btn-soft-primary">{{ __('modules.view_modules') }} →</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">⚡ {{ __('dashboard.quick_actions') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4 col-sm-6">
                                <a href="{{ admin_url('pages/create') }}" class="btn btn-soft-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">➕</span> {{ __('pages.new_page') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="{{ admin_url('menus/create') }}" class="btn btn-soft-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">📋</span> {{ __('menus.create_menu') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="{{ admin_url('themes') }}" class="btn btn-soft-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">🎨</span> {{ __('themes.change_theme') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="{{ admin_url('plugins') }}" class="btn btn-soft-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">📦</span> {{ __('plugins.install') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="{{ admin_url('settings') }}" class="btn btn-soft-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">⚙️</span> {{ __('settings.title') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="/" class="btn btn-soft-secondary w-100 d-flex align-items-center justify-content-start" target="_blank" rel="noopener">
                                    <span class="me-2">🌐</span> {{ __('dashboard.view_site') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
  .dashboard-welcome{
    background: linear-gradient(135deg, #e8f2ff 0%, #d9ecff 55%, #eef7ff 100%);
    border: 1px solid rgba(13, 110, 253, 0.16);
    color: #0b2e4a;
    box-shadow: 0 6px 18px rgba(17, 24, 39, 0.06);
  }
  .dashboard-welcome .subtitle{
    color: rgba(11, 46, 74, 0.75);
  }
  .dashboard-stat{
    border: 1px solid rgba(17, 24, 39, 0.06);
    box-shadow: 0 10px 20px rgba(17, 24, 39, 0.04);
  }
  .stat-icon{
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: rgba(13, 110, 253, 0.08);
    border: 1px solid rgba(13, 110, 253, 0.10);
    font-size: 1.5rem;
    line-height: 1;
  }
  .btn-soft-primary{
    color: #0b5ed7;
    background: rgba(13, 110, 253, 0.10);
    border: 1px solid rgba(13, 110, 253, 0.06);
  }
  .btn-soft-primary:hover{
    color: #084298;
    background: rgba(13, 110, 253, 0.16);
    border-color: rgba(13, 110, 253, 0.10);
  }
  .btn-soft-secondary{
    color: #495057;
    background: rgba(108, 117, 125, 0.08);
    border: 1px solid rgba(108, 117, 125, 0.05);
  }
  .btn-soft-secondary:hover{
    color: #343a40;
    background: rgba(108, 117, 125, 0.12);
    border-color: rgba(108, 117, 125, 0.08);
  }
</style>
@endpush

