<?php
/** @var string $author_name @var string $title @var string $editor_message */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#059669; margin:0 0 16px;"><?php esc_html_e('Your submission has been accepted', 'tainacan-journal-manager'); ?></h2>
<p><?php printf(esc_html__('Dear %s,', 'tainacan-journal-manager'), esc_html($author_name ?? '')); ?></p>
<p>
    <?php printf(
        esc_html__('We are pleased to inform you that your submission %s has been accepted for publication.', 'tainacan-journal-manager'),
        '<strong>"' . esc_html($title ?? '') . '"</strong>'
    ); ?>
</p>
<?php if (! empty($editor_message)) : ?>
<div style="background:#f0fdf4; border-left:4px solid #059669; padding:16px; margin:16px 0; border-radius:0 4px 4px 0;">
    <strong><?php esc_html_e('Editor\'s message:', 'tainacan-journal-manager'); ?></strong>
    <p style="margin:8px 0 0;"><?php echo esc_html($editor_message); ?></p>
</div>
<?php endif; ?>
<p><?php esc_html_e('The article will now go through copyediting and production. You will be notified when the proof is ready for your approval.', 'tainacan-journal-manager'); ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
