<?php

/* 
 * PHP Type Trainer
 * 
 * @author  mpyw
 * @github  https://github.com/mpyw/php-type-trainer
 * @license MIT
 */

namespace mpyw\PhpTypeTrainer;

spl_autoload_register(function ($class) {
    if (preg_match('/^mpyw\\\\PhpTypeTrainer(\\\\.+?)$/', $class, $m)) {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, __DIR__ . $m[1] . '.php');
        if (is_file($path)) {
            require $path;
        }
    }
});

lib\Application::run();