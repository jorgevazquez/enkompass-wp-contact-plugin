<?php
/**
 * Runs on plugin activation: creates database tables, seeds options and prepares
 * the protected uploads directory.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Activation routines.
 */
final class Activator
{
    public static function activate(): void
    {
        self::create_tables();

        if (false === get_option('enk_db_version')) {
            add_option('enk_db_version', ENK_DB_VERSION);
        } else {
            update_option('enk_db_version', ENK_DB_VERSION);
        }

        if (false === get_option('enk_settings')) {
            add_option('enk_settings', []);
        }

        // Create + harden the CSV storage directory.
        enk_uploads_dir();

        // Ship a ready-to-use Enkompass contact form on a fresh install.
        Form_Repository::maybe_seed_example();
    }

    /**
     * Create the forms and submissions tables with dbDelta.
     */
    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $forms           = $wpdb->prefix . Form_Repository::TABLE;
        $submissions     = $wpdb->prefix . Submission_Repository::TABLE;

        $sql_forms = "CREATE TABLE {$forms} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            definition longtext NOT NULL,
            csv_filename varchar(191) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY name (name),
            KEY status (status)
        ) {$charset_collate};";

        $sql_submissions = "CREATE TABLE {$submissions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned DEFAULT NULL,
            form_name varchar(191) NOT NULL,
            payload longtext NOT NULL,
            fields_snapshot longtext DEFAULT NULL,
            submitted_at datetime NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY submitted_at (submitted_at)
        ) {$charset_collate};";

        dbDelta($sql_forms);
        dbDelta($sql_submissions);
    }
}
