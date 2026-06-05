<?php
/**
 * Admin page shell with Forms / Settings tabs.
 *
 * @package Enkompass\Contact
 * @var string                          $tab
 * @var array<int, array<string,mixed>> $cards
 */

defined('ABSPATH') || exit;

$enk_base_url = admin_url('admin.php?page=' . ENK_MENU_SLUG);
?>
<div class="wrap enk-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Contacts', 'enkompass'); ?></h1>

    <nav class="nav-tab-wrapper enk-tabs">
        <a href="<?php echo esc_url($enk_base_url . '&tab=forms'); ?>"
           class="nav-tab <?php echo 'forms' === $tab ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Forms', 'enkompass'); ?>
        </a>
        <a href="<?php echo esc_url($enk_base_url . '&tab=settings'); ?>"
           class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Settings', 'enkompass'); ?>
        </a>
    </nav>

    <div class="enk-tab-content">
        <?php
        if ('settings' === $tab) {
            include ENK_PLUGIN_DIR . 'admin/views/tab-settings.php';
        } else {
            include ENK_PLUGIN_DIR . 'admin/views/tab-forms.php';
        }
        ?>
    </div>
</div>

<?php include ENK_PLUGIN_DIR . 'admin/views/modal-builder.php'; ?>
