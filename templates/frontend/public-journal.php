<?php
/** @var \WP_Post $journal */
if (! defined('ABSPATH')) exit;
?>
<article class="tjm-journal-public">
    <header class="tjm-journal-header">
        <h1><?php echo esc_html($journal->post_title); ?></h1>
        <?php if ($journal->post_excerpt) : ?>
            <p class="tjm-journal-tagline"><?php echo esc_html($journal->post_excerpt); ?></p>
        <?php endif; ?>
    </header>

    <div class="tjm-journal-content">
        <?php echo apply_filters('the_content', $journal->post_content); ?>
    </div>
</article>
