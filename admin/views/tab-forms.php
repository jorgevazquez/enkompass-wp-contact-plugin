<?php
/**
 * Forms tab: cards grid plus the always-present "+" card.
 *
 * @package Enkompass\Contact
 * @var array<int, array<string,mixed>> $cards
 */

defined('ABSPATH') || exit;
?>
<p class="enk-intro">
    <?php esc_html_e('Click a card to edit a form. Click a form name to rename it. Use the + card to add a new form.', 'enkompass'); ?>
</p>

<div class="enk-cards" id="enk-cards">
    <?php foreach ($cards as $card) : ?>
        <div class="enk-card-wrap" data-form-id="<?php echo (int) $card['id']; ?>">
            <div class="enk-card-title" role="textbox" tabindex="0" data-form-id="<?php echo (int) $card['id']; ?>">
                <?php echo esc_html($card['name']); ?>
            </div>

            <button type="button" class="enk-card enk-open-form" data-form-id="<?php echo (int) $card['id']; ?>">
                <span class="enk-card-stat"><strong><?php echo (int) $card['fieldCount']; ?></strong>
                    <?php esc_html_e('fields', 'enkompass'); ?></span>
                <span class="enk-card-stat"><strong><?php echo (int) $card['submissions']; ?></strong>
                    <?php esc_html_e('submissions', 'enkompass'); ?></span>
            </button>

            <div class="enk-card-actions">
                <?php if (!empty($card['csvUrl'])) : ?>
                    <a class="button button-small enk-csv-dl" href="<?php echo esc_url((string) $card['csvUrl']); ?>">
                        <?php esc_html_e('Download CSV', 'enkompass'); ?>
                    </a>
                <?php endif; ?>
                <button type="button" class="button button-small button-link-delete enk-delete-form"
                        data-form-id="<?php echo (int) $card['id']; ?>">
                    <?php esc_html_e('Delete', 'enkompass'); ?>
                </button>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="enk-card-wrap enk-add-card-wrap">
        <div class="enk-card-title">&nbsp;</div>
        <button type="button" class="enk-card enk-add-card" id="enk-add-form"
                aria-label="<?php esc_attr_e('Add form', 'enkompass'); ?>">
            <span aria-hidden="true">+</span>
        </button>
    </div>
</div>
