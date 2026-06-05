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

    /**
     * A ready-to-use form mirroring the live Enkompass contact form
     * (https://enkompass.net/contact). Seeded on activation so a fresh install
     * ships with a working, correctly-styled form. The Database destination is
     * enabled so it captures submissions out of the box.
     *
     * @return array<string, mixed>
     */
    public static function example_definition(): array
    {
        $help_options = [
            ['name' => 'Select…', 'value' => ''],
            ['name' => 'Cloud Assessment / Well-Architected Review', 'value' => 'cloud_assessment'],
            ['name' => 'Migration to AWS', 'value' => 'migration'],
            ['name' => 'Cost Optimization / FinOps', 'value' => 'cost_optimization'],
            ['name' => 'Security & Compliance', 'value' => 'security_compliance'],
            ['name' => 'DevOps Automation', 'value' => 'devops'],
            ['name' => 'Managed Cloud Operations', 'value' => 'managed_ops'],
            ['name' => 'Something Else', 'value' => 'other'],
        ];

        $spend_options = [
            ['name' => 'Select…', 'value' => ''],
            ['name' => 'Under $10K', 'value' => 'under_10k'],
            ['name' => '$10K to $50K', 'value' => '10k_50k'],
            ['name' => '$50K to $250K', 'value' => '50k_250k'],
            ['name' => 'Over $250K', 'value' => 'over_250k'],
        ];

        $field = static function (array $overrides): array {
            return array_merge([
                'id'                => uniqid('f_'),
                'type'              => 'first_name',
                'name'              => '',
                'label'             => '',
                'required'          => false,
                'taborder'          => 0,
                'css_class'         => 'input',
                'grid'              => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 1],
                'validation'        => [],
                'validation_params' => [],
                'fail_message'      => '',
                'options'           => [],
                'props'             => [],
            ], $overrides);
        };

        $fields = [
            $field(['type' => 'first_name', 'name' => 'first_name', 'label' => 'First name', 'required' => true, 'taborder' => 1, 'grid' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 1], 'fail_message' => 'Please enter your first name.']),
            $field(['type' => 'last_name', 'name' => 'last_name', 'label' => 'Last name', 'required' => true, 'taborder' => 2, 'grid' => ['x' => 6, 'y' => 0, 'w' => 6, 'h' => 1], 'fail_message' => 'Please enter your last name.']),
            $field(['type' => 'email', 'name' => 'email', 'label' => 'Work email', 'required' => true, 'taborder' => 3, 'grid' => ['x' => 0, 'y' => 1, 'w' => 6, 'h' => 1], 'validation' => ['IsEmail'], 'fail_message' => 'Please enter a valid work email.', 'props' => ['placeholder' => 'you@company.com']]),
            $field(['type' => 'company', 'name' => 'company', 'label' => 'Company', 'required' => false, 'taborder' => 4, 'grid' => ['x' => 6, 'y' => 1, 'w' => 6, 'h' => 1]]),
            $field(['type' => 'dropdown', 'name' => 'help_with', 'label' => 'What can we help with?', 'required' => true, 'taborder' => 5, 'css_class' => 'select', 'grid' => ['x' => 0, 'y' => 2, 'w' => 12, 'h' => 1], 'options' => $help_options, 'fail_message' => 'Please choose an option.']),
            $field(['type' => 'dropdown', 'name' => 'monthly_spend', 'label' => 'Approx. monthly AWS spend', 'required' => true, 'taborder' => 6, 'css_class' => 'select', 'grid' => ['x' => 0, 'y' => 3, 'w' => 12, 'h' => 1], 'options' => $spend_options, 'fail_message' => 'Please choose an option.']),
            $field(['type' => 'comments', 'name' => 'message', 'label' => 'Tell us more', 'required' => true, 'taborder' => 7, 'css_class' => 'textarea', 'grid' => ['x' => 0, 'y' => 4, 'w' => 12, 'h' => 3], 'fail_message' => 'Please tell us a bit more.']),
            $field(['type' => 'checkbox', 'name' => 'privacy_agree', 'label' => 'Privacy Policy', 'required' => true, 'taborder' => 8, 'css_class' => 'check', 'grid' => ['x' => 0, 'y' => 5, 'w' => 12, 'h' => 1], 'options' => [['name' => 'I agree to the Privacy Policy', 'value' => '1']], 'fail_message' => 'Please accept the privacy policy to continue.']),
            $field(['type' => 'submit', 'name' => '', 'label' => 'Send message', 'taborder' => 9, 'css_class' => 'btn btn--primary', 'grid' => ['x' => 0, 'y' => 6, 'w' => 12, 'h' => 1]]),
        ];

        return [
            'version'      => 1,
            'title'        => 'Contact Us',
            'css_class'    => 'form-card',
            'grid'         => ['columns' => 12],
            'destinations' => [
                'email'    => ['enabled' => false, 'recipients' => []],
                'database' => ['enabled' => true],
                'textfile' => ['enabled' => false, 'filename' => null],
            ],
            'fields'       => $fields,
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
     * Create a form with a given name and definition. Returns the new id.
     *
     * @param array<string, mixed> $definition
     */
    public static function create_with(string $name, array $definition): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $definition['name'] = $name;

        $wpdb->insert(
            self::table(),
            [
                'name'         => $name,
                'definition'   => wp_json_encode($definition),
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
     * Seed the bundled Enkompass example form, but only when no forms exist yet,
     * so it never duplicates on re-activation or clobbers a user's work.
     */
    public static function maybe_seed_example(): void
    {
        if (empty(self::names())) {
            self::create_with('Contact', self::example_definition());
        }
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
