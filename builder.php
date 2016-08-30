<?php

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'You have to execute via CLI' . PHP_EOL;
    exit;
}
if (ini_get('phar.readonly') || ini_get('phar.require_hash')) {
    fprintf(fopen('php://stderr', 'wb'), '%s', 'You have to disable "phar.readonly" and "phar.require_hash"' . PHP_EOL);
    exit(1);
}

$autoloader = "spl_autoload_register(function (\$class) {
    if (preg_match('/^mpyw\\\\\\\\PhpTypeTrainer\\\\\\\\lib(\\\\\\\\.+?)\$/', \$class, \$m)) {
        \$path = str_replace('\\\\', DIRECTORY_SEPARATOR, 'phar://' . __FILE__ . \$m[1] . '.php');
        if (is_file(\$path)) {
            require \$path;
        }
    }
});";

$pharpath = __DIR__ . '/build/PhpTypeTrainer.phar';
if (is_file($pharpath)) {
    unlink($pharpath);
}
$phar = new \Phar($pharpath, 0, basename($pharpath));
$phar->setStub("<?php

/*
 * PHP Type Trainer
 *
 * @author  mpyw
 * @license MIT
 */

namespace mpyw\PhpTypeTrainer;

$autoloader;

lib\Application::run();

__HALT_COMPILER(); ?>");
$phar->buildFromDirectory(__DIR__ . '/src/lib');
