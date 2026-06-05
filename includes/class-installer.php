<?php
/**
 * Handles schema upgrades when the stored db version is behind the code.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Runs lightweight migrations on load.
 */
final class Installer
{
    public static function maybe_upgrade(): void
    {
        $installed = get_option('enk_db_version');

        if ($installed === ENK_DB_VERSION) {
            return;
        }

        // Bring the schema up to date (dbDelta is idempotent).
        Activator::create_tables();
        enk_uploads_dir();

        update_option('enk_db_version', ENK_DB_VERSION);
    }
}
