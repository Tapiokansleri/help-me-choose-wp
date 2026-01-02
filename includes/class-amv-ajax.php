<?php
/**
 * AJAX handlers class
 *
 * @package Auta_Minua_Valitsemaan
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX class
 */
class AMV_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_amv_search_posts', array($this, 'ajax_search_posts'));
        add_action('wp_ajax_amv_get_post_content', array($this, 'ajax_get_post_content'));
        add_action('wp_ajax_nopriv_amv_get_post_content', array($this, 'ajax_get_post_content'));
        add_action('wp_ajax_amv_track_usage', array($this, 'ajax_track_usage'));
        add_action('wp_ajax_nopriv_amv_track_usage', array($this, 'ajax_track_usage'));
    }
    
    /**
     * AJAX handler for searching posts
     */
    public function ajax_search_posts() {
        check_ajax_referer('amv_search_posts', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
        
        if (empty($search_term)) {
            wp_send_json_error(array('message' => __('Please enter a search term', 'auta-minua-valitsemaan')));
        }
        
        $args = array(
            'post_type' => $post_type,
            's' => $search_term,
            'posts_per_page' => 20,
            'post_status' => 'publish',
        );
        
        $query = new WP_Query($args);
        $results = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                );
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for getting post content
     */
    public function ajax_get_post_content() {
        check_ajax_referer('amv_get_post_content', 'nonce');
        
        $post_id = absint($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'auta-minua-valitsemaan')));
        }
        
        $post = get_post($post_id);
        
        if (!$post || $post->post_status !== 'publish') {
            wp_send_json_error(array('message' => __('Post not found', 'auta-minua-valitsemaan')));
        }
        
        $post_type = $post->post_type;
        $is_product = ($post_type === 'product' && class_exists('WooCommerce'));
        
        // Get config for results settings
        $config = AMV_Helper::get_config();
        $styles = $config['styles'] ?? array();
        $excerpt_length = absint($styles['results_excerpt_length'] ?? 35);
        $show_title = isset($styles['results_show_title']) ? $styles['results_show_title'] : '1';
        $show_image = isset($styles['results_show_image']) ? $styles['results_show_image'] : '1';
        $show_excerpt = isset($styles['results_show_excerpt']) ? $styles['results_show_excerpt'] : '1';
        
        // Get post data
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $excerpt = get_the_excerpt($post_id);
        if (empty($excerpt)) {
            $excerpt = $post->post_content;
        }
        // Limit excerpt based on settings
        if ($excerpt_length > 0) {
            $excerpt = strip_tags($excerpt);
            $excerpt = mb_substr($excerpt, 0, $excerpt_length);
            if (mb_strlen($excerpt) >= $excerpt_length) {
                $excerpt .= '...';
            }
        } else {
            $excerpt = strip_tags($excerpt);
        }
        
        // Get thumbnail
        $thumbnail = '';
        if (has_post_thumbnail($post_id)) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'medium');
            $thumbnail = '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($title) . '" class="amv-card-thumbnail">';
        } else {
            // Use placeholder if no thumbnail
            $thumbnail = '<div class="amv-card-thumbnail-placeholder"><span class="dashicons dashicons-format-image"></span></div>';
        }
        
        if ($is_product) {
            // Build product card HTML
            $product = wc_get_product($post_id);
            
            // Get product price
            $price_html = '';
            if ($product) {
                $price_html = $product->get_price_html();
                if (empty($price_html)) {
                    $price_html = '';
                }
            }
            
            // Get product rating
            $rating_html = '';
            $rating = 0;
            if ($product) {
                $rating = $product->get_average_rating();
                if ($rating > 0) {
                    $rating_html = '<div class="amv-product-rating">';
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= round($rating)) {
                            $rating_html .= '<span class="amv-star amv-star-filled">★</span>';
                        } else {
                            $rating_html .= '<span class="amv-star amv-star-empty">☆</span>';
                        }
                    }
                    $rating_html .= '</div>';
                }
            }
            
            // Finnish flag badge
            $badge_html = '<div class="amv-product-badge"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="20" height="20" fill="white"/><rect x="0" y="7" width="20" height="6" fill="#003580"/><rect x="0" y="0" width="20" height="4" fill="#003580"/><rect x="0" y="16" width="20" height="4" fill="#003580"/><rect x="0" y="7" width="6" height="6" fill="#003580"/></svg></div>';
            
            $card_html = '<div class="amv-product-card">';
            if ($show_image === '1') {
                $card_html .= '<div class="amv-product-image-wrapper">';
                $card_html .= $badge_html;
                $card_html .= '<a href="' . esc_url($permalink) . '" class="amv-product-image-link">';
                $card_html .= '<div class="amv-product-thumbnail">' . $thumbnail . '</div>';
                $card_html .= '</a>';
                $card_html .= '</div>';
            }
            $card_html .= '<div class="amv-product-content">';
            if ($show_title === '1') {
                $card_html .= '<h4 class="amv-product-title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h4>';
            }
            if ($show_excerpt === '1') {
                $card_html .= '<p class="amv-product-excerpt">' . esc_html($excerpt) . '</p>';
            }
            if ($rating_html) {
                $card_html .= $rating_html;
            }
            if ($price_html) {
                $card_html .= '<div class="amv-product-price">' . $price_html . '</div>';
            }
            $card_html .= '<a href="' . esc_url($permalink) . '" class="amv-product-buy-btn">';
            $card_html .= '<span class="amv-cart-icon">🛒</span>';
            $card_html .= '<span class="amv-buy-text">' . esc_html__('Buy', 'auta-minua-valitsemaan') . '</span>';
            $card_html .= '</a>';
            $card_html .= '</div>';
            $card_html .= '</div>';
        } else {
            // Build regular page/post card HTML
            $card_html = '<div class="amv-content-card">';
            $card_html .= '<a href="' . esc_url($permalink) . '" class="amv-card-link">';
            if ($show_image === '1') {
                $card_html .= '<div class="amv-card-thumbnail-wrapper">' . $thumbnail . '</div>';
            }
            $card_html .= '<div class="amv-card-content">';
            if ($show_title === '1') {
                $card_html .= '<h4 class="amv-card-title">' . esc_html($title) . '</h4>';
            }
            if ($show_excerpt === '1') {
                $card_html .= '<p class="amv-card-excerpt">' . esc_html($excerpt) . '</p>';
            }
            $card_html .= '</div>';
            $card_html .= '</a>';
            $card_html .= '</div>';
        }
        
        wp_send_json_success($card_html);
    }
    
    /**
     * AJAX handler for tracking usage
     */
    public function ajax_track_usage() {
        // Check if tracking is enabled
        $config = AMV_Helper::get_config();
        $tracking_enabled = isset($config['tracking_enabled']) ? $config['tracking_enabled'] : '1';
        
        if ($tracking_enabled !== '1') {
            // Tracking disabled, return success but don't track
            wp_send_json_success(array('message' => __('Tracking disabled', 'auta-minua-valitsemaan')));
            return;
        }
        
        // Verify nonce (works for both regular AJAX and sendBeacon FormData)
        // For sendBeacon, we need to verify nonce manually since check_ajax_referer might fail
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'amv_track_usage')) {
            wp_send_json_error(array('message' => __('Security check failed', 'auta-minua-valitsemaan')));
            return;
        }
        
        $user_id = isset($_POST['user_id']) ? sanitize_text_field($_POST['user_id']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'started';
        $steps_completed = isset($_POST['steps_completed']) ? absint($_POST['steps_completed']) : 0;
        $form_state = isset($_POST['form_state']) ? json_decode(stripslashes($_POST['form_state']), true) : null;
        
        if (empty($user_id)) {
            wp_send_json_error(array('message' => __('User ID is required', 'auta-minua-valitsemaan')));
            return;
        }
        
        // Validate status
        $valid_statuses = array('started', 'in_progress', 'completed', 'abandoned');
        if (!in_array($status, $valid_statuses)) {
            $status = 'started';
        }
        
        // For abandoned status, only update if user hasn't completed
        if ($status === 'abandoned') {
            global $wpdb;
            $table_name = AMV_Database::get_table_name();
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT status FROM $table_name WHERE user_id = %s",
                $user_id
            ));
            
            // Don't overwrite completed status with abandoned
            if ($existing && $existing->status === 'completed') {
                wp_send_json_success(array('message' => __('User already completed', 'auta-minua-valitsemaan')));
                return;
            }
        }
        
        $result = AMV_Database::track_usage($user_id, $status, $steps_completed, $form_state);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Usage tracked', 'auta-minua-valitsemaan')));
        } else {
            wp_send_json_error(array('message' => __('Failed to track usage', 'auta-minua-valitsemaan')));
        }
    }
}

