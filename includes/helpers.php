<?php
/**
 * Procedural helpers shared across the plugin.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Absolute filesystem path (with trailing slash) to the protected directory that
 * stores per-form CSV files. The directory is created and hardened on demand.
 */
function enk_uploads_dir(): string
{
    $uploads = wp_upload_dir();
    $dir     = trailingslashit($uploads['basedir']) . 'enkompass-forms/';

    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }

    enk_protect_dir($dir);

    return $dir;
}

/**
 * Drop an index.php and a deny-all .htaccess into a directory so its contents
 * cannot be listed or downloaded directly over the web.
 */
function enk_protect_dir(string $dir): void
{
    $dir = trailingslashit($dir);

    $index = $dir . 'index.php';
    if (!file_exists($index)) {
        file_put_contents($index, "<?php\n// Silence is golden.\n");
    }

    $htaccess = $dir . '.htaccess';
    if (!file_exists($htaccess)) {
        $rules = "# Enkompass Contact - deny direct access to stored submissions\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
        file_put_contents($htaccess, $rules);
    }
}

/**
 * Generate a unique, hard-to-guess CSV filename for a form.
 */
function enk_csv_filename(int $formId): string
{
    return 'form-' . $formId . '-' . wp_generate_password(12, false, false) . '.csv';
}

/**
 * Convenience accessor for a hydrated form definition.
 *
 * @return array<string, mixed>|null
 */
function enk_get_form(int $id): ?array
{
    return \Enkompass\Contact\Form_Repository::get($id);
}
