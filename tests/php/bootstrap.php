<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Minimal-Konstanten damit Plugin-Code-Pfade nicht stolpern.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../');
}
if (!defined('JUMPNRUN_FILE')) {
    define('JUMPNRUN_FILE', __DIR__ . '/../../jumpnrun.php');
}
if (!defined('JUMPNRUN_DIR')) {
    define('JUMPNRUN_DIR', __DIR__ . '/../../');
}
if (!defined('JUMPNRUN_URL')) {
    define('JUMPNRUN_URL', 'https://example.test/wp-content/plugins/jumpnrun/');
}
if (!defined('JUMPNRUN_VERSION')) {
    define('JUMPNRUN_VERSION', '0.0.0-test');
}
