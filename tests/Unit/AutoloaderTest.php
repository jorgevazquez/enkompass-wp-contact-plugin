<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Autoloader;
use PHPUnit\Framework\TestCase;

final class AutoloaderTest extends TestCase
{
    public function testFilenameConvertsClassToWordPressConvention(): void
    {
        $this->assertSame(
            'class-field-registry.php',
            Autoloader::filename('Enkompass\\Contact\\Field_Registry')
        );
    }

    public function testFilenameHandlesSingleWordClass(): void
    {
        $this->assertSame(
            'class-plugin.php',
            Autoloader::filename('Enkompass\\Contact\\Plugin')
        );
    }

    public function testFilenameReturnsNullForForeignNamespace(): void
    {
        $this->assertNull(Autoloader::filename('Some\\Other\\Thing'));
    }

    public function testResolveFindsExistingClassFile(): void
    {
        $path = Autoloader::resolve(Autoloader::class);

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertStringEndsWith('includes/class-autoloader.php', $path);
    }

    public function testRegisterMakesNamespacedClassesAutoloadable(): void
    {
        Autoloader::register();

        // The Autoloader class itself resolves through our base directories.
        $this->assertTrue(class_exists('Enkompass\\Contact\\Autoloader'));
    }
}
