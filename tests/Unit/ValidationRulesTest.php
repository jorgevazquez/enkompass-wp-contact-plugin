<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Validation_Rules;
use PHPUnit\Framework\TestCase;

final class ValidationRulesTest extends TestCase
{
    public function testKeysReturnsTheOrderedRuleCatalog(): void
    {
        $expected = [
            'NotBlank', 'IsEmail', 'IsDate', 'IsZIPCode', 'IsNumber',
            'IsInteger', 'IsDecimal', 'IsPhone', 'IsURL', 'IsAlpha',
            'IsAlphaNumeric', 'MinLength', 'MaxLength', 'IsInRange', 'RegEx',
            'MatchesField',
        ];

        $this->assertSame($expected, Validation_Rules::keys());
    }

    public function testExistsReflectsCatalogMembership(): void
    {
        $this->assertTrue(Validation_Rules::exists('IsEmail'));
        $this->assertFalse(Validation_Rules::exists('Nope'));
    }

    public function testLabelReturnsHumanLabelOrNull(): void
    {
        $this->assertSame('Is Email', Validation_Rules::label('IsEmail'));
        $this->assertNull(Validation_Rules::label('Nope'));
    }

    public function testUnknownRuleAlwaysFails(): void
    {
        $this->assertFalse(Validation_Rules::test('Nope', 'anything'));
    }

    // --- NotBlank: the only rule WITHOUT the empty-passes exemption. ---

    public function testNotBlankPassesOnNonEmptyValue(): void
    {
        $this->assertTrue(Validation_Rules::test('NotBlank', 'hello'));
    }

    public function testNotBlankFailsOnNullEmptyAndWhitespace(): void
    {
        $this->assertFalse(Validation_Rules::test('NotBlank', null));
        $this->assertFalse(Validation_Rules::test('NotBlank', ''));
        $this->assertFalse(Validation_Rules::test('NotBlank', "   \t\n"));
    }

    public function testNotBlankHandlesArrays(): void
    {
        $this->assertTrue(Validation_Rules::test('NotBlank', ['a']));
        $this->assertFalse(Validation_Rules::test('NotBlank', []));
    }

    // --- IsEmail ---

    public function testIsEmailPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsEmail', 'a@b.com'));
        $this->assertFalse(Validation_Rules::test('IsEmail', 'not-an-email'));
        $this->assertTrue(Validation_Rules::test('IsEmail', ''));
    }

    // --- IsDate ---

    public function testIsDatePassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsDate', '2026-06-05'));
        $this->assertFalse(Validation_Rules::test('IsDate', '06/05/2026'));
        $this->assertFalse(Validation_Rules::test('IsDate', '2026-13-40'));
        $this->assertTrue(Validation_Rules::test('IsDate', ''));
    }

    // --- IsZIPCode ---

    public function testIsZipCodePassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsZIPCode', '90210'));
        $this->assertTrue(Validation_Rules::test('IsZIPCode', '90210-1234'));
        $this->assertFalse(Validation_Rules::test('IsZIPCode', 'ABCDE'));
        $this->assertTrue(Validation_Rules::test('IsZIPCode', ''));
    }

    // --- IsNumber ---

    public function testIsNumberPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsNumber', '3.14'));
        $this->assertFalse(Validation_Rules::test('IsNumber', 'abc'));
        $this->assertTrue(Validation_Rules::test('IsNumber', ''));
    }

    // --- IsInteger ---

    public function testIsIntegerPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsInteger', '-42'));
        $this->assertFalse(Validation_Rules::test('IsInteger', '4.2'));
        $this->assertTrue(Validation_Rules::test('IsInteger', ''));
    }

    // --- IsDecimal ---

    public function testIsDecimalPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsDecimal', '-4.2'));
        $this->assertTrue(Validation_Rules::test('IsDecimal', '42'));
        $this->assertFalse(Validation_Rules::test('IsDecimal', '4.'));
        $this->assertTrue(Validation_Rules::test('IsDecimal', ''));
    }

    // --- IsPhone ---

    public function testIsPhonePassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsPhone', '+1 (555) 123-4567'));
        $this->assertFalse(Validation_Rules::test('IsPhone', '12345'));
        $this->assertFalse(Validation_Rules::test('IsPhone', 'phone!!'));
        $this->assertTrue(Validation_Rules::test('IsPhone', ''));
    }

    // --- IsURL ---

    public function testIsUrlPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsURL', 'https://example.com'));
        $this->assertFalse(Validation_Rules::test('IsURL', 'not a url'));
        $this->assertTrue(Validation_Rules::test('IsURL', ''));
    }

    // --- IsAlpha ---

    public function testIsAlphaPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsAlpha', 'abcXYZ'));
        $this->assertFalse(Validation_Rules::test('IsAlpha', 'abc123'));
        $this->assertTrue(Validation_Rules::test('IsAlpha', ''));
    }

    // --- IsAlphaNumeric ---

    public function testIsAlphaNumericPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsAlphaNumeric', 'abc123'));
        $this->assertFalse(Validation_Rules::test('IsAlphaNumeric', 'abc 123'));
        $this->assertTrue(Validation_Rules::test('IsAlphaNumeric', ''));
    }

    // --- MinLength (param rule) ---

    public function testMinLengthPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('MinLength', 'abcd', ['length' => 3]));
        $this->assertFalse(Validation_Rules::test('MinLength', 'ab', ['length' => 3]));
        $this->assertTrue(Validation_Rules::test('MinLength', '', ['length' => 3]));
    }

    // --- MaxLength (param rule) ---

    public function testMaxLengthPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('MaxLength', 'ab', ['length' => 3]));
        $this->assertFalse(Validation_Rules::test('MaxLength', 'abcd', ['length' => 3]));
        $this->assertTrue(Validation_Rules::test('MaxLength', '', ['length' => 3]));
    }

    // --- IsInRange (param rule) ---

    public function testIsInRangePassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('IsInRange', '5', ['min' => 1, 'max' => 10]));
        $this->assertFalse(Validation_Rules::test('IsInRange', '11', ['min' => 1, 'max' => 10]));
        $this->assertFalse(Validation_Rules::test('IsInRange', 'abc', ['min' => 1, 'max' => 10]));
        $this->assertTrue(Validation_Rules::test('IsInRange', '', ['min' => 1, 'max' => 10]));
    }

    // --- RegEx (param rule) ---

    public function testRegExPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('RegEx', 'abc', ['pattern' => '/^[a-z]+$/']));
        $this->assertFalse(Validation_Rules::test('RegEx', 'ABC', ['pattern' => '/^[a-z]+$/']));
        $this->assertTrue(Validation_Rules::test('RegEx', '', ['pattern' => '/^[a-z]+$/']));
    }

    public function testRegExReturnsFalseSafelyForMissingOrInvalidPattern(): void
    {
        $this->assertFalse(Validation_Rules::test('RegEx', 'abc'));
        $this->assertFalse(Validation_Rules::test('RegEx', 'abc', ['pattern' => '/[unterminated']));
    }

    // --- MatchesField (param rule) ---

    public function testMatchesFieldPassFailAndEmptyPolicy(): void
    {
        $this->assertTrue(Validation_Rules::test('MatchesField', 'secret', ['value' => 'secret']));
        $this->assertFalse(Validation_Rules::test('MatchesField', 'secret', ['value' => 'other']));
        $this->assertTrue(Validation_Rules::test('MatchesField', '', ['value' => 'secret']));
    }
}
