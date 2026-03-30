/**
 * Sistema de Notificaciones en Tiempo Real
 * Soporte para Polling y WebSocket
 */
(function() {
    'use strict';

    // Detectar si estamos en panel de tenant o superadmin
    const isTenantPanel = window.location.pathname.startsWith('/admin') ||
                          (window.location.hostname !== window.location.hostname.replace('www.', ''));
    const isSuperadminPanel = window.location.pathname.startsWith('/musedock');

    // URLs de API según el contexto
    const API_BASE = isTenantPanel ? admin_url('api/notifications') : '/musedock/api/notifications';
    const API_UNREAD = `${API_BASE}/unread`;
    const API_COUNT = `${API_BASE}/unread-count`;
    const API_MARK_READ = `${API_BASE}/{id}/mark-read`;
    const API_MARK_ALL = `${API_BASE}/mark-all-read`;

    // Configuración
    const POLL_INTERVAL = 30000; // 30 segundos
    const MAX_NOTIFICATIONS_DISPLAY = 10;

    // Estado
    let pollTimer = null;
    let lastCount = 0;

    // Elementos DOM
    const badge = document.getElementById('notification-badge');
    const countText = document.getElementById('notification-count-text');
    const notificationsList = document.getElementById('notifications-list');
    const noNotifications = document.getElementById('no-notifications');
    const markAllReadBtn = document.getElementById('mark-all-read');

    /**
     * Helper: Obtener URL admin dinámica
     */
    function admin_url(path) {
        const adminPath = window.ADMIN_PATH || 'admin';
        return `/${adminPath}/${path.replace(/^\//, '')}`;
    }

    /**
     * Obtener token CSRF
     */
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    /**
     * Actualizar contador de notificaciones
     */
    async function updateNotificationCount() {
        try {
            const response = await fetch(API_COUNT, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Error al obtener contador');
            }

            const data = await response.json();
            const count = data.count || 0;

            updateBadge(count);

            // Si el contador aumentó, actualizar la lista
            if (count > lastCount) {
                await loadNotifications();
            }

            lastCount = count;

        } catch (error) {
            console.error('Error actualizando contador de notificaciones:', error);
        }
    }

    /**
     * Actualizar badge visual
     */
    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline-block';
            countText.textContent = `${count} notificación${count !== 1 ? 'es' : ''} nueva${count !== 1 ? 's' : ''}`;
        } else {
            badge.style.display = 'none';
            countText.textContent = 'No hay notificaciones nuevas';
        }
    }

    /**
     * Cargar notificaciones no leídas
     */
    async function loadNotifications() {
        try {
            const response = await fetch(`${API_UNREAD}?limit=${MAX_NOTIFICATIONS_DISPLAY}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Error al cargar notificaciones');
            }

            const data = await response.json();
            const notifications = data.notifications || [];

            renderNotifications(notifications);

        } catch (error) {
            console.error('Error cargando notificaciones:', error);
        }
    }

    /**
     * Renderizar lista de notificaciones
     */
    function renderNotifications(notifications) {
        if (!notifications || notifications.length === 0) {
            notificationsList.innerHTML = `
                <div class="text-center py-4 text-muted" id="no-notifications">
                    <i class="align-middle" data-feather="inbox" style="width: 48px; height: 48px;"></i>
                    <p class="mt-2 mb-0">No hay notificaciones</p>
                </div>
            `;

            // Re-inicializar feather icons
            if (typeof feather !== 'undefined') {
                feather.replace();
            }

            return;
        }

        let html = '';

        notifications.forEach(notification => {
            const icon = getNotificationIcon(notification.type);
            const iconColor = getNotificationColor(notification.type);
            const timeAgo = formatTimeAgo(notification.created_at);
            const actionUrl = notification.data?.action_url || '#';

            html += `
                <a href="${actionUrl}" class="list-group-item list-group-item-action border-0" data-notification-id="${notification.id}">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="avatar rounded-circle" style="background-color: ${iconColor};">
                                <i class="align-middle" data-feather="${icon}"></i>
                            </span>
                        </div>
                        <div class="col ps-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <strong class="d-block">${escapeHtml(notification.title)}</strong>
                                    <p class="text-muted mb-0 small">${escapeHtml(notification.message)}</p>
                                </div>
                                <small class="text-muted ms-2">${timeAgo}</small>
                            </div>
                        </div>
                    </div>
                </a>
            `;
        });

        notificationsList.innerHTML = html;

        // Re-inicializar feather icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Añadir event listeners para marcar como leído al hacer clic
        document.querySelectorAll('[data-notification-id]').forEach(item => {
            item.addEventListener('click', async function(e) {
                const notificationId = this.dataset.notificationId;
                await markAsRead(notificationId);
            });
        });
    }

    /**
     * Obtener icono según tipo de notificación
     */
    function getNotificationIcon(type) {
        const icons = {
            'ticket_created': 'file-plus',
            'ticket_reply': 'message-circle',
            'ticket_assigned': 'user-check',
            'ticket_status_changed': 'check-circle',
            'default': 'bell'
        };

        return icons[type] || icons.default;
    }

    /**
     * Obtener color según tipo de notificación
     */
    function getNotificationColor(type) {
        const colors = {
            'ticket_created': '#3b7ddd',
            'ticket_reply': '#28a745',
            'ticket_assigned': '#ffc107',
            'ticket_status_changed': '#17a2b8',
            'default': '#6c757d'
        };

        return colors[type] || colors.default;
    }

    /**
     * Formatear tiempo relativo
     */
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'Ahora';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)}d`;

        return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
    }

    /**
     * Escape HTML para prevenir XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Marcar notificación como leída
     */
    async function markAsRead(notificationId) {
        try {
            const url = API_MARK_READ.replace('{id}', notificationId);
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `_csrf=${getCsrfToken()}`
            });

            if (response.ok) {
                // Actualizar contador
                await updateNotificationCount();
            }

        } catch (error) {
            console.error('Error marcando notificación como leída:', error);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    async function markAllAsRead() {
        try {
            const response = await fetch(API_MARK_ALL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `_csrf=${getCsrfToken()}`
            });

            if (response.ok) {
                // Limpiar lista y actualizar contador
                updateBadge(0);
                renderNotifications([]);
                lastCount = 0;
            }

        } catch (error) {
            console.error('Error marcando todas como leídas:', error);
        }
    }

    /**
     * Iniciar polling
     */
    function startPolling() {
        // Primera carga
        updateNotificationCount();
        loadNotifications();

        // Polling continuo
        pollTimer = setInterval(() => {
            updateNotificationCount();
        }, POLL_INTERVAL);

        console.log('Sistema de notificaciones iniciado (polling cada 30s)');
    }

    /**
     * Detener polling
     */
    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    /**
     * Event Listeners
     */
    function setupEventListeners() {
        // Marcar todas como leídas
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                await markAllAsRead();
            });
        }

        // Cargar notificaciones al abrir el dropdown
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.addEventListener('click', function() {
                loadNotifications();
            });
        }

        // Limpiar al cerrar sesión
        window.addEventListener('beforeunload', stopPolling);
    }

    /**
     * Inicialización
     */
    function init() {
        // Solo iniciar si los elementos existen
        if (!badge || !countText || !notificationsList) {
            console.warn('Elementos de notificaciones no encontrados en el DOM');
            return;
        }

        setupEventListeners();
        startPolling();
    }

    // Auto-iniciar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Exponer API pública
    window.NotificationSystem = {
        refresh: updateNotificationCount,
        markAsRead: markAsRead,
        markAllAsRead: markAllAsRead,
        startPolling: startPolling,
        stopPolling: stopPolling
    };

})();
