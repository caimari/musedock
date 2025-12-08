@php
    $type = $item['type'] ?? 'module';
    $typeIcons = [
        'module' => 'bi-puzzle',
        'plugin' => 'bi-plug',
        'theme' => 'bi-palette',
    ];
    $typeColors = [
        'module' => 'primary',
        'plugin' => 'success',
        'theme' => 'info',
    ];
    $icon = $typeIcons[$type] ?? 'bi-box';
    $color = $typeColors[$type] ?? 'secondary';
@endphp

<div class="card h-100 border-0 shadow-sm item-card">
    {{-- Thumbnail --}}
    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 140px;">
        @if(!empty($item['thumbnail']) && file_exists(APP_ROOT . '/public' . $item['thumbnail']))
            <img src="{{ $item['thumbnail'] }}" alt="{{ $item['name'] }}" class="img-fluid" style="max-height: 140px; object-fit: cover;">
        @else
            <i class="bi {{ $icon }} display-3 text-{{ $color }} opacity-50"></i>
        @endif
    </div>

    <div class="card-body">
        {{-- Badge de tipo --}}
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-{{ $color }}">
                <i class="bi {{ $icon }} me-1"></i>
                {{ ucfirst($type) }}
            </span>
            @if(($item['price'] ?? 0) == 0)
                <span class="badge bg-success">Gratis</span>
            @else
                <span class="badge bg-warning text-dark">${{ number_format($item['price'], 0) }}</span>
            @endif
        </div>

        {{-- Nombre --}}
        <h5 class="card-title mb-1">{{ $item['name'] }}</h5>

        {{-- Autor --}}
        <small class="text-muted d-block mb-2">
            <i class="bi bi-person me-1"></i>
            {{ $item['author'] ?? 'MuseDock' }}
        </small>

        {{-- Descripción --}}
        <p class="card-text text-muted small mb-3">
            {{ mb_substr($item['description'] ?? '', 0, 80) }}{{ strlen($item['description'] ?? '') > 80 ? '...' : '' }}
        </p>

        {{-- Stats --}}
        <div class="d-flex justify-content-between align-items-center small text-muted mb-3">
            <span title="Descargas">
                <i class="bi bi-download me-1"></i>
                {{ number_format($item['downloads'] ?? 0) }}
            </span>
            <span title="Valoración">
                <i class="bi bi-star-fill text-warning me-1"></i>
                {{ number_format($item['rating'] ?? 0, 1) }}
            </span>
            <span title="Versión">
                <i class="bi bi-tag me-1"></i>
                v{{ $item['version'] ?? '1.0.0' }}
            </span>
        </div>
    </div>

    <div class="card-footer bg-transparent border-0 pt-0">
        <div class="d-grid gap-2">
            <a href="{{ route('superadmin.marketplace.show', ['type' => $type, 'slug' => $item['slug']]) }}" class="btn btn-outline-{{ $color }}">
                <i class="bi bi-eye me-1"></i> Ver Detalles
            </a>
        </div>
    </div>
</div>

<style>
.item-card {
    transition: all 0.3s ease;
}
.item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;
}
</style>
