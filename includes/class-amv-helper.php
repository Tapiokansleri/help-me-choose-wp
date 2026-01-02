<?php
/**
 * Helper class for shared functionality
 *
 * @package Auta_Minua_Valitsemaan
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper class
 */
class AMV_Helper {
    
    /**
     * Get configuration
     */
    public static function get_config() {
        return get_option('amv_config', array('steps' => array(), 'recommendations' => array()));
    }
    
    /**
     * Save configuration
     */
    public static function save_config($config) {
        return update_option('amv_config', $config);
    }
    
    /**
     * Sanitize steps data
     */
    public static function sanitize_steps($steps) {
        $sanitized = array();
        if (!is_array($steps)) {
            return $sanitized;
        }
        
        $step_counter = 1;
        $used_ids = array();
        
        foreach ($steps as $step_id => $step) {
            if (empty($step_id) || !is_array($step)) {
                continue;
            }
            
            $step_id_clean = sanitize_key($step_id);
            if (empty($step_id_clean)) {
                continue;
            }
            
            // Ensure unique step IDs - if ID already exists, generate a new one
            $original_id = $step_id_clean;
            while (isset($sanitized[$step_id_clean]) || in_array($step_id_clean, $used_ids)) {
                $step_id_clean = 'step_' . $step_counter;
                $step_counter++;
            }
            $used_ids[] = $step_id_clean;
            
            $sanitized[$step_id_clean] = array(
                'title' => sanitize_text_field($step['title'] ?? ''),
                'description' => wp_kses_post($step['description'] ?? ''),
                'options' => array(),
            );
            
            if (!empty($step['options']) && is_array($step['options'])) {
                foreach ($step['options'] as $opt_id => $option) {
                    if (empty($opt_id) || !is_array($option)) {
                        continue;
                    }
                    
                    $opt_id_clean = sanitize_key($opt_id);
                    if (empty($opt_id_clean)) {
                        continue;
                    }
                    
                    $label = sanitize_text_field($option['label'] ?? '');
                    if (!empty($label)) {
                        $recommendations = array();
                        if (!empty($option['recommendations']) && is_array($option['recommendations'])) {
                            foreach ($option['recommendations'] as $rec_id) {
                                $rec_id_clean = sanitize_key($rec_id);
                                if (!empty($rec_id_clean)) {
                                    $recommendations[] = $rec_id_clean;
                                }
                            }
                        }
                        
                        $image_id = absint($option['image_id'] ?? 0);
                        $description = wp_kses_post($option['description'] ?? '');
                        
                        // Preserve "RECOMMENDATION" as special case, sanitize others
                        $target_step_raw = $option['target_step'] ?? '';
                        $target_step = '';
                        if ($target_step_raw === 'RECOMMENDATION') {
                            $target_step = 'RECOMMENDATION';
                        } elseif (!empty($target_step_raw)) {
                            $target_step = sanitize_key($target_step_raw);
                        }
                        
                        $sanitized[$step_id_clean]['options'][$opt_id_clean] = array(
                            'label' => $label,
                            'description' => $description,
                            'target_step' => $target_step,
                            'recommendations' => $recommendations,
                            'image_id' => $image_id,
                        );
                    }
                }
            }
        }
        return $sanitized;
    }
    
    /**
     * Sanitize recommendations data
     */
    public static function sanitize_recommendations($recommendations) {
        $sanitized = array();
        if (!is_array($recommendations)) {
            return $sanitized;
        }
        
        foreach ($recommendations as $rec_id => $rec) {
            if (empty($rec_id) || !is_array($rec)) {
                continue;
            }
            
            $rec_id_clean = sanitize_key($rec_id);
            if (empty($rec_id_clean)) {
                continue;
            }
            
            // Handle content_ids as array (new bundle format)
            $content_ids = array();
            if (!empty($rec['content_ids']) && is_array($rec['content_ids'])) {
                foreach ($rec['content_ids'] as $content_id) {
                    $content_id_clean = absint($content_id);
                    if ($content_id_clean > 0) {
                        $content_ids[] = $content_id_clean;
                    }
                }
            }
            // Backward compatibility: if content_id exists but content_ids doesn't
            elseif (!empty($rec['content_id'])) {
                $content_id_clean = absint($rec['content_id']);
                if ($content_id_clean > 0) {
                    $content_ids[] = $content_id_clean;
                }
            }
            
            $sanitized[$rec_id_clean] = array(
                'title' => sanitize_text_field($rec['title'] ?? ''),
                'content' => wp_kses_post($rec['content'] ?? ''),
                'content_ids' => $content_ids,
            );
        }
        return $sanitized;
    }
    
    /**
     * Sanitize styles data
     */
    public static function sanitize_styles($styles) {
        $sanitized = array();
        if (!is_array($styles)) {
            return $sanitized;
        }
        
        // Style preset
        $sanitized['style_preset'] = in_array($styles['style_preset'] ?? 'custom', array('style1', 'style2', 'style3', 'custom')) ? $styles['style_preset'] : 'custom';
        
        // Container styles
        $sanitized['container_width'] = absint($styles['container_width'] ?? 100);
        $sanitized['container_width_unit'] = in_array($styles['container_width_unit'] ?? '%', array('%', 'px')) ? $styles['container_width_unit'] : '%';
        $sanitized['container_padding'] = absint($styles['container_padding'] ?? 0);
        
        // Step styles
        $sanitized['step_border_enabled'] = isset($styles['step_border_enabled']) ? '1' : '0';
        $sanitized['step_border_color'] = sanitize_hex_color($styles['step_border_color'] ?? '#e0e0e0');
        $sanitized['step_border_width'] = absint($styles['step_border_width'] ?? 2);
        $sanitized['step_border_radius'] = absint($styles['step_border_radius'] ?? 8);
        $sanitized['step_bg_color'] = sanitize_hex_color($styles['step_bg_color'] ?? '#ffffff');
        $sanitized['step_text_color'] = sanitize_hex_color($styles['step_text_color'] ?? '#000000');
        $sanitized['step_padding'] = isset($styles['step_padding']) ? absint($styles['step_padding']) : 0;
        
        // Option styles
        $sanitized['option_border_enabled'] = isset($styles['option_border_enabled']) ? '1' : '0';
        $sanitized['option_border_color'] = sanitize_hex_color($styles['option_border_color'] ?? '#e0e0e0');
        $sanitized['option_border_width'] = absint($styles['option_border_width'] ?? 1);
        $sanitized['option_border_radius'] = absint($styles['option_border_radius'] ?? 8);
        $sanitized['option_bg_color'] = sanitize_hex_color($styles['option_bg_color'] ?? '#ffffff');
        $sanitized['option_hover_bg_color'] = sanitize_hex_color($styles['option_hover_bg_color'] ?? '#f5f5f5');
        $sanitized['option_text_color'] = sanitize_hex_color($styles['option_text_color'] ?? '#000000');
        $sanitized['option_padding'] = isset($styles['option_padding']) ? absint($styles['option_padding']) : 0;
        
        // Image settings
        $sanitized['image_size'] = absint($styles['image_size'] ?? 300);
        
        // Results settings
        $sanitized['results_excerpt_length'] = absint($styles['results_excerpt_length'] ?? 35);
        $sanitized['results_show_title'] = isset($styles['results_show_title']) ? '1' : '0';
        $sanitized['results_show_image'] = isset($styles['results_show_image']) ? '1' : '0';
        $sanitized['results_show_excerpt'] = isset($styles['results_show_excerpt']) ? '1' : '0';
        
        return $sanitized;
    }
}
