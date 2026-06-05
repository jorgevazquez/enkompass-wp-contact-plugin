<?php
/**
 * Admin subsystem: registers the Contacts submenu, enqueues the builder assets
 * and serves secured CSV downloads.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Admin;

use Enkompass\Contact\Field_Registry;
use Enkompass\Contact\Field_Type;
use Enkompass\Contact\Form_Repository;
use Enkompass\Contact\Validation_Rules;

/**
 * Wires all admin-side hooks.
 */
final class Admin
{
    private string $hookSuffix = '';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_enk_download_csv', [$this, 'download_csv']);
        add_action('rest_api_init', [Rest_Admin_Controller::class, 'register_routes']);
    }

    public function add_menu(): void
    {
        // Top-level menu placed directly BELOW Comments (position 25) in the
        // admin sidebar — a sibling of Comments, not a child of it.
        $this->hookSuffix = (string) add_menu_page(
            __('Contacts', 'enkompass'),
            __('Contacts', 'enkompass'),
            ENK_CAP,
            ENK_MENU_SLUG,
            [Admin_Page::class, 'render'],
            'dashicons-email-alt',
            25.5
        );
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== $this->hookSuffix) {
            return;
        }

        $base = ENK_PLUGIN_URL;
        $ver  = ENK_VERSION;

        wp_enqueue_style('enk-gridstack', $base . 'admin/css/vendor/gridstack.min.css', [], $ver);
        wp_enqueue_style('enk-admin', $base . 'admin/css/admin.css', ['enk-gridstack'], $ver);
        wp_enqueue_style('enk-builder-preview', $base . 'admin/css/builder-preview.css', ['enk-admin'], $ver);

        wp_enqueue_script('enk-gridstack', $base . 'admin/js/vendor/gridstack-all.js', [], $ver, true);
        wp_enqueue_script('enk-field-types', $base . 'admin/js/field-types.js', [], $ver, true);
        wp_enqueue_script('enk-validation', $base . 'admin/js/validation.js', [], $ver, true);
        wp_enqueue_script('enk-builder', $base . 'admin/js/builder.js', ['enk-gridstack', 'enk-field-types', 'enk-validation'], $ver, true);
        wp_enqueue_script('enk-admin', $base . 'admin/js/admin.js', ['enk-builder'], $ver, true);

        wp_localize_script('enk-admin', 'ENK', [
            'restUrl'         => esc_url_raw(rest_url(ENK_REST_NS)),
            'nonce'           => wp_create_nonce('wp_rest'),
            'csvAction'       => admin_url('admin-post.php'),
            'fieldTypes'      => self::field_types_payload(),
            'validationRules' => self::validation_rules_payload(),
            'i18n'            => [
                'confirmDelete' => __('Delete this form? Its collected data will be kept.', 'enkompass'),
                'unsaved'       => __('You have unsaved changes. Discard them?', 'enkompass'),
                'newField'      => __('New Field', 'enkompass'),
            ],
        ]);
    }

    /**
     * Build the field-type catalog for the JS builder.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function field_types_payload(): array
    {
        return array_values(array_map(
            static fn (Field_Type $t): array => [
                'key'             => $t->key,
                'label'           => $t->label,
                'isAction'        => $t->isAction,
                'resizeWidth'     => $t->resizeWidth,
                'resizeHeight'    => $t->resizeHeight,
                'hasOptions'      => $t->hasOptions,
                'defaultCssClass' => $t->defaultCssClass,
            ],
            Field_Registry::all()
        ));
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function validation_rules_payload(): array
    {
        $rules = [];
        foreach (Validation_Rules::keys() as $key) {
            $rules[] = ['key' => $key, 'label' => (string) Validation_Rules::label($key)];
        }

        return $rules;
    }

    /**
     * Stream a form's CSV file as a download after capability + nonce checks.
     */
    public function download_csv(): void
    {
        if (!current_user_can(ENK_CAP)) {
            wp_die(esc_html__('You are not allowed to do this.', 'enkompass'), '', ['response' => 403]);
        }

        $formId = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        check_admin_referer('enk_csv_' . $formId);

        $form = Form_Repository::get($formId);
        if (!$form || empty($form['csv_filename'])) {
            wp_die(esc_html__('No CSV file is available for this form.', 'enkompass'), '', ['response' => 404]);
        }

        $path = enk_uploads_dir() . $form['csv_filename'];
        if (!is_file($path)) {
            wp_die(esc_html__('The CSV file could not be found.', 'enkompass'), '', ['response' => 404]);
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($form['name'] . '.csv') . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
