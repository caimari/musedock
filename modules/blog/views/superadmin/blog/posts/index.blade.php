@extends('layouts.app')

@section('title', $title)

@section('styles')
<style>
  /* Estilos para la tabla ordenable */
  .sortable-link {
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }
  .sortable-link:hover {
    color: #0d6efd;
  }
  .sortable-link .sort-icon {
    opacity: 0.4;
  }
  .sortable-link.active .sort-icon {
    opacity: 1;
    color: #0d6efd;
  }

  /* Selector de registros por página */
  .posts-per-page-selector {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
  }
  .posts-per-page-selector label {
    margin-right: 0.5rem;
    margin-bottom: 0;
    font-size: 0.875rem;
  }
  .posts-per-page-selector select {
    width: auto;
  }
</style>
@endsection

@php
  // Función helper para generar URLs de ordenamiento
  $sortUrl = function($column) use ($orderBy, $order) {
    $params = $_GET;
    $params['orderby'] = $column;
    // Si ya estamos ordenando por esta columna, invertir el orden
    if ($orderBy === $column) {
      $params['order'] = ($order === 'ASC') ? 'DESC' : 'ASC';
    } else {
      // Por defecto ASC para título, DESC para fecha
      $params['order'] = ($column === 'published_at') ? 'DESC' : 'ASC';
    }
    // Resetear a página 1 al cambiar orden
    unset($params['page']);
    return '/musedock/blog/posts?' . http_build_query($params);
  };

  // Función helper para obtener el icono de ordenamiento
  $sortIcon = function($column) use ($orderBy, $order) {
    if ($orderBy !== $column) {
      return '<i class="fas fa-sort sort-icon"></i>';
    }
    return $order === 'ASC'
      ? '<i class="fas fa-sort-up sort-icon"></i>'
      : '<i class="fas fa-sort-down sort-icon"></i>';
  };

  // Función helper para determinar si la columna está activa
  $isActiveSort = function($column) use ($orderBy) {
    return $orderBy === $column ? 'active' : '';
  };
