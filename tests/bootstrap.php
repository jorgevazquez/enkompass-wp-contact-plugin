<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Loads the Composer autoloader (PHPUnit, Brain Monkey, Mockery, test classes),
 * defines the minimal WordPress constants the plugin references, and registers
 * the plugin's own class autoloader so production classes are available in tests.
 *
 * WordPress functions themselves are mocked per-test with Brain Monkey, so no
 * WordPress installation is required for the unit suite.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// WordPress function stand-ins for pure-logic unit tests.
require_once __DIR__ . '/wp-stubs.php';

// Register the plugin autoloader once it exists (it is built via TDD below).
$enk_autoloader = dirname(__DIR__) . '/includes/class-autoloader.php';
if (file_exists($enk_autoloader)) {
    require_once $enk_autoloader;
    \Enkompass\Contact\Autoloader::register();
}
