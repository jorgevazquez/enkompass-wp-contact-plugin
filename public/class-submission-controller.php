<?php
/**
 * Public REST endpoint that receives a form submission, validates it server-side
 * and dispatches it to every enabled destination (email, database, CSV).
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Frontend;

use Enkompass\Contact\Csv_Writer;
use Enkompass\Contact\Field_Registry;
use Enkompass\Contact\Form_Repository;
use Enkompass\Contact\Submission_Repository;
use Enkompass\Contact\Validator;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles POST enkompass/v1/submit.
 */
final class Submission_Controller
{
    public static function register_routes(): void
    {
        register_rest_route(ENK_REST_NS, '/submit', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'submit'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function submit(WP_REST_Request $request): WP_REST_Response
    {
        $formId = absint($request->get_param('form_id'));

        // CSRF: the per-form nonce injected by the renderer.
        $nonce = (string) $request->get_param('_enk_nonce');
        if (!wp_verify_nonce($nonce, 'enk_submit_' . $formId)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Security check failed. Please reload and try again.', 'enkompass')], 403);
        }

        // Honeypot: pretend success so bots do not learn they were caught.
        if ('' !== trim((string) $request->get_param('enk_hp'))) {
            return new WP_REST_Response(['success' => true], 200);
        }

        $form = Form_Repository::get($formId);
        if (!$form) {
            return new WP_REST_Response(['success' => false, 'message' => __('Form not found.', 'enkompass')], 404);
        }

        $dataFields = self::data_fields($form);
        $values     = [];
        foreach ($dataFields as $field) {
            $values[$field['name']] = self::sanitize_value($field, $request->get_param($field['name']));
        }

        $errors = Validator::validate($values, $form);
        if (!empty($errors)) {
            return new WP_REST_Response(['success' => false, 'errors' => $errors], 400);
        }

        $snapshot = array_map(
            static fn (array $f): array => ['name' => $f['name'], 'label' => $f['label'] ?? $f['name'], 'type' => $f['type']],
            $dataFields
        );

        $delivered = self::dispatch($form, $values, $snapshot, $dataFields);

        if (!$delivered) {
            return new WP_REST_Response(['success' => false, 'message' => __('We could not process your submission. Please try again later.', 'enkompass')], 500);
        }

        return new WP_REST_Response(['success' => true, 'message' => __('Thank you. Your submission has been received.', 'enkompass')], 200);
    }

    /**
     * Run every enabled destination. Returns true if at least one succeeded.
     *
     * @param array<string, mixed>             $form
     * @param array<string, mixed>             $values
     * @param array<int, array<string, mixed>> $snapshot
     * @param array<int, array<string, mixed>> $dataFields
     */
    private static function dispatch(array $form, array $values, array $snapshot, array $dataFields): bool
    {
        $destinations = $form['destinations'] ?? [];
        $anyEnabled   = false;
        $anySuccess   = false;

        if (!empty($destinations['database']['enabled'])) {
            $anyEnabled = true;
            [$ip, $ua]  = self::client_meta();
            $id = Submission_Repository::insert((int) $form['id'], (string) $form['name'], $values, $snapshot, $ip, $ua);
            $anySuccess = $anySuccess || $id > 0;
        }

        if (!empty($destinations['email']['enabled'])) {
            $anyEnabled = true;
            $anySuccess = self::send_email($form, $values, $dataFields) || $anySuccess;
        }

        if (!empty($destinations['textfile']['enabled']) && !empty($form['csv_filename'])) {
            $anyEnabled = true;
            $anySuccess = self::append_csv($form, $values, $dataFields) || $anySuccess;
        }

        // If no destination is configured, accept the submission anyway.
        return $anyEnabled ? $anySuccess : true;
    }

    /**
     * @param array<string, mixed>             $form
     * @param array<string, mixed>             $values
     * @param array<int, array<string, mixed>> $dataFields
     */
    private static function send_email(array $form, array $values, array $dataFields): bool
    {
        $recipients = array_filter((array) ($form['destinations']['email']['recipients'] ?? []));
        if (empty($recipients)) {
            return false;
        }

        $lines = [];
        foreach ($dataFields as $field) {
            $value   = $values[$field['name']] ?? '';
            $lines[] = sprintf('%s: %s', $field['label'] ?? $field['name'], self::flatten($value));
        }

        $subject = sprintf(
            /* translators: %s: form name */
            __('New submission: %s', 'enkompass'),
            $form['name']
        );
        $body    = implode("\n", $lines);
        $headers = [];

        $replyTo = self::first_email($dataFields, $values);
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $sent = false;
        foreach ($recipients as $to) {
            $sent = wp_mail($to, $subject, $body, $headers) || $sent;
        }

        return $sent;
    }

    /**
     * @param array<string, mixed>             $form
     * @param array<string, mixed>             $values
     * @param array<int, array<string, mixed>> $dataFields
     */
    private static function append_csv(array $form, array $values, array $dataFields): bool
    {
        $path   = enk_uploads_dir() . $form['csv_filename'];
        $writer = new Csv_Writer($path);

        $headers = array_map(static fn (array $f): string => $f['name'], $dataFields);
        $writer->syncHeader($headers);

        $row = [];
        foreach ($dataFields as $field) {
            $row[$field['name']] = self::flatten($values[$field['name']] ?? '');
        }
        $writer->appendRow($row);

        return true;
    }

    /**
     * Data (non-action) fields in tab order.
     *
     * @param array<string, mixed> $form
     * @return array<int, array<string, mixed>>
     */
    private static function data_fields(array $form): array
    {
        $fields = array_filter((array) ($form['fields'] ?? []), static function ($field): bool {
            $def = Field_Registry::get((string) ($field['type'] ?? ''));

            return $def && !$def->isAction && '' !== (string) ($field['name'] ?? '');
        });

        usort($fields, static fn ($a, $b): int => ((int) ($a['taborder'] ?? 0)) <=> ((int) ($b['taborder'] ?? 0)));

        return array_values($fields);
    }

    /**
     * @param array<string, mixed> $field
     * @return mixed
     */
    private static function sanitize_value(array $field, $value)
    {
        $type = (string) ($field['type'] ?? '');

        if (is_array($value)) {
            return array_map(static fn ($v) => sanitize_text_field((string) $v), $value);
        }

        $value = (string) $value;

        return match ($type) {
            'email'    => sanitize_email($value),
            'comments' => sanitize_textarea_field($value),
            default    => sanitize_text_field($value),
        };
    }

    /**
     * @param mixed $value
     */
    private static function flatten($value): string
    {
        return is_array($value) ? implode('; ', $value) : (string) $value;
    }

    /**
     * @param array<int, array<string, mixed>> $dataFields
     * @param array<string, mixed>             $values
     */
    private static function first_email(array $dataFields, array $values): ?string
    {
        foreach ($dataFields as $field) {
            if ('email' === ($field['type'] ?? '')) {
                $email = (string) ($values[$field['name']] ?? '');
                if ($email && is_email($email)) {
                    return $email;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function client_meta(): array
    {
        $settings = (array) get_option('enk_settings', []);
        if (empty($settings['store_pii'])) {
            return [null, null];
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : null;

        return [$ip, $ua];
    }
}
