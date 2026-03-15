@extends('layouts.app')

@section('title', 'Explorar Posts - Cross-Publisher')

@section('content')
<div class="container-fluid p-4">
    <div class="mb-4">
        <h1 class="h3 mb-1"><i class="bi bi-newspaper me-2"></i>Explorar Posts</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/musedock/cross-publisher">Cross-Publisher</a></li>
                <li class="breadcrumb-item active">Explorar Posts</li>
            </ol>
        </nav>
    </div>

    @include('partials.alerts-sweetalert2')

    {{-- Filtros --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Grupo Editorial</label>
                    <select class="form-select" id="filter-group">
                        <option value="">Todos los grupos</option>
                        @foreach($groups as $g)
                        <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->member_count }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Tenant</label>
                    <select class="form-select" id="filter-tenant">
                        <option value="">Todos los tenants</option>
                        @foreach($tenants as $t)
                        <option value="{{ $t->id }}" data-group="{{ $t->group_id }}">{{ $t->name }} ({{ $t->domain }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Estado</label>
                    <select class="form-select" id="filter-status">
                        <option value="published">Publicados</option>
                        <option value="draft">Borradores</option>
                        <option value="all">Todos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Buscar</label>
                    <input type="text" class="form-control" id="filter-search" placeholder="Título o slug...">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" onclick="loadPosts(1)"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </div>
    </div>

    {{-- Resultados --}}
    <div class="card">
        <div class="card-body p-0">
            <div id="posts-loading" class="text-center py-5" style="display:none;">
                <div class="spinner-border text-primary"></div>
                <p class="text-muted mt-2">Cargando posts...</p>
            </div>
            <div id="posts-empty" class="text-center py-5">
                <i class="bi bi-newspaper text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">Selecciona un grupo o tenant y busca posts.</p>
            </div>
            <div id="posts-table" style="display:none;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="50"></th>
                                <th>Título</th>
                                <th>Tenant</th>
                                <th>Grupo</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th width="100">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="posts-tbody"></tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="posts-info"></small>
                    <div id="posts-pagination"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- SweetAlert2 se usa para el modal de publicación --}}

@push('scripts')
<script>
const allTenants = @json($tenants);
const autoTranslate = @json($autoTranslate);
const langNames = {es:'Español',en:'English',ca:'Català',fr:'Français',de:'Deutsch',it:'Italiano',pt:'Português',nl:'Nederlands',pl:'Polski',ru:'Русский',ja:'日本語',zh:'中文'};
let currentPage = 1;
let currentSourceLang = 'es';

// Filter group → tenant dropdown
document.getElementById('filter-group').addEventListener('change', function() {
    const groupId = this.value;
    const tenantSelect = document.getElementById('filter-tenant');
    tenantSelect.innerHTML = '<option value="">Todos los tenants</option>';
    allTenants.forEach(t => {
        if (!groupId || t.group_id == groupId) {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name + ' (' + t.domain + ')';
            opt.dataset.group = t.group_id || '';
            tenantSelect.appendChild(opt);
        }
    });
});

function loadPosts(page) {
    currentPage = page || 1;
    const params = new URLSearchParams({
        tenant_id: document.getElementById('filter-tenant').value,
        group_id: document.getElementById('filter-group').value,
        status: document.getElementById('filter-status').value,
        search: document.getElementById('filter-search').value,
        page: currentPage
    });

    document.getElementById('posts-loading').style.display = 'block';
    document.getElementById('posts-empty').style.display = 'none';
    document.getElementById('posts-table').style.display = 'none';

    fetch('/musedock/cross-publisher/posts/fetch?' + params.toString())
        .then(r => r.json())
        .then(data => {
            document.getElementById('posts-loading').style.display = 'none';
            if (data.posts.length === 0) {
                document.getElementById('posts-empty').style.display = 'block';
                document.getElementById('posts-empty').querySelector('p').textContent = 'No se encontraron posts.';
                return;
            }
            document.getElementById('posts-table').style.display = 'block';
            renderPosts(data);
        })
        .catch(err => {
            document.getElementById('posts-loading').style.display = 'none';
            document.getElementById('posts-empty').style.display = 'block';
            console.error(err);
        });
}

function renderPosts(data) {
    const tbody = document.getElementById('posts-tbody');
    tbody.innerHTML = '';
    data.posts.forEach(post => {
        const statusBadge = post.status === 'published'
            ? '<span class="badge bg-success">Publicado</span>'
            : '<span class="badge bg-secondary">Borrador</span>';
        const date = post.published_at ? new Date(post.published_at).toLocaleDateString('es') : '-';
        let img;
        if (post.featured_image) {
            const imgUrl = post.featured_image.startsWith('http') ? post.featured_image : 'https://' + post.tenant_domain + '/' + post.featured_image;
            img = '<img src="' + imgUrl + '" class="rounded" width="40" height="40" style="object-fit:cover;" onerror="this.style.display=\'none\'">';
        } else {
            img = '<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="bi bi-image text-muted"></i></div>';
        }

        const postData = JSON.stringify({id: post.id, tenant_id: post.tenant_id, title: post.title, tenant_name: post.tenant_name, tenant_domain: post.tenant_domain, source_lang: post.source_lang || 'es'}).replace(/'/g, '&#39;').replace(/"/g, '&quot;');

        tbody.innerHTML += '<tr>' +
            '<td>' + img + '</td>' +
            '<td><strong>' + escapeHtml(post.title) + '</strong><br><small class="text-muted">' + escapeHtml(post.slug) + '</small></td>' +
            '<td><small>' + escapeHtml(post.tenant_name) + '<br><span class="text-muted">' + escapeHtml(post.tenant_domain) + '</span></small></td>' +
            '<td>' + (post.group_name ? '<span class="badge bg-info">' + escapeHtml(post.group_name) + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
            '<td>' + statusBadge + '</td>' +
            '<td><small>' + date + '</small></td>' +
            '<td><button class="btn btn-sm btn-primary" data-post="' + postData + '" onclick="openPublishModal(this)"><i class="bi bi-share"></i></button></td>' +
            '</tr>';
    });

    document.getElementById('posts-info').textContent = 'Mostrando ' + data.posts.length + ' de ' + data.total + ' posts (pág. ' + data.page + '/' + data.totalPages + ')';

    const pagDiv = document.getElementById('posts-pagination');
    pagDiv.innerHTML = '';
    if (data.totalPages > 1) {
        let html = '<nav><ul class="pagination pagination-sm mb-0">';
        for (let i = 1; i <= Math.min(data.totalPages, 10); i++) {
            html += '<li class="page-item ' + (i === data.page ? 'active' : '') + '"><a class="page-link" href="#" onclick="loadPosts(' + i + ');return false;">' + i + '</a></li>';
        }
        html += '</ul></nav>';
        pagDiv.innerHTML = html;
    }
}

function openPublishModal(btn) {
    const post = JSON.parse(btn.dataset.post);
    currentSourceLang = post.source_lang || 'es';

    // Build target list HTML
    let targetsHtml = '';
    allTenants.forEach(t => {
        if (t.id == post.tenant_id) return;
        const targetLang = t.default_lang || 'es';
        const needsTranslation = targetLang !== currentSourceLang;
        const shouldTranslate = autoTranslate && needsTranslation;
        const langLabel = langNames[targetLang] || targetLang;

        targetsHtml +=
            '<div class="form-check mb-2 border-bottom pb-2">' +
                '<input class="form-check-input target-check" type="checkbox" value="' + t.id + '" id="swal_target_' + t.id + '" data-lang="' + escapeHtml(targetLang) + '">' +
                '<label class="form-check-label d-flex justify-content-between align-items-center w-100" for="swal_target_' + t.id + '">' +
                    '<span><strong>' + escapeHtml(t.name) + '</strong> <small class="text-muted">(' + escapeHtml(t.domain) + ')</small></span>' +
                    '<span class="badge bg-' + (needsTranslation ? 'warning text-dark' : 'light text-dark border') + ' ms-2">' + langLabel + '</span>' +
                '</label>' +
                (needsTranslation ? '<div class="ms-4 mt-1"><div class="form-check form-switch"><input class="form-check-input translate-check" type="checkbox" id="swal_translate_' + t.id + '" data-tid="' + t.id + '" data-target-lang="' + escapeHtml(targetLang) + '"' + (shouldTranslate ? ' checked' : '') + '><label class="form-check-label small" for="swal_translate_' + t.id + '">Traducir a ' + escapeHtml(langLabel) + '</label></div></div>' : '') +
            '</div>';
    });

    const swalHtml =
        '<div class="text-start">' +
            '<div class="alert alert-info">' +
                '<strong>' + escapeHtml(post.title) + '</strong><br>' +
                '<small class="text-muted">' + escapeHtml(post.tenant_name) + ' (' + escapeHtml(post.tenant_domain) + ') — ' + (langNames[currentSourceLang] || currentSourceLang).toUpperCase() + '</small>' +
            '</div>' +
            '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '<h6 class="mb-0">Seleccionar destinos:</h6>' +
                '<div>' +
                    '<button type="button" class="btn btn-outline-primary btn-sm me-1" id="swal-select-all"><i class="bi bi-check-all me-1"></i>Todos</button>' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm" id="swal-deselect-all"><i class="bi bi-x-lg me-1"></i>Ninguno</button>' +
                '</div>' +
            '</div>' +
            '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: .375rem; padding: .5rem;" class="mb-3">' + targetsHtml + '</div>' +
            '<div class="row"><div class="col-md-6">' +
                '<label class="form-label small">Estado en destino</label>' +
                '<select class="form-select form-select-sm" id="swal-target-status">' +
                    '<option value="draft">Borrador</option>' +
                    '<option value="published">Publicado</option>' +
                '</select>' +
            '</div></div>' +
        '</div>';

    Swal.fire({
        title: '<i class="bi bi-share me-2"></i>Publicar en otros tenants',
        html: swalHtml,
        width: '700px',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        confirmButtonText: '<i class="bi bi-plus-circle me-1"></i> Añadir a Cola',
        confirmButtonColor: '#0d6efd',
        didOpen: () => {
            const popup = Swal.getPopup();
            popup.querySelector('#swal-select-all').addEventListener('click', () => {
                popup.querySelectorAll('.target-check').forEach(cb => cb.checked = true);
            });
            popup.querySelector('#swal-deselect-all').addEventListener('click', () => {
                popup.querySelectorAll('.target-check').forEach(cb => cb.checked = false);
            });
        },
        preConfirm: () => {
            const popup = Swal.getPopup();
            const checked = popup.querySelectorAll('.target-check:checked');
            if (checked.length === 0) {
                Swal.showValidationMessage('Selecciona al menos un tenant destino.');
                return false;
            }

            const targetStatus = popup.querySelector('#swal-target-status').value;
            const targets = {};

            checked.forEach(cb => {
                const tid = cb.value;
                targets[tid] = { tenant_id: tid, target_status: targetStatus };

                const parent = cb.closest('.form-check');
                const translateCb = parent.querySelector('.translate-check');
                if (translateCb && translateCb.checked) {
                    targets[tid].translate = '1';
                    targets[tid].source_language = currentSourceLang;
                    targets[tid].target_language = translateCb.dataset.targetLang;
                }
            });

            return { targets: targets, targetStatus: targetStatus };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Build and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/musedock/cross-publisher/posts/queue';
            form.style.display = 'none';

            // CSRF
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            // Source post
            const postIdInput = document.createElement('input');
            postIdInput.type = 'hidden';
            postIdInput.name = 'source_post_id';
            postIdInput.value = post.id;
            form.appendChild(postIdInput);

            // Source tenant
            const tenantIdInput = document.createElement('input');
            tenantIdInput.type = 'hidden';
            tenantIdInput.name = 'source_tenant_id';
            tenantIdInput.value = post.tenant_id;
            form.appendChild(tenantIdInput);

            // Targets
            const targets = result.value.targets;
            Object.keys(targets).forEach(tid => {
                Object.keys(targets[tid]).forEach(key => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'targets[' + tid + '][' + key + ']';
                    input.value = targets[tid][key];
                    form.appendChild(input);
                });
            });

            document.body.appendChild(form);
            form.submit();
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-load on enter in search
document.getElementById('filter-search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') loadPosts(1);
});
</script>
@endpush
@endsection
