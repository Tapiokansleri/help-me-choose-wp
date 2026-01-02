<?php
/**
 * Admin class for settings page
 *
 * @package Auta_Minua_Valitsemaan
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class AMV_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_post_amv_save_config', array($this, 'save_config'));
        add_action('wp_ajax_amv_create_table', array($this, 'ajax_create_table'));
        add_action('wp_ajax_amv_reset_database', array($this, 'ajax_reset_database'));
        add_action('wp_ajax_amv_export_config', array($this, 'ajax_export_config'));
        add_action('wp_ajax_amv_import_config', array($this, 'ajax_import_config'));
        add_action('wp_ajax_amv_generate_starter_pack', array($this, 'ajax_generate_starter_pack'));
        add_action('wp_ajax_amv_check_updates', array($this, 'ajax_check_updates'));
        add_action('wp_ajax_amv_clear_update_cache', array($this, 'ajax_clear_update_cache'));
    }
    
    /**
     * AJAX handler for checking updates
     */
    public function ajax_check_updates() {
        check_ajax_referer('amv_check_updates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'auta-minua-valitsemaan')));
        }
        
        $update_info = $this->check_update_status(true); // Force refresh
        
        wp_send_json_success($update_info);
    }
    
    /**
     * AJAX handler for clearing update cache
     */
    public function ajax_clear_update_cache() {
        check_ajax_referer('amv_clear_update_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'auta-minua-valitsemaan')));
        }
        
        $slug = 'help-me-choose-wp';
        delete_transient('amv_remote_version_' . $slug);
        delete_site_transient('update_plugins');
        
        wp_send_json_success(array('message' => __('Update cache cleared. Please refresh the page.', 'auta-minua-valitsemaan')));
    }
    
    /**
     * Check update status
     */
    private function check_update_status($force_refresh = false) {
        $slug = 'help-me-choose-wp';
        $cache_key = 'amv_remote_version_' . $slug;
        
        if (!$force_refresh) {
            $cached_version = get_transient($cache_key);
            if ($cached_version !== false) {
                $remote_version = $cached_version;
            } else {
                $remote_version = $this->fetch_remote_version();
            }
        } else {
            $remote_version = $this->fetch_remote_version();
        }
        
        if ($remote_version === false) {
            return array(
                'success' => false,
                'error' => __('Unable to fetch version from GitHub. Please check your internet connection and try again.', 'auta-minua-valitsemaan'),
                'remote_version' => null,
            );
        }
        
        return array(
            'success' => true,
            'remote_version' => $remote_version,
            'current_version' => AMV_VERSION,
            'update_available' => version_compare(AMV_VERSION, $remote_version, '<'),
        );
    }
    
    /**
     * Fetch remote version from GitHub
     */
    private function fetch_remote_version() {
        $api_url = 'https://api.github.com/repos/Tapiokansleri/help-me-choose-wp/releases/latest';
        
        $response = wp_remote_get(
            $api_url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version'),
                ),
            )
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['tag_name'])) {
            return false;
        }
        
        // Remove 'v' prefix if present
        $version = ltrim($data['tag_name'], 'v');
        
        // Cache for 12 hours
        $slug = 'help-me-choose-wp';
        set_transient('amv_remote_version_' . $slug, $version, 12 * HOUR_IN_SECONDS);
        
        return $version;
    }
    
    /**
     * AJAX handler for creating database table
     */
    public function ajax_create_table() {
        check_ajax_referer('amv_create_table', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'auta-minua-valitsemaan')));
        }
        
        AMV_Database::create_table();
        
        wp_send_json_success(array('message' => __('Table created successfully', 'auta-minua-valitsemaan')));
    }
    
    /**
     * AJAX handler for resetting database
     */
    public function ajax_reset_database() {
        check_ajax_referer('amv_reset_database', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'auta-minua-valitsemaan')));
        }
        
        $result = AMV_Database::reset_database();
        
        if ($result) {
            wp_send_json_success(array('message' => __('Database reset successfully', 'auta-minua-valitsemaan')));
        } else {
            wp_send_json_error(array('message' => __('Failed to reset database', 'auta-minua-valitsemaan')));
        }
    }
    
    /**
     * AJAX handler for exporting configuration
     */
    public function ajax_export_config() {
        check_ajax_referer('amv_export_config', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'auta-minua-valitsemaan')));
        }
        
        $config = AMV_Helper::get_config();
        
        // Only export steps and recommendations (not styles, tracking settings, etc.)
        $export_data = array(
            'steps' => $config['steps'] ?? array(),
            'recommendations' => $config['recommendations'] ?? array(),
            'export_date' => current_time('mysql'),
            'plugin_version' => AMV_VERSION,
        );
        
        $json = wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            wp_send_json_error(array('message' => __('Failed to generate JSON', 'auta-minua-valitsemaan')));
        }
        
        wp_send_json_success(array('json' => $json));
    }
    
    /**
     * AJAX handler for importing configuration
     */
    public function ajax_import_config() {
        check_ajax_referer('amv_import_config', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'auta-minua-valitsemaan')));
        }
        
        $json_input = isset($_POST['json']) ? wp_unslash($_POST['json']) : '';
        
        if (empty($json_input)) {
            wp_send_json_error(array('message' => __('No JSON data provided', 'auta-minua-valitsemaan')));
        }
        
        // Decode JSON
        $import_data = json_decode($json_input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => sprintf(__('Invalid JSON: %s', 'auta-minua-valitsemaan'), json_last_error_msg())));
        }
        
        // Validate structure
        if (!is_array($import_data)) {
            wp_send_json_error(array('message' => __('Invalid configuration format', 'auta-minua-valitsemaan')));
        }
        
        // Get current config to preserve settings
        $current_config = AMV_Helper::get_config();
        
        // Merge imported data with current config (preserve styles, tracking settings, etc.)
        $new_config = array(
            'steps' => isset($import_data['steps']) && is_array($import_data['steps']) ? AMV_Helper::sanitize_steps($import_data['steps']) : $current_config['steps'],
            'recommendations' => isset($import_data['recommendations']) && is_array($import_data['recommendations']) ? AMV_Helper::sanitize_recommendations($import_data['recommendations']) : $current_config['recommendations'],
            'styles' => $current_config['styles'] ?? array(),
            'tracking_enabled' => $current_config['tracking_enabled'] ?? '1',
            'debug_enabled' => $current_config['debug_enabled'] ?? '1',
        );
        
        // Save configuration
        AMV_Helper::save_config($new_config);
        
        wp_send_json_success(array(
            'message' => __('Configuration imported successfully', 'auta-minua-valitsemaan'),
            'steps_count' => count($new_config['steps']),
            'recommendations_count' => count($new_config['recommendations']),
        ));
    }
    
    /**
     * AJAX handler for generating starter pack
     */
    public function ajax_generate_starter_pack() {
        check_ajax_referer('amv_generate_starter_pack', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'auta-minua-valitsemaan')));
        }
        
        $starter_pack = array(
            'generated_date' => current_time('mysql'),
            'site_url' => home_url(),
            'site_name' => get_bloginfo('name'),
            'pages' => array(),
            'posts' => array(),
            'custom_post_types' => array(),
            'products' => array(),
            'menus' => array(),
        );
        
        // Get pages (limit 100) - only names and IDs
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        
        foreach ($pages as $page) {
            $starter_pack['pages'][] = array(
                'id' => $page->ID,
                'title' => get_the_title($page->ID),
            );
        }
        
        // Get posts (limit 100) - only names and IDs
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        
        foreach ($posts as $post) {
            $starter_pack['posts'][] = array(
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
            );
        }
        
        // Get custom post types (limit 100 each) - only names and IDs
        $post_types = get_post_types(array('public' => true), 'objects');
        $excluded_types = array('page', 'post', 'attachment', 'revision', 'nav_menu_item');
        
        if (class_exists('WooCommerce')) {
            $excluded_types[] = 'product'; // Handle products separately
        }
        
        foreach ($post_types as $post_type_name => $post_type_obj) {
            if (in_array($post_type_name, $excluded_types)) {
                continue;
            }
            
            $custom_posts = get_posts(array(
                'post_type' => $post_type_name,
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'orderby' => 'title',
                'order' => 'ASC',
            ));
            
            if (!empty($custom_posts)) {
                $starter_pack['custom_post_types'][$post_type_name] = array(
                    'label' => $post_type_obj->labels->name,
                    'singular_label' => $post_type_obj->labels->singular_name,
                    'items' => array(),
                );
                
                foreach ($custom_posts as $custom_post) {
                    $starter_pack['custom_post_types'][$post_type_name]['items'][] = array(
                        'id' => $custom_post->ID,
                        'title' => get_the_title($custom_post->ID),
                    );
                }
            }
        }
        
        // Get WooCommerce products if installed (limit 100) - only names and IDs
        if (class_exists('WooCommerce')) {
            $products = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'orderby' => 'title',
                'order' => 'ASC',
            ));
            
            foreach ($products as $product) {
                $starter_pack['products'][] = array(
                    'id' => $product->ID,
                    'title' => get_the_title($product->ID),
                );
            }
        }
        
        // Get menu hierarchy
        $menus = wp_get_nav_menus();
        foreach ($menus as $menu) {
            $menu_items = wp_get_nav_menu_items($menu->term_id);
            if ($menu_items) {
                $menu_structure = $this->build_menu_hierarchy($menu_items);
                $starter_pack['menus'][] = array(
                    'id' => $menu->term_id,
                    'name' => $menu->name,
                    'items' => $menu_structure,
                );
            }
        }
        
        $json = wp_json_encode($starter_pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            wp_send_json_error(array('message' => __('Failed to generate JSON', 'auta-minua-valitsemaan')));
        }
        
        wp_send_json_success(array('json' => $json));
    }
    
    /**
     * Build menu hierarchy from menu items
     */
    private function build_menu_hierarchy($menu_items, $parent_id = 0) {
        $children = array();
        
        foreach ($menu_items as $item) {
            if ($item->menu_item_parent == $parent_id) {
                $menu_item = array(
                    'id' => $item->ID,
                    'title' => $item->title,
                    'url' => $item->url,
                    'object_id' => $item->object_id,
                    'object' => $item->object, // 'page', 'post', 'custom', etc.
                );
                
                // Recursively get children
                $child_items = $this->build_menu_hierarchy($menu_items, $item->ID);
                if (!empty($child_items)) {
                    $menu_item['children'] = $child_items;
                }
                
                $children[] = $menu_item;
            }
        }
        
        return $children;
    }
    
    /**
     * Add settings page under Tools menu
     */
    public function add_settings_page() {
        add_management_page(
            __('Help me choose', 'auta-minua-valitsemaan'),
            __('Help me choose', 'auta-minua-valitsemaan'),
            'manage_options',
            'auta-minua-valitsemaan',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Ensure database table exists
        AMV_Database::create_table();
        
        $config = AMV_Helper::get_config();
        $tracking_enabled = isset($config['tracking_enabled']) ? $config['tracking_enabled'] : '1';
        
        // Check if steps are empty to determine default tab
        $steps_empty = true;
        if (!empty($config['steps']) && is_array($config['steps'])) {
            foreach ($config['steps'] as $step) {
                // Check if step has a title or at least one option with a label
                if (!empty($step['title']) || (!empty($step['options']) && is_array($step['options']))) {
                    $has_content = false;
                    if (!empty($step['title'])) {
                        $has_content = true;
                    } else {
                        foreach ($step['options'] as $option) {
                            if (!empty($option['label'])) {
                                $has_content = true;
                                break;
                            }
                        }
                    }
                    if ($has_content) {
                        $steps_empty = false;
                        break;
                    }
                }
            }
        }
        $default_tab = $steps_empty ? 'wizard' : 'steps';
        
        if (isset($_GET['settings-updated'])) {
            if ($_GET['settings-updated'] === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'auta-minua-valitsemaan') . '</p></div>';
            } elseif (isset($_GET['error'])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post(urldecode($_GET['error'])) . '</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('amv_save_config', 'amv_nonce'); ?>
                <input type="hidden" name="action" value="amv_save_config">
                
                <!-- Tab Navigation -->
                <nav class="nav-tab-wrapper amv-nav-tab-wrapper">
                    <a href="#amv-tab-steps" class="nav-tab <?php echo ($default_tab === 'steps') ? 'nav-tab-active' : ''; ?> amv-tab-link" data-tab="steps">
                        <?php _e('Steps', 'auta-minua-valitsemaan'); ?>
                    </a>
                    <a href="#amv-tab-recommendations" class="nav-tab amv-tab-link" data-tab="recommendations">
                        <?php _e('Recommendations', 'auta-minua-valitsemaan'); ?>
                    </a>
                    <a href="#amv-tab-styling" class="nav-tab amv-tab-link" data-tab="styling">
                        <?php _e('Styling', 'auta-minua-valitsemaan'); ?>
                    </a>
                    <?php if ($tracking_enabled === '1'): ?>
                    <a href="#amv-tab-statistics" class="nav-tab amv-tab-link" data-tab="statistics">
                        <?php _e('Statistics', 'auta-minua-valitsemaan'); ?>
                    </a>
                    <?php endif; ?>
                    <a href="#amv-tab-settings" class="nav-tab amv-tab-link" data-tab="settings">
                        <?php _e('Settings', 'auta-minua-valitsemaan'); ?>
                    </a>
                    <a href="#amv-tab-shortcode" class="nav-tab amv-tab-link" data-tab="shortcode">
                        <?php _e('Shortcode', 'auta-minua-valitsemaan'); ?>
                    </a>
                    <a href="#amv-tab-wizard" class="nav-tab <?php echo ($default_tab === 'wizard') ? 'nav-tab-active' : ''; ?> amv-tab-link" data-tab="wizard">
                        <?php _e('Installation Wizard', 'auta-minua-valitsemaan'); ?>
                    </a>
                </nav>
                
                <!-- Tab Panels -->
                <div class="amv-tab-panel" id="amv-tab-steps" style="<?php echo ($default_tab === 'steps') ? '' : 'display: none;'; ?>">
                    <div id="amv-steps-container">
                        <h2><?php _e('Steps Configuration', 'auta-minua-valitsemaan'); ?></h2>
                        <p class="description"><?php _e('Configure the steps of your decision tree. Each option can have a target step - when selected, it will jump to that specific step. If no target is set, it will go to the next sequential step. The recommendation will be shown after the last step.', 'auta-minua-valitsemaan'); ?></p>
                        
                        <div style="margin-bottom: 10px;">
                            <button type="button" id="amv-expand-all-steps" class="button"><?php _e('Expand All', 'auta-minua-valitsemaan'); ?></button>
                            <button type="button" id="amv-collapse-all-steps" class="button"><?php _e('Collapse All', 'auta-minua-valitsemaan'); ?></button>
                        </div>
                        
                        <div id="amv-steps-list">
                            <?php
                            if (!empty($config['steps'])) {
                                $step_index = 1;
                                foreach ($config['steps'] as $step_id => $step) {
                                    $this->render_step_config($step_id, $step, $config, $step_index);
                                    $step_index++;
                                }
                            } else {
                                // Default empty step
                                $this->render_step_config('step_1', array('title' => '', 'options' => array()), $config, 1);
                            }
                            ?>
                        </div>
                        
                        <button type="button" id="amv-add-step" class="button"><?php _e('Add Step', 'auta-minua-valitsemaan'); ?></button>
                    </div>
                </div>
                
                <div class="amv-tab-panel" id="amv-tab-recommendations" style="display: none;">
                    <div id="amv-recommendations-container">
                        <h2><?php _e('Recommendations', 'auta-minua-valitsemaan'); ?></h2>
                        <p class="description"><?php _e('Define the recommendation that will be shown after completing all steps. Only one recommendation will be displayed.', 'auta-minua-valitsemaan'); ?></p>
                        
                        <div id="amv-recommendations-list">
                            <?php
                            if (!empty($config['recommendations'])) {
                                foreach ($config['recommendations'] as $rec_id => $rec) {
                                    $this->render_recommendation_config($rec_id, $rec);
                                }
                            }
                            ?>
                        </div>
                        
                        <button type="button" id="amv-add-recommendation" class="button"><?php _e('Add Recommendation', 'auta-minua-valitsemaan'); ?></button>
                    </div>
                </div>
                
                <div class="amv-tab-panel" id="amv-tab-styling" style="display: none;">
                    <div id="amv-styling-container">
                        <h2><?php _e('Styling Options', 'auta-minua-valitsemaan'); ?></h2>
                        <p class="description"><?php _e('Customize the appearance of your decision tree form.', 'auta-minua-valitsemaan'); ?></p>
                        
                        <?php
                        $styles = $config['styles'] ?? array();
                        $this->render_styling_options($styles);
                        ?>
                    </div>
                </div>
                
                <?php if ($tracking_enabled === '1'): ?>
                <div class="amv-tab-panel" id="amv-tab-statistics" style="display: none;">
                    <div id="amv-statistics-container">
                        <h2><?php _e('Usage Statistics', 'auta-minua-valitsemaan'); ?></h2>
                        <p class="description"><?php _e('View statistics about how users interact with your decision tree tool.', 'auta-minua-valitsemaan'); ?></p>
                        
                        <?php
                        // Check if table exists, if not show message
                        global $wpdb;
                        $table_name = AMV_Database::get_table_name();
                        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                        
                        if (!$table_exists) {
                            echo '<div class="notice notice-warning"><p>' . __('Database table not found. Please deactivate and reactivate the plugin to create it.', 'auta-minua-valitsemaan') . '</p></div>';
                            echo '<p><button type="button" class="button" id="amv-create-table">' . __('Create Table Now', 'auta-minua-valitsemaan') . '</button></p>';
                        } else {
                            $stats = AMV_Database::get_statistics();
                            $this->render_statistics($stats);
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="amv-tab-panel" id="amv-tab-settings" style="display: none;">
                    <div id="amv-settings-container">
                        <h2><?php _e('Settings', 'auta-minua-valitsemaan'); ?></h2>
                        <p class="description"><?php _e('Configure plugin settings and options.', 'auta-minua-valitsemaan'); ?></p>
                        
                        <?php
                        $tracking_enabled = isset($config['tracking_enabled']) ? $config['tracking_enabled'] : '1';
                        $debug_enabled = isset($config['debug_enabled']) ? $config['debug_enabled'] : '1';
                        $this->render_settings_options($tracking_enabled, $debug_enabled);
                        ?>
                    </div>
                </div>
                
                <div class="amv-tab-panel" id="amv-tab-shortcode" style="display: none;">
                    <div id="amv-shortcode-container">
                        <h2><?php _e('Shortcode', 'auta-minua-valitsemaan'); ?></h2>
                        <p class="description"><?php _e('Use this shortcode to display the decision tree form on any page or post.', 'auta-minua-valitsemaan'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Shortcode', 'auta-minua-valitsemaan'); ?></label></th>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="text" readonly value="[auta_minua_valitsemaan]" id="amv-shortcode-input" class="regular-text" style="font-family: monospace; font-size: 14px; background: #f5f5f5;">
                                        <button type="button" class="button" id="amv-copy-shortcode">
                                            <?php _e('Copy', 'auta-minua-valitsemaan'); ?>
                                        </button>
                                    </div>
                                    <p class="description" id="amv-copy-message" style="display: none; color: #46b450; margin-top: 5px;">
                                        <?php _e('Shortcode copied to clipboard!', 'auta-minua-valitsemaan'); ?>
                                    </p>
                                    <p class="description">
                                        <?php _e('Paste this shortcode into any page or post where you want to display the decision tree form.', 'auta-minua-valitsemaan'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="amv-tab-panel" id="amv-tab-wizard" style="<?php echo ($default_tab === 'wizard') ? '' : 'display: none;'; ?>">
                    <div id="amv-wizard-container">
                        <h2><?php _e('Installation Wizard', 'auta-minua-valitsemaan'); ?></h2>
                        <p class="description"><?php _e('Follow these simple steps to set up your decision tree with AI assistance.', 'auta-minua-valitsemaan'); ?></p>
                        
                        <div class="amv-wizard-steps" style="margin-top: 30px;">
                            <!-- Step 1 -->
                            <div class="amv-wizard-step" data-step="1" style="padding: 20px; margin-bottom: 20px; border: 2px solid #0073aa; border-radius: 8px; background: #f0f7fc;">
                                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #0073aa; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">1</div>
                                    <h3 style="margin: 0;"><?php _e('Generate Starter Pack', 'auta-minua-valitsemaan'); ?></h3>
                                </div>
                                <p style="margin: 0 0 15px 55px; color: #666;"><?php _e('Download a JSON file containing all your pages, posts, custom post types, products, and menu structure.', 'auta-minua-valitsemaan'); ?></p>
                                <div style="margin-left: 55px;">
                                    <button type="button" class="button button-primary" id="amv-wizard-generate-pack">
                                        <?php _e('Generate Starter Pack', 'auta-minua-valitsemaan'); ?>
                                    </button>
                                    <span id="amv-wizard-step1-status" style="margin-left: 15px; color: #46b450; display: none;">
                                        <span class="dashicons dashicons-yes-alt"></span> <?php _e('Starter pack generated!', 'auta-minua-valitsemaan'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Step 2 -->
                            <div class="amv-wizard-step" data-step="2" style="padding: 20px; margin-bottom: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">2</div>
                                    <h3 style="margin: 0;"><?php _e('Upload to AI Tool', 'auta-minua-valitsemaan'); ?></h3>
                                </div>
                                <p style="margin: 0 0 15px 55px; color: #666;"><?php _e('Open the AI configuration generator and upload your starter pack file. The tool will analyze your website and generate a decision tree configuration.', 'auta-minua-valitsemaan'); ?></p>
                                <div style="margin-left: 55px;">
                                    <a href="https://chatgpt.com/g/g-6953b0ee7ea48191b01e52c18e939f2e" target="_blank" class="button button-secondary">
                                        <?php _e('Open AI Configuration Generator', 'auta-minua-valitsemaan'); ?>
                                        <span class="dashicons dashicons-external" style="margin-left: 5px;"></span>
                                    </a>
                                    <p style="margin-top: 10px; color: #666;">
                                        <?php _e('After uploading your starter pack, copy the generated JSON configuration.', 'auta-minua-valitsemaan'); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Step 3 -->
                            <div class="amv-wizard-step" data-step="3" style="padding: 20px; margin-bottom: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">3</div>
                                    <h3 style="margin: 0;"><?php _e('Paste Generated JSON', 'auta-minua-valitsemaan'); ?></h3>
                                </div>
                                <p style="margin: 0 0 15px 55px; color: #666;"><?php _e('Paste the JSON configuration generated by the AI tool below.', 'auta-minua-valitsemaan'); ?></p>
                                <div style="margin-left: 55px;">
                                    <textarea id="amv-wizard-import-json" rows="10" class="large-text code" placeholder='<?php _e('Paste JSON configuration here...', 'auta-minua-valitsemaan'); ?>' style="font-family: monospace; font-size: 12px; width: 100%;"></textarea>
                                    <button type="button" class="button button-secondary" id="amv-wizard-import-config" style="margin-top: 10px;">
                                        <?php _e('Import Configuration', 'auta-minua-valitsemaan'); ?>
                                    </button>
                                    <div id="amv-wizard-import-message" style="margin-top: 10px; display: none;"></div>
                                </div>
                            </div>
                            
                            <!-- Step 4 -->
                            <div class="amv-wizard-step" data-step="4" style="padding: 20px; margin-bottom: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">4</div>
                                    <h3 style="margin: 0;"><?php _e('Voila!', 'auta-minua-valitsemaan'); ?></h3>
                                </div>
                                <p style="margin: 0 0 15px 55px; color: #666;"><?php _e('Your decision tree is now configured! You can customize it further in the Steps and Recommendations tabs.', 'auta-minua-valitsemaan'); ?></p>
                                <div style="margin-left: 55px;">
                                    <p style="color: #46b450; font-weight: 600; display: none;" id="amv-wizard-step4-success">
                                        <span class="dashicons dashicons-yes-alt"></span> <?php _e('Configuration imported successfully!', 'auta-minua-valitsemaan'); ?>
                                    </p>
                                    <p style="margin-top: 15px;">
                                        <a href="#amv-tab-steps" class="amv-tab-link button" data-tab="steps"><?php _e('Go to Steps', 'auta-minua-valitsemaan'); ?></a>
                                        <a href="#amv-tab-recommendations" class="amv-tab-link button" data-tab="recommendations" style="margin-left: 10px;"><?php _e('Go to Recommendations', 'auta-minua-valitsemaan'); ?></a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Configuration', 'auta-minua-valitsemaan'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render styling options
     */
    private function render_styling_options($styles) {
        $style_preset = $styles['style_preset'] ?? 'custom';
        
        $container_width = $styles['container_width'] ?? '100';
        $container_width_unit = $styles['container_width_unit'] ?? '%';
        $container_padding = $styles['container_padding'] ?? '0';
        
        $step_border_color = $styles['step_border_color'] ?? '#e0e0e0';
        $step_border_width = $styles['step_border_width'] ?? '2';
        $step_border_radius = $styles['step_border_radius'] ?? '8';
        $step_border_enabled = isset($styles['step_border_enabled']) ? $styles['step_border_enabled'] : '0';
        $step_bg_color = $styles['step_bg_color'] ?? '#ffffff';
        $step_text_color = $styles['step_text_color'] ?? '#000000';
        $step_padding = isset($styles['step_padding']) ? $styles['step_padding'] : '0';
        
        $option_border_color = $styles['option_border_color'] ?? '#e0e0e0';
        $option_border_width = $styles['option_border_width'] ?? '1';
        $option_border_radius = $styles['option_border_radius'] ?? '8';
        $option_border_enabled = isset($styles['option_border_enabled']) ? $styles['option_border_enabled'] : '0';
        $option_bg_color = $styles['option_bg_color'] ?? '#ffffff';
        $option_hover_bg_color = $styles['option_hover_bg_color'] ?? '#f5f5f5';
        $option_text_color = $styles['option_text_color'] ?? '#000000';
        $option_padding = isset($styles['option_padding']) ? $styles['option_padding'] : '0';
        
        $image_size = $styles['image_size'] ?? '300';
        ?>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Style Preset', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <select name="styles[style_preset]" id="amv-style-preset" style="min-width: 200px;">
                        <option value="style1" <?php selected($style_preset, 'style1'); ?>><?php _e('Style 1', 'auta-minua-valitsemaan'); ?></option>
                        <option value="style2" <?php selected($style_preset, 'style2'); ?>><?php _e('Style 2', 'auta-minua-valitsemaan'); ?></option>
                        <option value="style3" <?php selected($style_preset, 'style3'); ?>><?php _e('Style 3', 'auta-minua-valitsemaan'); ?></option>
                        <option value="custom" <?php selected($style_preset, 'custom'); ?>><?php _e('Custom', 'auta-minua-valitsemaan'); ?></option>
                    </select>
                    <p class="description"><?php _e('Select a preset style or choose Custom to modify individual styling options.', 'auta-minua-valitsemaan'); ?></p>
                </td>
            </tr>
        </table>
        
        <div id="amv-custom-styles" style="<?php echo ($style_preset === 'custom') ? '' : 'display: none;'; ?>">
        <table class="form-table">
            <tr>
                <th colspan="2"><h3><?php _e('Container Styling', 'auta-minua-valitsemaan'); ?></h3></th>
            </tr>
            <tr>
                <th><label><?php _e('Width', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[container_width]" value="<?php echo esc_attr($container_width); ?>" min="0" max="2000" step="1" class="small-text">
                    <select name="styles[container_width_unit]">
                        <option value="%" <?php selected($container_width_unit, '%'); ?>>%</option>
                        <option value="px" <?php selected($container_width_unit, 'px'); ?>>px</option>
                    </select>
                    <p class="description"><?php _e('Container width (default: 100%)', 'auta-minua-valitsemaan'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Padding (px)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[container_padding]" value="<?php echo esc_attr($container_padding); ?>" min="0" max="100" step="5" class="small-text"> px
                    <p class="description"><?php _e('Container padding (default: 0)', 'auta-minua-valitsemaan'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h3 style="margin-top: 20px;"><?php _e('Step Styling', 'auta-minua-valitsemaan'); ?></h3></th>
            </tr>
            <tr>
                <th colspan="2"><h3><?php _e('Step Styling', 'auta-minua-valitsemaan'); ?></h3></th>
            </tr>
            <tr>
                <th><label><?php _e('Enable Borders', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="styles[step_border_enabled]" value="1" <?php checked($step_border_enabled, '1'); ?>>
                        <?php _e('Show borders on steps', 'auta-minua-valitsemaan'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Border Color', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="color" name="styles[step_border_color]" value="<?php echo esc_attr($step_border_color); ?>" class="amv-color-picker">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Border Width (px)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[step_border_width]" value="<?php echo esc_attr($step_border_width); ?>" min="0" max="10" step="1" class="small-text"> px
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Border Radius (px)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[step_border_radius]" value="<?php echo esc_attr($step_border_radius); ?>" min="0" max="50" step="1" class="small-text"> px
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Background Color', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="color" name="styles[step_bg_color]" value="<?php echo esc_attr($step_bg_color); ?>" class="amv-color-picker">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Text Color', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="color" name="styles[step_text_color]" value="<?php echo esc_attr($step_text_color); ?>" class="amv-color-picker">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Padding (px)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[step_padding]" value="<?php echo esc_attr($step_padding); ?>" min="0" max="100" step="5" class="small-text"> px
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h3 style="margin-top: 20px;"><?php _e('Option Card Styling', 'auta-minua-valitsemaan'); ?></h3></th>
            </tr>
            <tr>
                <th><label><?php _e('Enable Borders', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="styles[option_border_enabled]" value="1" <?php checked($option_border_enabled, '1'); ?>>
                        <?php _e('Show borders on option cards', 'auta-minua-valitsemaan'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Border Color', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="color" name="styles[option_border_color]" value="<?php echo esc_attr($option_border_color); ?>" class="amv-color-picker">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Border Width (px)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[option_border_width]" value="<?php echo esc_attr($option_border_width); ?>" min="0" max="10" step="1" class="small-text"> px
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Border Radius (px)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[option_border_radius]" value="<?php echo esc_attr($option_border_radius); ?>" min="0" max="50" step="1" class="small-text"> px
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Background Color', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="color" name="styles[option_bg_color]" value="<?php echo esc_attr($option_bg_color); ?>" class="amv-color-picker">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Hover Background Color', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="color" name="styles[option_hover_bg_color]" value="<?php echo esc_attr($option_hover_bg_color); ?>" class="amv-color-picker">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Text Color', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="color" name="styles[option_text_color]" value="<?php echo esc_attr($option_text_color); ?>" class="amv-color-picker">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Padding (px)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[option_padding]" value="<?php echo esc_attr($option_padding); ?>" min="0" max="100" step="5" class="small-text"> px
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h3 style="margin-top: 20px;"><?php _e('Image Settings', 'auta-minua-valitsemaan'); ?></h3></th>
            </tr>
            <tr>
                <th><label><?php _e('Option Image Size (px)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <input type="number" name="styles[image_size]" value="<?php echo esc_attr($image_size); ?>" min="50" max="800" step="10" class="small-text"> px
                    <p class="description"><?php _e('Maximum width/height for option images', 'auta-minua-valitsemaan'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><h3 style="margin-top: 20px;"><?php _e('Results Settings', 'auta-minua-valitsemaan'); ?></h3></th>
            </tr>
            <tr>
                <th><label><?php _e('Excerpt Length (characters)', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <?php
                    $results_excerpt_length = isset($styles['results_excerpt_length']) ? absint($styles['results_excerpt_length']) : 35;
                    ?>
                    <input type="number" name="styles[results_excerpt_length]" value="<?php echo esc_attr($results_excerpt_length); ?>" min="0" max="500" step="1" class="small-text">
                    <p class="description"><?php _e('Maximum number of characters for recommendation excerpts (0 = no limit)', 'auta-minua-valitsemaan'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('What to Show', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <?php
                    $results_show_title = isset($styles['results_show_title']) ? $styles['results_show_title'] : '1';
                    $results_show_image = isset($styles['results_show_image']) ? $styles['results_show_image'] : '1';
                    $results_show_excerpt = isset($styles['results_show_excerpt']) ? $styles['results_show_excerpt'] : '1';
                    ?>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" name="styles[results_show_title]" value="1" <?php checked($results_show_title, '1'); ?>>
                        <?php _e('Show Title', 'auta-minua-valitsemaan'); ?>
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" name="styles[results_show_image]" value="1" <?php checked($results_show_image, '1'); ?>>
                        <?php _e('Show Image', 'auta-minua-valitsemaan'); ?>
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" name="styles[results_show_excerpt]" value="1" <?php checked($results_show_excerpt, '1'); ?>>
                        <?php _e('Show Excerpt', 'auta-minua-valitsemaan'); ?>
                    </label>
                    <p class="description"><?php _e('Select which elements to display in recommendation cards', 'auta-minua-valitsemaan'); ?></p>
                </td>
            </tr>
        </table>
        </div>
        <?php
    }
    
    /**
     * Get preset style configurations
     */
    private function get_preset_styles($preset_name) {
        $presets = array(
            'style1' => array(
                'container_width' => '100',
                'container_width_unit' => '%',
                'container_padding' => '20',
                'step_border_enabled' => '1',
                'step_border_color' => '#e0e0e0',
                'step_border_width' => '2',
                'step_border_radius' => '8',
                'step_bg_color' => '#ffffff',
                'step_text_color' => '#000000',
                'step_padding' => '30',
                'option_border_enabled' => '1',
                'option_border_color' => '#e0e0e0',
                'option_border_width' => '1',
                'option_border_radius' => '8',
                'option_bg_color' => '#ffffff',
                'option_hover_bg_color' => '#f5f5f5',
                'option_text_color' => '#000000',
                'option_padding' => '20',
                'image_size' => '300',
                'results_excerpt_length' => '35',
                'results_show_title' => '1',
                'results_show_image' => '1',
                'results_show_excerpt' => '1',
            ),
            'style2' => array(
                'container_width' => '100',
                'container_width_unit' => '%',
                'container_padding' => '0',
                'step_border_enabled' => '0',
                'step_border_color' => '#0073aa',
                'step_border_width' => '0',
                'step_border_radius' => '0',
                'step_bg_color' => '#f8f9fa',
                'step_text_color' => '#212529',
                'step_padding' => '40',
                'option_border_enabled' => '1',
                'option_border_color' => '#0073aa',
                'option_border_width' => '2',
                'option_border_radius' => '4',
                'option_bg_color' => '#ffffff',
                'option_hover_bg_color' => '#e7f3ff',
                'option_text_color' => '#0073aa',
                'option_padding' => '25',
                'image_size' => '250',
                'results_excerpt_length' => '35',
                'results_show_title' => '1',
                'results_show_image' => '1',
                'results_show_excerpt' => '1',
            ),
            'style3' => array(
                'container_width' => '100',
                'container_width_unit' => '%',
                'container_padding' => '15',
                'step_border_enabled' => '1',
                'step_border_color' => '#d4af37',
                'step_border_width' => '3',
                'step_border_radius' => '12',
                'step_bg_color' => '#fffef7',
                'step_text_color' => '#333333',
                'step_padding' => '35',
                'option_border_enabled' => '1',
                'option_border_color' => '#d4af37',
                'option_border_width' => '2',
                'option_border_radius' => '10',
                'option_bg_color' => '#ffffff',
                'option_hover_bg_color' => '#fff9e6',
                'option_text_color' => '#333333',
                'option_padding' => '22',
                'image_size' => '280',
                'results_excerpt_length' => '35',
                'results_show_title' => '1',
                'results_show_image' => '1',
                'results_show_excerpt' => '1',
            ),
        );
        
        return isset($presets[$preset_name]) ? $presets[$preset_name] : array();
    }
    
    /**
     * Render settings options
     */
    private function render_settings_options($tracking_enabled = '1', $debug_enabled = '1') {
        // Check update status
        $update_info = $this->check_update_status();
        ?>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Enable Tracking', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="tracking_enabled" value="1" <?php checked($tracking_enabled, '1'); ?>>
                        <?php _e('Track user interactions and statistics', 'auta-minua-valitsemaan'); ?>
                    </label>
                    <p class="description"><?php _e('When disabled, no user data will be tracked or stored.', 'auta-minua-valitsemaan'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Show Debug Information', 'auta-minua-valitsemaan'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="debug_enabled" value="1" <?php checked($debug_enabled, '1'); ?>>
                        <?php _e('Display debug information on the frontend (admin only)', 'auta-minua-valitsemaan'); ?>
                    </label>
                    <p class="description"><?php _e('When enabled, logged-in administrators will see debug information at the bottom of the form.', 'auta-minua-valitsemaan'); ?></p>
                </td>
            </tr>
        </table>
        
        <div class="amv-update-checker" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <h3><?php _e('Update Checker', 'auta-minua-valitsemaan'); ?></h3>
            <p><?php _e('Check if updates are available for this plugin.', 'auta-minua-valitsemaan'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th><?php _e('Current Version', 'auta-minua-valitsemaan'); ?></th>
                    <td><strong><?php echo esc_html(AMV_VERSION); ?></strong></td>
                </tr>
                <tr>
                    <th><?php _e('Latest Version', 'auta-minua-valitsemaan'); ?></th>
                    <td>
                        <?php if ($update_info['success']): ?>
                            <strong><?php echo esc_html($update_info['remote_version']); ?></strong>
                            <?php 
                            $comparison = version_compare(AMV_VERSION, $update_info['remote_version'], '<');
                            if ($comparison): ?>
                                <span style="color: #d63638; margin-left: 10px;"><?php _e('Update available!', 'auta-minua-valitsemaan'); ?></span>
                            <?php else: ?>
                                <span style="color: #00a32a; margin-left: 10px;"><?php _e('You are up to date.', 'auta-minua-valitsemaan'); ?></span>
                            <?php endif; ?>
                            <p class="description" style="margin-top: 5px;">
                                <?php 
                                printf(
                                    __('Current: %s | Remote: %s | Comparison: %s', 'auta-minua-valitsemaan'),
                                    esc_html(AMV_VERSION),
                                    esc_html($update_info['remote_version']),
                                    $comparison ? __('Update needed', 'auta-minua-valitsemaan') : __('No update needed', 'auta-minua-valitsemaan')
                                );
                                ?>
                            </p>
                        <?php else: ?>
                            <span style="color: #d63638;"><?php echo esc_html($update_info['error']); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('GitHub Repository', 'auta-minua-valitsemaan'); ?></th>
                    <td>
                        <a href="https://github.com/Tapiokansleri/help-me-choose-wp" target="_blank">https://github.com/Tapiokansleri/help-me-choose-wp</a>
                    </td>
                </tr>
            </table>
            
            <p>
                <button type="button" class="button" id="amv-check-updates"><?php _e('Check for Updates', 'auta-minua-valitsemaan'); ?></button>
                <button type="button" class="button" id="amv-clear-update-cache"><?php _e('Clear Update Cache', 'auta-minua-valitsemaan'); ?></button>
            </p>
            <p class="description">
                <?php _e('WordPress checks for updates automatically every 12 hours. Use "Clear Update Cache" to force an immediate check.', 'auta-minua-valitsemaan'); ?>
            </p>
        </div>
        
        <div class="amv-settings-import-export" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <h3><?php _e('Import / Export Configuration', 'auta-minua-valitsemaan'); ?></h3>
            <p class="description"><?php _e('Export your steps and recommendations configuration as JSON, or import a previously exported configuration.', 'auta-minua-valitsemaan'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Export Configuration', 'auta-minua-valitsemaan'); ?></label>
                    <td>
                        <button type="button" class="button button-primary" id="amv-export-config">
                            <?php _e('Export Configuration as JSON', 'auta-minua-valitsemaan'); ?>
                        </button>
                        <p class="description"><?php _e('Download your current steps and recommendations configuration as a JSON file.', 'auta-minua-valitsemaan'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Import Configuration', 'auta-minua-valitsemaan'); ?></label>
                    <td>
                        <textarea id="amv-import-json" rows="10" class="large-text code" placeholder='<?php _e('Paste JSON configuration here...', 'auta-minua-valitsemaan'); ?>' style="font-family: monospace; font-size: 12px;"></textarea>
                        <p class="description"><?php _e('Paste a JSON configuration exported from this plugin. This will replace your current configuration.', 'auta-minua-valitsemaan'); ?></p>
                        <button type="button" class="button button-secondary" id="amv-import-config" style="margin-top: 10px;">
                            <?php _e('Import Configuration', 'auta-minua-valitsemaan'); ?>
                        </button>
                        <div id="amv-import-message" style="margin-top: 10px; display: none;"></div>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Generate Starter Pack', 'auta-minua-valitsemaan'); ?></label>
                    <td>
                        <button type="button" class="button button-secondary" id="amv-generate-starter-pack">
                            <?php _e('Generate Starter Pack JSON', 'auta-minua-valitsemaan'); ?>
                        </button>
                        <p class="description"><?php _e('Generate a JSON file containing all pages, posts, custom post types, and products (if WooCommerce is installed) from your website. This can be used by AI tools to generate decision tree configurations.', 'auta-minua-valitsemaan'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="amv-settings-danger-zone" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #dc3232; border-radius: 4px;">
            <h3 style="color: #dc3232;"><?php _e('Danger Zone', 'auta-minua-valitsemaan'); ?></h3>
            <p><?php _e('Reset all tracking data. This action cannot be undone!', 'auta-minua-valitsemaan'); ?></p>
            <button type="button" class="button button-secondary" id="amv-reset-database" style="background: #dc3232; border-color: #dc3232; color: #fff;">
                <?php _e('Reset Database', 'auta-minua-valitsemaan'); ?>
            </button>
        </div>
        <?php
    }
    
    /**
     * Render statistics
     */
    private function render_statistics($stats) {
        $total_users = $stats['total_users'] ?? 0;
        $completed = $stats['completed'] ?? 0;
        $abandoned = $stats['abandoned'] ?? 0;
        
        $completion_rate = $total_users > 0 ? round(($completed / $total_users) * 100, 1) : 0;
        $abandonment_rate = $total_users > 0 ? round(($abandoned / $total_users) * 100, 1) : 0;
        ?>
        <div class="amv-statistics-wrapper">
            <div class="amv-stat-card">
                <h3><?php _e('Total Users', 'auta-minua-valitsemaan'); ?></h3>
                <div class="amv-stat-number"><?php echo esc_html($total_users); ?></div>
                <p class="amv-stat-description"><?php _e('Unique users who have used the tool', 'auta-minua-valitsemaan'); ?></p>
            </div>
            
            <div class="amv-stat-card amv-stat-success">
                <h3><?php _e('Completed', 'auta-minua-valitsemaan'); ?></h3>
                <div class="amv-stat-number"><?php echo esc_html($completed); ?></div>
                <p class="amv-stat-description">
                    <?php printf(__('%s%% completion rate', 'auta-minua-valitsemaan'), $completion_rate); ?>
                </p>
            </div>
            
            <div class="amv-stat-card amv-stat-warning">
                <h3><?php _e('Abandoned', 'auta-minua-valitsemaan'); ?></h3>
                <div class="amv-stat-number"><?php echo esc_html($abandoned); ?></div>
                <p class="amv-stat-description">
                    <?php printf(__('%s%% abandonment rate', 'auta-minua-valitsemaan'), $abandonment_rate); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render step configuration
     */
    private function render_step_config($step_id, $step, $config, $step_index = null) {
        // Use provided step index, or extract from step_id as fallback
        if ($step_index === null) {
            $step_num = preg_replace('/[^0-9]/', '', $step_id);
            // If no number found, use 1
            if (empty($step_num)) {
                $step_num = '1';
            }
        } else {
            $step_num = $step_index;
        }
        ?>
        <div class="amv-step-config amv-step-collapsed" data-step-id="<?php echo esc_attr($step_id); ?>">
            <div class="amv-step-header">
                <h3>
                    <button type="button" class="amv-toggle-step" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                    <?php _e('Step', 'auta-minua-valitsemaan'); ?> <span class="step-number"><?php echo esc_html($step_num); ?></span>
                    <?php if (!empty($step['title'])): ?>
                        <span class="step-title-preview">: <?php echo esc_html($step['title']); ?></span>
                    <?php endif; ?>
                </h3>
                <button type="button" class="button-link amv-remove-step"><?php _e('Remove', 'auta-minua-valitsemaan'); ?></button>
            </div>
            <div class="amv-step-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Step Title', 'auta-minua-valitsemaan'); ?></label></th>
                    <td>
                        <input type="text" name="steps[<?php echo esc_attr($step_id); ?>][title]" value="<?php echo esc_attr($step['title'] ?? ''); ?>" class="regular-text" placeholder="<?php _e('Enter step title', 'auta-minua-valitsemaan'); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Step Description', 'auta-minua-valitsemaan'); ?></label></th>
                    <td>
                        <textarea name="steps[<?php echo esc_attr($step_id); ?>][description]" rows="2" class="large-text" placeholder="<?php _e('Enter step description (optional)', 'auta-minua-valitsemaan'); ?>"><?php echo esc_textarea($step['description'] ?? ''); ?></textarea>
                        <p class="description"><?php _e('Optional text that appears under the step title', 'auta-minua-valitsemaan'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Options', 'auta-minua-valitsemaan'); ?></label></th>
                    <td>
                        <div class="amv-options-list">
                            <?php
                            if (!empty($step['options'])) {
                                foreach ($step['options'] as $opt_id => $option) {
                                    $this->render_option_config($step_id, $opt_id, $option, $config);
                                }
                            } else {
                                $this->render_option_config($step_id, 'opt_1', array('label' => ''), $config);
                            }
                            ?>
                        </div>
                        <button type="button" class="button amv-add-option"><?php _e('Add Option', 'auta-minua-valitsemaan'); ?></button>
                    </td>
                </tr>
            </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render option configuration
     */
    private function render_option_config($step_id, $opt_id, $option, $config) {
        $target_step = $option['target_step'] ?? '';
        $recommendations = $option['recommendations'] ?? array();
        $image_id = $option['image_id'] ?? '';
        if (!is_array($recommendations)) {
            $recommendations = array();
        }
        
        // Get image URL if exists
        $image_url = '';
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
        }
        ?>
        <div class="amv-option-config">
            <div class="amv-option-image-section">
                <div class="amv-option-image-preview" style="<?php echo $image_url ? '' : 'display:none;'; ?>">
                    <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 100px; height: auto;">
                    <?php endif; ?>
                </div>
                <input type="hidden" name="steps[<?php echo esc_attr($step_id); ?>][options][<?php echo esc_attr($opt_id); ?>][image_id]" class="amv-option-image-id" value="<?php echo esc_attr($image_id); ?>">
                <button type="button" class="button amv-upload-image-btn"><?php _e('Add Image', 'auta-minua-valitsemaan'); ?></button>
                <button type="button" class="button amv-remove-image-btn" style="<?php echo $image_id ? '' : 'display:none;'; ?>"><?php _e('Remove Image', 'auta-minua-valitsemaan'); ?></button>
            </div>
            <input type="text" name="steps[<?php echo esc_attr($step_id); ?>][options][<?php echo esc_attr($opt_id); ?>][label]" value="<?php echo esc_attr($option['label'] ?? ''); ?>" placeholder="<?php _e('Option label', 'auta-minua-valitsemaan'); ?>" class="regular-text">
            <textarea name="steps[<?php echo esc_attr($step_id); ?>][options][<?php echo esc_attr($opt_id); ?>][description]" rows="2" class="large-text" placeholder="<?php _e('Option description (optional)', 'auta-minua-valitsemaan'); ?>" style="margin-top: 5px;"><?php echo esc_textarea($option['description'] ?? ''); ?></textarea>
            <select name="steps[<?php echo esc_attr($step_id); ?>][options][<?php echo esc_attr($opt_id); ?>][target_step]" class="amv-target-step-select" style="margin-top: 5px;" required>
                <option value="" <?php echo empty($target_step) ? 'selected' : ''; ?>><?php _e('-- Select Target Step --', 'auta-minua-valitsemaan'); ?></option>
                <?php
                if (!empty($config['steps'])) {
                    foreach ($config['steps'] as $target_step_id => $target_step_data) {
                        if ($target_step_id !== $step_id) {
                            $selected = $target_step === $target_step_id ? 'selected' : '';
                            $step_title = !empty($target_step_data['title']) ? $target_step_data['title'] : $target_step_id;
                            echo '<option value="' . esc_attr($target_step_id) . '" ' . $selected . '>' . esc_html($step_title) . '</option>';
                        }
                    }
                }
                ?>
                <option value="RECOMMENDATION" <?php echo (strtoupper($target_step) === 'RECOMMENDATION') ? 'selected' : ''; ?>><?php _e('Jump to Recommendation', 'auta-minua-valitsemaan'); ?></option>
            </select>
            <div class="amv-recommendations-selector" style="display: <?php echo (strtoupper($target_step) === 'RECOMMENDATION') ? 'block' : 'none'; ?>;">
                <label class="amv-recommendations-label"><?php _e('Recommendations:', 'auta-minua-valitsemaan'); ?></label>
                <div class="amv-recommendations-checkboxes">
                    <?php
                    if (!empty($config['recommendations'])) {
                        foreach ($config['recommendations'] as $rec_id => $rec) {
                            $checked = in_array($rec_id, $recommendations) ? 'checked' : '';
                            $rec_title = !empty($rec['title']) ? $rec['title'] : $rec_id;
                            echo '<label class="amv-rec-checkbox-label">';
                            echo '<input type="checkbox" name="steps[' . esc_attr($step_id) . '][options][' . esc_attr($opt_id) . '][recommendations][]" value="' . esc_attr($rec_id) . '" ' . $checked . '> ';
                            echo esc_html($rec_title);
                            echo '</label>';
                        }
                    } else {
                        echo '<span class="description">' . __('No recommendations configured yet.', 'auta-minua-valitsemaan') . '</span>';
                    }
                    ?>
                </div>
            </div>
            <button type="button" class="button-link amv-remove-option"><?php _e('Remove', 'auta-minua-valitsemaan'); ?></button>
        </div>
        <?php
    }
    
    /**
     * Render recommendation configuration
     */
    private function render_recommendation_config($rec_id, $rec) {
        $content = $rec['content'] ?? '';
        $content_ids = $rec['content_ids'] ?? array();
        if (!is_array($content_ids)) {
            // Backward compatibility: if content_id exists, convert to array
            if (!empty($rec['content_id'])) {
                $content_ids = array($rec['content_id']);
            } else {
                $content_ids = array();
            }
        }
        
        // Get available post types
        $post_types = get_post_types(array('public' => true), 'objects');
        $hidden_types = array('attachment', 'revision', 'nav_menu_item');
        foreach ($hidden_types as $hidden) {
            unset($post_types[$hidden]);
        }
        
        // Get selected posts
        $selected_posts = array();
        if (!empty($content_ids)) {
            foreach ($content_ids as $post_id) {
                $post = get_post($post_id);
                if ($post && $post->post_status === 'publish') {
                    $selected_posts[$post_id] = $post;
                }
            }
        }
        ?>
        <div class="amv-recommendation-config" data-rec-id="<?php echo esc_attr($rec_id); ?>">
            <div class="amv-rec-header">
                <input type="text" name="recommendations[<?php echo esc_attr($rec_id); ?>][title]" value="<?php echo esc_attr($rec['title'] ?? ''); ?>" placeholder="<?php _e('Recommendation Title', 'auta-minua-valitsemaan'); ?>" class="regular-text">
                <button type="button" class="button-link amv-remove-recommendation"><?php _e('Remove', 'auta-minua-valitsemaan'); ?></button>
            </div>
            
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Description', 'auta-minua-valitsemaan'); ?></label></th>
                    <td>
                        <textarea name="recommendations[<?php echo esc_attr($rec_id); ?>][content]" rows="4" class="large-text" placeholder="<?php _e('Recommendation description (supports shortcodes)', 'auta-minua-valitsemaan'); ?>"><?php echo esc_textarea($content); ?></textarea>
                        <p class="description"><?php _e('Optional description text for this recommendation bundle. You can use shortcodes here.', 'auta-minua-valitsemaan'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Select Content Items', 'auta-minua-valitsemaan'); ?></label>
                    <td>
                        <div class="amv-post-selector">
                            <select class="amv-post-type-select" style="margin-bottom: 10px;">
                                <option value=""><?php _e('-- Select Post Type --', 'auta-minua-valitsemaan'); ?></option>
                                <?php foreach ($post_types as $post_type_name => $post_type_obj): ?>
                                    <option value="<?php echo esc_attr($post_type_name); ?>">
                                        <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" class="amv-post-search regular-text" placeholder="<?php _e('Search for pages, products, or posts...', 'auta-minua-valitsemaan'); ?>" style="margin-bottom: 10px;">
                            <div class="amv-post-search-results" style="display:none;"></div>
                        </div>
                        <div class="amv-selected-content-list" style="margin-top: 15px;">
                            <p><strong><?php _e('Selected Items:', 'auta-minua-valitsemaan'); ?></strong></p>
                            <ul class="amv-content-items-list" style="list-style: none; padding: 0; margin: 10px 0;">
                                <?php foreach ($selected_posts as $post_id => $post): ?>
                                    <li class="amv-content-item" data-post-id="<?php echo esc_attr($post_id); ?>" style="padding: 8px; margin: 5px 0; background: #f5f5f5; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                                        <span><?php echo esc_html(get_the_title($post_id)); ?> <em>(<?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?>)</em></span>
                                        <input type="hidden" name="recommendations[<?php echo esc_attr($rec_id); ?>][content_ids][]" value="<?php echo esc_attr($post_id); ?>">
                                        <button type="button" class="button-link amv-remove-content-item" style="color: #dc3232;"><?php _e('Remove', 'auta-minua-valitsemaan'); ?></button>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (empty($selected_posts)): ?>
                                    <li class="amv-no-items" style="padding: 8px; color: #666; font-style: italic;"><?php _e('No items selected. Search and add items above.', 'auta-minua-valitsemaan'); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <p class="description"><?php _e('Search and select multiple pages, products, or posts to include in this recommendation bundle.', 'auta-minua-valitsemaan'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save configuration
     */
    public function save_config() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        check_admin_referer('amv_save_config', 'amv_nonce');
        
        // Validate that all options have a target step selected
        $validation_errors = array();
        if (isset($_POST['steps']) && is_array($_POST['steps'])) {
            foreach ($_POST['steps'] as $step_id => $step) {
                if (!empty($step['options']) && is_array($step['options'])) {
                    foreach ($step['options'] as $opt_id => $option) {
                        $step_title = !empty($step['title']) ? sanitize_text_field($step['title']) : $step_id;
                        $option_label = !empty($option['label']) ? sanitize_text_field($option['label']) : $opt_id;
                        
                        // Check if target step is selected
                        if (!empty($option['label']) && empty($option['target_step'])) {
                            $validation_errors[] = sprintf(
                                __('Option "%s" in step "%s" must have a target step selected.', 'auta-minua-valitsemaan'),
                                $option_label,
                                $step_title
                            );
                        }
                        
                        // Check if "Jump to Recommendation" is selected but no recommendations are chosen
                        if (!empty($option['label']) && strtoupper($option['target_step'] ?? '') === 'RECOMMENDATION') {
                            $selected_recommendations = !empty($option['recommendations']) && is_array($option['recommendations']) ? $option['recommendations'] : array();
                            if (empty($selected_recommendations)) {
                                $validation_errors[] = sprintf(
                                    __('Option "%s" in step "%s" jumps to recommendation but no recommendations are selected. Please select at least one recommendation.', 'auta-minua-valitsemaan'),
                                    $option_label,
                                    $step_title
                                );
                            }
                        }
                    }
                }
            }
        }
        
        // If validation errors exist, redirect back with error message
        if (!empty($validation_errors)) {
            // Escape HTML entities for each error message
            $error_message = implode('<br>', array_map('esc_html', $validation_errors));
            wp_redirect(add_query_arg(array(
                'settings-updated' => 'false',
                'error' => urlencode($error_message)
            ), admin_url('tools.php?page=auta-minua-valitsemaan')));
            exit;
        }
        
        // Handle style preset
        $styles = isset($_POST['styles']) ? $_POST['styles'] : array();
        $style_preset = isset($styles['style_preset']) ? sanitize_text_field($styles['style_preset']) : 'custom';
        
        if ($style_preset !== 'custom' && in_array($style_preset, array('style1', 'style2', 'style3'))) {
            // Apply preset styles
            $preset_styles = $this->get_preset_styles($style_preset);
            $styles = array_merge($preset_styles, array('style_preset' => $style_preset));
        }
        
        $config = array(
            'steps' => isset($_POST['steps']) ? AMV_Helper::sanitize_steps($_POST['steps']) : array(),
            'recommendations' => isset($_POST['recommendations']) ? AMV_Helper::sanitize_recommendations($_POST['recommendations']) : array(),
            'styles' => AMV_Helper::sanitize_styles($styles),
            'tracking_enabled' => isset($_POST['tracking_enabled']) ? '1' : '0',
            'debug_enabled' => isset($_POST['debug_enabled']) ? '1' : '0',
        );
        
        AMV_Helper::save_config($config);
        
        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('tools.php?page=auta-minua-valitsemaan')));
        exit;
    }
}

