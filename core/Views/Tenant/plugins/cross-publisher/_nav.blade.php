{{-- Cross-Publisher Plugin Navigation --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-diagram-3"></i> Cross-Publisher</h2>
        <p class="text-muted mb-0">Publica artículos en múltiples medios de tu red editorial</p>
    </div>
    <a href="{{ admin_url('/plugins') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Plugins
    </a>
</div>

<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'dashboard' ? 'active' : '' }}" href="{{ admin_url('/plugins/cross-publisher') }}">
            <i class="bi bi-speedometer2"></i> Panel
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'queue' ? 'active' : '' }}" href="{{ admin_url('/plugins/cross-publisher/queue') }}">
            <i class="bi bi-list-task"></i> Cola
            @if(($stats['pending_count'] ?? 0) > 0)
                <span class="badge bg-warning text-dark ms-1">{{ $stats['pending_count'] }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'network' ? 'active' : '' }}" href="{{ admin_url('/plugins/cross-publisher/network') }}">
            <i class="bi bi-globe"></i> Red
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'settings' ? 'active' : '' }}" href="{{ admin_url('/plugins/cross-publisher/settings') }}">
            <i class="bi bi-gear"></i> Configuración
        </a>
    </li>
</ul>
