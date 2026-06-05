<?php
/**
 * Catalog of reusable, WordPress-independent validation rules.
 *
 * Each rule is a pure predicate over a single value (plus optional params). The
 * catalog is the single source of truth for both the builder UI (rule picker)
 * and the server-side Validator. It deliberately avoids WordPress functions and
 * uses native PHP (filter_var, preg_match, DateTime) so it can be unit tested in
 * isolation and reused anywhere.
 *
 * EMPTY-VALUE POLICY: every FORMAT rule returns true for an empty string '' so
 * that optional, left-blank fields never fail a format check. Presence is the
 * sole concern of NotBlank, which is therefore exempt from that exemption.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

use DateTime;

/**
 * Static catalog of validation rules keyed by rule-key string.
 */
final class Validation_Rules
{
    /**
     * Ordered map of rule key => human label.
     *
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'NotBlank'       => 'Not Blank',
            'IsEmail'        => 'Is Email',
            'IsDate'         => 'Is Date',
            'IsZIPCode'      => 'Is ZIP Code',
            'IsNumber'       => 'Is Number',
            'IsInteger'      => 'Is Integer',
            'IsDecimal'      => 'Is Decimal',
            'IsPhone'        => 'Is Phone',
            'IsURL'          => 'Is URL',
            'IsAlpha'        => 'Is Alpha',
            'IsAlphaNumeric' => 'Is Alpha-Numeric',
            'MinLength'      => 'Minimum Length',
            'MaxLength'      => 'Maximum Length',
            'IsInRange'      => 'Is In Range',
            'RegEx'          => 'Regular Expression',
            'MatchesField'   => 'Matches Field',
        ];
    }

    /**
     * Ordered list of rule-key strings.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    public static function exists(string $key): bool
    {
        return isset(self::labels()[$key]);
    }

    public static function label(string $key): ?string
    {
        return self::labels()[$key] ?? null;
    }

    /**
     * Evaluate a rule against a value.
     *
     * @param array<string, mixed> $params Rule-specific parameters.
     */
    public static function test(string $key, mixed $value, array $params = []): bool
    {
        if (!self::exists($key)) {
            return false;
        }

        // NotBlank is the only rule that enforces presence.
        if ('NotBlank' === $key) {
            return self::notBlank($value);
        }

        // FORMAT rules: an empty string always passes (optional-field policy).
        if ('' === $value) {
            return true;
        }

        return match ($key) {
            'IsEmail'        => false !== filter_var((string) $value, FILTER_VALIDATE_EMAIL),
            'IsDate'         => self::isDate((string) $value),
            'IsZIPCode'      => 1 === preg_match('/^\d{5}(-\d{4})?$/', (string) $value),
            'IsNumber'       => is_numeric($value),
            'IsInteger'      => 1 === preg_match('/^-?\d+$/', (string) $value),
            'IsDecimal'      => 1 === preg_match('/^-?\d+(\.\d+)?$/', (string) $value),
            'IsPhone'        => 1 === preg_match('/^[\d\s()+\-.]{7,}$/', (string) $value),
            'IsURL'          => false !== filter_var((string) $value, FILTER_VALIDATE_URL),
            'IsAlpha'        => 1 === preg_match('/^[A-Za-z]+$/', (string) $value),
            'IsAlphaNumeric' => 1 === preg_match('/^[A-Za-z0-9]+$/', (string) $value),
            'MinLength'      => mb_strlen((string) $value) >= (int) ($params['length'] ?? 0),
            'MaxLength'      => mb_strlen((string) $value) <= (int) ($params['length'] ?? PHP_INT_MAX),
            'IsInRange'      => self::isInRange($value, $params),
            'RegEx'          => self::regEx((string) $value, $params),
            'MatchesField'   => (string) $value === (string) ($params['value'] ?? null),
            default          => false,
        };
    }

    private static function notBlank(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        if (is_array($value)) {
            return [] !== $value;
        }

        return '' !== trim((string) $value);
    }

    private static function isDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if (false === $date) {
            return false;
        }

        $errors = DateTime::getLastErrors();
        if (false === $errors) {
            return true;
        }

        return 0 === $errors['warning_count'] && 0 === $errors['error_count'];
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function isInRange(mixed $value, array $params): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $number = (float) $value;

        return $number >= (float) ($params['min'] ?? 0)
            && $number <= (float) ($params['max'] ?? 0);
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function regEx(string $value, array $params): bool
    {
        $pattern = $params['pattern'] ?? null;
        if (!is_string($pattern) || '' === $pattern) {
            return false;
        }

        $result = @preg_match($pattern, $value);

        return 1 === $result;
    }

    /** Maximum allowed length of an admin-supplied RegEx pattern. */
    private const MAX_PATTERN_LENGTH = 200;

    /**
     * Whitelist and coerce the parameters relevant to a given rule, dropping
     * anything unexpected. Keeps only the keys a rule actually consumes so the
     * stored definition cannot smuggle arbitrary data, and validates RegEx
     * patterns (length cap + must compile) before they are ever persisted.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function sanitize_params(string $rule, array $params): array
    {
        switch ($rule) {
            case 'MinLength':
            case 'MaxLength':
                return isset($params['length']) ? ['length' => max(0, (int) $params['length'])] : [];

            case 'IsInRange':
                $out = [];
                if (isset($params['min'])) {
                    $out['min'] = (float) $params['min'];
                }
                if (isset($params['max'])) {
                    $out['max'] = (float) $params['max'];
                }

                return $out;

            case 'RegEx':
                $pattern = isset($params['pattern']) ? (string) $params['pattern'] : '';
                if ('' === $pattern || strlen($pattern) > self::MAX_PATTERN_LENGTH) {
                    return [];
                }
                if (false === @preg_match($pattern, '')) {
                    return []; // Pattern does not compile — discard it.
                }

                return ['pattern' => $pattern];

            case 'MatchesField':
                return isset($params['value']) ? ['value' => trim(strip_tags((string) $params['value']))] : [];

            default:
                return [];
        }
    }
}
