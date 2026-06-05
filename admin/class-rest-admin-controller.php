<?php
/**
 * REST controller backing the form builder: list / create / read / save /
 * rename / delete forms. All routes require the manage capability.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Admin;

use Enkompass\Contact\Csv_Writer;
use Enkompass\Contact\Field_Registry;
use Enkompass\Contact\Form_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin-only REST routes under the enkompass/v1 namespace.
 */
final class Rest_Admin_Controller
{
    public static function register_routes(): void
    {
        $perm = [self::class, 'permission'];

        register_rest_route(ENK_REST_NS, '/forms', [
            ['methods' => 'GET', 'callback' => [self::class, 'list_forms'], 'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [self::class, 'create_form'], 'permission_callback' => $perm],
        ]);

        register_rest_route(ENK_REST_NS, '/forms/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [self::class, 'get_form'], 'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [self::class, 'save_form'], 'permission_callback' => $perm],
            ['methods' => 'DELETE', 'callback' => [self::class, 'delete_form'], 'permission_callback' => $perm],
        ]);

        register_rest_route(ENK_REST_NS, '/forms/(?P<id>\d+)/rename', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'rename_form'],
            'permission_callback' => $perm,
        ]);
    }

    public static function permission(): bool
    {
        return current_user_can(ENK_CAP);
    }

    public static function list_forms(): WP_REST_Response
    {
        return new WP_REST_Response(Form_Repository::all(), 200);
    }

    public static function create_form(): WP_REST_Response
    {
        $id = Form_Repository::create();

        return new WP_REST_Response(Form_Repository::get($id), 201);
    }

    public static function get_form(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $form = Form_Repository::get((int) $request['id']);
        if (!$form) {
            return new WP_Error('enk_not_found', __('Form not found.', 'enkompass'), ['status' => 404]);
        }

        return new WP_REST_Response($form, 200);
    }

    public static function rename_form(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id   = (int) $request['id'];
        $name = sanitize_text_field((string) $request->get_param('name'));

        if ('' === $name) {
            return new WP_Error('enk_invalid', __('A form name is required.', 'enkompass'), ['status' => 400]);
        }

        Form_Repository::rename($id, $name);

        return new WP_REST_Response(['ok' => true, 'name' => $name], 200);
    }

    public static function delete_form(WP_REST_Request $request): WP_REST_Response
    {
        Form_Repository::delete((int) $request['id']);

        // Data (submissions + CSV) is intentionally preserved.
        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function save_form(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id   = (int) $request['id'];
        $form = Form_Repository::get($id);
        if (!$form) {
            return new WP_Error('enk_not_found', __('Form not found.', 'enkompass'), ['status' => 404]);
        }

        $incoming = $request->get_param('definition');
        if (!is_array($incoming)) {
            return new WP_Error('enk_invalid', __('Invalid form definition.', 'enkompass'), ['status' => 400]);
        }

        $definition = self::sanitize_definition($incoming, $form);

        // Keep the CSV file + header in sync when the Text File destination is on.
        $csvFilename = $form['csv_filename'] ?? null;
        if (!empty($definition['destinations']['textfile']['enabled'])) {
            if (empty($csvFilename)) {
                $csvFilename = enk_csv_filename($id);
            }
            $definition['destinations']['textfile']['filename'] = $csvFilename;

            $headers = [];
            foreach ($definition['fields'] as $field) {
                $type = $field['type'] ?? '';
                $def  = Field_Registry::get((string) $type);
                if ($def && !$def->isAction && '' !== (string) ($field['name'] ?? '')) {
                    $headers[] = (string) $field['name'];
                }
            }
            (new Csv_Writer(enk_uploads_dir() . $csvFilename))->syncHeader($headers);
        }

        Form_Repository::save($id, $definition, $csvFilename);

        return new WP_REST_Response(Form_Repository::get($id), 200);
    }

    /**
     * Deep-sanitise an incoming form definition.
     *
     * @param array<string, mixed> $incoming
     * @param array<string, mixed> $current
     * @return array<string, mixed>
     */
    private static function sanitize_definition(array $incoming, array $current): array
    {
        $def = Form_Repository::default_definition();

        $def['id']        = (int) $current['id'];
        $def['name']      = isset($incoming['name']) ? sanitize_text_field((string) $incoming['name']) : (string) $current['name'];
        $def['title']     = isset($incoming['title']) ? sanitize_text_field((string) $incoming['title']) : '';
        $def['css_class'] = isset($incoming['css_class']) ? self::sanitize_class_list((string) $incoming['css_class']) : 'form-card';

        // Destinations.
        $email = $incoming['destinations']['email'] ?? [];
        $recipients = [];
        foreach ((array) ($email['recipients'] ?? []) as $addr) {
            $addr = sanitize_email((string) $addr);
            if ($addr && is_email($addr)) {
                $recipients[] = $addr;
            }
        }
        $def['destinations'] = [
            'email'    => ['enabled' => !empty($email['enabled']), 'recipients' => $recipients],
            'database' => ['enabled' => !empty($incoming['destinations']['database']['enabled'])],
            'textfile' => [
                'enabled'  => !empty($incoming['destinations']['textfile']['enabled']),
                'filename' => $current['csv_filename'] ?? null,
            ],
        ];

        // Fields.
        $def['fields'] = [];
        foreach ((array) ($incoming['fields'] ?? []) as $field) {
            $sanitized = self::sanitize_field((array) $field);
            if (null !== $sanitized) {
                $def['fields'][] = $sanitized;
            }
        }

        return $def;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>|null
     */
    private static function sanitize_field(array $field): ?array
    {
        $type = sanitize_key((string) ($field['type'] ?? ''));
        if (!Field_Registry::exists($type)) {
            return null;
        }

        $grid = (array) ($field['grid'] ?? []);

        $options = [];
        foreach ((array) ($field['options'] ?? []) as $opt) {
            $options[] = [
                'name'  => sanitize_text_field((string) ($opt['name'] ?? '')),
                'value' => sanitize_text_field((string) ($opt['value'] ?? '')),
            ];
        }

        $validation = array_values(array_filter(array_map(
            static fn ($r): string => sanitize_text_field((string) $r),
            (array) ($field['validation'] ?? [])
        )));

        // Persist only the parameters that the selected rules actually consume.
        $rawParams        = (array) ($field['validation_params'] ?? []);
        $validationParams = [];
        foreach ($validation as $rule) {
            $clean = \Enkompass\Contact\Validation_Rules::sanitize_params($rule, (array) ($rawParams[$rule] ?? []));
            if (!empty($clean)) {
                $validationParams[$rule] = $clean;
            }
        }

        $props = [];
        foreach ((array) ($field['props'] ?? []) as $k => $v) {
            $props[sanitize_key((string) $k)] = sanitize_text_field((string) $v);
        }

        return [
            'id'           => sanitize_key((string) ($field['id'] ?? uniqid('f_'))),
            'type'         => $type,
            'name'         => sanitize_key((string) ($field['name'] ?? '')),
            'label'        => sanitize_text_field((string) ($field['label'] ?? '')),
            'required'     => !empty($field['required']),
            'taborder'     => (int) ($field['taborder'] ?? 0),
            'css_class'    => self::sanitize_class_list((string) ($field['css_class'] ?? '')),
            'grid'         => [
                'x' => (int) ($grid['x'] ?? 0),
                'y' => (int) ($grid['y'] ?? 0),
                'w' => max(1, (int) ($grid['w'] ?? 6)),
                'h' => max(1, (int) ($grid['h'] ?? 1)),
            ],
            'validation'        => $validation,
            'validation_params' => $validationParams,
            'fail_message' => sanitize_text_field((string) ($field['fail_message'] ?? '')),
            'options'      => $options,
            'props'        => $props,
        ];
    }

    private static function sanitize_class_list(string $classes): string
    {
        $parts = preg_split('/\s+/', trim($classes)) ?: [];
        $parts = array_map('sanitize_html_class', $parts);

        return trim(implode(' ', array_filter($parts)));
    }
}