@endphp

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botones de Acción --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <div class="d-flex gap-2">
        <a href="/musedock/blog/comments" class="btn btn-outline-secondary" title="Moderar comentarios">
          <i class="bi bi-chat-left-text me-1"></i> Comentarios
        </a>
        <a href="/musedock/blog/posts/trash" class="btn btn-outline-danger" title="{{ __('blog.post.view_trash') }}">
          <i class="bi bi-trash me-1"></i> {{ __('blog.post.trash') }}
        </a>
        @if (!empty($crossPublisherActive) && !empty($currentScope) && str_starts_with($currentScope, 'tenant:'))
          <a href="/musedock/blog/posts/create?tenant_id={{ substr($currentScope, 7) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Crear post en {{ $scope['label'] ?? 'tenant' }}
          </a>
        @else
          <a href="{{ route('blog.posts.create') }}" class="btn btn-primary">{{ __('blog.post.add_post') }}</a>
        @endif
      </div>
    </div>

    {{-- Filtro Cross-Publisher (solo si el plugin esta activo) --}}
    @if (!empty($crossPublisherActive))
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="/musedock/blog/posts" class="d-flex align-items-center gap-3 flex-wrap">
          @if (!empty($search))<input type="hidden" name="search" value="{{ $search }}">@endif
          <label class="form-label mb-0 fw-bold text-nowrap"><i class="bi bi-funnel me-1"></i> Filtrar por:</label>
          <select name="scope" class="form-select form-select-sm" style="width: auto; min-width: 280px;" onchange="this.form.submit()">
            <option value="mine" @if(($currentScope ?? 'mine') === 'mine') selected @endif>Mis posts (Superadmin)</option>
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
            <a href="/musedock/blog/posts" class="btn btn-sm btn-outline-secondary">Limpiar filtro</a>
          @endif
        </form>
      </div>
    </div>
    @endif

    {{-- Alertas con SweetAlert2 --}}
    @if (session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: {!! json_encode(__('common.success')) !!},
            text: {!! json_encode(session('success')) !!},
            confirmButtonColor: '#3085d6'
          });
        });
      </script>
    @endif
    @if (session('error'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'error',
            title: {!! json_encode(__('common.error')) !!},
            text: {!! json_encode(session('error')) !!},
            confirmButtonColor: '#d33'
          });
        });
      </script>
    @endif

    {{-- Formulario de Búsqueda y Selector de registros por página --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <form method="GET" action="{{ route('blog.posts.index') }}" class="d-flex align-items-center">
        @if (!empty($currentScope) && $currentScope !== 'mine')
          <input type="hidden" name="scope" value="{{ $currentScope }}">
        @endif
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('blog.post.search_placeholder') }}" class="form-control form-control-sm me-2" style="width: 250px;" id="search-input">
        <button type="submit" class="btn btn-outline-secondary btn-sm me-2">{{ __('common.search') }}</button>
        @if (!empty($search))
          @php $clearUrl = '/musedock/blog/posts' . ((!empty($currentScope) && $currentScope !== 'mine') ? '?scope=' . urlencode($currentScope) : ''); @endphp
          <a href="{{ $clearUrl }}" class="btn btn-outline-danger btn-sm">{{ __('common.clear_filter') }}</a>
        @endif
      </form>

      <div class="posts-per-page-selector">
        <label for="perPage">{{ __('common.show') }}</label>
        <select class="form-select form-select-sm" id="perPage">
          <option value="10" {{ (isset($_GET['perPage']) && $_GET['perPage'] == 10) ? 'selected' : '' }}>10</option>
          <option value="25" {{ (isset($_GET['perPage']) && $_GET['perPage'] == 25) ? 'selected' : '' }}>25</option>
          <option value="50" {{ (isset($_GET['perPage']) && $_GET['perPage'] == 50) ? 'selected' : '' }}>50</option>
          <option value="100" {{ (isset($_GET['perPage']) && $_GET['perPage'] == 100) ? 'selected' : '' }}>100</option>
          <option value="-1" {{ (isset($_GET['perPage']) && $_GET['perPage'] == -1) ? 'selected' : '' }}>{{ __('common.all') }}</option>
        </select>
      </div>
    </div>

    {{-- Formulario Acciones en Lote --}}
    <form method="POST" action="{{ route('blog.posts.bulk') }}" id="bulkActionForm">
      @csrf
      <div class="card">
        <div class="card-body table-responsive p-0">
          @if (!empty($posts) && count($posts) > 0)
          <table class="table table-hover align-middle" id="postsTable">
            <thead>
              <tr>
                <th style="width: 1%;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                <th>
                  <a href="{{ $sortUrl('title') }}" class="sortable-link {{ $isActiveSort('title') }}">
                    {{ __('blog.post.title') }} {!! $sortIcon('title') !!}
                  </a>
                </th>
                @if (!empty($scope) && ($scope['mode'] ?? 'mine') !== 'mine')
                <th>Sitio</th>
                @endif
                <th>{{ __('blog.post.author') }}</th>
                <th>{{ __('blog.post.categories') }}</th>
                <th>
                  <a href="{{ $sortUrl('status') }}" class="sortable-link {{ $isActiveSort('status') }}">
                    {{ __('blog.post.status') }} {!! $sortIcon('status') !!}
                  </a>
                </th>
                <th>{{ __('blog.post.language') }}</th>
                <th>
                  <a href="{{ $sortUrl('view_count') }}" class="sortable-link {{ $isActiveSort('view_count') }}">
                    {{ __('blog.views') }} {!! $sortIcon('view_count') !!}
                  </a>
                </th>
                <th>
                  <a href="{{ $sortUrl('published_at') }}" class="sortable-link {{ $isActiveSort('published_at') }}">
                    {{ __('blog.post.published_at') }} {!! $sortIcon('published_at') !!}
                  </a>
                </th>
              </tr>
            </thead>
            <tbody>
            {{-- Bucle para mostrar los posts --}}
            @foreach ($posts as $post)
              @php
                // Obtenemos datos asociados
                $author = $authors[$post->user_id] ?? null;
                $baseLang = $post->base_locale ?? setting('language', 'es');
                $status = $post->status === 'published' ? __('blog.post.published') : __('blog.post.draft');

                // Formato de fecha desde settings
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $dateTimeFormat = $dateFormat . ' ' . $timeFormat;

                // Obtener categorías del post (usando PHP nativo, no Laravel Collection)
                $postCategories = $post->categories ?? [];
                // Extraer nombres de las primeras 2 categorías
                $categoryNamesArray = array_slice(array_map(function($cat) {
                    return is_object($cat) ? $cat->name : ($cat['name'] ?? '');
                }, $postCategories), 0, 2);
                $categoryNames = implode(', ', $categoryNamesArray);
                if (count($postCategories) > 2) {
                  $categoryNames .= ' +' . (count($postCategories) - 2);
                }
              @endphp
              <tr data-id="{{ $post->id }}">
                <td><input type="checkbox" name="selected[]" value="{{ $post->id }}" class="form-check-input select-item"></td>
                <td>
                  <strong>{{ e($post->title) }}</strong>
                  @if($post->featured)
                    <span class="badge rounded-pill bg-warning text-dark ms-1" style="font-size: 0.7em; vertical-align: middle;">{{ __('blog.post.featured') }}</span>
                  @endif
                  @if(!$post->allow_comments)
                    <span class="badge rounded-pill bg-secondary ms-1" style="font-size: 0.7em; vertical-align: middle;"><i class="bi bi-chat-slash"></i></span>
                  @endif
                  @if(!empty($post->instagram_posted_at))
                    @php
                      $igTitle = 'Publicado en Instagram el ' . date('d/m/Y H:i', strtotime($post->instagram_posted_at));
                    @endphp
                    @if(!empty($post->instagram_permalink))
                      <a href="{{ $post->instagram_permalink }}" target="_blank" rel="noopener noreferrer" class="badge rounded-pill ms-1 text-decoration-none" style="background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); color: #fff; font-size: 0.7em; vertical-align: middle;" title="{{ $igTitle }}">
                        <i class="bi bi-instagram"></i> IG
                      </a>
                    @else
                      <span class="badge rounded-pill ms-1" style="background: linear-gradient(45deg, #f09433 0%, #dc2743 50%, #bc1888 100%); color: #fff; font-size: 0.7em; vertical-align: middle;" title="{{ $igTitle }}">
                        <i class="bi bi-instagram"></i> IG
                      </span>
                    @endif
                  @endif
                  @if(!empty($post->facebook_posted_at))
                    @php $fbTitle = 'Publicado en Facebook el ' . date('d/m/Y H:i', strtotime($post->facebook_posted_at)); @endphp
                    @if(!empty($post->facebook_permalink))
                      <a href="{{ $post->facebook_permalink }}" target="_blank" rel="noopener noreferrer" class="badge rounded-pill ms-1 text-decoration-none" style="background:#1877f2;color:#fff;font-size:0.7em;vertical-align:middle;" title="{{ $fbTitle }}">
                        <i class="bi bi-facebook"></i> FB
                      </a>
                    @else
                      <span class="badge rounded-pill ms-1" style="background:#1877f2;color:#fff;font-size:0.7em;vertical-align:middle;" title="{{ $fbTitle }}">
                        <i class="bi bi-facebook"></i> FB
                      </span>
                    @endif
                  @endif
                  <br>
                  <small>
                    @php
                      $editScope = (!empty($scope) && ($scope['mode'] ?? 'mine') !== 'mine') ? '?scope=' . urlencode($currentScope ?? '') : '';
                      // Check if this post's tenant has active Instagram
                      $igAvailableForPost = !empty($post->tenant_id)
                          && in_array((int)$post->tenant_id, $tenantsWithInstagram ?? [], true)
                          && !empty($post->featured_image)
                          && $post->status === 'published';
                    @endphp
                    <a href="{{ route('blog.posts.edit', ['id' => $post->id]) }}{{ $editScope }}">{{ __('common.edit') }}</a>
                    @if ($post->status === 'published')
                       |
                      @if ($post->tenant_id && !empty($tenantMap[$post->tenant_id]))
                        <a href="https://{{ $tenantMap[$post->tenant_id]->domain }}/{{ $post->slug }}" target="_blank" rel="noopener noreferrer">{{ __('blog.post.view_post') }}</a>
                      @else
                        <a href="/blog/{{ $post->slug }}" target="_blank" rel="noopener noreferrer">{{ __('blog.post.view_post') }}</a>
                      @endif
                    @endif
                    @if($igAvailableForPost)
                       |
                      <a href="#" class="share-instagram-link-sa" data-post-id="{{ $post->id }}" data-tenant-id="{{ $post->tenant_id }}" style="color:#dc2743;text-decoration:none;" title="Compartir en redes (Instagram / Facebook)">
                        <i class="bi bi-megaphone"></i> {{ (!empty($post->instagram_posted_at) || !empty($post->facebook_posted_at)) ? 'Re-publicar' : 'Compartir' }}
                      </a>
                    @endif
                     |
                    <a href="#" class="delete-post-link" data-post-id="{{ $post->id }}" data-post-title="{{ htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8') }}" style="color: #dc3545; text-decoration: none;">{{ __('common.delete') }}</a>
                  </small>
                </td>
                @if (!empty($scope) && ($scope['mode'] ?? 'mine') !== 'mine')
                <td>
                  @php $tenantInfo = $tenantMap[$post->tenant_id] ?? null; @endphp
                  <small class="text-muted">{{ $tenantInfo->domain ?? '—' }}</small>
                </td>
                @endif
                <td>{{ $author?->name ?? '—' }}</td>
                <td><small class="text-muted">{{ $categoryNames ?: '—' }}</small></td>
                <td>
                  {{-- Estado --}}
                  <span class="badge {{ $post->status === 'published' ? 'bg-success' : 'bg-secondary' }}">
                    {{ $status }}
                  </span>

                  {{-- Badge Visibilidad --}}
                  @if($post->visibility === 'private')
                    <span class="badge bg-danger ms-1">{{ __('blog.post.private') }}</span>
                  @endif

                  @if($post->visibility === 'password')
                    <span class="badge bg-warning text-dark ms-1">{{ __('blog.post.protected') }}</span>
                  @endif
                </td>
                <td>{{ strtoupper($baseLang) }}</td>
                <td class="text-center">
                  <span class="badge bg-light text-dark"><i class="bi bi-eye"></i> {{ number_format($post->view_count ?? 0) }}</span>
                </td>
                <td data-date="{{ $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : ($post->created_at ? $post->created_at->format('Y-m-d H:i:s') : '') }}">
                  {{ $post->published_at ? $post->published_at->format($dateTimeFormat) : ($post->created_at ? $post->created_at->format($dateTimeFormat) : '—') }}
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
          @else
            <div class="p-3 text-center">
              <p class="text-muted">{{ __('blog.post.no_posts_found') }}</p>
               @if(empty($search))
                 <a href="{{ route('blog.posts.create') }}" class="btn btn-sm btn-primary">{{ __('blog.post.create_first_post') }}</a>
               @endif
            </div>
          @endif
        </div>
      </div>

      {{-- Acciones en lote y Paginación --}}
      <div class="d-flex justify-content-between align-items-center mt-3">
        @if (!empty($posts) && count($posts) > 0)
        <div class="d-flex">
          <select name="action" class="form-select form-select-sm me-2" id="bulkActionSelect" style="width: auto;" required>
            <option value="">{{ __('blog.bulk.actions') }}</option>
            <option value="edit">{{ __('blog.bulk.edit') }}</option>
            <option value="delete">{{ __('blog.bulk.delete') }}</option>
            <option value="published">{{ __('blog.bulk.publish_selected') }}</option>
            <option value="draft">{{ __('blog.bulk.set_as_draft') }}</option>
            <option value="public">{{ __('blog.bulk.make_public') }}</option>
            <option value="private">{{ __('blog.bulk.make_private') }}</option>
            <option value="featured">{{ __('blog.bulk.mark_as_featured') }}</option>
            <option value="unfeatured">{{ __('blog.bulk.remove_featured') }}</option>
          </select>
          <button class="btn btn-secondary btn-sm" type="submit" id="applyBulkAction" disabled>{{ __('common.apply') }}</button>
        </div>
        @else
         <div></div>
        @endif
        @if (!empty($pagination) && isset($pagination['last_page']) && $pagination['last_page'] > 1)
            {{-- Paginación --}}
           {!! pagination_links($pagination, http_build_query(request()->except('page')), 'sm') !!}
        @endif
      </div>
    </form>

    {{-- Formularios de eliminación --}}
    @if (!empty($posts) && count($posts) > 0)
      @foreach ($posts as $post)
        <form method="POST" action="/musedock/blog/posts/{{ $post->id }}" style="display: none;" id="delete-form-{{ $post->id }}">
          {!! csrf_field() !!}
          @method('DELETE')
        </form>
      @endforeach
    @endif

  </div> {{-- fin container-fluid --}}
