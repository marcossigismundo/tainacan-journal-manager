<?php

/**
 * Plugin Name: Tainacan Journal Manager
 * Plugin URI:  https://github.com/marcossigismundo/tainacan-journal-manager
 * Description: Tainacan-integrated platform for managing electronic scientific journals (editorial workflow inspired by OJS, with admin pages embedded in the Tainacan admin shell).
 * Version:     0.7.0
 * Author:      Marcos Sigismundo
 * Author URI:  https://github.com/marcossigismundo
 * License:     GPL-2.0-or-later
 * Text Domain: tainacan-journal-manager
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Requires Plugins: tainacan
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

// ── Plugin constants ─────────────────────────────────────────────────
define('TJM_VERSION',  '0.7.0');
define('TJM_PATH',     plugin_dir_path(__FILE__));
define('TJM_URL',      plugin_dir_url(__FILE__));
define('TJM_BASENAME', plugin_basename(__FILE__));

// ── PHP version check ────────────────────────────────────────────────
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
    load_plugin_textdomain(
        'tainacan-journal-manager',
        false,
        dirname(TJM_BASENAME) . '/languages'
    );

    // Tainacan is now a hard dependency — the plugin's admin UI extends
    // \Tainacan\Pages and the publishing layer talks to Tainacan repos.
    // Missing Tainacan ⇒ show a notice and skip plugin init entirely.
    if (! class_exists('\Tainacan\Pages')) {
        add_action('admin_notices', function (): void {
            echo '<div class="notice notice-error"><p><strong>';
            echo esc_html__('Tainacan Journal Manager', 'tainacan-journal-manager');
            echo '</strong> — ';
            echo esc_html__('requires the Tainacan plugin (version 1.0.0+) to be installed and activated.', 'tainacan-journal-manager');
            echo '</p></div>';
        });
        return;
    }

    \TainacanJournalManager\Plugin::get_instance()->init();
});
