<?php
/**
 * Plugin Name:       Enkompass Contact
 * Plugin URI:        https://enkompass.net/
 * Description:       Build contact forms with a drag-and-drop editor, styled to match the Enkompass site, and collect submissions via email, database and CSV.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Enkompass
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       enkompass
 * Domain Path:       /languages
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('ENK_VERSION', '1.0.0');
define('ENK_DB_VERSION', '1');
define('ENK_PLUGIN_FILE', __FILE__);
define('ENK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ENK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ENK_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ENK_MENU_SLUG', 'enkompass-contacts');
define('ENK_CAP', 'manage_options');
define('ENK_REST_NS', 'enkompass/v1');
define('ENK_TEXT_DOMAIN', 'enkompass');

require_once ENK_PLUGIN_DIR . 'includes/class-autoloader.php';
\Enkompass\Contact\Autoloader::register();
require_once ENK_PLUGIN_DIR . 'includes/helpers.php';

register_activation_hook(__FILE__, ['\Enkompass\Contact\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\Enkompass\Contact\Deactivator', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    \Enkompass\Contact\Plugin::instance()->boot();
});
