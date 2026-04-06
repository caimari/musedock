<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared";
} else {
    echo "opcache_reset not available";
}
unlink(__FILE__); // Auto-delete