</div> {{-- fin app-content --}}
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Referencia al checkbox "seleccionar todo"
  const selectAllCheckbox = document.getElementById('selectAll');

  // Referencias a todos los checkboxes individuales
  const checkboxes = document.querySelectorAll('.select-item');

  // Evento para seleccionar/deseleccionar todos
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
      checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
      });

      // Actualizar estado del botón
      updateButtonState();
    });
  }

  // Evento para cada checkbox individual
  checkboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      // Verificar si todos están seleccionados
      const allChecked = Array.from(checkboxes).every(cb => cb.checked);
      const anyChecked = Array.from(checkboxes).some(cb => cb.checked);

      // Actualizar checkbox "seleccionar todo"
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = anyChecked && !allChecked;
      }

      // Actualizar estado del botón
      updateButtonState();
    });
  });

  // Referencia al formulario y botón de acción
  const bulkForm = document.getElementById('bulkActionForm');
  const actionSelect = document.getElementById('bulkActionSelect');
  const actionButton = document.getElementById('applyBulkAction');

  // Función para actualizar estado del botón
  function updateButtonState() {
    const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
    if (actionButton) {
      actionButton.disabled = !anyChecked;
    }
  }

  // Inicializar estado del botón
  updateButtonState();

  // Manejar envío del formulario
  if (bulkForm) {
    bulkForm.addEventListener('submit', function(e) {
      e.preventDefault();

      // Verificar que haya elementos seleccionados
      const selectedCount = document.querySelectorAll('.select-item:checked').length;
      if (selectedCount === 0) {
        Swal.fire({
          icon: 'warning',
          title: {!! json_encode(__('blog.bulk.selection_required')) !!},
          text: {!! json_encode(__('blog.bulk.select_at_least_one_post')) !!}
        });
        return false;
      }

      // Verificar que haya una acción seleccionada
      const action = actionSelect.value;
      if (!action) {
        Swal.fire({
          icon: 'warning',
          title: {!! json_encode(__('blog.bulk.action_required')) !!},
          text: {!! json_encode(__('blog.bulk.select_action')) !!}
        });
        return false;
      }

      // Para acciones que requieren confirmación
      if (action === 'delete') {
        Swal.fire({
          title: {!! json_encode(__('common.are_you_sure')) !!},
          text: {!! json_encode(__('blog.bulk.confirm_delete_posts', ['count' => '${selectedCount}'])) !!}.replace(':count', selectedCount),
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: {!! json_encode(__('blog.bulk.confirm_delete_yes')) !!},
          cancelButtonText: {!! json_encode(__('blog.bulk.confirm_cancel')) !!}
        }).then((result) => {
          if (result.isConfirmed) {
            bulkForm.submit();
          }
        });
      } else if (action === 'published' || action === 'draft' ||
                action === 'public' || action === 'private' ||
                action === 'featured' || action === 'unfeatured') {
        // Acciones que cambian estados
        const actionLabels = {
          'published': {!! json_encode(__('blog.bulk.confirm_action_publish', ['count' => ':count'])) !!},
          'draft': {!! json_encode(__('blog.bulk.confirm_action_draft', ['count' => ':count'])) !!},
          'public': {!! json_encode(__('blog.bulk.confirm_action_public', ['count' => ':count'])) !!},
          'private': {!! json_encode(__('blog.bulk.confirm_action_private', ['count' => ':count'])) !!},
          'featured': {!! json_encode(__('blog.bulk.confirm_action_featured', ['count' => ':count'])) !!},
          'unfeatured': {!! json_encode(__('blog.bulk.confirm_action_unfeatured', ['count' => ':count'])) !!}
        };

        Swal.fire({
          title: {!! json_encode(__('blog.bulk.confirm_change')) !!},
          text: actionLabels[action].replace(':count', selectedCount),
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#6c757d',
          confirmButtonText: {!! json_encode(__('blog.bulk.confirm_yes_continue')) !!},
          cancelButtonText: {!! json_encode(__('blog.bulk.confirm_cancel')) !!}
        }).then((result) => {
          if (result.isConfirmed) {
            bulkForm.submit();
          }
        });
      } else {
        // Otras acciones que no necesitan confirmación
        bulkForm.submit();
      }
    });
  }

  // Event listener para eliminar posts (delegación de eventos)
  document.addEventListener('click', function(e) {
    const deleteLink = e.target.closest('.delete-post-link');
    if (!deleteLink) return;

    e.preventDefault();

    const postId = deleteLink.getAttribute('data-post-id');
    const postTitle = deleteLink.getAttribute('data-post-title');

    Swal.fire({
      title: {!! json_encode(__('common.are_you_sure')) !!},
      html: {!! json_encode(__('blog.delete.confirm_delete_post', ['title' => ':title'])) !!}.replace(':title', escapeHtml(postTitle)),
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: {!! json_encode(__('blog.bulk.confirm_delete_yes')) !!},
      cancelButtonText: {!! json_encode(__('blog.bulk.confirm_cancel')) !!}
    }).then((result) => {
      if (result.isConfirmed) {
        // Buscar y enviar el formulario oculto
        const formId = `delete-form-${postId}`;
        const form = document.getElementById(formId);

        if (!form) {
          Swal.fire({
            icon: 'error',
            title: {!! json_encode(__('common.error')) !!},
            text: {!! json_encode(__('blog.delete.form_not_found')) !!}
          });
          return;
        }

        form.submit();
      }
    });
  });

  // Función auxiliar para escapar HTML
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  // Selector de registros por página
  const perPageSelect = document.getElementById('perPage');
  if (perPageSelect) {
    perPageSelect.addEventListener('change', function() {
      const currentUrl = new URL(window.location.href);
      currentUrl.searchParams.set('perPage', this.value);
      // Resetear a página 1 al cambiar cantidad
      currentUrl.searchParams.delete('page');
      window.location.href = currentUrl.toString();
    });
  }
});
</script>

