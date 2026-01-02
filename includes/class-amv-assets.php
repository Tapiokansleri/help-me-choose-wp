<?php
/**
 * Assets class for CSS/JS enqueuing
 *
 * @package Auta_Minua_Valitsemaan
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets class
 */
class AMV_Assets {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('tools_page_auta-minua-valitsemaan' !== $hook) {
            return;
        }
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_media(); // For image uploader
        
        // Enqueue admin JS
        wp_enqueue_script(
            'amv-admin-js',
            AMV_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-autocomplete'),
            AMV_VERSION,
            true
        );
        
        // Localize script with data
        $config = AMV_Helper::get_config();
        $step_counter = 1;
        if (!empty($config['steps'])) {
            $max_step_num = 0;
            foreach ($config['steps'] as $step_id => $step) {
                $step_num = preg_replace('/[^0-9]/', '', $step_id);
                if ($step_num > $max_step_num) {
                    $max_step_num = (int) $step_num;
                }
            }
            $step_counter = $max_step_num + 1;
        }
        
        // Get post types
        $post_types = get_post_types(array('public' => true), 'objects');
        $hidden_types = array('attachment', 'revision', 'nav_menu_item');
        foreach ($hidden_types as $hidden) {
            unset($post_types[$hidden]);
        }
        $post_types_array = array();
        foreach ($post_types as $name => $obj) {
            $post_types_array[] = array('value' => $name, 'label' => $obj->labels->singular_name);
        }
        
