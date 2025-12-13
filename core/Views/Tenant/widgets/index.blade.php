{{-- views/tenant/widgets/index.blade.php --}}
@php use Screenart\Musedock\Widgets\WidgetManager; @endphp
@extends('layouts.app')

@section('title', $title ?? 'Gestionar Widgets')

@push('styles')
<style>
/* Variables para personalización */
:root {
    --primary-color: #2271b1;
    --primary-hover: #135e96;
    --secondary-color: #f0f0f1;
    --border-color: #dcdcde;
    --text-color: #3c434a;
    --light-text: #646970;
    --widget-bg: #fff;
    --area-bg: #f6f7f7;
    --hover-bg: #f6f7f7;
    --shadow: 0 1px 2px rgba(0,0,0,0.05);
    --border-radius: 4px;
}

/* Estilos generales */
.widget-management-container {
    margin-top: 1.5rem;
}

.widget-management-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 2rem;
    align-items: start;
}

/* Columna de widgets disponibles */
.available-widgets-panel {
    background-color: var(--widget-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
}

.available-widgets-header {
    padding: 12px 15px;
    background-color: var(--secondary-color);
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    color: var(--text-color);
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.available-widgets-body {
    padding: 15px;
    max-height: 70vh;
    overflow-y: auto;
}

.available-widgets-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.widget-block {
    background-color: var(--widget-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 14px 16px;
    margin-bottom: 10px;
    cursor: grab;
    user-select: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
    box-shadow: var(--shadow);
}

.widget-block:hover {
    background-color: var(--hover-bg);
    border-color: #c3c4c7;
}

.widget-block:active {
    cursor: grabbing;
    background-color: var(--secondary-color);
}

.widget-icon {
    color: var(--light-text);
    font-size: 1.2em;
    display: flex;
    align-items: center;
}

.widget-title {
    flex-grow: 1;
    font-weight: 500;
}

.widget-description {
    font-size: 12px;
    color: var(--light-text);
    margin-top: 5px;
}

/* Áreas de widgets */
.widget-areas-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.widget-area-card {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    background-color: var(--widget-bg);
    box-shadow: var(--shadow);
    transition: box-shadow 0.2s ease;
}

.widget-area-card:hover {
    box-shadow: 0 2px 5px rgba(0,0,0,0.08);
}

.widget-area-header {
    background-color: var(--secondary-color);
    padding: 12px 20px;
    font-weight: 600;
    color: var(--text-color);
    border-bottom: 1px solid var(--border-color);
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.widget-area-description {
    font-size: 12px;
    color: var(--light-text);
}

.widget-area-body {
    padding: 15px;
    min-height: 150px;
    background-color: var(--area-bg);
    border-radius: 0 0 var(--border-radius) var(--border-radius);
}

/* Widgets instanciados */
.widget-instance {
    background-color: var(--widget-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    margin-bottom: 12px;
    box-shadow: var(--shadow);
    transition: box-shadow 0.2s ease;
}

.widget-instance:hover {
    box-shadow: 0 2px 5px rgba(0,0,0,0.08);
}

.widget-instance-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    cursor: grab;
    border-bottom: 1px solid var(--border-color);
    background-color: #fafafa;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.widget-instance-title {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.widget-instance-title::before {
    content: "≡";
    color: var(--light-text);
    font-size: 1.2em;
}

.widget-instance-actions {
    display: flex;
    gap: 5px;
}

.widget-instance-actions button {
    background: none;
    border: none;
    color: var(--light-text);
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.widget-instance-actions button:hover {
    background-color: var(--secondary-color);
    color: var(--primary-color);
}

.widget-edit-toggle.active {
    color: var(--primary-color);
    background-color: var(--secondary-color);
}

.widget-instance-form {
    display: none;
    padding: 15px;
    border-top: 1px solid var(--border-color);
    background-color: #fff;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
}

.widget-instance-form.active {
    display: block;
}

/* Placeholder y mensajes */
.widget-placeholder {
    background-color: rgba(var(--primary-color-rgb, 34, 113, 177), 0.1);
    border: 2px dashed var(--primary-color);
    border-radius: var(--border-radius);
    height: 56px;
    margin-bottom: 12px;
}

.no-widgets-msg {
    color: var(--light-text);
    text-align: center;
    padding: 2rem 1rem;
    font-style: italic;
    border: 2px dashed var(--border-color);
    border-radius: var(--border-radius);
    background-color: rgba(0,0,0,0.02);
}

/* Botones y controles */
.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-hover);
    border-color: var(--primary-hover);
}

/* Responsive */
@media (max-width: 992px) {
    .widget-management-grid {
        grid-template-columns: 1fr;
    }

    .available-widgets-body {
        max-height: none;
    }
}

/* Formularios dentro de widgets */
.widget-form-group {
    margin-bottom: 15px;
}

.widget-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.widget-form-group input[type="text"],
.widget-form-group select,
.widget-form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
}
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-grid-3x3-gap me-2"></i>
                    Widgets - {{ e($themeConfig['name'] ?? $themeSlug) }}
                </h1>
                <p class="text-muted mb-0">
                    <small>Configura los widgets para tu sitio</small>
                </p>
            </div>
            <div>
                <a href="/{{ admin_path() }}/themes" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Temas
                </a>
                <button type="button" id="saveWidgetsBtn" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i> Guardar Cambios
                </button>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="widget-management-container">
            <form id="widgetAreasForm" method="POST" action="/{{ admin_path() }}/widgets/{{ $themeSlug }}/save">
                @csrf

                <div class="widget-management-grid">

                    {{-- Widgets Disponibles --}}
                    <div class="available-widgets-panel">
                        <div class="available-widgets-header">
                            <i class="bi bi-grid-3x3-gap me-2"></i> Widgets Disponibles
                        </div>
                        <div class="available-widgets-body">
                            <p class="small text-muted mb-3">Arrastra widgets a las áreas de la derecha para añadirlos a tu tema.</p>
                            <ul id="available-widgets" class="available-widgets-list">
                                @foreach($availableWidgets as $slug => $widgetInfo)
                                    @php $widgetInstance = WidgetManager::getWidgetInstance($slug); @endphp
                                    @if($widgetInstance)
                                        <li class="widget-block" data-widget-slug="{{ $slug }}">
                                            <div class="widget-icon">
                                                <i class="bi {{ $widgetInfo['icon'] ?? 'bi-puzzle' }}"></i>
                                            </div>
                                            <div>
                                                <span class="widget-title">{{ e($widgetInfo['name']) }}</span>
                                                @if(!empty($widgetInfo['description']))
                                                    <div class="widget-description">{{ e($widgetInfo['description']) }}</div>
                                                @endif
                                            </div>
                                            <div class="widget-form-template" style="display:none;">
                                                {!! $widgetInstance->form() !!}
                                            </div>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    {{-- Áreas del Tema --}}
                    <div class="widget-areas-container">
                        <h5 class="mb-3">
                            <i class="bi bi-columns-gap me-2"></i> Áreas del Tema
                            <span class="text-muted small">({{ count($widgetAreas) }} áreas disponibles)</span>
                        </h5>

                        @forelse($widgetAreas as $areaSlug => $areaInfo)
                            <div class="widget-area-card">
                                <div class="widget-area-header">
                                    <span>{{ e($areaInfo['name'] ?? $areaSlug) }}</span>
                                    @if(!empty($areaInfo['description']))
                                        <span class="widget-area-description">{{ e($areaInfo['description']) }}</span>
                                    @endif
                                </div>
                                <div class="widget-area-body widget-area-sortable" data-area-slug="{{ $areaSlug }}">
                                    @if(isset($assignedWidgets[$areaSlug]) && count($assignedWidgets[$areaSlug]) > 0)
                                        @foreach($assignedWidgets[$areaSlug] as $index => $instanceData)
                                            @include('widgets._widget_instance', compact('instanceData', 'areaSlug', 'index'))
                                        @endforeach
                                    @else
                                        <div class="no-widgets-msg">
                                            <i class="bi bi-plus-circle mb-2 d-block" style="font-size: 1.5rem;"></i>
                                            Arrastra widgets aquí para añadirlos a esta área
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> No hay áreas disponibles en este tema.
                            </div>
                        @endforelse
                    </div>

                </div>
            </form>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    console.log("Inicializando sistema de widgets para tenant...");

    const availableWidgets = document.getElementById('available-widgets');
    const widgetAreas = document.querySelectorAll('.widget-area-sortable');
    const widgetForm = document.getElementById('widgetAreasForm');
    const saveButton = document.getElementById('saveWidgetsBtn');

    // Inicializar SortableJS para los widgets disponibles
    if (availableWidgets) {
        new Sortable(availableWidgets, {
            group: { name: 'widgets', pull: 'clone', put: false },
            sort: false,
            animation: 150,
            ghostClass: 'widget-placeholder'
        });
    }

    // Inicializar SortableJS para cada área de widgets
    widgetAreas.forEach(area => {
        new Sortable(area, {
            group: 'widgets',
            animation: 150,
            handle: '.widget-instance-header',
            ghostClass: 'widget-placeholder',
            onStart(evt) {
                const item = evt.item;
                if (item.dataset.widgetSlug) {
                    item._savedWidgetSlug = item.dataset.widgetSlug;
                } else {
                    const hiddenInput = item.querySelector('input[type="hidden"][name*="widget_slug"]');
                    if (hiddenInput && hiddenInput.value) {
                        item._savedWidgetSlug = hiddenInput.value;
                    }
                }
            },
            onAdd(evt) {
                const item = evt.item;

                let slug = item._savedWidgetSlug || item.dataset.widgetSlug;

                if (!slug) {
                    const hiddenInput = item.querySelector('input[type="hidden"][name*="widget_slug"]');
                    if (hiddenInput && hiddenInput.value) {
                        slug = hiddenInput.value;
                    }
                }

                if (!slug) {
                    const anyHiddenInput = item.querySelector('.widget-instance-form input[type="hidden"]');
                    if (anyHiddenInput && anyHiddenInput.value) {
                        slug = anyHiddenInput.value;
                    }
                }

                if (slug && !item.dataset.widgetSlug) {
                    item.dataset.widgetSlug = slug;
                }

                if (!slug || slug.trim() === '') {
                    alert('Error: Este widget está corrupto. Por favor, elimínalo y crea uno nuevo.');
                    item.remove();
                    return;
                }

                const isAlreadyInstance = item.classList.contains('widget-instance');

                if (isAlreadyInstance) {
                    item.dataset.widgetSlug = slug;
                    updateFormInputNames();
                    delete item._savedWidgetSlug;
                    return;
                }

                const formTemplate = item.querySelector('.widget-form-template');
                const widgetTitleEl = item.querySelector('.widget-title');
                const widgetTitle = widgetTitleEl ? widgetTitleEl.textContent : 'Widget';

                const noWidgetsMsg = area.querySelector('.no-widgets-msg');
                if (noWidgetsMsg) {
                    noWidgetsMsg.remove();
                }

                item.classList.remove('widget-block');
                item.classList.add('widget-instance');
                item.dataset.instanceId = 'new-' + Date.now();
                item.dataset.widgetSlug = slug;

                item.innerHTML = `
                    <div class="widget-instance-header">
                        <div class="widget-instance-title">
                            <i class="bi bi-puzzle me-2"></i>
                            <span>${widgetTitle}</span>
                        </div>
                        <div class="widget-instance-actions">
                            <button type="button" class="widget-edit-toggle" title="Editar configuración">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="widget-delete-button" title="Eliminar widget">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="widget-instance-form">
                        <input type="hidden" name="widget_slug_placeholder" value="${slug}">
                        ${formTemplate ? formTemplate.innerHTML : ''}
                    </div>
                `;

                updateFormInputNames();
                delete item._savedWidgetSlug;

                setTimeout(() => {
                    const editToggle = item.querySelector('.widget-edit-toggle');
                    if (editToggle) {
                        editToggle.click();
                    }
                }, 100);
            },
            onEnd(evt) {
                const item = evt.item;
                if (item._savedWidgetSlug) {
                    delete item._savedWidgetSlug;
                }
            },
            onUpdate(evt) {
                updateFormInputNames();
            },
            onRemove(evt) {
                updateFormInputNames();
            }
        });
    });

    // Manejador de eventos para botones
    document.querySelector('.widget-areas-container').addEventListener('click', function (e) {
        // Botón Editar
        if (e.target.closest('.widget-edit-toggle')) {
            const button = e.target.closest('.widget-edit-toggle');
            const instance = button.closest('.widget-instance');
            const form = instance.querySelector('.widget-instance-form');

            button.classList.toggle('active');
            form.classList.toggle('active');
        }

        // Botón Aceptar
        if (e.target.closest('.widget-form-close')) {
            const button = e.target.closest('.widget-form-close');
            const instance = button.closest('.widget-instance');
            const form = instance.querySelector('.widget-instance-form');
            const editButton = instance.querySelector('.widget-edit-toggle');

            form.classList.remove('active');
            if (editButton) editButton.classList.remove('active');
        }

        // Botón Eliminar
        if (e.target.closest('.widget-delete-button')) {
            const instance = e.target.closest('.widget-instance');
            const area = instance.closest('.widget-area-sortable');

            Swal.fire({
                title: '¿Eliminar widget?',
                text: '¿Estás seguro de que deseas eliminar este widget?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    instance.remove();
                    updateFormInputNames();

                    if (area.querySelectorAll('.widget-instance').length === 0) {
                        area.innerHTML = `
                            <div class="no-widgets-msg">
                                <i class="bi bi-plus-circle mb-2 d-block" style="font-size: 1.5rem;"></i>
                                Arrastra widgets aquí para añadirlos a esta área
                            </div>
                        `;
                    }

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });

                    Toast.fire({
                        icon: 'success',
                        title: 'Widget eliminado'
                    });
                }
            });
        }
    });

    // Guardar cambios
    if (saveButton) {
        saveButton.addEventListener('click', function() {
            Swal.fire({
                title: 'Guardar cambios',
                text: '¿Estás seguro de que deseas guardar los cambios en los widgets?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const loadingSwal = Swal.fire({
                        title: 'Guardando...',
                        text: 'Guardando la configuración de widgets',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData(widgetForm);

                    fetch(widgetForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        return response.text().then(text => {
                            try {
                                return {
                                    data: JSON.parse(text),
                                    response,
                                    statusCode: response.status
                                };
                            } catch (e) {
                                return {
                                    text,
                                    response,
                                    statusCode: response.status
                                };
                            }
                        });
                    })
                    .then(result => {
                        loadingSwal.close();

                        if (result.data) {
                            if (result.data.success) {
                                Swal.fire({
                                    title: 'Guardado con éxito',
                                    text: result.data.message || 'La configuración de widgets se ha guardado correctamente',
                                    icon: 'success',
                                    confirmButtonColor: '#3085d6'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: result.data.message || 'Ha ocurrido un error al guardar los widgets',
                                    icon: 'error',
                                    confirmButtonColor: '#3085d6'
                                });
                            }
                        } else {
                            if (result.response.redirected) {
                                window.location.href = result.response.url;
                                return;
                            }

                            if (result.statusCode >= 400) {
                                Swal.fire({
                                    title: 'Error del servidor',
                                    text: `Error HTTP ${result.statusCode}`,
                                    icon: 'error',
                                    confirmButtonColor: '#3085d6'
                                });
                                return;
                            }

                            window.location.reload();
                        }
                    })
                    .catch(error => {
                        loadingSwal.close();

                        Swal.fire({
                            title: 'Error de conexión',
                            text: 'Ha ocurrido un error al comunicarse con el servidor: ' + error.message,
                            icon: 'error',
                            confirmButtonColor: '#3085d6'
                        });
                    });
                }
            });
        });
    }

    // Función para actualizar nombres de campos
    function updateFormInputNames() {
        widgetAreas.forEach(area => {
            const areaSlug = area.dataset.areaSlug;
            const widgets = area.querySelectorAll('.widget-instance');

            widgets.forEach((widget, index) => {
                const inputs = widget.querySelectorAll('.widget-instance-form [name]');
                const hiddenInput = widget.querySelector('.widget-instance-form input[type="hidden"]');
                const hiddenValue = hiddenInput ? hiddenInput.value : '';

                let widgetSlug = widget.dataset.widgetSlug || hiddenValue;

                if (!widget.dataset.widgetSlug && hiddenValue) {
                    widget.dataset.widgetSlug = hiddenValue;
                    widgetSlug = hiddenValue;
                }

                if (!widgetSlug) {
                    return;
                }

                let widgetSlugInput = widget.querySelector('.widget-instance-form input[type="hidden"][name*="widget_slug"]');

                if (!widgetSlugInput) {
                    widgetSlugInput = widget.querySelector('.widget-instance-form > input[type="hidden"]:first-child');
                }

                if (widgetSlugInput) {
                    widgetSlugInput.setAttribute('name', `areas[${areaSlug}][${index}][widget_slug]`);
                    widgetSlugInput.value = widgetSlug;
                }

                inputs.forEach(input => {
                    const currentName = input.getAttribute('name');

                    if (input === widgetSlugInput) {
                        return;
                    }

                    let configKey = null;

                    const simpleMatch = currentName.match(/^config\[(.*?)\]$/);
                    if (simpleMatch) {
                        configKey = simpleMatch[1];
                    }

                    if (!configKey) {
                        const complexMatch = currentName.match(/areas\[.*?\]\[\d+\]\[config\]\[(.*?)\]$/);
                        if (complexMatch) {
                            configKey = complexMatch[1];
                        }
                    }

                    if (configKey) {
                        const newName = `areas[${areaSlug}][${index}][config][${configKey}]`;
                        if (currentName !== newName) {
                            input.setAttribute('name', newName);
                        }
                    }
                });
            });
        });
    }

    // Inicializar widgets existentes
    document.querySelectorAll('.widget-instance').forEach(instance => {
        const slug = instance.dataset.widgetSlug;
        const id = instance.dataset.instanceId;
        console.log(`- Widget existente: '${slug}' (ID: ${id})`);
    });

    updateFormInputNames();

    document.addEventListener('click', function(e) {
        if (e.target.closest('.widget-form-close')) {
            const closeBtn = e.target.closest('.widget-form-close');
            const form = closeBtn.closest('.widget-instance-form');
            const instance = closeBtn.closest('.widget-instance');
            const editBtn = instance.querySelector('.widget-edit-toggle');

            if (form) {
                form.classList.remove('active');
            }

            if (editBtn) {
                editBtn.classList.remove('active');
            }
        }
    });
});
</script>
@endpush
