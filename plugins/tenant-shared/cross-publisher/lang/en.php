<?php
/**
 * Cross-Publisher - English Translations
 */

return [
    // General
    'plugin_name' => 'Cross-Publisher',
    'plugin_description' => 'Publish articles across multiple outlets in your editorial network',

    // Menu
    'menu_dashboard' => 'Dashboard',
    'menu_queue' => 'Queue',
    'menu_history' => 'History',
    'menu_relations' => 'Relations',
    'menu_settings' => 'Settings',

    // Dashboard
    'dashboard_title' => 'Cross-Publisher',
    'dashboard_stats_pending' => 'Pending',
    'dashboard_stats_completed' => 'Completed today',
    'dashboard_stats_failed' => 'Failed',
    'dashboard_stats_tokens' => 'Tokens used',
    'dashboard_recent' => 'Recent publications',
    'dashboard_no_data' => 'No data to display',

    // Settings
    'settings_title' => 'Cross-Publisher Settings',
    'settings_network' => 'Network Configuration',
    'settings_network_key' => 'Network Key',
    'settings_network_key_help' => 'Shared key with other tenants in your editorial network. Only tenants with the same key can share content.',
    'settings_display_name' => 'Display Name',
    'settings_display_name_help' => 'Name that other outlets will see when selecting this tenant as destination.',
    'settings_language' => 'Primary Language',
    'settings_adaptation_prompt' => 'Adaptation Prompt',
    'settings_adaptation_prompt_help' => 'Instructions for AI when adapting incoming content to this outlet.',
    'settings_defaults' => 'Default Values',
    'settings_default_status' => 'Default status at destination',
    'settings_default_status_draft' => 'Draft',
    'settings_default_status_published' => 'Published',
    'settings_auto_translate' => 'Auto-translate',
    'settings_auto_translate_help' => 'Automatically translate when destination language differs.',
    'settings_ai_provider' => 'AI Provider',
    'settings_ai_provider_help' => 'Provider to use for translations and adaptations.',
    'settings_save' => 'Save Settings',
    'settings_saved' => 'Settings saved successfully.',
    'settings_error' => 'Error saving settings.',

    // Queue
    'queue_title' => 'Publication Queue',
    'queue_empty' => 'No publications in queue.',
    'queue_source' => 'Source',
    'queue_target' => 'Target',
    'queue_status' => 'Status',
    'queue_created' => 'Created',
    'queue_actions' => 'Actions',
    'queue_retry' => 'Retry',
    'queue_cancel' => 'Cancel',
    'queue_status_pending' => 'Pending',
    'queue_status_processing' => 'Processing',
    'queue_status_completed' => 'Completed',
    'queue_status_failed' => 'Failed',
    'queue_status_cancelled' => 'Cancelled',

    // History
    'history_title' => 'Publication History',
    'history_empty' => 'No records in history.',
    'history_action' => 'Action',
    'history_action_publish' => 'Publish',
    'history_action_translate' => 'Translate',
    'history_action_adapt' => 'Adapt',
    'history_action_sync' => 'Sync',
    'history_tokens' => 'Tokens',
    'history_date' => 'Date',

    // Relations
    'relations_title' => 'Linked Posts',
    'relations_empty' => 'No linked posts.',
    'relations_original' => 'Original post',
    'relations_copies' => 'Copies',
    'relations_sync' => 'Synchronize',
    'relations_sync_enabled' => 'Sync enabled',
    'relations_sync_disabled' => 'Sync disabled',
    'relations_last_sync' => 'Last sync',

    // Post editor panel
    'panel_title' => 'Publish to other outlets',
    'panel_no_targets' => 'No other outlets available in your network.',
    'panel_configure_link' => 'Configure network',
    'panel_select_targets' => 'Select outlets where you want to publish this article:',
    'panel_target_status' => 'Status at destination',
    'panel_translate' => 'Automatically translate if language differs',
    'panel_adapt' => 'Adapt tone/style to destination outlet',
    'panel_send' => 'Send to selected outlets',
    'panel_success' => 'Article added to cross-publication queue.',
    'panel_error' => 'Error adding to queue.',

    // Errors
    'error_not_configured' => 'Plugin not configured. Please configure the network key.',
    'error_no_targets' => 'No tenants available in the network.',
    'error_post_not_found' => 'Post not found.',
    'error_permission_denied' => 'You do not have permission to perform this action.',
    'error_translation_failed' => 'Error translating content.',
    'error_publish_failed' => 'Error publishing to destination tenant.',
];
