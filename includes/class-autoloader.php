<?php
/**
 * PSR-4-style autoloader that maps the plugin namespace to WordPress-convention
 * class files (e.g. Enkompass\Contact\Field_Registry => class-field-registry.php).
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

if (!defined('ABSPATH') && !defined('ENK_TESTS')) {
    // Allow loading in the test bootstrap (which defines ABSPATH) and at runtime.
    // No exit here so the file remains require-able by tooling.
}

/**
 * Resolves and loads plugin classes from the includes/, admin/ and public/ dirs.
 */
final class Autoloader
{
    /** Root namespace handled by this autoloader. */
    private const NAMESPACE_PREFIX = 'Enkompass\\Contact\\';

    /** Directories (relative to the plugin root) searched for class files. */
    private const BASE_DIRS = ['includes', 'admin', 'public'];

    /**
     * Convert a fully-qualified class name to its WordPress-convention filename.
     *
     * Returns null when the class is outside the plugin namespace.
     */
    public static function filename(string $fqcn): ?string
    {
        if (!str_starts_with($fqcn, self::NAMESPACE_PREFIX)) {
            return null;
        }

        $relative  = substr($fqcn, strlen(self::NAMESPACE_PREFIX));
        $segments  = explode('\\', $relative);
        $shortName = (string) array_pop($segments);
        $slug      = str_replace('_', '-', strtolower($shortName));

        return 'class-' . $slug . '.php';
    }

    /**
     * Resolve a class name to an existing file path, or null if none exists.
     */
    public static function resolve(string $fqcn): ?string
    {
        $filename = self::filename($fqcn);
        if (null === $filename) {
            return null;
        }

        $root = dirname(__DIR__);
        foreach (self::BASE_DIRS as $dir) {
            $path = $root . '/' . $dir . '/' . $filename;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Register the autoloader with the SPL stack.
     */
    public static function register(): void
    {
        spl_autoload_register(static function (string $fqcn): void {
            $path = self::resolve($fqcn);
            if (null !== $path) {
                require_once $path;
            }
        });
    }
}
