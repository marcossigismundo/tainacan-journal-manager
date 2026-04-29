<?php
/** @var string $author_name @var string $title @var int $submission_id */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#1a4480; margin:0 0 16px;"><?php esc_html_e('Submission received', 'tainacan-journal-manager'); ?></h2>
<p><?php printf(esc_html__('Dear %s,', 'tainacan-journal-manager'), esc_html($author_name ?? '')); ?></p>
<p>
    <?php printf(
        esc_html__('We have received your submission %s and it is now in our editorial system.', 'tainacan-journal-manager'),
        '<strong>"' . esc_html($title ?? '') . '"</strong>'
    ); ?>
</p>
<p>
    <?php printf(
        esc_html__('Submission ID: %s', 'tainacan-journal-manager'),
        '<strong>#' . (int) ($submission_id ?? 0) . '</strong>'
    ); ?>
</p>
<p><?php esc_html_e('You can track the status of your submission at any time through the author portal.', 'tainacan-journal-manager'); ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
