<?php
/**
 * Database class for tracking usage
 *
 * @package Auta_Minua_Valitsemaan
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class
 */
class AMV_Database {
    
    /**
     * Get table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'amv_usage';
    }
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'started',
            steps_completed int(11) DEFAULT 0,
            form_state longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Track usage
     */
    public static function track_usage($user_id, $status, $steps_completed = 0, $form_state = null) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Check if user exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %s",
            $user_id
        ));
        
        $form_state_json = $form_state ? json_encode($form_state) : null;
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table_name,
                array(
                    'status' => $status,
                    'steps_completed' => $steps_completed,
                    'form_state' => $form_state_json,
                ),
                array('user_id' => $user_id),
                array('%s', '%d', '%s'),
                array('%s')
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'status' => $status,
                    'steps_completed' => $steps_completed,
                    'form_state' => $form_state_json,
                ),
                array('%s', '%s', '%d', '%s')
            );
        }
        
        return true;
    }
    
    /**
     * Get statistics
     */
    public static function get_statistics() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $stats = array(
            'total_users' => 0,
            'completed' => 0,
            'abandoned' => 0,
        );
        
        // Total unique users
        $stats['total_users'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name");
        
        // Completed (status = 'completed')
        $stats['completed'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM $table_name WHERE status = 'completed'"
        );
        
        // Abandoned (status = 'abandoned' AND never completed)
        // Users who abandoned but never completed
        $stats['abandoned'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT a.user_id) 
             FROM $table_name a 
             LEFT JOIN $table_name b ON a.user_id = b.user_id AND b.status = 'completed'
             WHERE a.status = 'abandoned' AND b.user_id IS NULL"
        );
        
        return $stats;
    }
    
    /**
     * Reset database (delete all tracking data)
     */
    public static function reset_database() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Delete all rows
        $result = $wpdb->query("DELETE FROM $table_name");
        
        return $result !== false;
    }
}

