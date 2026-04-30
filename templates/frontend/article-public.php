<?php
/**
 * Public single-article view.
 *
 * @var array<string, mixed> $data
 */
if (! defined('ABSPATH')) exit;

$keywords = is_array($data['keywords']) ? $data['keywords'] : [];
$authors  = is_array($data['authors']) ? $data['authors'] : [];
$galleys  = is_array($data['galleys']) ? $data['galleys'] : [];
?>
<article class="tjm-journal-public tjm-article">
    <header class="tjm-journal-header">
        <h1><?php echo esc_html((string) $data['title']); ?></h1>

        <?php if (! empty($authors)) : ?>
            <p class="tjm-article-authors">
                <?php
                $names = [];
                foreach ($authors as $a) {
                    $line = (string) ($a['name'] ?? '');
                    if (! empty($a['affiliation'])) $line .= ' (' . (string) $a['affiliation'] . ')';
                    if (! empty($a['orcid']))       $line .= ' [' . (string) $a['orcid'] . ']';
                    $names[] = $line;
                }
                echo esc_html(implode('; ', $names));
                ?>
            </p>
        <?php endif; ?>

        <p class="tjm-article-meta tjm-detail-meta">
            <?php if (! empty($data['journal_id'])) : ?>
                <span><?php esc_html_e('Journal:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html((string) get_the_title((int) $data['journal_id'])); ?></strong></span>
            <?php endif; ?>
            <?php if (! empty($data['issue_id'])) : ?>
                <span><?php esc_html_e('Issue:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html((string) get_the_title((int) $data['issue_id'])); ?></strong></span>
            <?php endif; ?>
            <?php if (! empty($data['published_at'])) : ?>
                <span><?php esc_html_e('Published:', 'tainacan-journal-manager'); ?> <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime((string) $data['published_at']))); ?></strong></span>
            <?php endif; ?>
            <?php if (! empty($data['language'])) : ?>
                <span><?php echo esc_html((string) $data['language']); ?></span>
            <?php endif; ?>
        </p>
    </header>

    <section class="tjm-section">
        <h2><?php esc_html_e('Abstract', 'tainacan-journal-manager'); ?></h2>
        <p><?php echo nl2br(esc_html((string) $data['abstract'])); ?></p>
    </section>

    <?php if (! empty($keywords)) : ?>
        <section class="tjm-section">
            <h3><?php esc_html_e('Keywords', 'tainacan-journal-manager'); ?></h3>
            <p><?php echo esc_html(implode(', ', array_map('strval', $keywords))); ?></p>
        </section>
    <?php endif; ?>

    <?php if (! empty($galleys)) : ?>
        <section class="tjm-section">
            <h3><?php esc_html_e('Download', 'tainacan-journal-manager'); ?></h3>
            <ul class="tjm-galley-list">
                <?php foreach ($galleys as $g) : ?>
                    <li>
                        <a class="tjm-btn tjm-btn--secondary" href="<?php echo esc_url((string) ($g['url'] ?? '')); ?>" target="_blank" rel="noopener">
                            &darr; <?php echo esc_html((string) ($g['label'] ?? strtoupper((string) ($g['format'] ?? '')))); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <footer class="tjm-article-footer tjm-detail-meta">
        <?php if (! empty($data['doi'])) : ?>
            <span><?php esc_html_e('DOI:', 'tainacan-journal-manager'); ?> <code><?php echo esc_html((string) $data['doi']); ?></code></span>
        <?php endif; ?>
        <?php if (! empty($data['license'])) : ?>
            <span><?php esc_html_e('License:', 'tainacan-journal-manager'); ?> <?php echo esc_html((string) $data['license']); ?></span>
        <?php endif; ?>
        <?php if (! empty($data['tainacan_id'])) : ?>
            <span><a href="<?php echo esc_url((string) get_permalink((int) $data['tainacan_id'])); ?>" target="_blank" rel="noopener"><?php esc_html_e('View in Tainacan collection', 'tainacan-journal-manager'); ?></a></span>
        <?php endif; ?>
    </footer>
</article>
