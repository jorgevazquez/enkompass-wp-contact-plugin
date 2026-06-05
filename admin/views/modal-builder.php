<?php
/**
 * Form-builder modal skeleton. Hydrated by admin/js/builder.js.
 *
 * Layout (left → right): properties sidebar (20%), form grid (50%),
 * palette + actions sidebar (20%).
 *
 * @package Enkompass\Contact
 */

defined('ABSPATH') || exit;
?>
<div class="enk-modal-overlay" id="enk-modal" hidden>
    <div class="enk-modal" role="dialog" aria-modal="true" aria-labelledby="enk-modal-title">
        <button type="button" class="enk-modal-close" id="enk-modal-close"
                aria-label="<?php esc_attr_e('Close', 'enkompass'); ?>">&times;</button>

        <h2 id="enk-modal-title" class="enk-modal-title screen-reader-text">
            <?php esc_html_e('Edit form', 'enkompass'); ?>
        </h2>

        <div class="enk-modal-body">
            <aside class="enk-sidebar enk-sidebar-left" id="enk-props" aria-label="<?php esc_attr_e('Properties', 'enkompass'); ?>">
                <div class="enk-props-empty"><?php esc_html_e('Select a field, or click empty space to edit form settings.', 'enkompass'); ?></div>
            </aside>

            <section class="enk-grid-wrap" aria-label="<?php esc_attr_e('Form layout', 'enkompass'); ?>">
                <div class="grid-stack enk-canvas" id="enk-canvas"></div>
            </section>

            <aside class="enk-sidebar enk-sidebar-right" aria-label="<?php esc_attr_e('Field types', 'enkompass'); ?>">
                <div class="enk-palette-head"><?php esc_html_e('Field Types', 'enkompass'); ?></div>
                <ul class="enk-palette" id="enk-palette"></ul>

                <div class="enk-modal-actions">
                    <button type="button" class="button" id="enk-cancel"><?php esc_html_e('Cancel', 'enkompass'); ?></button>
                    <button type="button" class="button button-primary" id="enk-save"><?php esc_html_e('Save', 'enkompass'); ?></button>
                </div>
            </aside>
        </div>
    </div>
</div>
