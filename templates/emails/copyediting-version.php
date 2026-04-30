<?php
/** @var string $author_name @var string $title @var string $note */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#1a4480; margin:0 0 16px;"><?php esc_html_e('Copyediting update', 'tainacan-journal-manager'); ?></h2>
<p><?php printf(esc_html__('Dear %s,', 'tainacan-journal-manager'), esc_html($author_name ?? '')); ?></p>
<p>
    <?php printf(
        esc_html__('A new copyedited version of %s is ready for your review.', 'tainacan-journal-manager'),
        '<strong>"' . esc_html($title ?? '') . '"</strong>'
    ); ?>
</p>
<?php if (! empty($note)) : ?>
<div style="background:#f1f5f9; border-left:4px solid #1a4480; padding:16px; margin:16px 0; border-radius:0 4px 4px 0;">
    <strong><?php esc_html_e('Copyeditor note:', 'tainacan-journal-manager'); ?></strong>
    <p style="margin:8px 0 0;"><?php echo esc_html((string) $note); ?></p>
</div>
<?php endif; ?>
<p><?php esc_html_e('Please log in to your author portal to review and respond.', 'tainacan-journal-manager'); ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
