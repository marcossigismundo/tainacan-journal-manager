<?php
/** @var string $reviewer_name @var string $title @var string $deadline @var string $accept_url @var string $decline_url */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#1a4480; margin:0 0 16px;"><?php esc_html_e('Invitation to review', 'tainacan-journal-manager'); ?></h2>
<p><?php printf(esc_html__('Dear %s,', 'tainacan-journal-manager'), esc_html($reviewer_name ?? '')); ?></p>
<p>
    <?php printf(
        esc_html__('You have been invited to review the submission %s.', 'tainacan-journal-manager'),
        '<strong>"' . esc_html($title ?? '') . '"</strong>'
    ); ?>
</p>
<p>
    <?php printf(
        esc_html__('Review deadline: %s', 'tainacan-journal-manager'),
        '<strong>' . esc_html($deadline ?? '') . '</strong>'
    ); ?>
</p>
<p style="text-align:center; margin:24px 0;">
    <a href="<?php echo esc_url($accept_url ?? '#'); ?>" style="display:inline-block; background:#059669; color:#fff; padding:12px 24px; text-decoration:none; border-radius:6px; margin:0 8px;"><?php esc_html_e('Accept', 'tainacan-journal-manager'); ?></a>
    <a href="<?php echo esc_url($decline_url ?? '#'); ?>" style="display:inline-block; background:#dc2626; color:#fff; padding:12px 24px; text-decoration:none; border-radius:6px; margin:0 8px;"><?php esc_html_e('Decline', 'tainacan-journal-manager'); ?></a>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
