<?php
/** @var string $redirect */
if (! defined('ABSPATH')) exit;
?>
<div class="tjm-login-container">
    <div class="tjm-login-card">
        <h1><?php esc_html_e('Editorial Portal', 'tainacan-journal-manager'); ?></h1>
        <p class="tjm-login-subtitle"><?php esc_html_e('Sign in to access submissions, reviews and editorial tools.', 'tainacan-journal-manager'); ?></p>

        <form id="tjm-login-form" class="tjm-form">
            <div class="tjm-field">
                <label for="tjm-username"><?php esc_html_e('Username or Email', 'tainacan-journal-manager'); ?></label>
                <input type="text" id="tjm-username" name="username" required autocomplete="username">
            </div>
            <div class="tjm-field">
                <label for="tjm-password"><?php esc_html_e('Password', 'tainacan-journal-manager'); ?></label>
                <input type="password" id="tjm-password" name="password" required autocomplete="current-password">
            </div>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
            <button type="submit" class="tjm-btn tjm-btn--primary"><?php esc_html_e('Sign in', 'tainacan-journal-manager'); ?></button>
        </form>

        <div id="tjm-login-message" class="tjm-message" role="alert"></div>

        <p class="tjm-login-footer">
            <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Forgot password?', 'tainacan-journal-manager'); ?></a>
        </p>
    </div>
</div>
