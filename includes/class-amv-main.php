<?php
/**
 * Main plugin class
 *
 * @package Auta_Minua_Valitsemaan
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Auta_Minua_Valitsemaan {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Admin instance
     */
    private $admin;
    
    /**
     * Frontend instance
     */
    private $frontend;
    
    /**
     * AJAX instance
     */
    private $ajax;
    
    /**
     * Assets instance
     */
    private $assets;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once AMV_PLUGIN_DIR . 'includes/class-amv-helper.php';
        require_once AMV_PLUGIN_DIR . 'includes/class-amv-database.php';
        require_once AMV_PLUGIN_DIR . 'includes/class-amv-admin.php';
        require_once AMV_PLUGIN_DIR . 'includes/class-amv-frontend.php';
        require_once AMV_PLUGIN_DIR . 'includes/class-amv-ajax.php';
        require_once AMV_PLUGIN_DIR . 'includes/class-amv-assets.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $this->admin = new AMV_Admin();
        $this->frontend = new AMV_Frontend();
        $this->ajax = new AMV_Ajax();
        $this->assets = new AMV_Assets();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Components handle their own hooks
    }
    
    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Get frontend instance
     */
    public function get_frontend() {
        return $this->frontend;
    }
    
    /**
     * Get AJAX instance
     */
    public function get_ajax() {
        return $this->ajax;
    }
    
    /**
     * Get assets instance
     */
    public function get_assets() {
        return $this->assets;
    }
}

