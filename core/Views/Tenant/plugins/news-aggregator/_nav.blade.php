{{-- News Aggregator Plugin Navigation --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-newspaper"></i> News Aggregator</h2>
        <p class="text-muted mb-0">Captura y reescribe noticias de fuentes externas</p>
    </div>
    <a href="{{ admin_url('/plugins') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Plugins
    </a>
</div>

<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'dashboard' ? 'active' : '' }}" href="{{ admin_url('/plugins/news-aggregator') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'sources' ? 'active' : '' }}" href="{{ admin_url('/plugins/news-aggregator/sources') }}">
            <i class="bi bi-rss"></i> Fuentes
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'items' ? 'active' : '' }}" href="{{ admin_url('/plugins/news-aggregator/items') }}">
            <i class="bi bi-file-text"></i> Noticias
            @if(($stats['pending_count'] ?? 0) > 0)
                <span class="badge bg-warning text-dark ms-1">{{ $stats['pending_count'] }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'logs' ? 'active' : '' }}" href="{{ admin_url('/plugins/news-aggregator/logs') }}">
            <i class="bi bi-clock-history"></i> Logs
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'settings' ? 'active' : '' }}" href="{{ admin_url('/plugins/news-aggregator/settings') }}">
            <i class="bi bi-gear"></i> Configuración
        </a>
    </li>
</ul>
