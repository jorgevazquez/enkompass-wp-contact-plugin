<?php
/**
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Tests\Unit;

use Enkompass\Contact\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function emailField(array $overrides = []): array
    {
        return array_merge([
            'type'         => 'email',
            'name'         => 'email',
            'label'        => 'Email',
            'required'     => false,
            'validation'   => ['IsEmail'],
            'fail_message' => '',
        ], $overrides);
    }

    public function testValidFormReturnsEmptyArray(): void
    {
        $definition = [
            'fields' => [
                $this->emailField(['required' => true]),
            ],
        ];

        $this->assertSame([], Validator::validate(['email' => 'a@b.com'], $definition));
    }

    public function testRequiredBlankFieldReturnsItsFailMessage(): void
    {
        $definition = [
            'fields' => [
                $this->emailField([
                    'required'     => true,
                    'fail_message' => 'We need your email.',
                ]),
            ],
        ];

        $this->assertSame(
            ['email' => 'We need your email.'],
            Validator::validate(['email' => ''], $definition)
        );
    }

    public function testRequiredBlankFieldFallsBackToDefaultMessage(): void
    {
        $definition = [
            'fields' => [
                $this->emailField(['required' => true, 'fail_message' => '']),
            ],
        ];

        $this->assertSame(
            ['email' => 'This field is required.'],
            Validator::validate(['email' => ''], $definition)
        );
    }

    public function testInvalidEmailReturnsError(): void
    {
        $definition = [
            'fields' => [
                $this->emailField([
                    'required'     => true,
                    'fail_message' => 'Bad email.',
                ]),
            ],
        ];

        $this->assertSame(
            ['email' => 'Bad email.'],
            Validator::validate(['email' => 'nope'], $definition)
        );
    }

    public function testInvalidFieldFallsBackToLabelMessage(): void
    {
        $definition = [
            'fields' => [
                $this->emailField([
                    'required'     => true,
                    'fail_message' => '',
                    'label'        => 'Email',
                ]),
            ],
        ];

        $this->assertSame(
            ['email' => 'Email is invalid.'],
            Validator::validate(['email' => 'nope'], $definition)
        );
    }

    public function testActionFieldIsIgnored(): void
    {
        $definition = [
            'fields' => [
                [
                    'type'         => 'submit',
                    'name'         => 'submit',
                    'label'        => 'Send',
                    'required'     => true,
                    'validation'   => ['NotBlank'],
                    'fail_message' => 'should never appear',
                ],
            ],
        ];

        $this->assertSame([], Validator::validate([], $definition));
    }

    public function testFieldWithoutNameIsIgnored(): void
    {
        $definition = [
            'fields' => [
                $this->emailField(['name' => '', 'required' => true]),
            ],
        ];

        $this->assertSame([], Validator::validate([], $definition));
    }

    public function testUnknownFieldTypeIsIgnored(): void
    {
        $definition = [
            'fields' => [
                [
                    'type'         => 'mystery',
                    'name'         => 'mystery',
                    'label'        => 'Mystery',
                    'required'     => true,
                    'validation'   => ['NotBlank'],
                    'fail_message' => 'should never appear',
                ],
            ],
        ];

        $this->assertSame([], Validator::validate([], $definition));
    }

    public function testRequiredPrecedenceReportsRequiredMessageNotFormatMessage(): void
    {
        $definition = [
            'fields' => [
                $this->emailField([
                    'required'     => true,
                    'validation'   => ['IsEmail'],
                    'fail_message' => 'Email is required and must be valid.',
                ]),
            ],
        ];

        // A blank required field reports the fail_message via the required check,
        // and must NOT run the format rule afterwards.
        $this->assertSame(
            ['email' => 'Email is required and must be valid.'],
            Validator::validate(['email' => ''], $definition)
        );
    }

    public function testOptionalBlankFieldWithFormatRuleRecordsNoError(): void
    {
        $definition = [
            'fields' => [
                $this->emailField([
                    'required'   => false,
                    'validation' => ['IsEmail'],
                ]),
            ],
        ];

        $this->assertSame([], Validator::validate(['email' => ''], $definition));
    }

    public function testFirstFailureWinsAmongMultipleRules(): void
    {
        $definition = [
            'fields' => [
                [
                    'type'              => 'first_name',
                    'name'              => 'username',
                    'label'             => 'Username',
                    'required'          => true,
                    'validation'        => ['MinLength', 'IsAlphaNumeric'],
                    'fail_message'      => 'Username is no good.',
                    'validation_params' => [
                        'MinLength' => ['length' => 5],
                    ],
                ],
            ],
        ];

        // 'ab' fails MinLength (first rule), so the single recorded error is the
        // field's fail_message; the second rule is not consulted.
        $this->assertSame(
            ['username' => 'Username is no good.'],
            Validator::validate(['username' => 'ab'], $definition)
        );
    }

    public function testValidationParamsArePassedThrough(): void
    {
        $definition = [
            'fields' => [
                [
                    'type'              => 'first_name',
                    'name'              => 'pin',
                    'label'             => 'PIN',
                    'required'          => true,
                    'validation'        => ['MinLength'],
                    'fail_message'      => '',
                    'validation_params' => [
                        'MinLength' => ['length' => 4],
                    ],
                ],
            ],
        ];

        $this->assertSame([], Validator::validate(['pin' => '1234'], $definition));
        $this->assertSame(
            ['pin' => 'PIN is invalid.'],
            Validator::validate(['pin' => '12'], $definition)
        );
    }

    public function testMultipleFieldsEachReportIndependently(): void
    {
        $definition = [
            'fields' => [
                $this->emailField(['required' => true, 'fail_message' => 'Bad email.']),
                [
                    'type'         => 'first_name',
                    'name'         => 'first_name',
                    'label'        => 'First Name',
                    'required'     => true,
                    'validation'   => [],
                    'fail_message' => 'First name required.',
                ],
            ],
        ];

        $this->assertSame(
            [
                'email'      => 'Bad email.',
                'first_name' => 'First name required.',
            ],
            Validator::validate(['email' => 'nope', 'first_name' => ''], $definition)
        );
    }
}
