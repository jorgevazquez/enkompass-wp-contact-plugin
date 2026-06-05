<?php
/**
 * Data-access layer for form definitions stored in the {prefix}enk_forms table.
 *
 * The auto-numbering rule (next_form_name) is a pure function and is unit
 * tested; the $wpdb CRUD methods are exercised by the integration suite.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Create, read, update and delete stored forms.
 */
final class Form_Repository
{
    public const TABLE = 'enk_forms';

    /**
     * Compute the next auto-generated form name: one higher than the highest
     * existing "FORM<n>" name. Names that do not match that exact pattern are
     * ignored, so a fresh install (or only custom-named forms) yields "FORM1".
     *
     * @param array<int, string> $existingNames
     */
    public static function next_form_name(array $existingNames): string
    {
        $max = 0;
        foreach ($existingNames as $name) {
            if (preg_match('/^FORM(\d+)$/', (string) $name, $m)) {
                $num = (int) $m[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        return 'FORM' . ($max + 1);
    }

    /**
     * Default definition for a freshly created form.
     *
     * @return array<string, mixed>
     */
    public static function default_definition(): array
    {
        return [
            'version'      => 1,
            'title'        => '',
            'css_class'    => 'form-card',
            'grid'         => ['columns' => 12],
            'destinations' => [
                'email'    => ['enabled' => false, 'recipients' => []],
                'database' => ['enabled' => false],
                'textfile' => ['enabled' => false, 'filename' => null],
            ],
            'fields'       => [],
        ];
    }

    private static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Existing form names, used for auto-numbering.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        global $wpdb;

        $table = self::table();
        $rows  = $wpdb->get_col("SELECT name FROM {$table} WHERE status = 'active'");

        return array_map('strval', (array) $rows);
    }

    /**
     * All active forms as hydrated arrays (definition decoded, id/name attached).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        global $wpdb;

        $table = self::table();
        $rows  = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'active' ORDER BY id ASC", ARRAY_A);

        return array_map([self::class, 'hydrate'], (array) $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(int $id): ?array
    {
        global $wpdb;

        $table = self::table();
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? self::hydrate($row) : null;
    }

    /**
     * Create a new auto-named form. Returns the new form id.
     */
    public static function create(): int
    {
        global $wpdb;

        $name = self::next_form_name(self::names());
        $now  = current_time('mysql');
        $def  = self::default_definition();
        $def['name'] = $name;

        $wpdb->insert(
            self::table(),
            [
                'name'         => $name,
                'definition'   => wp_json_encode($def),
                'csv_filename' => null,
                'status'       => 'active',
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Persist an edited definition for a form.
     *
     * @param array<string, mixed> $definition
     */
    public static function save(int $id, array $definition, ?string $csvFilename = null): bool
    {
        global $wpdb;

        $data    = ['definition' => wp_json_encode($definition), 'updated_at' => current_time('mysql')];
        $formats = ['%s', '%s'];

        if (isset($definition['name'])) {
            $data['name'] = (string) $definition['name'];
            $formats[]    = '%s';
        }
        if (null !== $csvFilename) {
            $data['csv_filename'] = $csvFilename;
            $formats[]            = '%s';
        }

        return false !== $wpdb->update(self::table(), $data, ['id' => $id], $formats, ['%d']);
    }

    public static function rename(int $id, string $name): bool
    {
        global $wpdb;

        return false !== $wpdb->update(
            self::table(),
            ['name' => $name, 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Delete a form row. Submissions and any CSV file are intentionally kept.
     */
    public static function delete(int $id): bool
    {
        global $wpdb;

        return false !== $wpdb->delete(self::table(), ['id' => $id], ['%d']);
    }

    /**
     * Decode a raw DB row into a working definition array.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        $definition = json_decode((string) ($row['definition'] ?? ''), true);
        if (!is_array($definition)) {
            $definition = self::default_definition();
        }

        $definition['id']           = (int) ($row['id'] ?? 0);
        $definition['name']         = (string) ($row['name'] ?? '');
        $definition['csv_filename'] = $row['csv_filename'] ?? null;

        return $definition;
    }
}
