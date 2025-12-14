{{-- views/superadmin/widgets/index.blade.php --}}
@php use Screenart\Musedock\Widgets\WidgetManager; @endphp
@extends('layouts.app')

@section('title', $title ?? 'Gestionar Widgets')

@push('styles')
<style>
/* Variables para fácil personalización */
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
.widgets-sticky-wrapper {
    z-index: 1000;
}

.widgets-sticky-wrapper.is-fixed {
    position: fixed !important;
}

.widgets-sticky-placeholder {
    display: none;
}

.widgets-sticky-placeholder.is-active {
    display: block;
}

.btn-widgets-pin.active {
    background: rgba(0, 0, 0, 0.08);
}

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
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.available-widgets-header .header-title {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
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
                    <small>{{ $tenantId ? 'Tenant #' . $tenantId : 'CMS Principal (Global)' }}</small>
                </p>
            </div>
            <div class="d-flex align-items-center gap-3">
                {{-- Selector de Temas --}}
                @if(!empty($availableThemes) && count($availableThemes) > 1)
                <label for="themeSelector" class="form-label small text-muted mb-0 me-2">Seleccionar Tema:</label>
                <select id="themeSelector" class="form-select form-select-sm" style="width: auto;" onchange="window.location.href='/musedock/widgets/' + this.value">
                    @foreach($availableThemes as $slug => $theme)
                        <option value="{{ $slug }}" {{ $slug === $themeSlug ? 'selected' : '' }}>
                            {{ e($theme['name']) }} (v{{ e($theme['version']) }})
                        </option>
                    @endforeach
                </select>
                @endif
                <button type="button" id="saveWidgetsBtn" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i> Guardar Cambios
                </button>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="widget-management-container">
            <form id="widgetAreasForm" method="POST" action="{{ route('widgets.save.global', ['slug' => $themeSlug]) }}">
                @csrf
                @if($tenantId)
                <input type="hidden" name="tenantId" value="{{ $tenantId }}">
                @endif

                <div class="widget-management-grid">

                    {{-- Widgets Disponibles --}}
                    <div class="widgets-sticky-placeholder"></div>
                    <div class="widgets-sticky-wrapper">
                        <div class="available-widgets-panel">
                            <div class="available-widgets-header">
                                <span class="header-title">
                                    <i class="bi bi-grid-3x3-gap"></i> Widgets Disponibles
                                </span>
                                <button type="button"
                                        id="toggle-widgets-pin"
                                        class="btn btn-sm btn-outline-secondary btn-widgets-pin"
                                        title="Anclar / desanclar panel"
                                        aria-pressed="true">
                                    <i class="bi bi-pin-angle"></i>
                                </button>
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
                                    <span>{{ e($areaInfo['name']) }}</span>
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
    console.log("Inicializando sistema de widgets...");

    const availableWidgets = document.getElementById('available-widgets');
    const widgetAreas = document.querySelectorAll('.widget-area-sortable');
    const widgetForm = document.getElementById('widgetAreasForm');
    const saveButton = document.getElementById('saveWidgetsBtn');

    // ============================================
    // STICKY SIDEBAR: Widgets Disponibles
    // ============================================
    function setupStickyAvailableWidgets() {
        const wrapper = document.querySelector('.widgets-sticky-wrapper');
        const placeholder = document.querySelector('.widgets-sticky-placeholder');
        const pinBtn = document.getElementById('toggle-widgets-pin');

        if (!wrapper || !placeholder) return;

        const topOffset = 16;
        const storageKey = 'musedock.widgetsSidebarPinned';

        const getScrollContainer = (startEl) => {
            let node = startEl.parentElement;
            while (node) {
                const style = window.getComputedStyle(node);
                const overflowY = style.overflowY;
                if ((overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay') && node.scrollHeight > node.clientHeight) {
                    return node;
                }
                node = node.parentElement;
            }
            return document.querySelector('main.content') || null;
        };

        const scrollContainer = getScrollContainer(wrapper);
        const usesWindow = !scrollContainer || scrollContainer === document.body || scrollContainer === document.documentElement;

        const getScrollTop = () => {
            if (usesWindow) return window.pageYOffset || document.documentElement.scrollTop || 0;
            return scrollContainer.scrollTop || 0;
        };

        const getScrollerTopInViewport = () => {
            if (usesWindow) return 0;
            return scrollContainer.getBoundingClientRect().top;
        };

        const originalInlineStyle = wrapper.getAttribute('style') || '';

        let isFixed = false;
        let rafId = null;
        let pinEnabled = true;

        const readPinned = () => {
            try {
                const v = window.localStorage.getItem(storageKey);
                // default: false (unpinned)
                if (v === null) return false;
                return v === '1';
            } catch (_) {
                return false;
            }
        };

        const writePinned = (value) => {
            try {
                window.localStorage.setItem(storageKey, value ? '1' : '0');
            } catch (_) {
                // ignore
            }
        };

        const syncPinBtn = () => {
            if (!pinBtn) return;
            pinBtn.classList.toggle('active', pinEnabled);
            pinBtn.setAttribute('aria-pressed', pinEnabled ? 'true' : 'false');
            pinBtn.title = pinEnabled ? 'Panel anclado (clic para desanclar)' : 'Panel desanclado (clic para anclar)';
        };

        const applyPosition = () => {
            const phRect = placeholder.getBoundingClientRect();
            const scrollerTop = Math.max(getScrollerTopInViewport(), 0);
            const maxHeight = window.innerHeight - (scrollerTop + topOffset) - 16;

            wrapper.style.position = 'fixed';
            wrapper.style.top = (scrollerTop + topOffset) + 'px';
            wrapper.style.left = phRect.left + 'px';
            wrapper.style.width = phRect.width + 'px';
            wrapper.style.zIndex = '1100';

            // Mantener scroll interno del panel dentro del viewport
            const body = wrapper.querySelector('.available-widgets-body');
            if (body) body.style.maxHeight = Math.max(240, maxHeight) + 'px';
        };

        const fix = () => {
            if (isFixed) return;
            isFixed = true;

            placeholder.style.height = wrapper.offsetHeight + 'px';
            placeholder.classList.add('is-active');
            wrapper.classList.add('is-fixed');

            // Extra-robusto frente a overflow/transform en ancestros
            document.body.appendChild(wrapper);
            applyPosition();
        };

        const unfix = () => {
            if (!isFixed) return;
            isFixed = false;

            wrapper.classList.remove('is-fixed');
            wrapper.setAttribute('style', originalInlineStyle);

            const body = wrapper.querySelector('.available-widgets-body');
            if (body) body.style.maxHeight = '';

            placeholder.classList.remove('is-active');
            placeholder.style.height = '';
            placeholder.insertAdjacentElement('afterend', wrapper);
        };

        const onScroll = () => {
            if (rafId) return;
            rafId = window.requestAnimationFrame(() => {
                rafId = null;

                if (window.innerWidth < 992) {
                    unfix();
                    return;
                }

                if (!pinEnabled) {
                    unfix();
                    return;
                }

                const scrollTop = getScrollTop();
                const scrollerTop = getScrollerTopInViewport();
                const phRect = placeholder.getBoundingClientRect();
                const phTopInScroll = scrollTop + (phRect.top - scrollerTop);

                if (scrollTop >= phTopInScroll - topOffset) {
                    fix();
                    applyPosition();
                } else {
                    unfix();
                }
            });
        };

        const addScrollListener = (target) => {
            if (!target) return;
            target.addEventListener('scroll', onScroll, { passive: true });
        };

        addScrollListener(usesWindow ? window : scrollContainer);
        addScrollListener(window); // fallback

        pinEnabled = readPinned();
        syncPinBtn();
        if (pinBtn) {
            pinBtn.addEventListener('click', () => {
                pinEnabled = !pinEnabled;
                writePinned(pinEnabled);
                syncPinBtn();
                onScroll();
            });
        }

        window.addEventListener('resize', () => {
            if (isFixed) applyPosition();
            onScroll();
        });

        setTimeout(onScroll, 200);
    }

    setupStickyAvailableWidgets();

    // Inicializar SortableJS para los widgets disponibles
    if (availableWidgets) {
        new Sortable(availableWidgets, {
            group: { name: 'widgets', pull: 'clone', put: false },
            sort: false,
            animation: 150,
            ghostClass: 'widget-placeholder'
        });
        console.log("SortableJS inicializado para lista de widgets disponibles");
    }

    // Inicializar SortableJS para cada área de widgets
    widgetAreas.forEach(area => {
        new Sortable(area, {
            group: 'widgets',
            animation: 150,
            handle: '.widget-instance-header',
            ghostClass: 'widget-placeholder',
            // CRÍTICO: Antes de que el elemento se mueva, guardar el slug
            onStart(evt) {
                const item = evt.item;
                // Guardar el slug en una propiedad temporal por si se pierde durante el move
                if (item.dataset.widgetSlug) {
                    item._savedWidgetSlug = item.dataset.widgetSlug;
                    console.log(`onStart: Guardando slug '${item._savedWidgetSlug}' antes del move`);
                } else {
                    // Intentar recuperar del hidden input
                    const hiddenInput = item.querySelector('input[type="hidden"][name*="widget_slug"]');
                    if (hiddenInput && hiddenInput.value) {
                        item._savedWidgetSlug = hiddenInput.value;
                        console.log(`onStart: Slug recuperado del hidden input: '${item._savedWidgetSlug}'`);
                    }
                }
            },
            onAdd(evt) {
                const item = evt.item;

                // DEBUG: Inspeccionar el elemento completo
                console.log('=== DEBUG onAdd ===');
                console.log('Item HTML:', item.outerHTML.substring(0, 500));
                console.log('Item dataset:', JSON.stringify(item.dataset));
                console.log('Item classes:', item.className);
                console.log('Saved slug from onStart:', item._savedWidgetSlug);

                // Intentar obtener el slug de múltiples fuentes (prioridad: guardado > dataset > hidden)
                let slug = item._savedWidgetSlug || item.dataset.widgetSlug;

                // Si no está, intentar obtenerlo del hidden input
                if (!slug) {
                    // Buscar específicamente el input de widget_slug
                    const hiddenInput = item.querySelector('input[type="hidden"][name*="widget_slug"]');
                    if (hiddenInput && hiddenInput.value) {
                        slug = hiddenInput.value;
                        console.log('Slug recuperado del hidden input (name*=widget_slug):', slug);
                    }
                }

                // Si aún no hay slug, buscar cualquier hidden input con valor
                if (!slug) {
                    const anyHiddenInput = item.querySelector('.widget-instance-form input[type="hidden"]');
                    if (anyHiddenInput && anyHiddenInput.value) {
                        slug = anyHiddenInput.value;
                        console.log('Slug recuperado de cualquier hidden input:', slug);
                    }
                }

                // IMPORTANTE: Restaurar el slug al dataset si lo recuperamos
                if (slug && !item.dataset.widgetSlug) {
                    item.dataset.widgetSlug = slug;
                    console.log(`Slug '${slug}' restaurado al dataset`);
                }

                console.log(`Añadiendo widget '${slug}' al área '${area.dataset.areaSlug}'`);

                // CRÍTICO: Validar que el slug no esté vacío
                if (!slug || slug.trim() === '') {
                    console.error('ERROR: Intentando añadir widget sin slug. Esto no está permitido.');
                    console.error('Hidden inputs encontrados:', item.querySelectorAll('input[type="hidden"]').length);
                    item.querySelectorAll('input[type="hidden"]').forEach((inp, i) => {
                        console.error(`  Hidden ${i}: name="${inp.name}", value="${inp.value}"`);
                    });
                    alert('Error: Este widget está corrupto (sin tipo). Por favor, elimínalo y crea uno nuevo.');
                    item.remove();
                    return;
                }

                // Verificar si el elemento ya es una instancia de widget (movido de otra área)
                // o si es un widget nuevo desde la lista de disponibles
                const isAlreadyInstance = item.classList.contains('widget-instance');

                if (isAlreadyInstance) {
                    // Si ya es una instancia, asegurar que el slug esté en el dataset
                    item.dataset.widgetSlug = slug;
                    console.log(`Widget '${slug}' movido entre áreas - slug asegurado en dataset`);
                    updateFormInputNames();
                    // Limpiar la propiedad temporal
                    delete item._savedWidgetSlug;
                    return;
                }

                // Es un widget nuevo desde la lista de disponibles
                const formTemplate = item.querySelector('.widget-form-template');
                const widgetTitleEl = item.querySelector('.widget-title');
                const widgetTitle = widgetTitleEl ? widgetTitleEl.textContent : 'Widget';
                const widgetIconEl = item.querySelector('.widget-icon i');
                const widgetIcon = widgetIconEl ? widgetIconEl.outerHTML : '<i class="bi bi-puzzle"></i>';

                // Eliminar mensaje de área vacía si existe
                const noWidgetsMsg = area.querySelector('.no-widgets-msg');
                if (noWidgetsMsg) {
                    noWidgetsMsg.remove();
                }

                // Transformar elemento arrastrado en instancia de widget
                item.classList.remove('widget-block');
                item.classList.add('widget-instance');
                item.dataset.instanceId = 'new-' + Date.now();
                item.dataset.widgetSlug = slug; // ¡CRÍTICO! Necesario para updateFormInputNames()

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

                // Actualizar nombres de inputs y abrir formulario automáticamente
                updateFormInputNames();

                // Limpiar la propiedad temporal
                delete item._savedWidgetSlug;

                setTimeout(() => {
                    const editToggle = item.querySelector('.widget-edit-toggle');
                    if (editToggle) {
                        editToggle.click();
                    }
                }, 100);
            },
            onEnd(evt) {
                // Limpiar cualquier propiedad temporal al finalizar cualquier operación
                const item = evt.item;
                if (item._savedWidgetSlug) {
                    // Si llegamos aquí sin haber usado el slug, algo salió mal - loguear
                    console.log(`onEnd: Limpiando slug guardado '${item._savedWidgetSlug}'`);
                    delete item._savedWidgetSlug;
                }
            },
            onUpdate(evt) {
                console.log(`Widget reordenado en área '${area.dataset.areaSlug}'`);
                updateFormInputNames();
            },
            onRemove(evt) {
                console.log(`Widget eliminado de área '${area.dataset.areaSlug}'`);
                updateFormInputNames();
            }
        });
        console.log(`SortableJS inicializado para área '${area.dataset.areaSlug}'`);
    });

    // Manejador de eventos para todos los botones dentro de widget-areas-container
    document.querySelector('.widget-areas-container').addEventListener('click', function (e) {
        // Botón Editar/Mostrar Formulario
        if (e.target.closest('.widget-edit-toggle')) {
            const button = e.target.closest('.widget-edit-toggle');
            const instance = button.closest('.widget-instance');
            const form = instance.querySelector('.widget-instance-form');
            
            // Toggle estados
            button.classList.toggle('active');
            form.classList.toggle('active');
            
            console.log(`Formulario de widget ${form.classList.contains('active') ? 'abierto' : 'cerrado'}`);
        }

        // Botón Aceptar del formulario
        if (e.target.closest('.widget-form-close')) {
            const button = e.target.closest('.widget-form-close');
            const instance = button.closest('.widget-instance');
            const form = instance.querySelector('.widget-instance-form');
            const editButton = instance.querySelector('.widget-edit-toggle');
            
            // Cerrar formulario y quitar estado activo
            form.classList.remove('active');
            if (editButton) editButton.classList.remove('active');
            
            console.log('Formulario cerrado con botón Aceptar');
        }

        // Botón Eliminar
        if (e.target.closest('.widget-delete-button')) {
            const instance = e.target.closest('.widget-instance');
            const area = instance.closest('.widget-area-sortable');
            const widgetId = instance.dataset.instanceId;
            const widgetSlug = instance.dataset.widgetSlug;
            const areaSlug = area.dataset.areaSlug;
            
            console.log(`Solicitando eliminar widget ID: ${widgetId}, tipo: ${widgetSlug}, del área: ${areaSlug}`);
            
            // Usar SweetAlert2 para confirmar eliminación
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
                    // Marcar como eliminado (opcional)
                    instance.classList.add('widget-deleted');
                    
                    // Eliminar widget del DOM
                    instance.remove();
                    
                    // Actualizar nombres de inputs para mantener la secuencia correcta
                    updateFormInputNames();
                    
                    // Si no quedan widgets, mostrar mensaje
                    if (area.querySelectorAll('.widget-instance').length === 0) {
                        area.innerHTML = `
                            <div class="no-widgets-msg">
                                <i class="bi bi-plus-circle mb-2 d-block" style="font-size: 1.5rem;"></i>
                                Arrastra widgets aquí para añadirlos a esta área
                            </div>
                        `;
                    }
                    
                    console.log(`Widget eliminado del área '${areaSlug}'`);
                    
                    // Opcional: Notificar al usuario
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

    // Guardar cambios (botón principal)
    if (saveButton) {
        saveButton.addEventListener('click', function() {
            console.group('Guardando widgets');
            console.log('Formulario:', widgetForm);
            console.log('Áreas con widgets:', document.querySelectorAll('.widget-area-sortable'));
            console.log("Solicitando confirmación para guardar widgets...");
            
            // Pedir confirmación antes de guardar
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
                    console.log("Enviando formulario...");
                    
                    // Mostrar indicador de carga
                    const loadingSwal = Swal.fire({
                        title: 'Guardando...',
                        text: 'Guardando la configuración de widgets',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Debug de formulario
                    console.log("URL de acción:", widgetForm.action);
                    
                    // Usar FormData para enviar el formulario
                    const formData = new FormData(widgetForm);
                    
                    // Debug de FormData
                    for (let [key, value] of formData.entries()) {
                        console.log(`${key}: ${value}`);
                    }
                    
                    // Enviar por AJAX
                    fetch(widgetForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        // Intentar obtener texto de respuesta
                        return response.text().then(text => {
                            try {
                                // Parsear como JSON si es posible
                                return { 
                                    data: JSON.parse(text), 
                                    response,
                                    statusCode: response.status
                                };
                            } catch (e) {
                                console.error("Error al parsear respuesta como JSON:", e);
                                console.log("Contenido de respuesta:", text.substring(0, 500) + "...");
                                
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
                        
                        console.log("Respuesta del servidor:", result);
                        
                        // Manejar respuesta JSON
                        if (result.data) {
                            if (result.data.success) {
                                Swal.fire({
                                    title: 'Guardado con éxito',
                                    text: result.data.message || 'La configuración de widgets se ha guardado correctamente',
                                    icon: 'success',
                                    confirmButtonColor: '#3085d6'
                                }).then(() => {
                                    // Recargar página
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
                        } 
                        // Manejar redirecciones o errores HTTP
                        else {
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
                            
                            // Fallback: recargar página
                            window.location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error al comunicarse con el servidor:', error);
                        loadingSwal.close();
                        
                        Swal.fire({
                            title: 'Error de conexión',
                            text: 'Ha ocurrido un error al comunicarse con el servidor: ' + error.message,
                            icon: 'error',
                            confirmButtonColor: '#3085d6'
                        });
                    })
                    .finally(() => {
                        console.groupEnd();
                    });
                } else {
                    console.groupEnd();
                }
            });
        });
    }

    // Función para actualizar nombres de campos
    function updateFormInputNames() {
        widgetAreas.forEach(area => {
            const areaSlug = area.dataset.areaSlug;
            const widgets = area.querySelectorAll('.widget-instance');

            console.log(`Actualizando nombres de ${widgets.length} widgets en área '${areaSlug}'`);

            widgets.forEach((widget, index) => {
                const inputs = widget.querySelectorAll('.widget-instance-form [name]');

                // CRÍTICO: Obtener el slug del hidden input PRIMERO (antes de modificarlo)
                const hiddenInput = widget.querySelector('.widget-instance-form input[type="hidden"]');
                const hiddenValue = hiddenInput ? hiddenInput.value : '';

                // El slug puede venir del dataset O del hidden input
                let widgetSlug = widget.dataset.widgetSlug || hiddenValue;

                // Si el dataset está vacío pero el hidden tiene valor, actualizar el dataset
                if (!widget.dataset.widgetSlug && hiddenValue) {
                    widget.dataset.widgetSlug = hiddenValue;
                    widgetSlug = hiddenValue;
                    console.log(`  - Widget ${index}: slug recuperado del hidden input: '${widgetSlug}'`);
                }

                console.log(`  - Widget ${index}: '${widgetSlug}', ${inputs.length} campos`);

                // Validar que tengamos un slug válido
                if (!widgetSlug) {
                    console.error(`Widget sin slug en área '${areaSlug}', índice ${index}. Este widget está corrupto.`);
                    return; // Saltar este widget
                }

                // Primero, buscar o crear el input hidden para widget_slug
                let widgetSlugInput = widget.querySelector('.widget-instance-form input[type="hidden"][name*="widget_slug"]');

                if (!widgetSlugInput) {
                    // Buscar el primer hidden input que podría ser el widget_slug placeholder
                    widgetSlugInput = widget.querySelector('.widget-instance-form > input[type="hidden"]:first-child');
                }

                // Configurar el input del widget_slug
                if (widgetSlugInput) {
                    widgetSlugInput.setAttribute('name', `areas[${areaSlug}][${index}][widget_slug]`);
                    widgetSlugInput.value = widgetSlug;
                    console.log(`    Widget slug input configurado: name="${widgetSlugInput.name}", value="${widgetSlugInput.value}"`);
                }

                // Ahora procesar los campos de configuración
                inputs.forEach(input => {
                    const currentName = input.getAttribute('name');

                    // Saltar el input de widget_slug que ya configuramos
                    if (input === widgetSlugInput) {
                        return;
                    }

                    // Detectar si es un campo de configuración
                    // Formato 1: config[key] (widget recién arrastrado)
                    // Formato 2: areas[oldArea][oldIndex][config][key] (widget ya existente o movido)
                    let configKey = null;

                    // Intentar formato 1: config[key]
                    const simpleMatch = currentName.match(/^config\[(.*?)\]$/);
                    if (simpleMatch) {
                        configKey = simpleMatch[1];
                    }

                    // Intentar formato 2: areas[...][...][config][key]
                    if (!configKey) {
                        const complexMatch = currentName.match(/areas\[.*?\]\[\d+\]\[config\]\[(.*?)\]$/);
                        if (complexMatch) {
                            configKey = complexMatch[1];
                        }
                    }

                    if (configKey) {
                        // Campo de configuración - renombrar a areas[area][index][config][key]
                        const newName = `areas[${areaSlug}][${index}][config][${configKey}]`;
                        if (currentName !== newName) {
                            input.setAttribute('name', newName);
                            console.log(`    Config renombrado: ${currentName} -> ${newName}`);
                        }
                    }
                });
            });
        });
    }

    // Inicializar widgets existentes
    console.log("Inicializando widgets existentes...");
    document.querySelectorAll('.widget-instance').forEach(instance => {
        const slug = instance.dataset.widgetSlug;
        const id = instance.dataset.instanceId;
        console.log(`- Widget existente: '${slug}' (ID: ${id})`);
    });

    // Actualizar todos los nombres al cargar
    updateFormInputNames();
    
    // Agregar manejador específico para el botón de aceptar
    document.addEventListener('click', function(e) {
        // Detectar clics en botones de aceptar
        if (e.target.closest('.widget-form-close')) {
            console.log("Botón Aceptar pulsado");
            const closeBtn = e.target.closest('.widget-form-close');
            const form = closeBtn.closest('.widget-instance-form');
            const instance = closeBtn.closest('.widget-instance');
            const editBtn = instance.querySelector('.widget-edit-toggle');
            
            // Cerrar formulario
            if (form) {
                form.classList.remove('active');
                console.log("Formulario cerrado");
            }
            
            // Desactivar botón de edición
            if (editBtn) {
                editBtn.classList.remove('active');
            }
        }
    });
});
</script>
@endpush
