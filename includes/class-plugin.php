<?php
/**
 * Central bootstrap that wires the admin and front-end subsystems.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

use Enkompass\Contact\Admin\Admin;
use Enkompass\Contact\Frontend\Frontend;

/**
 * Singleton application container.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private bool $booted = false;

    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        add_action('init', static function (): void {
            load_plugin_textdomain('enkompass', false, dirname(ENK_PLUGIN_BASENAME) . '/languages');
        });

        // Run idempotent migrations after plugins are loaded.
        Installer::maybe_upgrade();

        (new Admin())->register();
        (new Frontend())->register();
    }
}
