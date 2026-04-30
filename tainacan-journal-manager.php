<?php

/**
 * Plugin Name: Tainacan Journal Manager
 * Plugin URI:  https://github.com/marcossigismundo/tainacan-journal-manager
 * Description: Transforms a Tainacan-powered WordPress installation into a complete electronic scientific journal management platform with editorial workflow inspired by OJS (Open Journal Systems).
 * Version:     0.5.0
 * Author:      Marcos Sigismundo
 * Author URI:  https://github.com/marcossigismundo
 * License:     GPL-2.0-or-later
 * Text Domain: tainacan-journal-manager
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

// ── Plugin constants ─────────────────────────────────────────────────
define('TJM_VERSION',  '0.5.0');
define('TJM_PATH',     plugin_dir_path(__FILE__));
define('TJM_URL',      plugin_dir_url(__FILE__));
define('TJM_BASENAME', plugin_basename(__FILE__));

// ── Requirements check ───────────────────────────────────────────────
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Tainacan Journal Manager requires PHP 8.0 or higher.', 'tainacan-journal-manager');
        echo '</p></div>';
    });
    return;
}

// ── Autoload ─────────────────────────────────────────────────────────
$autoload = TJM_PATH . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    require_once TJM_PATH . 'src/Autoloader.php';
    \TainacanJournalManager\Autoloader::register();
}

// ── Activation / Deactivation ────────────────────────────────────────
register_activation_hook(__FILE__, [\TainacanJournalManager\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\TainacanJournalManager\Deactivator::class, 'deactivate']);

// ── Bootstrap ────────────────────────────────────────────────────────
add_action('plugins_loaded', function (): void {
    // i18n
    load_plugin_textdomain(
        'tainacan-journal-manager',
        false,
        dirname(TJM_BASENAME) . '/languages'
    );

    // Notify if Tainacan is missing
    if (! class_exists('\Tainacan\Repositories\Items')) {
        add_action('admin_notices', function (): void {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Tainacan Journal Manager requires the Tainacan plugin to be active. Please install and activate Tainacan to use the publishing features.', 'tainacan-journal-manager');
            echo '</p></div>';
        });
        // Continue loading — plugin still works for editorial workflow without Tainacan
    }

    \TainacanJournalManager\Plugin::get_instance()->init();
});