        wp_localize_script('amv-admin-js', 'amvAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amv_search_posts'),
            'createTableNonce' => wp_create_nonce('amv_create_table'),
            'resetDatabaseNonce' => wp_create_nonce('amv_reset_database'),
            'exportConfigNonce' => wp_create_nonce('amv_export_config'),
            'importConfigNonce' => wp_create_nonce('amv_import_config'),
            'generateStarterPackNonce' => wp_create_nonce('amv_generate_starter_pack'),
            'checkUpdatesNonce' => wp_create_nonce('amv_check_updates'),
            'clearUpdateCacheNonce' => wp_create_nonce('amv_clear_update_cache'),
            'stepCounter' => $step_counter,
            'config' => $config,
            'postTypes' => $post_types_array,
            'strings' => array(
                'step' => __('Step', 'auta-minua-valitsemaan'),
                'remove' => __('Remove', 'auta-minua-valitsemaan'),
                'step_title' => __('Step Title', 'auta-minua-valitsemaan'),
                'enter_step_title' => __('Enter step title', 'auta-minua-valitsemaan'),
                'options' => __('Options', 'auta-minua-valitsemaan'),
                'add_option' => __('Add Option', 'auta-minua-valitsemaan'),
                'option_label' => __('Option label', 'auta-minua-valitsemaan'),
                'option_description' => __('Option description (optional)', 'auta-minua-valitsemaan'),
                'target_step' => __('Target Step', 'auta-minua-valitsemaan'),
                'select_target_step' => __('-- Select Target Step --', 'auta-minua-valitsemaan'),
                'next_step_sequential' => __('Next Step (Sequential)', 'auta-minua-valitsemaan'),
                'jump_to_recommendation' => __('Jump to Recommendation', 'auta-minua-valitsemaan'),
                'recommendations_label' => __('Recommendations:', 'auta-minua-valitsemaan'),
                'no_recommendations' => __('No recommendations configured yet.', 'auta-minua-valitsemaan'),
                'recommendation_title' => __('Recommendation Title', 'auta-minua-valitsemaan'),
                'recommendation_content' => __('Recommendation content', 'auta-minua-valitsemaan'),
                'content_type' => __('Content Type', 'auta-minua-valitsemaan'),
                'plain_text' => __('Plain Text', 'auta-minua-valitsemaan'),
                'select_content' => __('Select Content', 'auta-minua-valitsemaan'),
                'select_content_items' => __('Select Content Items', 'auta-minua-valitsemaan'),
                'select_content_items_help' => __('Search and select multiple pages, products, or posts to include in this recommendation bundle.', 'auta-minua-valitsemaan'),
                'selected_items' => __('Selected Items:', 'auta-minua-valitsemaan'),
                'no_items_selected' => __('No items selected. Search and add items above.', 'auta-minua-valitsemaan'),
                'item_already_added' => __('This item is already in the list', 'auta-minua-valitsemaan'),
                'select_post_type' => __('-- Select Post Type --', 'auta-minua-valitsemaan'),
                'select_post_type_first' => __('Please select a post type first', 'auta-minua-valitsemaan'),
                'search_content' => __('Search for pages, products, or posts...', 'auta-minua-valitsemaan'),
                'description' => __('Description', 'auta-minua-valitsemaan'),
                'recommendation_description' => __('Recommendation description (supports shortcodes)', 'auta-minua-valitsemaan'),
                'recommendation_description_help' => __('Optional description text for this recommendation bundle. You can use shortcodes here.', 'auta-minua-valitsemaan'),
                'content' => __('Content', 'auta-minua-valitsemaan'),
                'search' => __('Search...', 'auta-minua-valitsemaan'),
                'clear' => __('Clear', 'auta-minua-valitsemaan'),
                'no_results' => __('No results found', 'auta-minua-valitsemaan'),
                'add_image' => __('Add Image', 'auta-minua-valitsemaan'),
                'remove_image' => __('Remove Image', 'auta-minua-valitsemaan'),
                'select_image' => __('Select Image', 'auta-minua-valitsemaan'),
                'use_image' => __('Use this image', 'auta-minua-valitsemaan'),
                'exporting' => __('Exporting...', 'auta-minua-valitsemaan'),
                'export_success' => __('Configuration exported successfully!', 'auta-minua-valitsemaan'),
                'export_error' => __('Failed to export configuration.', 'auta-minua-valitsemaan'),
                'importing' => __('Importing...', 'auta-minua-valitsemaan'),
                'import_success' => __('Configuration imported successfully!', 'auta-minua-valitsemaan'),
                'import_error' => __('Failed to import configuration.', 'auta-minua-valitsemaan'),
                'import_no_data' => __('Please paste JSON configuration data.', 'auta-minua-valitsemaan'),
                'import_confirm' => __('This will replace your current configuration. Are you sure?', 'auta-minua-valitsemaan'),
                'steps_imported' => __('Steps imported:', 'auta-minua-valitsemaan'),
                'recommendations_imported' => __('Recommendations imported:', 'auta-minua-valitsemaan'),
                'generating_starter_pack' => __('Generating...', 'auta-minua-valitsemaan'),
                'starter_pack_success' => __('Starter pack generated successfully!', 'auta-minua-valitsemaan'),
                'starter_pack_error' => __('Failed to generate starter pack.', 'auta-minua-valitsemaan'),
                'installation_wizard' => __('Installation Wizard', 'auta-minua-valitsemaan'),
                'open_ai_generator' => __('Open AI Configuration Generator', 'auta-minua-valitsemaan'),
                'go_to_steps' => __('Go to Steps', 'auta-minua-valitsemaan'),
                'go_to_recommendations' => __('Go to Recommendations', 'auta-minua-valitsemaan'),
            ),
        ));
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'amv-admin-css',
            AMV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AMV_VERSION
        );
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Check if shortcode is used on the page
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'auta_minua_valitsemaan')) {
            return;
        }
        
        $this->enqueue_frontend_assets();
    }
    
    /**
     * Enqueue frontend assets (called from shortcode)
     */
    public function enqueue_frontend_assets() {
        if (wp_script_is('amv-frontend-js', 'enqueued')) {
            return; // Already enqueued
        }
        
        wp_enqueue_script('jquery');
        
        // Enqueue frontend JS
        wp_enqueue_script(
            'amv-frontend-js',
            AMV_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            AMV_VERSION,
            true
        );
        
        // Localize script with config
        $config = AMV_Helper::get_config();
        
        // Process shortcodes in recommendation text content (bundles always have content)
        if (!empty($config['recommendations']) && is_array($config['recommendations'])) {
            foreach ($config['recommendations'] as $rec_id => &$rec) {
                if (!empty($rec['content'])) {
                    // Process shortcodes in text content (content is already sanitized when saved)
                    $rec['content'] = do_shortcode($rec['content']);
                }
            }
            unset($rec); // Unset reference to avoid issues
        }
        
        wp_localize_script('amv-frontend-js', 'amvFrontend', array(
            'config' => $config,
            'styles' => $config['styles'] ?? array(),
            'trackingEnabled' => isset($config['tracking_enabled']) ? $config['tracking_enabled'] : '1',
            'debugEnabled' => isset($config['debug_enabled']) ? $config['debug_enabled'] : '1',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amv_get_post_content'),
            'trackNonce' => wp_create_nonce('amv_track_usage'),
            'isAdmin' => current_user_can('manage_options'),
            'strings' => array(
                'loading' => __('Loading...', 'auta-minua-valitsemaan'),
                'error_loading' => __('Error loading content', 'auta-minua-valitsemaan'),
                'recommendations_header' => __('Sinun valintoihin sopivat ratkaisut', 'auta-minua-valitsemaan'),
                'reset_form' => __('Reset Form', 'auta-minua-valitsemaan'),
            ),
        ));
        
        // Enqueue frontend CSS
        wp_enqueue_style(
            'amv-frontend-css',
            AMV_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            AMV_VERSION
        );
        
        // Enqueue preset style CSS if not custom
        $style_preset = $config['styles']['style_preset'] ?? 'custom';
        if ($style_preset !== 'custom' && in_array($style_preset, array('style1', 'style2', 'style3'))) {
            wp_enqueue_style(
                'amv-preset-style',
                AMV_PLUGIN_URL . 'assets/css/' . $style_preset . '.css',
                array('amv-frontend-css'),
                AMV_VERSION
            );
        }
    }
}

