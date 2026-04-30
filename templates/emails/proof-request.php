<?php
/** @var string $author_name @var string $title */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#1a4480; margin:0 0 16px;"><?php esc_html_e('Please review the proof', 'tainacan-journal-manager'); ?></h2>
<p><?php printf(esc_html__('Dear %s,', 'tainacan-journal-manager'), esc_html($author_name ?? '')); ?></p>
<p>
    <?php printf(
        esc_html__('The production team has prepared the final files (galleys) for %s and would like your approval before publication.', 'tainacan-journal-manager'),
        '<strong>"' . esc_html($title ?? '') . '"</strong>'
    ); ?>
</p>
<p><?php esc_html_e('Please log in to your author portal to download the proof and either approve it or request changes.', 'tainacan-journal-manager'); ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
