<?php
/**
 * The ENK settings object (REST URL, nonce, field-type catalog) must be printed
 * before every script that reads it. field-types.js and builder.js capture
 * window.ENK once at load time, so localizing the data onto a later handle
 * leaves them with an empty object and a dead admin UI.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Enkompass\Contact\Admin\Admin;
use PHPUnit\Framework\TestCase;

final class AdminEnqueueTest extends TestCase
{
    private const HOOK = 'toplevel_page_enkompass-contacts';

    /** @var string[] Script handles in the order they were enqueued. */
    private array $enqueued = [];

    /** @var array<string, string> JS object name => handle it was localized onto. */
    private array $localized = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        if (!defined('ENK_VERSION')) { define('ENK_VERSION', '0.0.0-test'); }
        if (!defined('ENK_PLUGIN_URL')) { define('ENK_PLUGIN_URL', 'https://example.test/wp-content/plugins/enkompass-contact/'); }
        if (!defined('ENK_CAP')) { define('ENK_CAP', 'manage_options'); }
        if (!defined('ENK_MENU_SLUG')) { define('ENK_MENU_SLUG', 'enkompass-contacts'); }
        if (!defined('ENK_REST_NS')) { define('ENK_REST_NS', 'enkompass/v1'); }

        $this->enqueued = [];
        $this->localized = [];

        Functions\when('add_menu_page')->justReturn(self::HOOK);
        Functions\when('wp_enqueue_style')->justReturn(null);
        Functions\when('rest_url')->alias(static fn ($path = '') => 'https://example.test/wp-json/' . $path);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('admin_url')->alias(static fn ($path = '') => 'https://example.test/wp-admin/' . $path);

        Functions\when('wp_enqueue_script')->alias(function (string $handle): void {
            $this->enqueued[] = $handle;
        });
        Functions\when('wp_localize_script')->alias(function (string $handle, string $objectName): void {
            $this->localized[$objectName] = $handle;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testEnkDataIsLocalizedBeforeEveryScriptThatReadsIt(): void
    {
        $admin = new Admin();
        $admin->add_menu();
        $admin->enqueue(self::HOOK);

        $this->assertArrayHasKey('ENK', $this->localized, 'the ENK settings object must be localized');

        $handle = $this->localized['ENK'];
        $this->assertContains($handle, $this->enqueued, 'ENK must be attached to an enqueued script handle');

        // wp_localize_script prints the data immediately before its handle's
        // tag, and dependencies print earlier — so the data is only visible to
        // the attached handle and anything after it. Every ENK consumer must
        // therefore load at or after the attached handle.
        $dataPos = array_search($handle, $this->enqueued, true);
        foreach (['enk-field-types', 'enk-builder', 'enk-admin'] as $consumer) {
            $consumerPos = array_search($consumer, $this->enqueued, true);
            $this->assertNotFalse($consumerPos, "$consumer must be enqueued");
            $this->assertLessThanOrEqual(
                $consumerPos,
                $dataPos,
                "ENK is localized onto '$handle' which prints after '$consumer', so $consumer.js sees an empty ENK"
            );
        }
    }
}
