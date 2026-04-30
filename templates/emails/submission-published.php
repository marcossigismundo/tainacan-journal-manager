<?php
/** @var string $author_name @var string $title */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#059669; margin:0 0 16px;"><?php esc_html_e('Your article is published', 'tainacan-journal-manager'); ?></h2>
<p><?php printf(esc_html__('Dear %s,', 'tainacan-journal-manager'), esc_html($author_name ?? '')); ?></p>
<p>
    <?php printf(
        esc_html__('We are pleased to inform you that %s has been published.', 'tainacan-journal-manager'),
        '<strong>"' . esc_html($title ?? '') . '"</strong>'
    ); ?>
</p>
<p><?php esc_html_e('Thank you for choosing this journal. The article is now publicly available.', 'tainacan-journal-manager'); ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
