<?php
/** @var string $author_name @var string $title @var string $note */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#d97706; margin:0 0 16px;"><?php esc_html_e('Revisions requested', 'tainacan-journal-manager'); ?></h2>
<p><?php printf(esc_html__('Dear %s,', 'tainacan-journal-manager'), esc_html($author_name ?? '')); ?></p>
<p>
    <?php printf(
        esc_html__('The editors have requested revisions on your submission %s.', 'tainacan-journal-manager'),
        '<strong>"' . esc_html($title ?? '') . '"</strong>'
    ); ?>
</p>
<?php if (! empty($note)) : ?>
<div style="background:#fffbeb; border-left:4px solid #d97706; padding:16px; margin:16px 0; border-radius:0 4px 4px 0;">
    <strong><?php esc_html_e('Editor message:', 'tainacan-journal-manager'); ?></strong>
    <p style="margin:8px 0 0;"><?php echo esc_html((string) $note); ?></p>
</div>
<?php endif; ?>
<p><?php esc_html_e('Please log in to your author portal to upload the revised version.', 'tainacan-journal-manager'); ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
