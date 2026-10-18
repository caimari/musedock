#!/usr/bin/env php
<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/core/bootstrap.php';
\Screenart\Musedock\ModuleAutoloader::registerNamespace('MediaManager\\Controllers',APP_ROOT.'/modules/media-manager/controllers');
\Screenart\Musedock\ModuleAutoloader::registerNamespace('MediaManager\\Models',APP_ROOT.'/modules/media-manager/models');
use Screenart\Musedock\Services\Mcp\Direct\{Store,Content,ApprovalQueue};
if (!filter_var(Screenart\Musedock\Env::get('MCP_CMS_DIRECT_ENABLED',false),FILTER_VALIDATE_BOOLEAN) || !filter_var(Screenart\Musedock\Env::get('MCP_ENABLED',true),FILTER_VALIDATE_BOOLEAN)) exit;
// En un nodo réplica o cercado la base es de solo lectura: no hay nada que aplicar (y el cron fallaba cada minuto).
try {
    $__pdo=Screenart\Musedock\Database::connect();
    if ($__pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql') {
        $__ro=$__pdo->query("SELECT pg_is_in_recovery() OR current_setting('default_transaction_read_only')='on'")->fetchColumn();
        if ($__ro===true || $__ro==='t' || $__ro==='1' || $__ro===1) exit;
    }
} catch (\Throwable $__e) { /* si no se puede saber, se sigue como siempre */ }
$directory=(string)Screenart\Musedock\Env::get('MCP_CMS_PRIVATE_DIRECTORY',dirname(APP_ROOT).'/.musedock-cms');
$lock=fopen($directory.'/approvals-worker.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit;
try {
    $count=(new ApprovalQueue(new Store(),new Content(Screenart\Musedock\Database::connect())))->run();
    if ($count) echo 'Processed '.$count." approved MCP requests.\n";
} finally { flock($lock,LOCK_UN); fclose($lock); }
