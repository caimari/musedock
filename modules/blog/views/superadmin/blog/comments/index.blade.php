@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    @php
      $actionScopeQuery = '';
      if (!empty($currentScope) && $currentScope !== 'mine') {
        $actionScopeQuery = '?scope=' . urlencode($currentScope);
      }
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <div class="d-flex gap-2">
        <a href="/musedock/blog/posts" class="btn btn-outline-secondary">Volver a posts</a>
      </div>
    </div>

    @if (!empty($crossPublisherActive))
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="/musedock/blog/comments" class="d-flex align-items-center gap-3 flex-wrap">
          @if (!empty($search))<input type="hidden" name="search" value="{{ $search }}">@endif
          @if (!empty($status))<input type="hidden" name="status" value="{{ $status }}">@endif
          @if (!empty($_GET['perPage']))<input type="hidden" name="perPage" value="{{ (int)$_GET['perPage'] }}">@endif

          <label class="form-label mb-0 fw-bold text-nowrap"><i class="bi bi-funnel me-1"></i> Alcance:</label>
          <select name="scope" class="form-select form-select-sm" style="width: auto; min-width: 280px;" onchange="this.form.submit()">
            <option value="mine" @if(($currentScope ?? 'mine') === 'mine') selected @endif>Mis comentarios (Superadmin)</option>
            @foreach ($groups as $group)
              <optgroup label="{{ $group->name }} ({{ $group->member_count }} sitios)">
                <option value="group:{{ $group->id }}" @if(($currentScope ?? '') === "group:{$group->id}") selected @endif>
                  Todo el grupo: {{ $group->name }}
                </option>
                @foreach ($groupedTenants as $tenant)
                  @if ($tenant->group_id == $group->id)
                    <option value="tenant:{{ $tenant->id }}" @if(($currentScope ?? '') === "tenant:{$tenant->id}") selected @endif>
                      {{ $tenant->domain }}
                    </option>
                  @endif
                @endforeach
              </optgroup>
            @endforeach
          </select>

          @if (($currentScope ?? 'mine') !== 'mine')
            <span class="badge bg-info text-dark">{{ $scope['label'] ?? '' }}</span>
            <a href="/musedock/blog/comments" class="btn btn-sm btn-outline-secondary">Limpiar</a>
          @endif
        </form>
      </div>
    </div>
    @endif

    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
      <form method="GET" action="/musedock/blog/comments" class="d-flex align-items-center gap-2 flex-wrap">
        @if (!empty($currentScope) && $currentScope !== 'mine')
          <input type="hidden" name="scope" value="{{ $currentScope }}">
        @endif

        <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm" style="width: 260px;" placeholder="Buscar por autor, email o contenido">

        <select name="status" class="form-select form-select-sm" style="width:auto;">
          <option value="pending" @selected(($status ?? 'pending') === 'pending')>Pendientes</option>
          <option value="approved" @selected(($status ?? '') === 'approved')>Aprobados</option>
          <option value="spam" @selected(($status ?? '') === 'spam')>Spam</option>
          <option value="rejected" @selected(($status ?? '') === 'rejected')>Rechazados</option>
          <option value="all" @selected(($status ?? '') === 'all')>Todos</option>
        </select>

        <select name="perPage" class="form-select form-select-sm" style="width:auto;">
          <option value="10" @selected((int)($_GET['perPage'] ?? 20) === 10)>10</option>
          <option value="20" @selected((int)($_GET['perPage'] ?? 20) === 20)>20</option>
          <option value="50" @selected((int)($_GET['perPage'] ?? 20) === 50)>50</option>
          <option value="100" @selected((int)($_GET['perPage'] ?? 20) === 100)>100</option>
        </select>

        <button type="submit" class="btn btn-outline-secondary btn-sm">Filtrar</button>

        @php
          $resetUrl = '/musedock/blog/comments';
          if (!empty($currentScope) && $currentScope !== 'mine') {
              $resetUrl .= '?scope=' . urlencode($currentScope);
          }
        @endphp
        <a href="{{ $resetUrl }}" class="btn btn-outline-danger btn-sm">Limpiar</a>
      </form>
    </div>

    <div class="card">
      <div class="card-body table-responsive p-0">
        @if (!empty($comments) && count($comments) > 0)
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="min-width: 180px;">Autor</th>
                <th>Comentario</th>
                <th style="min-width: 200px;">Post</th>
                <th style="width: 120px;">Estado</th>
                <th style="width: 150px;">RGPD</th>
                <th style="width: 150px;">Fecha</th>
                <th style="width: 220px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach($comments as $comment)
                @php
                  $status = $comment->status ?? 'pending';
                  $statusClass = match($status) {
                    'approved' => 'bg-success',
                    'spam' => 'bg-danger',
                    'rejected' => 'bg-secondary',
                    default => 'bg-warning text-dark'
                  };
                  $preview = trim((string)($comment->content ?? ''));
                  if (mb_strlen($preview) > 180) $preview = mb_substr($preview, 0, 180) . '...';
                @endphp
                <tr>
                  <td>
                    <strong>{{ e($comment->author_name) }}</strong><br>
                    <small class="text-muted">{{ e($comment->author_email) }}</small>
                    @if(!empty($comment->tenant_id) && !empty($tenantMap[$comment->tenant_id]))
                      <br><span class="badge bg-info text-dark mt-1">{{ $tenantMap[$comment->tenant_id]->domain }}</span>
                    @endif
                  </td>
                  <td>
                    <div style="max-width: 560px; white-space: pre-wrap;">{{ e($preview) }}</div>
                  </td>
                  <td>
                    <strong>{{ e($comment->post_title ?? 'Post') }}</strong>
                    @if(!empty($comment->post_slug))
                      <br><a href="{{ blog_url($comment->post_slug) }}" target="_blank" rel="noopener noreferrer" class="small">Ver post</a>
                    @endif
                  </td>
                  <td>
                    <span class="badge {{ $statusClass }}">{{ strtoupper($status) }}</span>
                  </td>
                  <td>
                    @if((int)($comment->legal_consent ?? 0) === 1)
                      <span class="badge bg-success">Aceptado</span>
                      <br><small class="text-muted">{{ !empty($comment->legal_consent_at) ? date('d/m/Y H:i', strtotime((string)$comment->legal_consent_at)) : '—' }}</small>
                    @else
                      <span class="badge bg-secondary">No</span>
                    @endif
                  </td>
                  <td>
                    <small>{{ !empty($comment->created_at) ? date('d/m/Y H:i', strtotime((string)$comment->created_at)) : '—' }}</small>
                  </td>
                  <td>
                    <div class="d-flex gap-1 flex-wrap">
                      @if(($comment->status ?? '') !== 'approved')
                      <form method="POST" action="/musedock/blog/comments/{{ $comment->id }}/approve{{ $actionScopeQuery }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                      </form>
                      @endif

                      @if(($comment->status ?? '') !== 'spam')
                      <form method="POST" action="/musedock/blog/comments/{{ $comment->id }}/spam{{ $actionScopeQuery }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning">Spam</button>
                      </form>
                      @endif

                      <form method="POST" action="/musedock/blog/comments/{{ $comment->id }}{{ $actionScopeQuery }}" class="d-inline" onsubmit="return confirm('¿Eliminar comentario?');">
                        @csrf
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <div class="p-4 text-muted">No hay comentarios para los filtros seleccionados.</div>
        @endif
      </div>
    </div>

    @if (!empty($pagination) && isset($pagination['last_page']) && $pagination['last_page'] > 1)
    <div class="d-flex justify-content-center mt-4">
      {!! pagination_links($pagination, http_build_query(request()->except('page')), 'sm') !!}
    </div>
    @endif

  </div>
</div>
@endsection
