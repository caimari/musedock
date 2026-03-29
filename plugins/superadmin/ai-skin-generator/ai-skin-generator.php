<?php
/**
 * AI Skin Generator - Main plugin file
 *
 * Generates theme skins using AI based on descriptions,
 * color palettes and existing presets as reference.
 */

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

require_once __DIR__ . '/routes.php';
