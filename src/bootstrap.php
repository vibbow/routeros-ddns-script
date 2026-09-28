<?php

error_reporting(E_ALL & ~E_DEPRECATED);

define('BASE_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('CACHE_DIR', BASE_DIR . 'cache' . DIRECTORY_SEPARATOR);
define('LOG_DIR', BASE_DIR . 'logs' . DIRECTORY_SEPARATOR);

// PSR-4: Ddns\Foo\Bar => src/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Ddns\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
