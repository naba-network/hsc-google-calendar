<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Constant used at class load time; WordPress defines it in production.
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

require_once dirname(__DIR__) . '/includes/class-updater.php';
require_once dirname(__DIR__) . '/bin/Bumper.php';
