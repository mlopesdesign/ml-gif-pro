<?php
/**
 * Plugin Name: ML Gif Pro
 * Plugin URI: https://mlopesdesign.com.br
 * Description: Gerador profissional de GIF animado físico a partir de imagens da Media Library. Salva o arquivo .gif real em /wp-content/uploads/ para uso direto em banners, carrosséis e campos de URL.
 * Version: 1.1.4.6
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Marcio Lopes
 * Author URI: https://mlopesdesign.com.br
 * License: GPL2+
 * Text Domain: ml-gif-pro
 * Update URI: https://github.com/mlopesdesign/ml-gif-pro-releases
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MLGIF_VERSION', '1.1.4.6');
define('MLGIF_FILE', __FILE__);
define('MLGIF_DIR', plugin_dir_path(__FILE__));
define('MLGIF_URL', plugin_dir_url(__FILE__));
define('MLGIF_OPTION_KEY', 'mlgif_settings');

require_once MLGIF_DIR . 'includes/class-mlgif-helpers.php';
require_once MLGIF_DIR . 'includes/class-mlgif-license.php';
require_once MLGIF_DIR . 'includes/class-mlgif-settings.php';
require_once MLGIF_DIR . 'includes/class-mlgif-db.php';
require_once MLGIF_DIR . 'includes/class-mlgif-optimizer.php';
require_once MLGIF_DIR . 'includes/class-mlgif-engine.php';
require_once MLGIF_DIR . 'includes/class-mlgif-admin-ui.php';
require_once MLGIF_DIR . 'includes/class-mlgif-plugin.php';
require_once MLGIF_DIR . 'includes/class-mlgif-updater.php';

function mlgif_run_plugin(): void {
    static $instance = null;
    if ($instance === null) {
        $instance = new MLGIF_Plugin();
    }
}
mlgif_run_plugin();

// Auto-updater via GitHub Releases
$mlgif_updater = new MLGIF_Updater(__FILE__, MLGIF_Plugin::get_version());
$mlgif_updater->init();

register_activation_hook(__FILE__, function () {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    MLGIF_DB::create_table();
});

add_shortcode('ml_gif_pro', function ($atts) {
    $atts = shortcode_atts(['id' => 0], $atts, 'ml_gif_pro');
    $id = (int) $atts['id'];
    if ($id <= 0) {
        return '';
    }
    $project = MLGIF_DB::get_project($id);
    if (!$project || empty($project['output_url'])) {
        return '';
    }
    return '<img src="' . esc_url($project['output_url']) . '" alt="' . esc_attr($project['title']) . '" class="ml-gif-pro-preview">';
});
