<?php
/**
 * The catalog of available form field types.
 *
 * This registry is the single source of truth that drives the builder palette,
 * the frontend renderer, validation defaults and CSV header generation. It is
 * deliberately free of WordPress dependencies so it can be unit tested in
 * isolation; translation of labels happens in the admin layer.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Static registry of the eleven supported field types.
 */
final class Field_Registry
{
    /**
     * Lazily-built map of type key => Field_Type, in display order.
     *
     * @var array<string, Field_Type>|null
     */
    private static ?array $types = null;

    /**
     * Definitions: [key, label, isAction, resizeW, resizeH, hasOptions, cssClass].
     *
     * @return array<int, array{0:string,1:string,2:bool,3:bool,4:bool,5:bool,6:string}>
     */
    private static function definitions(): array
    {
        return [
            // key,            label,             action, resizeW, resizeH, options, cssClass
            ['first_name',    'First Name',       false,  true,    false,   false,   'input'],
            ['last_name',     'Last Name',        false,  true,    false,   false,   'input'],
            ['email',         'Email',            false,  true,    false,   false,   'input'],
            ['company',       'Company',          false,  true,    false,   false,   'input'],
            ['dropdown',      'Dropdown',         false,  true,    false,   true,    'select'],
            ['comments',      'Comments',         false,  true,    true,    false,   'textarea'],
            ['date',          'Date',             false,  true,    false,   false,   'input'],
            ['checkbox',      'Checkbox',         false,  false,   false,   true,    'check'],
            ['radio',         'Radio Button',     false,  false,   false,   true,    'check'],
            ['submit',        'Submit',           true,   true,    true,    false,   'btn btn--primary'],
            ['submit_cancel', 'Submit / Cancel',  true,   true,    true,    false,   'btn btn--primary'],
        ];
    }

    /**
     * All field types keyed by type key, in display order.
     *
     * @return array<string, Field_Type>
     */
    public static function all(): array
    {
        if (null === self::$types) {
            self::$types = [];
            foreach (self::definitions() as $def) {
                [$key, $label, $isAction, $resizeW, $resizeH, $hasOptions, $cssClass] = $def;
                self::$types[$key] = new Field_Type(
                    $key,
                    $label,
                    $isAction,
                    $resizeW,
                    $resizeH,
                    $hasOptions,
                    $cssClass,
                );
            }
        }

        return self::$types;
    }

    /**
     * Ordered list of type keys.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $key): ?Field_Type
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }
}
