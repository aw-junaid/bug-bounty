<?php
// Minimal vendor autoload stub
// In a real deployment run: composer install
// This stub allows the app to load without composer dependencies present

spl_autoload_register(function ($class) {
    // Map namespaces to directories
    $prefixes = [
        'Ratchet\\'  => __DIR__ . '/cboden/ratchet/src/Ratchet/',
        'React\\'    => __DIR__ . '/react/',
        'SlackClone\\' => __DIR__ . '/../src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});
