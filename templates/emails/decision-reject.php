<?php
/** @var string $author_name @var string $title @var string $editor_message */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#1a4480; margin:0 0 16px;"><?php esc_html_e('Decision on your submission', 'tainacan-journal-manager'); ?></h2>
<p><?php printf(esc_html__('Dear %s,', 'tainacan-journal-manager'), esc_html($author_name ?? '')); ?></p>
<p>
    <?php printf(
        esc_html__('After careful review, the editorial team has decided not to accept your submission %s for publication at this time.', 'tainacan-journal-manager'),
        '<strong>"' . esc_html($title ?? '') . '"</strong>'
    ); ?>
</p>
<?php if (! empty($editor_message)) : ?>
<div style="background:#fef2f2; border-left:4px solid #dc2626; padding:16px; margin:16px 0; border-radius:0 4px 4px 0;">
    <strong><?php esc_html_e('Editor\'s message:', 'tainacan-journal-manager'); ?></strong>
    <p style="margin:8px 0 0;"><?php echo esc_html($editor_message); ?></p>
</div>
<?php endif; ?>
<p><?php esc_html_e('We thank you for considering our journal and encourage you to submit your work elsewhere.', 'tainacan-journal-manager'); ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
