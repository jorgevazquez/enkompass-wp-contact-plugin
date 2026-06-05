<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Field_Registry;
use Enkompass\Contact\Field_Type;
use PHPUnit\Framework\TestCase;

final class FieldRegistryTest extends TestCase
{
    public function testRegistryContainsTheElevenSpecifiedFieldTypes(): void
    {
        $expected = [
            'first_name', 'last_name', 'email', 'company', 'dropdown',
            'comments', 'date', 'checkbox', 'radio', 'submit', 'submit_cancel',
        ];

        $this->assertSame($expected, Field_Registry::keys());
        $this->assertCount(11, Field_Registry::all());
    }

    public function testGetReturnsFieldTypeForKnownKey(): void
    {
        $email = Field_Registry::get('email');

        $this->assertInstanceOf(Field_Type::class, $email);
        $this->assertSame('email', $email->key);
    }

    public function testGetReturnsNullForUnknownKey(): void
    {
        $this->assertNull(Field_Registry::get('nope'));
    }

    public function testExistsReflectsRegistryMembership(): void
    {
        $this->assertTrue(Field_Registry::exists('dropdown'));
        $this->assertFalse(Field_Registry::exists('nope'));
    }

    /**
     * Width-resizable: first_name, last_name, email, company, dropdown,
     * comments, date, submit, submit_cancel.
     */
    public function testWidthResizableTypesMatchSpec(): void
    {
        $widthResizable = array_keys(
            array_filter(Field_Registry::all(), static fn (Field_Type $t) => $t->resizeWidth)
        );

        $this->assertSame(
            ['first_name', 'last_name', 'email', 'company', 'dropdown', 'comments', 'date', 'submit', 'submit_cancel'],
            $widthResizable
        );
    }

    /**
     * Height-resizable: comments, submit, submit_cancel only.
     */
    public function testHeightResizableTypesMatchSpec(): void
    {
        $heightResizable = array_keys(
            array_filter(Field_Registry::all(), static fn (Field_Type $t) => $t->resizeHeight)
        );

        $this->assertSame(['comments', 'submit', 'submit_cancel'], $heightResizable);
    }

    public function testCheckboxAndRadioAreNotResizable(): void
    {
        foreach (['checkbox', 'radio'] as $key) {
            $type = Field_Registry::get($key);
            $this->assertFalse($type->resizeWidth, "$key width");
            $this->assertFalse($type->resizeHeight, "$key height");
        }
    }

    public function testActionTypesAreFlaggedAndDataTypesAreNot(): void
    {
        $this->assertTrue(Field_Registry::get('submit')->isAction);
        $this->assertTrue(Field_Registry::get('submit_cancel')->isAction);
        $this->assertFalse(Field_Registry::get('email')->isAction);
    }

    /**
     * Options repeater: dropdown, checkbox, radio.
     */
    public function testOnlyChoiceTypesHaveOptions(): void
    {
        $withOptions = array_keys(
            array_filter(Field_Registry::all(), static fn (Field_Type $t) => $t->hasOptions)
        );

        $this->assertSame(['dropdown', 'checkbox', 'radio'], $withOptions);
    }

    public function testDefaultCssClassesMatchEnkompassStyleGuide(): void
    {
        $this->assertSame('input', Field_Registry::get('first_name')->defaultCssClass);
        $this->assertSame('input', Field_Registry::get('email')->defaultCssClass);
        $this->assertSame('select', Field_Registry::get('dropdown')->defaultCssClass);
        $this->assertSame('textarea', Field_Registry::get('comments')->defaultCssClass);
        $this->assertSame('check', Field_Registry::get('checkbox')->defaultCssClass);
        $this->assertSame('check', Field_Registry::get('radio')->defaultCssClass);
        $this->assertSame('btn btn--primary', Field_Registry::get('submit')->defaultCssClass);
        $this->assertSame('btn btn--primary', Field_Registry::get('submit_cancel')->defaultCssClass);
    }

    public function testDateFieldIsWidthResizableDataFieldWithInputClass(): void
    {
        $date = Field_Registry::get('date');

        $this->assertFalse($date->isAction);
        $this->assertTrue($date->resizeWidth);
        $this->assertFalse($date->resizeHeight);
        $this->assertSame('input', $date->defaultCssClass);
    }
}
