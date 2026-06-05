<?php
/**
 * Renders the Contacts admin page shell (tabbed: Forms / Settings).
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Admin;

use Enkompass\Contact\Form_Repository;
use Enkompass\Contact\Submission_Repository;

/**
 * Page controller for the Contacts screen.
 */
final class Admin_Page
{
    public static function render(): void
    {
        if (!current_user_can(ENK_CAP)) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'forms';
        $tab = in_array($tab, ['forms', 'settings'], true) ? $tab : 'forms';

        $cards = array_map([self::class, 'card_data'], Form_Repository::all());

        include ENK_PLUGIN_DIR . 'admin/views/page-shell.php';
    }

    /**
     * Flatten a hydrated form into the data a card template needs.
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public static function card_data(array $form): array
    {
        return [
            'id'          => (int) $form['id'],
            'name'        => (string) $form['name'],
            'fieldCount'  => is_array($form['fields'] ?? null) ? count($form['fields']) : 0,
            'submissions' => self::submission_count($form),
            'csvUrl'      => self::csv_has_data($form) ? self::csv_download_url($form) : null,
        ];
    }

    /**
     * Whether a form has a CSV file containing at least one data row.
     *
     * @param array<string, mixed> $form
     */
    public static function csv_has_data(array $form): bool
    {
        if (empty($form['csv_filename'])) {
            return false;
        }

        $path = enk_uploads_dir() . $form['csv_filename'];
        if (!is_file($path)) {
            return false;
        }

        return (new \Enkompass\Contact\Csv_Writer($path))->hasData();
    }

    /**
     * Secured download URL for a form's CSV.
     *
     * @param array<string, mixed> $form
     */
    public static function csv_download_url(array $form): string
    {
        return wp_nonce_url(
            add_query_arg(
                ['action' => 'enk_download_csv', 'form_id' => (int) $form['id']],
                admin_url('admin-post.php')
            ),
            'enk_csv_' . (int) $form['id']
        );
    }

    /**
     * Submission count for a form (for display on its card).
     *
     * @param array<string, mixed> $form
     */
    public static function submission_count(array $form): int
    {
        return Submission_Repository::count_for_form((int) $form['id']);
    }
}
