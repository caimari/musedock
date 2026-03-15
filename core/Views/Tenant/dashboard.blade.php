@extends('layouts.app')

@section('title', __('dashboard.title'))

@push('styles')
<style>
.dash-welcome {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 28px 32px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.dash-welcome h2 {
    font-size: 1.35rem;
    font-weight: 600;
    color: #1a1f36;
    margin-bottom: 4px;
}
.dash-welcome p {
    color: #6b7280;
    font-size: .9rem;
    margin: 0;
}
.dash-stat {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 22px 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    gap: 16px;
    text-decoration: none;
    color: inherit;
    transition: box-shadow .15s, border-color .15s;
}
.dash-stat:hover {
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    border-color: #d1d5db;
    color: inherit;
    text-decoration: none;
}
.dash-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.dash-stat-icon.pages   { background: #f0fdf4; }
.dash-stat-icon.posts   { background: #fef9ec; }
.dash-stat-icon.menus   { background: #f5f3ff; }
.dash-stat-icon.modules { background: #fff1f0; }
.dash-stat-body h3 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a1f36;
    margin: 0;
    line-height: 1.1;
}
.dash-stat-body span {
    font-size: .8rem;
    color: #9ca3af;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.dash-actions-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}
@media (max-width: 767px) {
    .dash-actions-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .dash-actions-grid { grid-template-columns: 1fr; }
}
.dash-action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 16px;
    background: #f9fafb;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    color: #374151;
    font-size: .875rem;
    font-weight: 500;
    text-decoration: none;
    transition: background .15s, border-color .15s, color .15s;
}
.dash-action-btn:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #111827;
    text-decoration: none;
}
.dash-action-btn i {
    font-size: 1rem;
    color: #6b7280;
    flex-shrink: 0;
}
.dash-action-btn:hover i {
    color: #374151;
}
.dash-section-title {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #9ca3af;
    margin-bottom: 14px;
}
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid" style="max-width: 1100px;">

        {{-- Bienvenida --}}
        <div class="dash-welcome mb-4">
            <h2>{{ __('dashboard.welcome', ['name' => $name ?? $email]) }}</h2>
            <p>{{ site_setting('site_name', '') }} &mdash; Panel de administración</p>
        </div>

        {{-- Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <a href="{{ admin_url('pages') }}" class="dash-stat">
                    <div class="dash-stat-icon pages"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="dash-stat-body">
                        <h3>{{ $stats['pages'] ?? 0 }}</h3>
                        <span>{{ __('pages.title') }}</span>
                    </div>
                </a>
            </div>
            <div class="col-xl-3 col-sm-6">
                <a href="{{ admin_url('blog/posts') }}" class="dash-stat">
                    <div class="dash-stat-icon posts"><i class="bi bi-newspaper"></i></div>
                    <div class="dash-stat-body">
                        <h3>{{ $stats['posts'] ?? 0 }}</h3>
                        <span>Posts</span>
                    </div>
                </a>
            </div>
            <div class="col-xl-3 col-sm-6">
                <a href="{{ admin_url('menus') }}" class="dash-stat">
                    <div class="dash-stat-icon menus"><i class="bi bi-list-nested"></i></div>
                    <div class="dash-stat-body">
                        <h3>{{ $stats['menus'] ?? 0 }}</h3>
                        <span>{{ __('menus.title') }}</span>
                    </div>
                </a>
            </div>
            <div class="col-xl-3 col-sm-6">
                <a href="{{ admin_url('modules') }}" class="dash-stat">
                    <div class="dash-stat-icon modules"><i class="bi bi-puzzle"></i></div>
                    <div class="dash-stat-body">
                        <h3>{{ $stats['modules_enabled'] ?? 0 }}</h3>
                        <span>Módulos activos</span>
                    </div>
                </a>
            </div>
        </div>

        {{-- Acciones rápidas --}}
        <div class="bg-white border rounded-3 p-4" style="border-color: #e9ecef !important; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
            <p class="dash-section-title">Acciones rápidas</p>
            <div class="dash-actions-grid">
                <a href="{{ admin_url('pages/create') }}" class="dash-action-btn">
                    <i class="bi bi-file-earmark-plus"></i> {{ __('pages.new_page') }}
                </a>
                <a href="{{ admin_url('blog/posts/create') }}" class="dash-action-btn">
                    <i class="bi bi-pencil-square"></i> Nuevo post
                </a>
                <a href="{{ admin_url('menus/create') }}" class="dash-action-btn">
                    <i class="bi bi-list-nested"></i> {{ __('menus.create_menu') }}
                </a>
                <a href="{{ admin_url('themes') }}" class="dash-action-btn">
                    <i class="bi bi-palette"></i> {{ __('themes.change_theme') }}
                </a>
                <a href="{{ admin_url('settings') }}" class="dash-action-btn">
                    <i class="bi bi-gear"></i> {{ __('settings.title') }}
                </a>
                <a href="/" target="_blank" rel="noopener" class="dash-action-btn">
                    <i class="bi bi-box-arrow-up-right"></i> {{ __('dashboard.view_site') }}
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
