{{-- News Aggregator Superadmin - Navigation --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-newspaper"></i> News Aggregator</h2>
        <p class="text-muted mb-0">Panel centralizado de gestión multi-tenant</p>
    </div>
</div>

@if(!empty($tenantId))
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'dashboard' ? 'active' : '' }}" href="/musedock/news-aggregator?tenant={{ $tenantId }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'sources' ? 'active' : '' }}" href="/musedock/news-aggregator/sources?tenant={{ $tenantId }}">
            <i class="bi bi-rss"></i> Fuentes
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'items' ? 'active' : '' }}" href="/musedock/news-aggregator/items?tenant={{ $tenantId }}">
            <i class="bi bi-file-text"></i> Noticias
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'logs' ? 'active' : '' }}" href="/musedock/news-aggregator/logs?tenant={{ $tenantId }}">
            <i class="bi bi-clock-history"></i> Logs
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'settings' ? 'active' : '' }}" href="/musedock/news-aggregator/settings?tenant={{ $tenantId }}">
            <i class="bi bi-gear"></i> Configuración
        </a>
    </li>
</ul>
@endif

@include('plugins.news-aggregator._tenant_selector', ['tenants' => $tenants ?? [], 'tenantId' => $tenantId ?? 0])
