<?php
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Services/TranslationService.php';
require_once __DIR__ . '/../core/Security/SessionSecurity.php';

use Screenart\Musedock\Services\TranslationService;

// Iniciar sesión
session_start();

echo "<h1>Translation System Debug</h1>";

// Test 1: Check session
echo "<h2>Session Check</h2>";
echo "Session locale: " . ($_SESSION['locale'] ?? 'NOT SET') . "<br>";
echo "Cookie locale: " . ($_COOKIE['locale'] ?? 'NOT SET') . "<br>";

// Test 2: Get current locale
echo "<h2>Current Locale</h2>";
$currentLocale = TranslationService::getCurrentLocale();
echo "Current locale: {$currentLocale}<br>";

// Test 3: Load Spanish
echo "<h2>Loading Spanish (superadmin)</h2>";
TranslationService::setContext('superadmin');
TranslationService::load('es', 'superadmin');
echo "Spanish dashboard.welcome: " . TranslationService::get('dashboard.welcome', ['name' => 'Test']) . "<br>";
echo "Spanish auth.login: " . TranslationService::get('auth.login') . "<br>";

// Test 4: Load English
echo "<h2>Loading English (superadmin)</h2>";
TranslationService::load('en', 'superadmin');
echo "English dashboard.welcome: " . TranslationService::get('dashboard.welcome', ['name' => 'Test']) . "<br>";
echo "English auth.login: " . TranslationService::get('auth.login') . "<br>";

// Test 5: Using __ helper
echo "<h2>Using __ helper</h2>";
echo "Helper dashboard.title: " . __('dashboard.title') . "<br>";

// Test 6: Check translation files
echo "<h2>Translation Files</h2>";
$files = [
    '/home/user/musedock/lang/superadmin/es.json',
    '/home/user/musedock/lang/superadmin/en.json',
    '/home/user/musedock/lang/tenant/es.json',
    '/home/user/musedock/lang/tenant/en.json',
];

foreach ($files as $file) {
    $exists = file_exists($file) ? 'EXISTS' : 'NOT FOUND';
    $size = file_exists($file) ? filesize($file) : 0;
    echo "{$file}: {$exists} ({$size} bytes)<br>";
}

// Test 7: Set locale to English and test
echo "<h2>Set Locale to English</h2>";
TranslationService::setLocale('en');
echo "After setLocale('en'):<br>";
echo "Session locale: " . ($_SESSION['locale'] ?? 'NOT SET') . "<br>";
echo "Get dashboard.title: " . __('dashboard.title') . "<br>";

// Reload and test again
TranslationService::load(TranslationService::getCurrentLocale(), 'superadmin');
echo "After reload:<br>";
echo "Get dashboard.title: " . __('dashboard.title') . "<br>";
