@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h2 class="mb-2">👋 {{ __('dashboard.welcome', ['name' => $email]) }}</h2>
                        <p class="mb-0 opacity-75">{{ __('dashboard.admin_panel_subtitle') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span style="font-size: 2.5rem;">📄</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1">{{ __('pages.title') }}</h5>
                                <h3 class="mb-0">-</h3>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/{{ admin_path() }}/pages" class="btn btn-sm btn-outline-primary">{{ __('pages.view_all') }} →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span style="font-size: 2.5rem;">📋</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1">{{ __('menus.title') }}</h5>
                                <h3 class="mb-0">-</h3>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/{{ admin_path() }}/menus" class="btn btn-sm btn-outline-primary">{{ __('menus.manage') }} →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span style="font-size: 2.5rem;">🧩</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1">{{ __('modules.title') }}</h5>
                                <h3 class="mb-0">-</h3>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/{{ admin_path() }}/modules" class="btn btn-sm btn-outline-primary">{{ __('modules.view_modules') }} →</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span style="font-size: 2.5rem;">🔌</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1">{{ __('plugins.my_plugins') }}</h5>
                                <h3 class="mb-0">-</h3>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="/{{ admin_path() }}/plugins" class="btn btn-sm btn-outline-primary">{{ __('plugins.manage') }} →</a>
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
                                <a href="/{{ admin_path() }}/pages/create" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">➕</span> {{ __('pages.new_page') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="/{{ admin_path() }}/menus/create" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">📋</span> {{ __('menus.create_menu') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="/{{ admin_path() }}/themes" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">🎨</span> {{ __('themes.change_theme') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="/{{ admin_path() }}/plugins" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">📦</span> {{ __('plugins.install') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="/{{ admin_path() }}/settings" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-start">
                                    <span class="me-2">⚙️</span> {{ __('settings.title') }}
                                </a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="/" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-start" target="_blank">
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



