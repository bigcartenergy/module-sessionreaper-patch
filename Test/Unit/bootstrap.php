<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

/*
 * Standalone bootstrap: autoloads this module and, when BCE_MAGENTO_ROOT points at a Magento installation,
 * that installation's composer autoloader (the framework and its reflection library). Inside a Magento
 * installation the unit test framework in dev/tests/unit provides its own bootstrap and this file is not used.
 * Tests that need the framework skip when it is not available.
 */
$bceModuleRoot = dirname(__DIR__, 2);
$bceMagentoRoot = getenv('BCE_MAGENTO_ROOT') ?: null;

spl_autoload_register(function (string $class) use ($bceModuleRoot): void {
    $modulePrefix = 'BigCartEnergy\\SessionReaperPatch\\';
    if (strncmp($class, $modulePrefix, strlen($modulePrefix)) === 0) {
        $file = $bceModuleRoot . '/' . str_replace('\\', '/', substr($class, strlen($modulePrefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

if ($bceMagentoRoot !== null && is_file($bceMagentoRoot . '/vendor/autoload.php')) {
    require $bceMagentoRoot . '/vendor/autoload.php';
}

if (!function_exists('__')) {
    /**
     * Minimal stand-in for Magento's translation function: placeholder replacement only.
     *
     * @param mixed ...$arguments
     */
    function __(...$arguments): string
    {
        $text = (string)array_shift($arguments);
        if ($arguments && is_array($arguments[0])) {
            $arguments = $arguments[0];
        }
        foreach (array_values($arguments) as $position => $argument) {
            $text = str_replace('%' . ($position + 1), (string)$argument, $text);
        }
        return $text;
    }
}
