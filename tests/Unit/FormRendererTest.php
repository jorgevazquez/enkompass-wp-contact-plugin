<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Form_Renderer;
use PHPUnit\Framework\TestCase;

final class FormRendererTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    private function form(array $fields, array $overrides = []): array
    {
        return array_merge([
            'id'           => 7,
            'name'         => 'FORM7',
            'title'        => 'Contact Us',
            'css_class'    => 'form-card',
            'grid'         => ['columns' => 12],
            'destinations' => [],
            'fields'       => $fields,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function field(array $overrides): array
    {
        return array_merge([
            'id'           => 'f_1',
            'type'         => 'first_name',
            'name'         => 'first_name',
            'label'        => 'First Name',
            'required'     => false,
            'taborder'     => 1,
            'css_class'    => 'input',
            'grid'         => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 1],
            'validation'   => [],
            'fail_message' => '',
            'options'      => [],
            'props'        => [],
        ], $overrides);
    }

    public function testRendersFormCardContainerWithFormId(): void
    {
        $html = Form_Renderer::render($this->form([$this->field([])]));

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('class="form-card"', $html);
        $this->assertStringContainsString('data-enk-form="7"', $html);
        $this->assertStringContainsString('class="form-grid"', $html);
    }

    public function testRendersTextInputWithLabelLinkedById(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['type' => 'first_name', 'name' => 'first_name', 'label' => 'First Name']),
        ]));

        $this->assertMatchesRegularExpression('/<div class="field[^"]*"/', $html);
        $this->assertStringContainsString('class="label"', $html);
        $this->assertStringContainsString('name="first_name"', $html);
        $this->assertStringContainsString('class="input"', $html);
        $this->assertStringContainsString('type="text"', $html);
        // label "for" matches the input "id"
        $this->assertMatchesRegularExpression('/<label class="label" for="(enk-[^"]+)">.*<input[^>]*id="\1"/s', $html);
    }

    public function testRequiredFieldRendersAsteriskAndRequiredAttribute(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['required' => true]),
        ]));

        $this->assertStringContainsString('<span class="req">*</span>', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function testEmailFieldUsesEmailInputType(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['type' => 'email', 'name' => 'email', 'label' => 'Email']),
        ]));

        $this->assertStringContainsString('type="email"', $html);
    }

    public function testCommentsFieldRendersTextarea(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['type' => 'comments', 'name' => 'message', 'label' => 'Comments', 'css_class' => 'textarea']),
        ]));

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('class="textarea"', $html);
        $this->assertStringContainsString('name="message"', $html);
    }

    public function testDropdownRendersSelectWithOptions(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field([
                'type'      => 'dropdown',
                'name'      => 'department',
                'label'     => 'Department',
                'css_class' => 'select',
                'options'   => [
                    ['name' => 'Sales', 'value' => 'sales'],
                    ['name' => 'Support', 'value' => 'support'],
                ],
            ]),
        ]));

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('class="select"', $html);
        $this->assertStringContainsString('<option value="sales">Sales</option>', $html);
        $this->assertStringContainsString('<option value="support">Support</option>', $html);
    }

    public function testCheckboxRendersCheckWrapperWithValuePerOption(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field([
                'type'      => 'checkbox',
                'name'      => 'consent',
                'label'     => 'Consent',
                'css_class' => 'check',
                'options'   => [['name' => 'Yes', 'value' => '1']],
            ]),
        ]));

        $this->assertStringContainsString('class="check"', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('Yes', $html);
    }

    public function testSubmitRendersPrimaryButtonInsideFormFoot(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['type' => 'submit', 'name' => '', 'label' => 'Send', 'css_class' => 'btn btn--primary']),
        ]));

        $this->assertStringContainsString('class="form-foot"', $html);
        $this->assertStringContainsString('class="btn btn--primary"', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('Send', $html);
    }

    public function testSubmitCancelRendersTwoButtons(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field([
                'type'      => 'submit_cancel',
                'name'      => '',
                'label'     => 'Send',
                'css_class' => 'btn btn--primary',
                'props'     => ['cancel_label' => 'Reset', 'cancel_css_class' => 'btn btn--ghost'],
            ]),
        ]));

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('class="btn btn--ghost"', $html);
        $this->assertStringContainsString('Reset', $html);
    }

    public function testFieldsAreRenderedInTabOrder(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['id' => 'f_b', 'type' => 'last_name', 'name' => 'last_name', 'label' => 'Last', 'taborder' => 2]),
            $this->field(['id' => 'f_a', 'type' => 'first_name', 'name' => 'first_name', 'label' => 'First', 'taborder' => 1]),
        ]));

        $this->assertLessThan(
            strpos($html, 'name="last_name"'),
            strpos($html, 'name="first_name"'),
            'first_name (taborder 1) should appear before last_name (taborder 2)'
        );
    }

    public function testFullWidthFieldGetsFullClass(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['grid' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1]]),
        ]));

        $this->assertMatchesRegularExpression('/class="field[^"]*\bfull\b[^"]*"/', $html);
    }

    public function testHoneypotAndNonceAreIncluded(): void
    {
        $html = Form_Renderer::render($this->form([$this->field([])]));

        $this->assertStringContainsString('_enk_nonce', $html);
        // Honeypot is a visually-hidden field bots tend to fill.
        $this->assertStringContainsString('enk-hp', $html);
    }

    public function testDataFieldWrapperCarriesValidationMetadata(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field([
                'type'         => 'email',
                'name'         => 'email',
                'label'        => 'Email',
                'required'     => true,
                'validation'   => ['IsEmail'],
                'fail_message' => 'Bad email',
            ]),
        ]));

        $this->assertStringContainsString('data-enk-name="email"', $html);
        $this->assertStringContainsString('data-enk-type="email"', $html);
        $this->assertStringContainsString('data-enk-required="1"', $html);
        $this->assertStringContainsString('data-enk-validate=', $html);
        $this->assertStringContainsString('IsEmail', $html);
        $this->assertStringContainsString('data-enk-fail="Bad email"', $html);
    }

    public function testOptionalFieldHasNoRequiredMetadata(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['required' => false]),
        ]));

        $this->assertStringNotContainsString('data-enk-required', $html);
    }

    public function testChoiceGroupWrapperAlsoCarriesMetadata(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field([
                'type'     => 'checkbox',
                'name'     => 'consent',
                'label'    => 'Consent',
                'required' => true,
                'options'  => [['name' => 'Yes', 'value' => '1']],
            ]),
        ]));

        $this->assertStringContainsString('data-enk-name="consent"', $html);
        $this->assertStringContainsString('data-enk-type="checkbox"', $html);
        $this->assertStringContainsString('data-enk-required="1"', $html);
    }

    public function testLabelContentIsEscaped(): void
    {
        $html = Form_Renderer::render($this->form([
            $this->field(['label' => '<script>alert(1)</script>']),
        ]));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
