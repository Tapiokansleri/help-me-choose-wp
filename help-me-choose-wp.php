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
    
    // Set a transient to show activation notice
    set_transient('amv_activation_notice', true, 30);
});

/**
 * Add settings link to plugin actions
 */
function amv_add_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('tools.php?page=auta-minua-valitsemaan') . '">' . __('Settings', 'auta-minua-valitsemaan') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'amv_add_plugin_action_links');

/**
 * Show activation notice
 */
function amv_activation_notice() {
    if (get_transient('amv_activation_notice')) {
        delete_transient('amv_activation_notice');
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php _e('Help me choose', 'auta-minua-valitsemaan'); ?></strong> <?php _e('has been activated!', 'auta-minua-valitsemaan'); ?>
            </p>
            <p>
                <?php _e('Get started quickly with our', 'auta-minua-valitsemaan'); ?> 
                <a href="<?php echo admin_url('tools.php?page=auta-minua-valitsemaan&tab=wizard'); ?>" class="button button-primary" style="margin-left: 5px;">
                    <?php _e('Installation Wizard', 'auta-minua-valitsemaan'); ?>
                </a>
                <?php _e('or', 'auta-minua-valitsemaan'); ?> 
                <a href="<?php echo admin_url('tools.php?page=auta-minua-valitsemaan'); ?>">
                    <?php _e('configure manually', 'auta-minua-valitsemaan'); ?>
                </a>.
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'amv_activation_notice');

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

