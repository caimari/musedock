<?php
$install=require __DIR__.'/migrate.php';$install(\Screenart\Musedock\Database::connect());
