@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-1">{{ __('languages.editor.title') }}</h2>
        <p class="text-muted mb-0">{{ __('languages.editor.subtitle') }}</p>
      </div>
      <a href="{{ route('languages.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> {{ __('languages.editor.back_languages') }}
      </a>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <form method="GET" action="{{ route('languages.translations') }}" class="row g-3 align-items-end">
          <div class="col-md-2">
            <label for="context" class="form-label">{{ __('languages.editor.context') }}</label>
            <select id="context" name="context" class="form-select">
              <option value="superadmin" {{ $context === 'superadmin' ? 'selected' : '' }}>{{ __('languages.editor.context_superadmin') }}</option>
              <option value="tenant" {{ $context === 'tenant' ? 'selected' : '' }}>{{ __('languages.editor.context_tenant') }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="tenant_id" class="form-label">{{ __('languages.editor.scope') }}</label>
            <select id="tenant_id" name="tenant_id" class="form-select">
              <option value="0" {{ (int)$selectedTenantId === 0 ? 'selected' : '' }}>{{ __('languages.editor.scope_global') }}</option>
              @foreach($availableTenants as $tenant)
                <option value="{{ $tenant['id'] }}" {{ (int)$selectedTenantId === (int)$tenant['id'] ? 'selected' : '' }}>
                  {{ $tenant['name'] }}{{ !empty($tenant['domain']) ? ' (' . $tenant['domain'] . ')' : '' }}
                </option>
              @endforeach
            </select>
            @if($context !== 'tenant')
              <small class="text-muted">{{ __('languages.editor.tenant_scope_hint') }}</small>
            @endif
          </div>
          <div class="col-md-2">
            <label for="locale" class="form-label">{{ __('languages.editor.locale') }}</label>
            <select id="locale" name="locale" class="form-select">
              @foreach($availableLocales as $code => $name)
                <option value="{{ $code }}" {{ $locale === $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label for="q" class="form-label">{{ __('common.search') }}</label>
            <input type="text" id="q" name="q" class="form-control" value="{{ $search }}" placeholder="{{ __('languages.editor.search_placeholder') }}">
          </div>
          <div class="col-md-2 d-grid gap-2">
            <button class="btn btn-primary" type="submit">{{ __('languages.editor.apply_filters') }}</button>
            <a href="{{ route('languages.translations') }}?context={{ $context }}&locale={{ $locale }}&tenant_id={{ (int)$selectedTenantId }}" class="btn btn-outline-secondary">{{ __('languages.editor.clear') }}</a>
          </div>
        </form>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-muted d-block">{{ __('languages.editor.stats_total_keys') }}</small>
            <h4 class="mb-0">{{ $totalKeys }}</h4>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-muted d-block">{{ __('languages.editor.stats_overridden') }}</small>
            <h4 class="mb-0">{{ $overriddenCount }}</h4>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-muted d-block">{{ __('languages.editor.stats_filtered') }}</small>
            <h4 class="mb-0">{{ $filteredCount }}</h4>
          </div>
        </div>
      </div>
    </div>

    <div class="alert alert-info">
      <h6 class="mb-2"><i class="bi bi-info-circle me-1"></i>{{ __('languages.editor.how_it_works_title') }}</h6>
      <ul class="mb-0 ps-3">
        <li>{{ __('languages.editor.how_it_works_1') }}</li>
        <li>{{ __('languages.editor.how_it_works_2') }}</li>
        <li>{{ __('languages.editor.how_it_works_3') }}</li>
        <li>{{ __('languages.editor.how_it_works_4') }}</li>
      </ul>
    </div>

    @if(!$baseFileExists)
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ __('languages.editor.base_missing') }}
      </div>
    @endif

    @if(!empty($overridesError))
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ $overridesError }}
      </div>
    @endif

    @php
      $activeTenant = ((int) $selectedTenantId > 0 && isset($availableTenants[(int) $selectedTenantId]))
        ? $availableTenants[(int) $selectedTenantId]
        : null;
    @endphp
    <div class="d-flex align-items-center mb-2">
      <small class="text-muted me-2">{{ __('languages.editor.active_scope') }}:</small>
      @if($activeTenant)
        <span class="badge bg-primary">
          {{ __('languages.editor.scope_label_tenant') }}: {{ $activeTenant['name'] }}{{ !empty($activeTenant['domain']) ? ' (' . $activeTenant['domain'] . ')' : '' }}
        </span>
      @else
        <span class="badge bg-secondary">{{ __('languages.editor.scope_label_global') }}</span>
      @endif
    </div>

    <div class="card">
      <div class="card-body table-responsive p-0">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 23%;">{{ __('languages.editor.key') }}</th>
              <th style="width: 27%;">{{ __('languages.editor.base_value') }}</th>
              <th style="width: 34%;">{{ __('languages.editor.override_value') }}</th>
              <th style="width: 16%;">{{ __('languages.editor.status') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $item)
              <tr>
                <td>
                  <code>{{ $item['key'] }}</code>
                </td>
                <td>
                  @if($item['base_value'] !== null && $item['base_value'] !== '')
                    <div class="small text-break">{{ $item['base_value'] }}</div>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  <form method="POST" action="{{ route('languages.translations.save') }}">
                    {!! csrf_field() !!}
                    <input type="hidden" name="context" value="{{ $context }}">
                    <input type="hidden" name="locale" value="{{ $locale }}">
                    <input type="hidden" name="tenant_id" value="{{ (int)$selectedTenantId }}">
                    <input type="hidden" name="translation_key" value="{{ $item['key'] }}">
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    <textarea name="translation_value" class="form-control form-control-sm mb-2" rows="2" placeholder="{{ __('languages.editor.override_value') }}">{{ $item['override_value'] }}</textarea>
                    <button type="submit" class="btn btn-sm btn-primary">
                      <i class="bi bi-save me-1"></i>{{ __('languages.editor.save') }}
                    </button>
                  </form>
                </td>
                <td>
                  @if($item['is_overridden'])
                    <span class="badge bg-primary mb-2">{{ __('languages.editor.status_override') }}</span>
                    <form method="POST" action="{{ route('languages.translations.reset') }}">
                      {!! csrf_field() !!}
                      <input type="hidden" name="context" value="{{ $context }}">
                      <input type="hidden" name="locale" value="{{ $locale }}">
                      <input type="hidden" name="tenant_id" value="{{ (int)$selectedTenantId }}">
                      <input type="hidden" name="translation_key" value="{{ $item['key'] }}">
                      <input type="hidden" name="return_to" value="{{ $returnTo }}">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('languages.editor.reset') }}
                      </button>
                    </form>
                  @else
                    <span class="badge bg-secondary">{{ __('languages.editor.status_base') }}</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-4">
                  {{ __('languages.editor.no_rows') }}
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(($pagination['total_pages'] ?? 1) > 1)
      @php
        $query = ['context' => $context, 'locale' => $locale, 'tenant_id' => (int)$selectedTenantId];
        if (!empty($search)) {
            $query['q'] = $search;
        }
        $prevPage = max(1, (int) $pagination['page'] - 1);
        $nextPage = min((int) $pagination['total_pages'], (int) $pagination['page'] + 1);
        $prevUrl = route('languages.translations') . '?' . http_build_query(array_merge($query, ['page' => $prevPage]));
        $nextUrl = route('languages.translations') . '?' . http_build_query(array_merge($query, ['page' => $nextPage]));
      @endphp
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">
          Página {{ $pagination['page'] }} de {{ $pagination['total_pages'] }} · {{ $pagination['total'] }} resultados
        </div>
        <div class="btn-group">
          <a href="{{ $prevUrl }}" class="btn btn-outline-secondary btn-sm {{ $pagination['page'] <= 1 ? 'disabled' : '' }}">{{ __('common.previous') }}</a>
          <a href="{{ $nextUrl }}" class="btn btn-outline-secondary btn-sm {{ $pagination['page'] >= $pagination['total_pages'] ? 'disabled' : '' }}">{{ __('common.next') }}</a>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection
