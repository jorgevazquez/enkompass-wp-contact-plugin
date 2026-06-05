<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Validation_Rules;
use PHPUnit\Framework\TestCase;

final class ValidationParamsTest extends TestCase
{
    public function testLengthParamsAreCoercedToIntAndUnknownKeysDropped(): void
    {
        $this->assertSame(['length' => 3], Validation_Rules::sanitize_params('MinLength', ['length' => '3', 'evil' => 'x']));
        $this->assertSame(['length' => 5], Validation_Rules::sanitize_params('MaxLength', ['length' => 5]));
    }

    public function testRangeParamsAreNumeric(): void
    {
        $this->assertSame(['min' => 1.5, 'max' => 10.0], Validation_Rules::sanitize_params('IsInRange', ['min' => '1.5', 'max' => '10']));
    }

    public function testValidRegexPatternIsKept(): void
    {
        $this->assertSame(['pattern' => '/^a/'], Validation_Rules::sanitize_params('RegEx', ['pattern' => '/^a/']));
    }

    public function testInvalidRegexPatternIsDropped(): void
    {
        $this->assertSame([], Validation_Rules::sanitize_params('RegEx', ['pattern' => '/[/']));
    }

    public function testOverlongRegexPatternIsDropped(): void
    {
        $long = '/' . str_repeat('a', 300) . '/';
        $this->assertSame([], Validation_Rules::sanitize_params('RegEx', ['pattern' => $long]));
    }

    public function testMatchesFieldValueIsKept(): void
    {
        $this->assertSame(['value' => 'abc'], Validation_Rules::sanitize_params('MatchesField', ['value' => 'abc']));
    }

    public function testRulesWithoutParamsReturnEmpty(): void
    {
        $this->assertSame([], Validation_Rules::sanitize_params('IsEmail', ['length' => 3, 'pattern' => '/x/']));
        $this->assertSame([], Validation_Rules::sanitize_params('NotBlank', []));
    }
}
