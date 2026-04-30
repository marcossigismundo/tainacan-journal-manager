<?php
/**
 * Editorial report (standalone HTML, print-friendly).
 *
 * Vars:
 *   array  $stats        StatsService::get_overview output
 *   int    $journal_id   0 = all journals
 *   string $journal_name
 *   string $generated_at
 *   string $author_name
 *   string $site_name
 */
if (! defined('ABSPATH')) exit;

$totals = (array) ($stats['total'] ?? []);
$status = (array) ($stats['submissions_per_status'] ?? []);
$months = (array) ($stats['submissions_per_month'] ?? []);
$reviewers = (array) ($stats['top_reviewers'] ?? []);
$top_journals = (array) ($stats['top_journals_published'] ?? []);
$ar = (array) ($stats['acceptance_rate'] ?? []);
?>
<!doctype html>
<html lang="<?php echo esc_attr(str_replace('_', '-', get_locale())); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(sprintf(__('Editorial report — %s', 'tainacan-journal-manager'), $site_name)); ?></title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            background: #fff;
            font-size: 13px;
            line-height: 1.45;
            padding: 28px 36px;
            max-width: 920px;
            margin: 0 auto;
        }
        h1, h2, h3 { color: #1a4480; margin: 0 0 12px; }
        h1 { font-size: 22px; }
        h2 { font-size: 16px; margin-top: 28px; padding-bottom: 4px; border-bottom: 2px solid #e2e8f0; }
        h3 { font-size: 14px; margin-top: 18px; }
        .header {
            display: flex; align-items: flex-end; justify-content: space-between;
            border-bottom: 3px solid #1a4480; padding-bottom: 10px; margin-bottom: 18px;
        }
        .meta { font-size: 11px; color: #64748b; text-align: right; }
        .summary {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 12px 0 22px;
        }
        .card {
            border: 1px solid #e2e8f0; padding: 12px 14px; border-radius: 6px;
            background: #f8fafc;
        }
        .card .num { font-size: 22px; font-weight: 700; color: #1a4480; line-height: 1; }
        .card .lbl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 18px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: left; font-size: 12px; }
        thead th { background: #f1f5f9; color: #1e293b; font-weight: 600; }
        .bar-row { display: grid; grid-template-columns: 200px 1fr 50px; gap: 8px; align-items: center; padding: 3px 0; }
        .bar { height: 14px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
        .bar-fill { height: 100%; background: #1a4480; }
        .footer {
            margin-top: 32px; padding-top: 12px; border-top: 1px solid #e2e8f0;
            font-size: 11px; color: #64748b; text-align: center;
        }
        .actions { margin: 16px 0; }
        .actions button {
            font-size: 13px; padding: 8px 14px; background: #1a4480; color: #fff;
            border: 0; border-radius: 4px; cursor: pointer; margin-right: 6px;
        }
        @media print {
            .actions { display: none !important; }
            body { padding: 0 12mm; max-width: none; font-size: 11px; }
            h1 { font-size: 18px; }
            h2 { font-size: 14px; }
            .card { background: #fff; }
            table { page-break-inside: avoid; }
            h2, h3 { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()"><?php esc_html_e('Print / Save as PDF', 'tainacan-journal-manager'); ?></button>
        <a href="javascript:window.close()"><?php esc_html_e('Close', 'tainacan-journal-manager'); ?></a>
    </div>

    <header class="header">
        <div>
            <h1><?php esc_html_e('Editorial Report', 'tainacan-journal-manager'); ?></h1>
            <div style="font-size:13px; color:#1a4480; margin-top:4px;">
                <strong><?php echo esc_html($site_name); ?></strong>
                <?php if ($journal_name) : ?> — <?php echo esc_html($journal_name); ?><?php endif; ?>
            </div>
        </div>
        <div class="meta">
            <?php echo esc_html(sprintf(__('Generated on %s', 'tainacan-journal-manager'), $generated_at)); ?><br>
            <?php if ($author_name) : ?>
                <?php echo esc_html(sprintf(__('By %s', 'tainacan-journal-manager'), $author_name)); ?>
            <?php endif; ?>
        </div>
    </header>

    <h2><?php esc_html_e('Executive summary', 'tainacan-journal-manager'); ?></h2>
    <div class="summary">
        <div class="card"><div class="num"><?php echo (int) ($totals['submissions'] ?? 0); ?></div><div class="lbl"><?php esc_html_e('Submissions', 'tainacan-journal-manager'); ?></div></div>
        <div class="card"><div class="num"><?php echo (int) ($totals['published'] ?? 0); ?></div><div class="lbl"><?php esc_html_e('Published', 'tainacan-journal-manager'); ?></div></div>
        <div class="card"><div class="num"><?php echo (int) ($totals['reviews'] ?? 0); ?></div><div class="lbl"><?php esc_html_e('Reviews', 'tainacan-journal-manager'); ?></div></div>
        <div class="card"><div class="num"><?php echo (int) ($totals['journals'] ?? 0); ?></div><div class="lbl"><?php esc_html_e('Journals', 'tainacan-journal-manager'); ?></div></div>
        <div class="card"><div class="num"><?php echo (int) ($totals['issues'] ?? 0); ?></div><div class="lbl"><?php esc_html_e('Issues', 'tainacan-journal-manager'); ?></div></div>
        <div class="card"><div class="num"><?php echo esc_html((string) ($ar['rate'] ?? 0)); ?>%</div><div class="lbl"><?php esc_html_e('Acceptance rate', 'tainacan-journal-manager'); ?></div></div>
    </div>

    <h2><?php esc_html_e('Submissions per status', 'tainacan-journal-manager'); ?></h2>
    <?php $max_status = max(1, max(array_map('intval', array_values($status)))); ?>
    <?php foreach ($status as $key => $count) :
        $w = (int) round(($count / $max_status) * 100);
    ?>
        <div class="bar-row">
            <span><?php echo esc_html(\TainacanJournalManager\Config::get_status_label((string) $key)); ?></span>
            <div class="bar"><div class="bar-fill" style="width: <?php echo (int) $w; ?>%;"></div></div>
            <span style="text-align:right;"><?php echo (int) $count; ?></span>
        </div>
    <?php endforeach; ?>

    <h2><?php esc_html_e('Submissions per month (last 12)', 'tainacan-journal-manager'); ?></h2>
    <?php $max_m = max(1, max(array_map('intval', array_values($months)))); ?>
    <table>
        <thead><tr><th><?php esc_html_e('Month', 'tainacan-journal-manager'); ?></th><th><?php esc_html_e('Submissions', 'tainacan-journal-manager'); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($months as $ym => $count) :
            $w = (int) round(($count / $max_m) * 100);
        ?>
            <tr>
                <td><?php echo esc_html((string) $ym); ?></td>
                <td><?php echo (int) $count; ?></td>
                <td style="width: 50%;"><div class="bar"><div class="bar-fill" style="width: <?php echo (int) $w; ?>%;"></div></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (! empty($top_journals) && ! $journal_id) : ?>
    <h2><?php esc_html_e('Top journals (published)', 'tainacan-journal-manager'); ?></h2>
    <table>
        <thead><tr><th>#</th><th><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></th><th><?php esc_html_e('Published', 'tainacan-journal-manager'); ?></th></tr></thead>
        <tbody>
            <?php $i = 0; foreach ($top_journals as $entry) : $i++; ?>
                <tr>
                    <td><?php echo (int) $i; ?></td>
                    <td><?php echo esc_html((string) ($entry['name'] ?? '')); ?></td>
                    <td><?php echo (int) ($entry['count'] ?? 0); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (! empty($reviewers)) : ?>
    <h2><?php esc_html_e('Top reviewers (submitted reviews)', 'tainacan-journal-manager'); ?></h2>
    <table>
        <thead><tr><th>#</th><th><?php esc_html_e('Reviewer', 'tainacan-journal-manager'); ?></th><th><?php esc_html_e('Submitted', 'tainacan-journal-manager'); ?></th></tr></thead>
        <tbody>
            <?php $i = 0; foreach ($reviewers as $entry) : $i++; ?>
                <tr>
                    <td><?php echo (int) $i; ?></td>
                    <td><?php echo esc_html((string) ($entry['name'] ?? '')); ?></td>
                    <td><?php echo (int) ($entry['count'] ?? 0); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2><?php esc_html_e('Acceptance rate', 'tainacan-journal-manager'); ?></h2>
    <table>
        <tbody>
            <tr><th><?php esc_html_e('Accepted (or beyond)', 'tainacan-journal-manager'); ?></th><td><?php echo (int) ($ar['accepted'] ?? 0); ?></td></tr>
            <tr><th><?php esc_html_e('Rejected', 'tainacan-journal-manager'); ?></th><td><?php echo (int) ($ar['rejected'] ?? 0); ?></td></tr>
            <tr><th><?php esc_html_e('Rate', 'tainacan-journal-manager'); ?></th><td><strong><?php echo esc_html((string) ($ar['rate'] ?? 0)); ?>%</strong></td></tr>
        </tbody>
    </table>

    <footer class="footer">
        <?php
        printf(
            esc_html__('Generated by Tainacan Journal Manager — %s', 'tainacan-journal-manager'),
            esc_html(TJM_VERSION)
        );
        ?>
    </footer>
</body>
</html>
<?php
exit;
