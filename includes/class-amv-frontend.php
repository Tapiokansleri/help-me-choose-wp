<?php
/**
 * Frontend class for form rendering
 *
 * @package Auta_Minua_Valitsemaan
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class
 */
class AMV_Frontend {
    
    /**
     * Flag to track if shortcode is used
     */
    private $shortcode_used = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('auta_minua_valitsemaan', array($this, 'render_form'));
    }
    
    /**
     * Render form shortcode
     */
    public function render_form($atts) {
        $this->shortcode_used = true;
        
        // Enqueue frontend assets
        $plugin = Auta_Minua_Valitsemaan::get_instance();
        $plugin->get_assets()->enqueue_frontend_assets();
        
        $config = AMV_Helper::get_config();
        
        if (empty($config['steps'])) {
            return '<p>' . __('Please configure the form in Tools → Help me choose', 'auta-minua-valitsemaan') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="amv-form-container" id="amv-form">
            <?php
            $step_index = 0;
            $total_steps = count($config['steps']);
            $first_step = true;
            foreach ($config['steps'] as $step_id => $step) {
                $step_index++;
                $is_last = ($step_index === $total_steps);
                $this->render_form_step($step_id, $step, $first_step, $is_last, $config);
                $first_step = false;
            }
            ?>
            <div class="amv-recommendation-result" id="amv-recommendation-result" style="display: none;">
                <!-- Recommendations will be inserted here -->
            </div>
            
            <?php 
            $config = AMV_Helper::get_config();
            $debug_enabled = isset($config['debug_enabled']) ? $config['debug_enabled'] : '1';
            if (current_user_can('manage_options') && $debug_enabled === '1'): ?>
            <div class="amv-debug-info" id="amv-debug-info" style="display: none;">
                <h4><?php _e('Debug Information (Admin Only)', 'auta-minua-valitsemaan'); ?></h4>
                <div class="amv-debug-content" id="amv-debug-content">
                    <!-- Debug info will be inserted here -->
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render form step
     */
    private function render_form_step($step_id, $step, $is_first, $is_last, $config) {
        $display_class = $is_first ? 'amv-step-active' : 'amv-step-hidden';
        ?>
        <div class="amv-step <?php echo esc_attr($display_class); ?>" data-step-id="<?php echo esc_attr($step_id); ?>" data-is-last="<?php echo $is_last ? '1' : '0'; ?>">
            <h3 class="amv-step-title"><?php echo esc_html($step['title']); ?></h3>
            <?php if (!empty($step['description'])): ?>
                <p class="amv-step-description"><?php echo wp_kses_post($step['description']); ?></p>
            <?php endif; ?>
            <div class="amv-step-options">
                <?php
                if (!empty($step['options'])) {
                    foreach ($step['options'] as $opt_id => $option) {
                        $this->render_form_option($step_id, $opt_id, $option);
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render form option
     */
    private function render_form_option($step_id, $opt_id, $option) {
        $target_step = $option['target_step'] ?? '';
        $recommendations = $option['recommendations'] ?? array();
        $image_id = $option['image_id'] ?? 0;
        if (!is_array($recommendations)) {
            $recommendations = array();
        }
        // Handle recommendation target
        if ($target_step === 'RECOMMENDATION') {
            $target_step = 'RECOMMENDATION';
        }
        
        // Get image URL if exists
        $image_url = '';
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'large');
        }
        ?>
        <label class="amv-option">
            <input type="radio" name="amv_step_<?php echo esc_attr($step_id); ?>" value="<?php echo esc_attr($opt_id); ?>" 
                   data-target-step="<?php echo esc_attr($target_step); ?>"
                   data-recommendations="<?php echo esc_attr(implode(',', $recommendations)); ?>"
                   class="amv-radio-input">
            <?php if ($image_url): ?>
                <div class="amv-option-image">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($option['label']); ?>">
                </div>
            <?php endif; ?>
            <div class="amv-option-content">
                <span class="amv-option-label"><?php echo esc_html($option['label']); ?></span>
                <?php if (!empty($option['description'])): ?>
                    <span class="amv-option-description"><?php echo wp_kses_post($option['description']); ?></span>
                <?php endif; ?>
            </div>
        </label>
        <?php
    }
    
    /**
     * Check if shortcode was used
     */
    public function is_shortcode_used() {
        return $this->shortcode_used;
    }
}