{{-- Instagram publish from superadmin --}}
@if(!empty($tenantsWithInstagram) && count($tenantsWithInstagram) > 0)
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';

    document.addEventListener('click', async function(e) {
        const link = e.target.closest('.share-instagram-link-sa');
        if (!link) return;
        e.preventDefault();
        await openShare(link.dataset.postId);
    });

    async function openShare(postId) {
        Swal.fire({
            title: 'Preparando publicación…',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        let preview;
        try {
            const res = await fetch(`/musedock/social-publisher/share/preview?post_id=${encodeURIComponent(postId)}`, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            preview = await res.json();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error de red', text: err.message });
            return;
        }

        if (!preview.ok) {
            Swal.fire({ icon: 'error', title: 'No se puede compartir', text: preview.message });
            return;
        }

        showShareForm(postId, preview);
    }

    function showShareForm(postId, preview, prefillCaption) {
        const accounts = preview.accounts || [];
        const accountOpts = accounts.map(a => {
            const fbTag = a.facebook_enabled ? ` — <i class="bi bi-facebook" style="color:#1877f2;"></i> ${escapeHtml(a.facebook_page_name || 'FB')}` : '';
            return `<option value="${a.id}" data-fb="${a.facebook_enabled ? '1' : '0'}" data-fb-name="${escapeAttr(a.facebook_page_name || '')}">@${escapeHtml(a.username)}${fbTag}</option>`;
        }).join('');
        const initialCaption = prefillCaption !== undefined ? prefillCaption : (preview.caption || '');
        const firstHasFb = accounts.length > 0 && accounts[0].facebook_enabled;

        Swal.fire({
            title: '<i class="bi bi-megaphone"></i> Compartir post',
            width: 720,
            html: `
                <div class="text-start">
                    <div class="mb-3 text-center">
                        <img src="${escapeAttr(preview.image_url)}" alt="" style="max-width:260px;max-height:260px;aspect-ratio:1/1;object-fit:cover;border-radius:8px;border:1px solid #dee2e6;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cuenta</label>
                        <select id="ig-account" class="form-select form-select-sm">${accountOpts}</select>
                    </div>
                    <div class="mb-3 d-flex gap-3 flex-wrap">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" id="swal-publish-ig" checked>
                            <i class="bi bi-instagram" style="color:#dc2743;"></i>
                            <strong>Instagram</strong>
                        </label>
                        <label id="swal-fb-label" style="display:${firstHasFb ? 'flex' : 'none'};align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" id="swal-publish-fb">
                            <i class="bi bi-facebook" style="color:#1877f2;"></i>
                            <strong>Facebook</strong>
                            <small class="text-muted" id="swal-fb-hint"></small>
                        </label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold d-flex justify-content-between">
                            <span>Caption</span>
                            <span class="text-muted" id="ig-caption-counter">0/2200</span>
                        </label>
                        <textarea id="ig-caption" class="form-control form-control-sm" rows="8" maxlength="2200">${escapeHtml(initialCaption)}</textarea>
                        <small class="text-muted">IG no permite enlaces clicables. En FB el link va aparte y genera preview.</small>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-send me-1"></i> Publicar ahora',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2743',
            focusConfirm: false,
            didOpen: () => {
                const ta = document.getElementById('ig-caption');
                const cc = document.getElementById('ig-caption-counter');
                const select = document.getElementById('ig-account');
                const fbLabel = document.getElementById('swal-fb-label');
                const fbCheck = document.getElementById('swal-publish-fb');
                const fbHint = document.getElementById('swal-fb-hint');

                const update = () => { cc.textContent = `${ta.value.length}/2200`; };
                ta.addEventListener('input', update);

                const refreshFb = () => {
                    const opt = select.options[select.selectedIndex];
                    const hasFb = opt && opt.dataset.fb === '1';
                    fbLabel.style.display = hasFb ? 'flex' : 'none';
                    if (!hasFb) { fbCheck.checked = false; fbHint.textContent = ''; }
                    else { fbHint.textContent = `(→ ${opt.dataset.fbName || ''})`; }
                };
                refreshFb();
                select.addEventListener('change', refreshFb);
                update();
            },
            preConfirm: async () => {
                const connectionId = document.getElementById('ig-account').value;
                const caption = document.getElementById('ig-caption').value;
                const doIG = document.getElementById('swal-publish-ig').checked;
                const doFB = document.getElementById('swal-publish-fb').checked;

                if (!connectionId) { Swal.showValidationMessage('Selecciona una cuenta'); return false; }
                if (!doIG && !doFB) { Swal.showValidationMessage('Marca al menos una red.'); return false; }

                try {
                    const res = await fetch('/musedock/social-publisher/share/publish', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            post_id: postId,
                            connection_id: connectionId,
                            caption: caption,
                            publish_instagram: doIG ? '1' : '0',
                            publish_facebook: doFB ? '1' : '0',
                            _csrf: csrfToken(),
                            _token: csrfToken(),
                        }),
                    });
                    const data = await res.json();
                    if (!data.ok && !data.partial) {
                        Swal.showValidationMessage(data.message || 'Error al publicar');
                        return false;
                    }
                    return data;
                } catch (err) {
                    Swal.showValidationMessage('Error de red: ' + err.message);
                    return false;
                }
            },
        }).then(result => {
            if (!result.isConfirmed || !result.value) return;
            const data = result.value;
            let linksHtml = '';
            if (data.instagram?.permalink) {
                linksHtml += `<div style="margin:6px 0;"><a href="${escapeAttr(data.instagram.permalink)}" target="_blank" rel="noopener" style="color:#dc2743;font-weight:600;"><i class="bi bi-instagram"></i> Ver en Instagram →</a></div>`;
            }
            if (data.facebook?.permalink) {
                linksHtml += `<div style="margin:6px 0;"><a href="${escapeAttr(data.facebook.permalink)}" target="_blank" rel="noopener" style="color:#1877f2;font-weight:600;"><i class="bi bi-facebook"></i> Ver en Facebook →</a></div>`;
            }
            if ((data.errors || []).length) {
                linksHtml += `<div class="alert alert-warning mt-3" style="text-align:left;font-size:0.85rem;"><strong>Parcial:</strong><br>${(data.errors || []).join('<br>')}</div>`;
            }
            Swal.fire({
                icon: data.partial ? 'warning' : 'success',
                title: data.partial ? 'Publicado con errores' : '¡Publicado!',
                html: linksHtml || data.message,
            }).then(() => { window.location.reload(); });
        });
    }

    function escapeHtml(s) {
        s = s == null ? '' : String(s);
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }
})();
</script>
@endif
@endpush
