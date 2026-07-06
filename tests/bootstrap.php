<?php

declare(strict_types=1);

/*
 * Test bootstrap. Uses Composer's autoloader when it is present, and otherwise
 * registers a minimal PSR-4 autoloader so the suite runs with just
 * `phpunit.phar` (the SDK itself has no runtime dependencies).
 */

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';

    return;
}

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'Silon\\Tests\\' => $root . '/tests/',
        'Silon\\' => $root . '/src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
