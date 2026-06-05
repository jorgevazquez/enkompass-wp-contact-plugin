<?php
/**
 * Data-access layer for stored submissions in {prefix}enk_submissions.
 *
 * Submissions are deliberately self-describing (form name + field snapshot) and
 * are never deleted when a form is deleted.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Insert and query form submissions.
 */
final class Submission_Repository
{
    public const TABLE = 'enk_submissions';

    private static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Persist one submission. Returns the new row id (0 on failure).
     *
     * @param array<string, mixed>             $payload        field_name => value
     * @param array<int, array<string, mixed>> $fieldsSnapshot [{name,label,type}, …]
     */
    public static function insert(
        int $formId,
        string $formName,
        array $payload,
        array $fieldsSnapshot,
        ?string $ip = null,
        ?string $userAgent = null
    ): int {
        global $wpdb;

        $wpdb->insert(
            self::table(),
            [
                'form_id'         => $formId,
                'form_name'       => $formName,
                'payload'         => wp_json_encode($payload),
                'fields_snapshot' => wp_json_encode($fieldsSnapshot),
                'submitted_at'    => current_time('mysql'),
                'ip_address'      => $ip,
                'user_agent'      => $userAgent,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for_form(int $formId): array
    {
        global $wpdb;

        $table = self::table();
        $rows  = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE form_id = %d ORDER BY submitted_at DESC", $formId),
            ARRAY_A
        );

        return (array) $rows;
    }

    public static function count_for_form(int $formId): int
    {
        global $wpdb;

        $table = self::table();

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE form_id = %d", $formId)
        );
    }
}
