<?php
/**
 * Server-side form validation engine.
 *
 * Drives the authoritative validation pass on submission: given the raw submitted
 * values and a form definition, it returns a map of fieldName => errorMessage for
 * every invalid field. Action (button) fields and nameless fields are skipped.
 * Validation rules themselves live in Validation_Rules; this class only sequences
 * them per the form definition and enforces the required-before-format precedence.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Stateless validator that evaluates submitted values against a form definition.
 */
final class Validator
{
    /**
     * Validate submitted values against a form definition.
     *
     * @param array<string, mixed> $values     Submitted field values keyed by name.
     * @param array<string, mixed> $definition Form definition; expects a 'fields' array.
     *
     * @return array<string, string> Map of fieldName => errorMessage; empty when all valid.
     */
    public static function validate(array $values, array $definition): array
    {
        $errors = [];
        $fields = $definition['fields'] ?? [];

        if (!is_array($fields)) {
            return $errors;
        }

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            // Skip unknown and action (button) field types.
            $type = Field_Registry::get((string) ($field['type'] ?? ''));
            if (null === $type || $type->isAction) {
                continue;
            }

            // Skip fields without a usable name.
            $name = (string) ($field['name'] ?? '');
            if ('' === $name) {
                continue;
            }

            $value = $values[$name] ?? '';

            // Required check takes precedence; first failure wins.
            if (!empty($field['required']) && !Validation_Rules::test('NotBlank', $value)) {
                $errors[$name] = self::requiredMessage($field);
                continue;
            }

            // Only run format rules on a present (non-blank) value.
            if (!Validation_Rules::test('NotBlank', $value)) {
                continue;
            }

            $rules = $field['validation'] ?? [];
            if (!is_array($rules)) {
                continue;
            }

            $params = $field['validation_params'] ?? [];
            if (!is_array($params)) {
                $params = [];
            }

            foreach ($rules as $rule) {
                $rule = (string) $rule;
                if (!Validation_Rules::test($rule, $value, $params[$rule] ?? [])) {
                    $errors[$name] = self::invalidMessage($field);
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function requiredMessage(array $field): string
    {
        $message = (string) ($field['fail_message'] ?? '');

        return '' !== $message ? $message : 'This field is required.';
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function invalidMessage(array $field): string
    {
        $message = (string) ($field['fail_message'] ?? '');
        if ('' !== $message) {
            return $message;
        }

        $label = (string) ($field['label'] ?? ($field['name'] ?? ''));

        return sprintf('%s is invalid.', $label);
    }
}
