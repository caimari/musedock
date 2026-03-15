<?php
/**
 * Cross-Publisher - Traducciones Español
 */

return [
    // General
    'plugin_name' => 'Cross-Publisher',
    'plugin_description' => 'Publica artículos en múltiples medios de tu red editorial',

    // Menú
    'menu_dashboard' => 'Panel',
    'menu_queue' => 'Cola',
    'menu_history' => 'Historial',
    'menu_relations' => 'Relaciones',
    'menu_settings' => 'Configuración',

    // Dashboard
    'dashboard_title' => 'Cross-Publisher',
    'dashboard_stats_pending' => 'Pendientes',
    'dashboard_stats_completed' => 'Completados hoy',
    'dashboard_stats_failed' => 'Fallidos',
    'dashboard_stats_tokens' => 'Tokens usados',
    'dashboard_recent' => 'Publicaciones recientes',
    'dashboard_no_data' => 'No hay datos para mostrar',

    // Settings
    'settings_title' => 'Configuración de Cross-Publisher',
    'settings_network' => 'Configuración de Red',
    'settings_network_key' => 'Clave de Red',
    'settings_network_key_help' => 'Clave compartida con otros tenants de tu red editorial. Solo los tenants con la misma clave podrán compartir contenido.',
    'settings_display_name' => 'Nombre para mostrar',
    'settings_display_name_help' => 'Nombre que verán otros medios cuando seleccionen este tenant como destino.',
    'settings_language' => 'Idioma principal',
    'settings_adaptation_prompt' => 'Prompt de adaptación',
    'settings_adaptation_prompt_help' => 'Instrucciones para la IA cuando adapte contenido entrante a este medio.',
    'settings_defaults' => 'Valores por defecto',
    'settings_default_status' => 'Estado por defecto en destino',
    'settings_default_status_draft' => 'Borrador',
    'settings_default_status_published' => 'Publicado',
    'settings_auto_translate' => 'Traducir automáticamente',
    'settings_auto_translate_help' => 'Traducir automáticamente cuando el idioma de destino es diferente.',
    'settings_ai_provider' => 'Proveedor de IA',
    'settings_ai_provider_help' => 'Proveedor a usar para traducciones y adaptaciones.',
    'settings_save' => 'Guardar configuración',
    'settings_saved' => 'Configuración guardada correctamente.',
    'settings_error' => 'Error al guardar la configuración.',

    // Queue
    'queue_title' => 'Cola de publicaciones',
    'queue_empty' => 'No hay publicaciones en cola.',
    'queue_source' => 'Origen',
    'queue_target' => 'Destino',
    'queue_status' => 'Estado',
    'queue_created' => 'Creado',
    'queue_actions' => 'Acciones',
    'queue_retry' => 'Reintentar',
    'queue_cancel' => 'Cancelar',
    'queue_status_pending' => 'Pendiente',
    'queue_status_processing' => 'Procesando',
    'queue_status_completed' => 'Completado',
    'queue_status_failed' => 'Fallido',
    'queue_status_cancelled' => 'Cancelado',

    // History
    'history_title' => 'Historial de publicaciones',
    'history_empty' => 'No hay registros en el historial.',
    'history_action' => 'Acción',
    'history_action_publish' => 'Publicar',
    'history_action_translate' => 'Traducir',
    'history_action_adapt' => 'Adaptar',
    'history_action_sync' => 'Sincronizar',
    'history_tokens' => 'Tokens',
    'history_date' => 'Fecha',

    // Relations
    'relations_title' => 'Posts vinculados',
    'relations_empty' => 'No hay posts vinculados.',
    'relations_original' => 'Post original',
    'relations_copies' => 'Copias',
    'relations_sync' => 'Sincronizar',
    'relations_sync_enabled' => 'Sincronización activa',
    'relations_sync_disabled' => 'Sincronización desactivada',
    'relations_last_sync' => 'Última sincronización',

    // Panel en editor de posts
    'panel_title' => 'Publicar en otros medios',
    'panel_no_targets' => 'No hay otros medios disponibles en tu red.',
    'panel_configure_link' => 'Configurar red',
    'panel_select_targets' => 'Selecciona los medios donde quieres publicar este artículo:',
    'panel_target_status' => 'Estado en destino',
    'panel_translate' => 'Traducir automáticamente si el idioma es diferente',
    'panel_adapt' => 'Adaptar tono/estilo según el medio destino',
    'panel_send' => 'Enviar a medios seleccionados',
    'panel_success' => 'Artículo añadido a la cola de publicación cruzada.',
    'panel_error' => 'Error al añadir a la cola.',

    // Network
    'network_title' => 'Configuración de Red',
    'network_not_configured' => 'Este tenant no está registrado en ninguna red editorial.',
    'network_configure' => 'Configurar ahora',
    'network_active' => 'Red activa',
    'network_members' => 'Miembros de la red',
    'network_register' => 'Registrar en una red',
    'network_register_help' => 'Introduce la clave de tu red editorial para conectar con otros tenants.',
    'network_key' => 'Clave de red',
    'network_language' => 'Idioma por defecto',
    'network_can_publish' => 'Puede publicar en otros tenants',
    'network_can_receive' => 'Puede recibir de otros tenants',
    'network_register_button' => 'Registrar',
    'network_current' => 'Configuración actual',
    'network_update' => 'Guardar cambios',
    'network_no_members' => 'No hay otros miembros en esta red.',
    'network_tenant' => 'Tenant',
    'network_domain' => 'Dominio',
    'network_permissions' => 'Permisos',
    'network_registered' => 'Registrado en la red correctamente',
    'network_updated' => 'Configuración actualizada',
    'error_network_key_required' => 'La clave de red es requerida',
    'error_network_key_invalid' => 'Formato de clave inválido',

    // Stats
    'stats_pending' => 'Pendientes',
    'stats_processing' => 'Procesando',
    'stats_completed' => 'Completados',
    'stats_tokens' => 'Tokens hoy',

    // Quick Actions
    'quick_actions' => 'Acciones rápidas',
    'queue_add' => 'Publicar en red',
    'queue_process_all' => 'Procesar cola',
    'queue_recent' => 'Cola reciente',
    'queue_view_all' => 'Ver todo',

    // Queue
    'queue_filter_status' => 'Filtrar por estado',
    'queue_post' => 'Post',
    'queue_date' => 'Fecha',
    'queue_process' => 'Procesar',
    'queue_view_post' => 'Ver post',
    'queue_delete' => 'Eliminar',
    'queue_create_title' => 'Publicar en la red',
    'queue_select_post' => 'Seleccionar post',
    'queue_select_targets' => 'Seleccionar destinos',
    'queue_translation_options' => 'Opciones de traducción',
    'queue_translate' => 'Traducir contenido',
    'queue_target_language' => 'Idioma destino',
    'queue_submit' => 'Añadir a la cola',
    'queue_already_exists' => 'Los destinos seleccionados ya están en cola',
    'queue_added' => ':count items añadidos a la cola',
    'queue_processed' => 'Item procesado correctamente',
    'queue_process_error' => 'Error: :error',
    'queue_deleted' => 'Item eliminado',
    'queue_batch_success' => ':count items procesados correctamente',
    'queue_batch_failed' => ':count items fallaron',
    'no_target_tenants' => 'No hay tenants disponibles en tu red editorial.',

    // Status
    'status_pending' => 'Pendiente',
    'status_processing' => 'Procesando',
    'status_completed' => 'Completado',
    'status_failed' => 'Fallido',

    // Settings extended
    'settings_general' => 'Configuración General',
    'settings_default_status' => 'Estado por defecto de posts',
    'settings_include_image' => 'Incluir imagen destacada',
    'settings_canonical' => 'Añadir URL canónica',
    'settings_source_credit' => 'Crédito de fuente',
    'settings_add_credit' => 'Añadir crédito de fuente al final',
    'settings_credit_template' => 'Plantilla de crédito',
    'settings_enabled' => 'Plugin activo',

    // Errores
    'error_not_configured' => 'El plugin no está configurado. Por favor, configura la clave de red.',
    'error_no_targets' => 'No hay tenants disponibles en la red.',
    'error_not_found' => 'Item no encontrado',
    'error_missing_data' => 'Datos incompletos',
    'error_post_not_found' => 'Post no encontrado.',
    'error_permission_denied' => 'No tienes permiso para realizar esta acción.',
    'error_translation_failed' => 'Error al traducir el contenido.',
    'error_publish_failed' => 'Error al publicar en el tenant de destino.',
];
