<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Form_Renderer;
use Enkompass\Contact\Form_Repository;
use Enkompass\Contact\Validator;
use PHPUnit\Framework\TestCase;

final class ExampleFormTest extends TestCase
{
    /** @return array<string, mixed> */
    private function def(): array
    {
        return Form_Repository::example_definition();
    }

    public function testUsesTheEnkompassFormCardWrapper(): void
    {
        $this->assertSame('form-card', $this->def()['css_class']);
    }

    public function testFieldTypesMatchTheLiveEnkompassContactForm(): void
    {
        $types = array_map(static fn (array $f): string => $f['type'], $this->def()['fields']);

        $this->assertSame(
            ['first_name', 'last_name', 'email', 'company', 'dropdown', 'dropdown', 'comments', 'checkbox', 'submit'],
            $types
        );
    }

    public function testFieldNamesAreUniqueSanitizedKeys(): void
    {
        $names = array_values(array_filter(array_map(
            static fn (array $f): string => (string) $f['name'],
            $this->def()['fields']
        )));

        $this->assertSame($names, array_unique($names), 'names must be unique');
        foreach ($names as $name) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $name);
        }
    }

    public function testEmailFieldIsRequiredAndValidatesAsEmail(): void
    {
        $email = $this->fieldByName('email');

        $this->assertTrue($email['required']);
        $this->assertContains('IsEmail', $email['validation']);
        $this->assertNotSame('', $email['fail_message']);
        $this->assertSame('input', $email['css_class']);
    }

    public function testHelpDropdownHasTheSiteOptions(): void
    {
        $help    = $this->fieldByName('help_with');
        $labels  = array_map(static fn (array $o): string => $o['name'], $help['options']);

        $this->assertTrue($help['required']);
        $this->assertSame('select', $help['css_class']);
        $this->assertContains('Migration to AWS', $labels);
        $this->assertContains('Security & Compliance', $labels);
    }

    public function testSpendDropdownHasTheSiteOptions(): void
    {
        $labels = array_map(static fn (array $o): string => $o['name'], $this->fieldByName('monthly_spend')['options']);

        $this->assertContains('Over $250K', $labels);
        $this->assertContains('Under $10K', $labels);
    }

    public function testRequiredDropdownsHaveABlankPlaceholderOption(): void
    {
        foreach (['help_with', 'monthly_spend'] as $name) {
            $first = $this->fieldByName($name)['options'][0];
            $this->assertSame('', $first['value'], "$name first option must be a blank placeholder");
        }
    }

    public function testPrivacyCheckboxIsRequiredWithASingleOption(): void
    {
        $privacy = $this->fieldByName('privacy_agree');

        $this->assertSame('checkbox', $privacy['type']);
        $this->assertTrue($privacy['required']);
        $this->assertCount(1, $privacy['options']);
        $this->assertSame('check', $privacy['css_class']);
    }

    public function testSubmitButtonSaysSendMessage(): void
    {
        $fields = $this->def()['fields'];
        $last   = end($fields);

        $this->assertSame('submit', $last['type']);
        $this->assertSame('Send message', $last['label']);
        $this->assertSame('btn btn--primary', $last['css_class']);
    }

    public function testCommentsFieldIsAFullWidthRequiredTextarea(): void
    {
        $message = $this->fieldByName('message');

        $this->assertSame('comments', $message['type']);
        $this->assertTrue($message['required']);
        $this->assertGreaterThanOrEqual(12, $message['grid']['w']);
    }

    public function testRendersWithoutErrorAndContainsTheKeyControls(): void
    {
        $def       = $this->def();
        $def['id'] = 1;
        $html      = Form_Renderer::render($def);

        $this->assertStringContainsString('class="form-card"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('Send message', $html);
    }

    public function testServerValidationRejectsAnEmptySubmissionForEveryRequiredField(): void
    {
        $errors = Validator::validate([], $this->def());

        foreach (['first_name', 'last_name', 'email', 'help_with', 'monthly_spend', 'message', 'privacy_agree'] as $required) {
            $this->assertArrayHasKey($required, $errors, "$required should be required");
        }
        $this->assertArrayNotHasKey('company', $errors, 'company is optional');
    }

    /** @return array<string, mixed> */
    private function fieldByName(string $name): array
    {
        foreach ($this->def()['fields'] as $field) {
            if (($field['name'] ?? null) === $name) {
                return $field;
            }
        }
        $this->fail("field $name not found");
    }
}
