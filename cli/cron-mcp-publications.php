#!/usr/bin/env php
<?php
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
require dirname(__DIR__).'/core/bootstrap.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Content,Scheduler};
if (!filter_var(Screenart\Musedock\Env::get('MCP_CMS_DIRECT_ENABLED',false),FILTER_VALIDATE_BOOLEAN) || !filter_var(Screenart\Musedock\Env::get('MCP_ENABLED',true),FILTER_VALIDATE_BOOLEAN)) exit;
$store=new Store();$result=(new Scheduler($store,new Content(Screenart\Musedock\Database::connect())))->run();
if ($result['published'] || $result['cancelled']) echo json_encode($result).PHP_EOL;
