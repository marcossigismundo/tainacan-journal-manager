<?php
/** @var string $title @var int $submission_id @var string $journal_name @var string $dashboard_url */
if (! defined('ABSPATH')) exit;

ob_start();
?>
<h2 style="color:#1a4480; margin:0 0 16px;"><?php esc_html_e('New submission received', 'tainacan-journal-manager'); ?></h2>
<p><?php esc_html_e('A new submission has been received and is awaiting triage:', 'tainacan-journal-manager'); ?></p>
<table style="width:100%; border-collapse:collapse; margin:16px 0;">
    <tr>
        <td style="padding:8px 12px; background:#f8f9fa; font-weight:600; width:40%; border:1px solid #dee2e6;"><?php esc_html_e('Title', 'tainacan-journal-manager'); ?></td>
        <td style="padding:8px 12px; border:1px solid #dee2e6;"><?php echo esc_html($title ?? ''); ?></td>
    </tr>
    <tr>
        <td style="padding:8px 12px; background:#f8f9fa; font-weight:600; border:1px solid #dee2e6;"><?php esc_html_e('Journal', 'tainacan-journal-manager'); ?></td>
        <td style="padding:8px 12px; border:1px solid #dee2e6;"><?php echo esc_html($journal_name ?? ''); ?></td>
    </tr>
    <tr>
        <td style="padding:8px 12px; background:#f8f9fa; font-weight:600; border:1px solid #dee2e6;"><?php esc_html_e('Submission ID', 'tainacan-journal-manager'); ?></td>
        <td style="padding:8px 12px; border:1px solid #dee2e6;">#<?php echo (int) ($submission_id ?? 0); ?></td>
    </tr>
</table>
<p style="text-align:center; margin:24px 0;">
    <a href="<?php echo esc_url($dashboard_url ?? '#'); ?>" style="display:inline-block; background:#1a4480; color:#fff; padding:12px 24px; text-decoration:none; border-radius:6px;"><?php esc_html_e('Open Editorial Dashboard', 'tainacan-journal-manager'); ?></a>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/base-layout.php';
