<?php
/**
 * Lightweight stand-ins for the handful of WordPress functions the plugin's
 * pure-logic classes call (escaping, i18n, sanitisation, JSON). Each is guarded
 * by function_exists so a real WordPress load always wins. Behaviour mirrors WP
 * closely enough for output-structure assertions; call-expectation testing of
 * side-effecting WP functions (wp_mail, $wpdb, …) is done with Brain Monkey or
 * the integration suite instead.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

if (!function_exists('__')) {
    function __($text, $domain = null)
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = null)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return htmlspecialchars((string) $url, ENT_QUOTES);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        $str = strip_tags((string) $str);
        $str = preg_replace('/[\r\n\t ]+/', ' ', $str);

        return trim((string) $str);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str)
    {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return (string) filter_var((string) $email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('absint')) {
    function absint($maybeint)
    {
        return abs((int) $maybeint);
    }
}

if (!function_exists('is_email')) {
    function is_email($email)
    {
        return filter_var((string) $email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data)
    {
        return (string) $data;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1)
    {
        return 'test-nonce-' . md5((string) $action);
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true)
    {
        $field = '<input type="hidden" name="' . htmlspecialchars((string) $name, ENT_QUOTES)
            . '" value="' . wp_create_nonce($action) . '" />';
        if ($display) {
            echo $field;
        }

        return $field;
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = null)
    {
        echo htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = null)
    {
        echo htmlspecialchars((string) $text, ENT_QUOTES);
    }
}
