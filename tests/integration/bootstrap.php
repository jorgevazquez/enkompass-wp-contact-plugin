<?php
/**
 * Standalone integration harness: fake $wpdb + WordPress function stubs + REST
 * request/response stubs, so the plugin's integration layer (repositories, REST
 * controllers, destination dispatch) can be exercised end-to-end without a real
 * WordPress install. Run via tests/integration/smoke.php.
 *
 * This is intentionally NOT part of the PHPUnit unit suite (which stays pure).
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__, 1) . '/');
define('ENK_VERSION', '1.0.0-test');
define('ENK_DB_VERSION', '1');
define('ENK_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
define('ENK_PLUGIN_URL', 'http://example.test/wp-content/plugins/enkompass-contact/');
define('ENK_PLUGIN_BASENAME', 'enkompass-contact/enkompass-contact.php');
define('ENK_MENU_SLUG', 'enkompass-contacts');
define('ENK_CAP', 'manage_options');
define('ENK_REST_NS', 'enkompass/v1');
define('ENK_TEXT_DOMAIN', 'enkompass');

// Reuse the escaping / sanitisation / i18n / nonce stubs from the unit suite.
require_once dirname(__DIR__) . '/wp-stubs.php';

// --- additional WordPress function stubs needed by the integration layer ---

$GLOBALS['enk_test_options'] = ['enk_settings' => []];
$GLOBALS['enk_test_mail']    = [];
$GLOBALS['enk_test_upload']  = sys_get_temp_dir() . '/enk-int-' . uniqid('', true);

function get_option($name, $default = false)
{
    return $GLOBALS['enk_test_options'][$name] ?? $default;
}
function add_option($name, $value)
{
    $GLOBALS['enk_test_options'][$name] = $value;
    return true;
}
function update_option($name, $value)
{
    $GLOBALS['enk_test_options'][$name] = $value;
    return true;
}
function current_time($type)
{
    return date('Y-m-d H:i:s');
}
function wp_generate_password($length = 12, $special = true, $extra = false)
{
    return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789', 3)), 0, $length);
}
function wp_upload_dir()
{
    return ['basedir' => $GLOBALS['enk_test_upload'], 'baseurl' => 'http://example.test/uploads'];
}
function trailingslashit($s)
{
    return rtrim((string) $s, '/\\') . '/';
}
function wp_mkdir_p($dir)
{
    return is_dir($dir) || mkdir($dir, 0755, true);
}
function wp_mail($to, $subject, $body, $headers = [])
{
    $GLOBALS['enk_test_mail'][] = compact('to', 'subject', 'body', 'headers');
    return true;
}
function wp_verify_nonce($nonce, $action = -1)
{
    return 1; // Treat all nonces as valid in the harness.
}
function register_rest_route(...$args)
{
    return true;
}
function sanitize_file_name($name)
{
    return preg_replace('/[^A-Za-z0-9._-]/', '', (string) $name);
}
function sanitize_html_class($class, $fallback = '')
{
    $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class);
    return '' === $sanitized ? $fallback : $sanitized;
}

// --- REST stubs ---

class WP_Error
{
    public string $code;
    public string $message;
    public $data;
    public function __construct($code = '', $message = '', $data = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
}

class WP_REST_Response
{
    private $data;
    private int $status;
    public function __construct($data = null, int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }
    public function get_data()
    {
        return $this->data;
    }
    public function get_status(): int
    {
        return $this->status;
    }
}

class WP_REST_Request implements ArrayAccess
{
    /** @var array<string, mixed> */
    private array $params;
    public function __construct(array $params = [])
    {
        $this->params = $params;
    }
    public function get_param($key)
    {
        return $this->params[$key] ?? null;
    }
    public function offsetExists($offset): bool
    {
        return isset($this->params[$offset]);
    }
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->params[$offset] ?? null;
    }
    public function offsetSet($offset, $value): void
    {
        $this->params[$offset] = $value;
    }
    public function offsetUnset($offset): void
    {
        unset($this->params[$offset]);
    }
}

// --- fake $wpdb ---

class FakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    /** @var array<int, array<string, mixed>> */
    public array $forms = [];
    /** @var array<int, array<string, mixed>> */
    public array $subs = [];
    private array $auto = ['forms' => 0, 'subs' => 0];

    public function get_charset_collate(): string
    {
        return '';
    }

    public function prepare($query, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        foreach ($args as $a) {
            $repl = is_int($a) || is_float($a) ? (string) $a : "'" . addslashes((string) $a) . "'";
            $query = preg_replace('/%[dsf]/', $repl, (string) $query, 1);
        }
        return (string) $query;
    }

    public function insert($table, $data, $formats = null): int
    {
        if (str_contains((string) $table, 'enk_forms')) {
            $id = ++$this->auto['forms'];
            $data['id'] = $id;
            $this->forms[$id] = $data;
        } else {
            $id = ++$this->auto['subs'];
            $data['id'] = $id;
            $this->subs[$id] = $data;
        }
        $this->insert_id = $id;
        return 1;
    }

    public function update($table, $data, $where, $f = null, $wf = null): int
    {
        $id = (int) $where['id'];
        if (str_contains((string) $table, 'enk_forms') && isset($this->forms[$id])) {
            $this->forms[$id] = array_merge($this->forms[$id], $data);
            return 1;
        }
        return 0;
    }

    public function delete($table, $where, $f = null): int
    {
        $id = (int) $where['id'];
        if (str_contains((string) $table, 'enk_forms')) {
            unset($this->forms[$id]);
            return 1;
        }
        return 0;
    }

    public function get_col($query): array
    {
        return array_values(array_map(
            static fn ($r) => $r['name'],
            array_filter($this->forms, static fn ($r) => ($r['status'] ?? 'active') === 'active')
        ));
    }

    public function get_results($query, $output = null): array
    {
        if (str_contains((string) $query, 'enk_submissions')) {
            if (preg_match('/form_id = (\d+)/', (string) $query, $m)) {
                $fid = (int) $m[1];
                return array_values(array_filter($this->subs, static fn ($r) => (int) ($r['form_id'] ?? 0) === $fid));
            }
            return array_values($this->subs);
        }
        return array_values(array_filter($this->forms, static fn ($r) => ($r['status'] ?? 'active') === 'active'));
    }

    public function get_row($query, $output = null)
    {
        if (preg_match('/id = (\d+)/', (string) $query, $m)) {
            return $this->forms[(int) $m[1]] ?? null;
        }
        return null;
    }

    public function get_var($query)
    {
        if (preg_match('/form_id = (\d+)/', (string) $query, $m)) {
            $fid = (int) $m[1];
            return count(array_filter($this->subs, static fn ($r) => (int) ($r['form_id'] ?? 0) === $fid));
        }
        return 0;
    }
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['wpdb'] = new FakeWpdb();

// Load the plugin.
require_once ENK_PLUGIN_DIR . 'includes/class-autoloader.php';
\Enkompass\Contact\Autoloader::register();
require_once ENK_PLUGIN_DIR . 'includes/helpers.php';
