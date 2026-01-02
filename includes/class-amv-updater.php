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
        
        $plugin_basename = plugin_basename($this->plugin_file);
        
        // Only check for our plugin
        if (!isset($transient->checked[$plugin_basename])) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        // Debug logging (only for admins)
        if (current_user_can('manage_options')) {
            error_log('AMV Updater: Current version: ' . AMV_VERSION . ', Remote version: ' . ($remote_version ? $remote_version : 'not found'));
            error_log('AMV Updater: Plugin basename: ' . $plugin_basename);
            if ($remote_version) {
                $comparison = version_compare(AMV_VERSION, $remote_version, '<');
                error_log('AMV Updater: Version comparison (' . AMV_VERSION . ' < ' . $remote_version . '): ' . ($comparison ? 'true - update needed' : 'false - no update'));
            }
        }
        
        if ($remote_version && version_compare(AMV_VERSION, $remote_version, '<')) {
            $download_url = $this->get_download_url();
            
            if (!$download_url) {
                if (current_user_can('manage_options')) {
                    error_log('AMV Updater: Update available but download URL is missing!');
                }
                return $transient;
            }
            
            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $remote_version;
            $obj->url = $this->get_repo_url();
            $obj->package = $download_url;
            $obj->plugin = $plugin_basename;
            $obj->id = $this->slug;
            
            $transient->response[$plugin_basename] = $obj;
            
            if (current_user_can('manage_options')) {
                error_log('AMV Updater: Update available! Version ' . $remote_version . ' - Download URL: ' . $download_url);
            }
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
        
        // Check if this is our plugin
        $plugin_basename = plugin_basename($this->plugin_file);
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $plugin_basename) {
            return $result;
        }
        
        $plugin_path = plugin_dir_path($this->plugin_file);
        $source = $result['destination'];
        
        // WordPress extracts ZIPs to a temp directory
        // The extracted folder might be named differently (e.g., repo-tag-name or just the folder name)
        // We need to find the actual plugin folder inside
        
        if ($wp_filesystem->exists($source)) {
            // List contents of the extracted directory
            $files = $wp_filesystem->dirlist($source);
            
            if ($files && count($files) === 1) {
                // If there's only one item, it's likely the plugin folder
                foreach ($files as $file => $fileinfo) {
                    if ($fileinfo['type'] === 'd') {
                        $source = trailingslashit($source) . $file;
                        break;
                    }
                }
            } else {
                // Multiple files/folders - look for our plugin folder
                foreach ($files as $file => $fileinfo) {
                    if ($fileinfo['type'] === 'd' && ($file === $this->slug || strpos($file, 'help-me-choose') !== false)) {
                        $source = trailingslashit($source) . $file;
                        break;
                    }
                }
            }
            
            // Copy all files from source to plugin path
            if ($wp_filesystem->exists($source)) {
                // Use WordPress's copy_dir function
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                $copy_result = copy_dir($source, $plugin_path);
                
                if (is_wp_error($copy_result)) {
                    if (current_user_can('manage_options')) {
                        error_log('AMV Updater: Error copying files - ' . $copy_result->get_error_message());
                    }
                    return $result;
                }
            }
        }
        
        $result['destination'] = $plugin_path;
        
        // Reactivate plugin
        activate_plugin($plugin_basename);
        
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
        
        $api_url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo);
        
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
            if (current_user_can('manage_options')) {
                error_log('AMV Updater: API Error - ' . $response->get_error_message());
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            if (current_user_can('manage_options')) {
                error_log('AMV Updater: API returned code ' . $response_code);
            }
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['tag_name'])) {
            if (current_user_can('manage_options')) {
                error_log('AMV Updater: No tag_name found in response. Response: ' . print_r($data, true));
            }
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
        $api_url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo);
        
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
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Look for the ZIP asset
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['browser_download_url']) && strpos($asset['browser_download_url'], '.zip') !== false) {
                    return $asset['browser_download_url'];
                }
            }
        }
        
        // Fallback to zipball URL (source code zip)
        if (isset($data['zipball_url'])) {
            return $data['zipball_url'];
        }
        
        return false;
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
        $api_url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo);
        
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
        $api_url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo);
        
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

