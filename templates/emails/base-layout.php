<?php
/** @var string $content */
if (! defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_bloginfo('name')); ?></title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f6f8; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="background:#1a4480; color:#fff; padding:24px 32px;">
                            <h1 style="margin:0; font-size:20px; font-weight:600;"><?php echo esc_html(get_bloginfo('name')); ?></h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; color:#1e293b; font-size:14px; line-height:1.6;">
                            <?php echo $content; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background:#f8f9fa; color:#64748b; font-size:12px; text-align:center; border-top:1px solid #e2e8f0;">
                            <?php printf(
                                /* translators: %s: site URL */
                                esc_html__('This message was sent by %s. Please do not reply.', 'tainacan-journal-manager'),
                                esc_html(get_bloginfo('name'))
                            ); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
