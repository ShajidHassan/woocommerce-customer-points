<?php

/**
 * Plugin Name: Woo Customer Points
 * Description: A plugin to manage customer points in WooCommerce.
 * Version: 1.0.0
 * Author: Mirailit Limited
 * Author URI: https://mirailit.com/
 * Text Domain: woo-customer-points
 * Domain Path: /languages
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}



// Hook into order placement
add_action('woocommerce_new_order', 'award_points_on_order', 10, 1);

function award_points_on_order($order_id)
{
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $order_count = 0;

    if ($user_id) {
        $order_count = wc_get_customer_order_count($user_id);
    }

    if ($order_count === 1) {
        // For the first order, award 300 points
        $order_total = $order->get_total();
        $points_earned = floor($order_total / 100); // 1 point for every 100 yen spent
        $total_points = $points_earned + 300;
        update_user_meta($user_id, 'customer_points', $total_points);
    } elseif ($order_count > 1) {
        // Calculate points for subsequent orders
        $order_total = $order->get_total();
        $points_earned = floor($order_total / 100); // 1 point for every 100 yen spent
        $current_points = get_user_meta($user_id, 'customer_points', true);
        $total_points = $current_points + $points_earned;
        update_user_meta($user_id, 'customer_points', $total_points);
    }
}



// Shortcode to display user points
add_shortcode('display_user_points', 'display_user_points_function');

function display_user_points_function($atts)
{
    $user_id = get_current_user_id();
    $points = get_user_meta($user_id, 'customer_points', true);

    // Check if user has points
    if ($points === '') {
        $points = 0; // If no points, set it to 0
    }

    return $points;
}
