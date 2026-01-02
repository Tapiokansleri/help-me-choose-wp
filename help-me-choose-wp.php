<?php
/**
 * Plugin Name: Help me choose
 * Plugin URI: https://tapiokauranen.com
 * Description: A tool that helps customers to choose the right product or a service.
 * Version: 1.0.0
 * Author: Tapio Kauranen
 * Author URI: https://tapiokauranen.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auta-minua-valitsemaan
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AMV_VERSION', '1.0.0');
define('AMV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMV_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required files
require_once AMV_PLUGIN_DIR . 'includes/class-amv-main.php';
require_once AMV_PLUGIN_DIR . 'includes/class-amv-database.php';
require_once AMV_PLUGIN_DIR . 'includes/class-amv-updater.php';

/**
 * Create database table on plugin activation
 */
register_activation_hook(__FILE__, function() {
    AMV_Database::create_table();
});

/**
 * Load plugin text domain for translations
 */
function amv_load_textdomain() {
    // Set Finnish as default locale if WordPress locale is not set or is default English
    $current_locale = get_locale();
    if (empty($current_locale) || $current_locale === 'en_US') {
        add_filter('locale', function($locale) {
            // Only override if locale is empty or default English
            $current = get_locale();
            if (empty($current) || $current === 'en_US') {
                return 'fi';
            }
            return $locale;
        }, 5);
    }
    
    // Load plugin text domain
    load_plugin_textdomain(
        'auta-minua-valitsemaan',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'amv_load_textdomain', 1);

/**
 * Initialize GitHub updater
 * 
 * Replace 'YOUR_GITHUB_USERNAME' and 'YOUR_REPO_NAME' with your actual GitHub username and repository name
 */
function amv_init_updater() {
    $github_owner = 'Tapiokansleri';
    $github_repo = 'help-me-choose-wp';
    
    new AMV_Updater($github_owner, $github_repo, __FILE__);
}
add_action('admin_init', 'amv_init_updater');

/**
 * Initialize the plugin
 */
function amv_init() {
    return Auta_Minua_Valitsemaan::get_instance();
}

// Start the plugin
amv_init();

