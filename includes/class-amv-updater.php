<?php
/**
 * GitHub Updater class for WordPress
 *
 * @package Auta_Minua_Valitsemaan
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitHub Updater class
 */
class AMV_Updater {
    
    /**
     * GitHub repository owner
     */
    private $owner;
    
    /**
     * GitHub repository name
     */
    private $repo;
    
    /**
     * Plugin file path
     */
    private $plugin_file;
    
    /**
     * Plugin slug
     */
    private $slug;
    
    /**
     * Constructor
     */
    public function __construct($owner, $repo, $plugin_file) {
        $this->owner = $owner;
        $this->repo = $repo;
        $this->plugin_file = $plugin_file;
        $this->slug = dirname(plugin_basename($plugin_file));
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
    }
    
    /**
     * Check for updates
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare(AMV_VERSION, $remote_version, '<')) {
            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $remote_version;
            $obj->url = $this->get_repo_url();
            $obj->package = $this->get_download_url();
            $obj->plugin = plugin_basename($this->plugin_file);
            
            $transient->response[$obj->plugin] = $obj;
        }
        
        return $transient;
    }
    
    /**
     * Get plugin info
     */
    public function plugin_info($false, $action, $response) {
        if ($response->slug !== $this->slug) {
            return $false;
        }
        
        $remote_version = $this->get_remote_version();
        
        if (!$remote_version) {
            return $false;
        }
        
        $response->slug = $this->slug;
        $response->plugin_name = 'Help me choose';
        $response->version = $remote_version;
        $response->author = 'Tapio Kauranen';
        $response->homepage = $this->get_repo_url();
        $response->download_link = $this->get_download_url();
        $response->requires = '5.0';
        $response->tested = '6.4';
        $response->last_updated = $this->get_remote_release_date();
        $response->sections = array(
            'description' => 'A tool that helps customers to choose the right product or a service through an interactive decision tree.',
            'changelog' => $this->get_changelog(),
        );
        
        return $response;
    }
    
    /**
     * Post install hook
     */
    public function post_install($true, $hook_extra, $result) {
        global $wp_filesystem;
        
        $plugin_path = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $plugin_path);
        $result['destination'] = $plugin_path;
        
        if (is_multisite()) {
            activate_plugin(plugin_basename($this->plugin_file));
        } else {
            activate_plugin(plugin_basename($this->plugin_file));
        }
        
        return $result;
    }
    
    /**
     * Get remote version from GitHub
     */
    private function get_remote_version() {
        $cache_key = 'amv_remote_version_' . $this->slug;
        $version = get_transient($cache_key);
        
        if (false !== $version) {
            return $version;
        }
        
        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo),
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                ),
            )
        );
        
        if (is_wp_error($response)) {
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
        set_transient($cache_key, $version, 12 * HOUR_IN_SECONDS);
        
        return $version;
    }
    
    /**
     * Get download URL
     */
    private function get_download_url() {
        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo),
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                ),
            )
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['assets'][0]['browser_download_url'])) {
            // Fallback to zipball URL
            return isset($data['zipball_url']) ? $data['zipball_url'] : false;
        }
        
        return $data['assets'][0]['browser_download_url'];
    }
    
    /**
     * Get repository URL
     */
    private function get_repo_url() {
        return sprintf('https://github.com/%s/%s', $this->owner, $this->repo);
    }
    
    /**
     * Get remote release date
     */
    private function get_remote_release_date() {
        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo),
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                ),
            )
        );
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['published_at']) ? $data['published_at'] : '';
    }
    
    /**
     * Get changelog
     */
    private function get_changelog() {
        $response = wp_remote_get(
            sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo),
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                ),
            )
        );
        
        if (is_wp_error($response)) {
            return 'Unable to fetch changelog.';
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['body']) && !empty($data['body'])) {
            return $data['body'];
        }
        
        return 'No changelog available.';
    }
}

