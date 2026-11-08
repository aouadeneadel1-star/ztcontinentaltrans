<?php
declare(strict_types=1);

/**
 * Amorçage de l'application : autoload, configuration, session, en-têtes de sécurité.
 */
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Autoload PSR-4 simplifié : App\Controllers\Foo => app/Controllers/Foo.php
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Helpers/functions.php';

$GLOBALS['zt_config'] = require ROOT_PATH . '/config/app.php';
$GLOBALS['zt_labels'] = require ROOT_PATH . '/config/labels.php';
date_default_timezone_set(config('timezone'));
mb_internal_encoding('UTF-8');

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

App\Helpers\Session::start();

// En-têtes de sécurité (envoyés seulement en mode web)
if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(self), geolocation=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
