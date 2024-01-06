<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Create the custom table during plugin activation
function create_custom_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_points_table';

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id BIGINT(19) NOT NULL AUTO_INCREMENT,
        used_id BIGINT(19) NOT NULL,
        stack VARCHAR(255) DEFAULT 'default',
        mvt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        points_moved INT(10) DEFAULT 0,
        new_total INT(10) DEFAULT 0,
        commentar TEXT,
        origin TINYTEXT DEFAULT NULL,
        origin2 BIGINT(19) DEFAULT NULL,
        order_id INT(10) DEFAULT 0,
        blog_id INT(10) DEFAULT 1,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'create_custom_table');
