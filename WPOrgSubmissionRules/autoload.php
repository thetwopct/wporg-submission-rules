<?php
/**
 * Autoloads the standard's helper classes.
 *
 * PHP_CodeSniffer only adds a standard to its autoloader when the standard is passed to
 * --standard, so this is needed when it is referenced from another ruleset without Composer.
 */
spl_autoload_register(function ($className) {
    $prefix = 'WPOrgSubmissionRules\\Helpers\\';
    if (strpos($className, $prefix) !== 0) {
        return;
    }

    $file = __DIR__ . '/Helpers/' . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
