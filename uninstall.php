<?php
/**
 * Uninstall handler.
 *
 * Data durability is a core requirement: deleting a form never deletes its
 * submissions or CSV files, and by extension neither does uninstalling the
 * plugin. We therefore preserve both database tables and all CSV files, removing
 * only the lightweight option flags. A future "delete all data on uninstall"
 * setting can opt in to full removal.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('enk_db_version');
delete_option('enk_settings');

// NOTE: {prefix}enk_forms, {prefix}enk_submissions and the uploads/enkompass-forms
// directory are intentionally left intact to preserve collected data.
