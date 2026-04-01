{{-- News Aggregator - Tenant Selector --}}
@if(!empty($tenants))
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
            <label class="form-label mb-0 fw-bold text-nowrap"><i class="bi bi-globe me-1"></i> Tenant:</label>
            <select name="tenant" class="form-select form-select-sm" style="width: auto; min-width: 300px;" onchange="this.form.submit()">
                <option value="">-- Seleccionar tenant --</option>
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}" {{ ($tenantId ?? 0) == $t->id ? 'selected' : '' }}>
                        {{ $t->domain }} {{ !empty($t->name) && $t->name !== $t->domain ? '(' . $t->name . ')' : '' }}
                    </option>
                @endforeach
            </select>
            @if(!empty($tenantId))
                @php
                    $currentTenantObj = null;
                    foreach($tenants as $t) {
                        if ($t->id == $tenantId) { $currentTenantObj = $t; break; }
                    }
                @endphp
                @if($currentTenantObj)
                    <span class="badge text-dark" style="background-color: #d0e8ff;">
                        <i class="bi bi-globe"></i> {{ $currentTenantObj->domain }}
                    </span>
                @endif
                <a href="/musedock/news-aggregator" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Ver todos
                </a>
            @endif
        </form>
    </div>
</div>
@endif
