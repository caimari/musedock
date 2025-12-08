<?php
/**
 * Script de instalación del sistema base de IA
 * Ejecutar desde: php core/Services/AI/install.php
 */

// Cargar dependencias
require_once __DIR__ . '/../../Database.php';

use Screenart\Musedock\Database;

try {
    // Crear tabla para proveedores de IA
    Database::query("
    CREATE TABLE IF NOT EXISTS ai_providers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        api_key VARCHAR(255),
        endpoint VARCHAR(255),
        provider_type ENUM('openai', 'claude', 'gemini', 'other') NOT NULL DEFAULT 'openai',
        model VARCHAR(100) DEFAULT 'gpt-4',
        temperature FLOAT DEFAULT 0.7,
        max_tokens INT DEFAULT 1000,
        active BOOLEAN DEFAULT 0,
        system_wide BOOLEAN DEFAULT 0,
        tenant_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (name, tenant_id)
    )
    ");

    // Crear tabla para logs de uso
    Database::query("
    CREATE TABLE IF NOT EXISTS ai_usage_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_id INT,
        prompt TEXT,
        tokens_used INT,
        status VARCHAR(50),
        user_id INT,
        user_type VARCHAR(20),
        module VARCHAR(50),
        action VARCHAR(50),
        tenant_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE SET NULL
    )
    ");

    // Crear tabla para configuraciones de IA por tenant
    Database::query("
    CREATE TABLE IF NOT EXISTS ai_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT,
        tenant_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (setting_key, tenant_id)
    )
    ");

    // Verificar si ya existe un proveedor por defecto
    $defaultProvider = Database::query("SELECT COUNT(*) FROM ai_providers WHERE provider_type = 'openai' AND system_wide = 1")->fetchColumn();

    if (!$defaultProvider) {
        // Insertar OpenAI
        Database::query("
            INSERT INTO ai_providers (name, provider_type, model, temperature, max_tokens, active, system_wide, tenant_id, created_at)
            VALUES ('OpenAI GPT-4', 'openai', 'gpt-4', 0.7, 1000, 0, 1, NULL, NOW())
        ");
        
        // Claude (desactivado por defecto)
        Database::query("
            INSERT INTO ai_providers (name, provider_type, model, temperature, max_tokens, active, system_wide, tenant_id, created_at)
            VALUES ('Anthropic Claude', 'claude', 'claude-3-opus-20240229', 0.7, 1000, 0, 1, NULL, NOW())
        ");
        
        // Gemini (desactivado por defecto)
        Database::query("
            INSERT INTO ai_providers (name, provider_type, model, temperature, max_tokens, active, system_wide, tenant_id, created_at)
            VALUES ('Google Gemini', 'gemini', 'gemini-pro', 0.7, 1000, 0, 1, NULL, NOW())
        ");
    }

    // Configuraciones por defecto
    $defaultSettings = [
        'ai_daily_token_limit' => '0',  // 0 = sin límite
        'ai_log_all_prompts' => '1',    // 1 = guardar prompts completos
        'ai_default_provider' => '1'     // ID del proveedor por defecto
    ];

    foreach ($defaultSettings as $key => $value) {
        $exists = Database::query(
            "SELECT COUNT(*) FROM ai_settings WHERE setting_key = :key AND tenant_id IS NULL",
            ['key' => $key]
        )->fetchColumn();

        if (!$exists) {
            Database::query(
                "INSERT INTO ai_settings (setting_key, setting_value, tenant_id, created_at, updated_at) 
                 VALUES (:key, :value, NULL, NOW(), NOW())",
                ['key' => $key, 'value' => $value]
            );
        }
    }

    echo "Sistema base de IA instalado correctamente\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}