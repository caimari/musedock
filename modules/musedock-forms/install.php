<?php
$install=require __DIR__.'/migrate.php';$install(\Screenart\Musedock\Database::connect());
$dest=APP_ROOT.'/public/assets/modules/musedock-forms';if(!is_dir($dest))mkdir($dest,0755,true);foreach(glob(__DIR__.'/assets/*') as $file)copy($file,$dest.'/'.basename($file));
