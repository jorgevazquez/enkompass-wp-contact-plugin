<?php
/**
 * Runs on plugin deactivation. Intentionally preserves all data (forms,
 * submissions and CSV files); only transient/runtime state is cleared.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Deactivation routines.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
