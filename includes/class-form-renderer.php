<?php
/**
 * Renders a stored form definition into the Enkompass `.form-card` markup so the
 * front-end form inherits the live theme's styling.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact;

/**
 * Converts a form definition array into HTML. Depends on Field_Registry to know
 * which types are action buttons vs data fields. All dynamic content is escaped.
 */
final class Form_Renderer
{
    /**
     * Render a complete form to an HTML string.
     *
     * @param array<string, mixed> $definition
     */
    public static function render(array $definition): string
    {
        $formId    = (string) ($definition['id'] ?? 0);
        $formClass = (string) ($definition['css_class'] ?? 'form-card');
        $fields    = self::sortByTabOrder($definition['fields'] ?? []);

        $out  = '<form class="' . esc_attr($formClass) . '" data-enk-form="' . esc_attr($formId)
            . '" method="post" novalidate>';
        $out .= self::honeypot();
        $out .= wp_nonce_field('enk_submit_' . $formId, '_enk_nonce', false, false);
        $out .= '<div class="form-grid">';

        foreach ($fields as $field) {
            $out .= self::renderField($field);
        }

        $out .= '</div></form>';

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array<string, mixed>>
     */
    private static function sortByTabOrder(array $fields): array
    {
        usort(
            $fields,
            static fn (array $a, array $b): int => ((int) ($a['taborder'] ?? 0)) <=> ((int) ($b['taborder'] ?? 0))
        );

        return $fields;
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function renderField(array $field): string
    {
        $type = (string) ($field['type'] ?? '');
        $def  = Field_Registry::get($type);

        if (null === $def) {
            return '';
        }

        if ($def->isAction) {
            return self::renderAction($field, $type);
        }

        return self::renderDataField($field, $type);
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function renderDataField(array $field, string $type): string
    {
        $name     = (string) ($field['name'] ?? '');
        $label    = (string) ($field['label'] ?? '');
        $cssClass = (string) ($field['css_class'] ?? '');
        $required = !empty($field['required']);
        $tab      = (int) ($field['taborder'] ?? 0);
        $domId    = 'enk-' . sanitize_key($name !== '' ? $name : (string) ($field['id'] ?? ''));
        $wrapper  = 'field' . (self::isFullWidth($field) ? ' full' : '');

        // Choice fields (checkbox / radio) render a group rather than a single control.
        if (in_array($type, ['checkbox', 'radio'], true)) {
            return self::renderChoiceGroup($field, $type, $wrapper, $label, $required);
        }

        $labelHtml = '<label class="label" for="' . esc_attr($domId) . '">' . esc_html($label)
            . self::requiredMark($required) . '</label>';

        $control = self::renderControl($type, $name, $domId, $cssClass, $required, $tab, $field);

        return self::wrapperOpen($field, $type, $wrapper) . $labelHtml . $control . '</div>';
    }

    /**
     * Opening tag for a field wrapper, carrying the validation metadata the
     * front-end script reads to mirror server-side validation.
     *
     * @param array<string, mixed> $field
     */
    private static function wrapperOpen(array $field, string $type, string $wrapperClass): string
    {
        $name  = (string) ($field['name'] ?? '');
        $attrs = ' data-enk-name="' . esc_attr($name) . '" data-enk-type="' . esc_attr($type) . '"';

        if (!empty($field['required'])) {
            $attrs .= ' data-enk-required="1"';
        }

        $validation = array_values((array) ($field['validation'] ?? []));
        if (!empty($validation)) {
            $attrs .= ' data-enk-validate="' . esc_attr((string) wp_json_encode($validation)) . '"';
        }

        $params = (array) ($field['validation_params'] ?? []);
        if (!empty($params)) {
            $attrs .= ' data-enk-params="' . esc_attr((string) wp_json_encode($params)) . '"';
        }

        $fail = (string) ($field['fail_message'] ?? '');
        if ('' !== $fail) {
            $attrs .= ' data-enk-fail="' . esc_attr($fail) . '"';
        }

        return '<div class="' . esc_attr($wrapperClass) . '"' . $attrs . '>';
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function renderControl(
        string $type,
        string $name,
        string $domId,
        string $cssClass,
        bool $required,
        int $tab,
        array $field
    ): string {
        $req   = $required ? ' required' : '';
        $tabAt = ' tabindex="' . esc_attr((string) $tab) . '"';
        $base  = 'class="' . esc_attr($cssClass) . '" name="' . esc_attr($name) . '" id="' . esc_attr($domId) . '"' . $tabAt . $req;

        if ('comments' === $type) {
            return '<textarea ' . $base . '></textarea>';
        }

        if ('dropdown' === $type) {
            $options = '';
            foreach (self::options($field) as $opt) {
                $options .= '<option value="' . esc_attr((string) $opt['value']) . '">'
                    . esc_html((string) $opt['name']) . '</option>';
            }

            return '<select ' . $base . '>' . $options . '</select>';
        }

        $inputType = match ($type) {
            'email' => 'email',
            'date'  => 'date',
            default => 'text',
        };

        $placeholder = '';
        if (!empty($field['props']['placeholder'])) {
            $placeholder = ' placeholder="' . esc_attr((string) $field['props']['placeholder']) . '"';
        }

        return '<input type="' . $inputType . '" ' . $base . $placeholder . ' />';
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function renderChoiceGroup(
        array $field,
        string $type,
        string $wrapper,
        string $label,
        bool $required
    ): string {
        $name     = (string) ($field['name'] ?? '');
        $cssClass = (string) ($field['css_class'] ?? 'check');
        $inputTy  = 'checkbox' === $type ? 'checkbox' : 'radio';
        $inputNm  = 'checkbox' === $type ? $name . '[]' : $name;

        $out  = self::wrapperOpen($field, $type, $wrapper);
        $out .= '<span class="label">' . esc_html($label) . self::requiredMark($required) . '</span>';

        foreach (self::options($field) as $opt) {
            $out .= '<label class="' . esc_attr($cssClass) . '">'
                . '<input type="' . $inputTy . '" name="' . esc_attr($inputNm) . '" value="'
                . esc_attr((string) $opt['value']) . '"' . ($required ? ' required' : '') . ' /> '
                . esc_html((string) $opt['name']) . '</label>';
        }

        $out .= '</div>';

        return $out;
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function renderAction(array $field, string $type): string
    {
        $label    = (string) ($field['label'] ?? 'Submit');
        $cssClass = (string) ($field['css_class'] ?? 'btn btn--primary');

        $out = '<div class="form-foot">';
        $out .= '<button type="submit" class="' . esc_attr($cssClass) . '">' . esc_html($label) . '</button>';

        if ('submit_cancel' === $type) {
            $cancelLabel = (string) ($field['props']['cancel_label'] ?? 'Cancel');
            $cancelClass = (string) ($field['props']['cancel_css_class'] ?? 'btn btn--ghost');
            $out .= '<button type="button" class="' . esc_attr($cancelClass) . '" data-enk-cancel="1">'
                . esc_html($cancelLabel) . '</button>';
        }

        $out .= '</div>';

        return $out;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<int, array{name: mixed, value: mixed}>
     */
    private static function options(array $field): array
    {
        $options = $field['options'] ?? [];

        return is_array($options) ? $options : [];
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function isFullWidth(array $field): bool
    {
        return (int) ($field['grid']['w'] ?? 0) >= 12;
    }

    private static function requiredMark(bool $required): string
    {
        return $required ? ' <span class="req">*</span>' : '';
    }

    private static function honeypot(): string
    {
        return '<div class="enk-hp" aria-hidden="true" style="position:absolute;left:-9999px;height:0;overflow:hidden;">'
            . '<label>Leave this field blank'
            . '<input type="text" name="enk_hp" tabindex="-1" autocomplete="off" /></label></div>';
    }
}
