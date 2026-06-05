<?php
/**
 * Front-end subsystem: the [enkompass_form] shortcode, the Gutenberg block and
 * the public assets.
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

namespace Enkompass\Contact\Frontend;

use Enkompass\Contact\Form_Renderer;
use Enkompass\Contact\Form_Repository;

/**
 * Wires all front-end hooks.
 */
final class Frontend
{
    private bool $assetsRegistered = false;

    public function register(): void
    {
        add_shortcode('enkompass_form', [$this, 'shortcode']);
        add_action('init', [$this, 'register_block']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('rest_api_init', [Submission_Controller::class, 'register_routes']);
    }

    public function register_assets(): void
    {
        if ($this->assetsRegistered) {
            return;
        }
        $this->assetsRegistered = true;

        wp_register_style('enk-form', ENK_PLUGIN_URL . 'public/css/public.css', [], ENK_VERSION);
        wp_register_script('enk-form-validation', ENK_PLUGIN_URL . 'admin/js/validation.js', [], ENK_VERSION, true);
        wp_register_script('enk-form', ENK_PLUGIN_URL . 'public/js/form.js', ['enk-form-validation'], ENK_VERSION, true);

        wp_localize_script('enk-form', 'ENK_FORM', [
            'restUrl' => esc_url_raw(rest_url(ENK_REST_NS . '/submit')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    private function enqueue_assets(): void
    {
        $this->register_assets();
        wp_enqueue_style('enk-form');
        wp_enqueue_script('enk-form');
    }

    /**
     * [enkompass_form id="N"] shortcode.
     *
     * @param array<string, mixed>|string $atts
     */
    public function shortcode($atts): string
    {
        $atts = shortcode_atts(['id' => 0], (array) $atts, 'enkompass_form');
        $id   = absint($atts['id']);
        if (!$id) {
            return '';
        }

        $form = Form_Repository::get($id);
        if (!$form) {
            return '';
        }

        $this->enqueue_assets();

        return Form_Renderer::render($form);
    }

    public function register_block(): void
    {
        $block_dir = ENK_PLUGIN_DIR . 'blocks/enkompass-form';
        if (function_exists('register_block_type') && file_exists($block_dir . '/block.json')) {
            register_block_type($block_dir, [
                'render_callback' => [$this, 'render_block'],
            ]);
        }
    }

    /**
     * Server render for the Gutenberg block.
     *
     * @param array<string, mixed> $attributes
     */
    public function render_block(array $attributes): string
    {
        $id = absint($attributes['formId'] ?? 0);
        if (!$id) {
            return '';
        }

        $form = Form_Repository::get($id);
        if (!$form) {
            return '';
        }

        $this->enqueue_assets();

        return Form_Renderer::render($form);
    }
}
