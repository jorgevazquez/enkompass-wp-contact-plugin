<?php
/**
 * Immutable description of a single form field type.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Value object describing one field type in the registry. Pure data — no
 * WordPress dependencies — so the registry can be unit tested without a WP load.
 * The human label is the untranslated source string; i18n is applied by the
 * admin layer at render time via esc_html__().
 */
final class Field_Type
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $isAction,
        public readonly bool $resizeWidth,
        public readonly bool $resizeHeight,
        public readonly bool $hasOptions,
        public readonly string $defaultCssClass,
    ) {
    }

    /** A field that captures and submits a value (i.e. not a button). */
    public function isDataField(): bool
    {
        return !$this->isAction;
    }
}
