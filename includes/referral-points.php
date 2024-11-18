<?php

if (!defined('ABSPATH')) {
    exit;
}

// Display referral link in the account page
add_action('woocommerce_account_dashboard', 'display_referral_link_in_account');
function display_referral_link_in_account()
{
    $user_id = get_current_user_id();

    // Retrieve the existing referral code or generate a new one
    $referral_code = get_user_meta($user_id, 'custom_referral_code', true);

    if (!$referral_code) {
        // Generate the referral code by hashing the user ID
        $hashed_id = strtoupper(substr(md5($user_id . 'secure_key'), 0, 10)); // 10-character hash
        $referral_code = 'USER-' . $hashed_id;

        // Save the referral code in user meta for reuse
        update_user_meta($user_id, 'custom_referral_code', $referral_code);
    }

    // Display the referral link
    $referral_link = home_url('/?ref=' . $referral_code);
    echo '<p>Your Referral Link: <a href="' . esc_url($referral_link) . '">' . esc_html($referral_link) . '</a></p>';
}

// Store Referral ID in session and transient when detected in URL
add_action('wp_loaded', 'set_referral_id');
function set_referral_id()
{
    if (isset($_GET['ref'])) {
        $referral_code = sanitize_text_field($_GET['ref']);
        $user_id = get_user_id_from_referral_code($referral_code);

        if ($user_id) {
            if (WC()->session) {
                WC()->session->set('referral_id', $user_id);
                set_transient('referral_id_' . session_id(), $user_id, HOUR_IN_SECONDS * 24); // Store for 24 hours
                error_log("Referral ID set in session and transient for user ID: " . $user_id);
            }
        } else {
            error_log("Invalid referral code: $referral_code");
        }
    }
}

// Helper function to extract user ID from referral code
function get_user_id_from_referral_code($referral_code)
{
    global $wpdb;

    // Check if referral code exists in the database
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'custom_referral_code' AND meta_value = %s",
        $referral_code
    ));

    return $user_id ? intval($user_id) : null;
}

// Ensure Referral ID is carried over during checkout
add_action('woocommerce_checkout_process', 'check_referral_during_checkout');
function check_referral_during_checkout()
{
    if (WC()->session) {
        $referral_id = WC()->session->get('referral_id');
    } else {
        $referral_id = get_user_meta(get_current_user_id(), 'referrer_id', true);
    }

    if ($referral_id) {
        error_log("Referral ID found during checkout: " . $referral_id);
    } else {
        error_log("No Referral ID found during checkout.");
    }
}

// Save Referral ID to Order meta when order is created
add_action('woocommerce_checkout_create_order', 'save_referral_id_to_order_meta', 10, 2);
function save_referral_id_to_order_meta($order, $data)
{
    $user_id = get_current_user_id();
    $referral_id = null;

    if (is_user_logged_in()) {
        $referral_id = get_user_meta($user_id, 'referrer_id', true);
    }

    if (!$referral_id && WC()->session) {
        $referral_id = WC()->session->get('referral_id');
    }

    if (!$referral_id) {
        $referral_id = get_transient('referral_id_' . session_id());
    }

    if ($referral_id) {
        $order->update_meta_data('_referral_id', $referral_id);
    }
}

// Display referral ID field at checkout for new customers
add_action('woocommerce_before_order_notes', 'conditionally_add_referral_id_field');
function conditionally_add_referral_id_field($checkout)
{
    $user_id = get_current_user_id();
    $has_completed_orders = ($user_id) ? wc_get_customer_order_count($user_id, 'completed') > 0 : false;

    if (!$has_completed_orders) {
        echo '<div id="referral_id_field"><h3>' . __('Referral ID') . '</h3>';
        $points = get_option('woo_referred_user_points', 100); // Default to 100 if option is not set

        woocommerce_form_field('referral_id', [
            'type'        => 'text',
            'class'       => ['referral-id-field form-row-wide'],
            'label'       => __('Enter Referral Code'),
            'placeholder' => __('Referral Code'),
            'required'    => false,
            'description' => sprintf(__('Use a referral code and get an additional %d points.', 'woocommerce'), $points),
        ], $checkout->get_value('referral_id'));

        echo '</div>';
    }
}

// Validate referral code during checkout
add_action('woocommerce_checkout_process', 'validate_referral_id_on_checkout');
function validate_referral_id_on_checkout()
{
    $referral_code = sanitize_text_field($_POST['referral_id']);

    if (!empty($referral_code)) {
        $user_id = get_user_id_from_referral_code($referral_code);

        if (!$user_id) {
            wc_add_notice(__('Invalid Referral Code. Please use a valid referral code.', 'woocommerce'), 'error');
        }
    }
}

// Save Referral ID to Order meta during checkout
add_action('woocommerce_checkout_create_order', 'save_referral_id_to_order_meta_on_checkout', 10, 2);
function save_referral_id_to_order_meta_on_checkout($order, $data)
{
    if (!empty($_POST['referral_id'])) {
        $referral_code = sanitize_text_field($_POST['referral_id']);
        $user_id = get_user_id_from_referral_code($referral_code);

        if ($user_id) {
            $order->update_meta_data('_referral_id', $user_id);
        }
    }
}

// Clear referral data after use
function clear_referral_data($order_id, $user_id)
{
    if (WC()->session && WC()->session->has_session()) {
        WC()->session->__unset('referral_id');
        error_log("Referral ID cleared from session for order ID $order_id.");
    } else {
        delete_transient('referral_id_' . session_id());
        error_log("WooCommerce session not available. Referral ID cleared from transient.");
    }

    delete_user_meta($user_id, 'referrer_id');
}


// Award referral points on first completed order
add_action('woocommerce_order_status_completed', 'award_referral_points_on_first_order', 15, 2);
function award_referral_points_on_first_order($order_id, $order)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_points_table';

    if (get_post_meta($order_id, '_referral_points_awarded', true)) {
        error_log("Referral points already awarded for order ID $order_id.");
        return;
    }

    $user_id = $order->get_user_id();
    $referral_id = $order->get_meta('_referral_id');

    if (!$referral_id || !$user_id) {
        error_log("No referral ID or user ID for order ID $order_id.");
        return;
    }

    $orders = wc_get_orders(['customer_id' => $user_id, 'status' => 'completed', 'limit' => -1]);
    if (count($orders) > 1) {
        error_log("User has multiple completed orders. No referral points awarded.");
        return;
    }

    $referral_points = get_option('woo_referral_points', 200);
    $referred_user_points = get_option('woo_referred_user_points', 100);

    $referrer_points = intval(get_user_meta($referral_id, 'customer_points', true) ?: 0);
    $referred_points = intval(get_user_meta($user_id, 'customer_points', true) ?: 0);

    // Get names for comment purposes
    $referrer_name = get_userdata($referral_id)->display_name;
    $referred_user_name = get_userdata($user_id)->display_name;

    // Insert points records in custom points table
    $wpdb->insert($table_name, [
        'used_id' => $referral_id,
        'stack' => 'referral_bonus',
        'mvt_date' => current_time('mysql'),
        'points_moved' => $referral_points,
        'new_total' => $referrer_points + $referral_points,
        'commentar' => "Referral bonus points for referring $referred_user_name",
        'origin' => 'referral',
        'order_id' => $order_id,
        'given_by' => $user_id,
    ]);

    $wpdb->insert($table_name, [
        'used_id' => $user_id,
        'stack' => 'first_order_bonus',
        'mvt_date' => current_time('mysql'),
        'points_moved' => $referred_user_points,
        'new_total' => $referred_points + $referred_user_points,
        'commentar' => "First order bonus points for being referred by $referrer_name",
        'origin' => 'referral',
        'order_id' => $order_id,
        'given_by' => $user_id,
    ]);

    update_user_meta($referral_id, 'customer_points', $referrer_points + $referral_points);
    update_user_meta($user_id, 'customer_points', $referred_points + $referred_user_points);

    update_post_meta($order_id, '_referral_points_awarded', true);
    clear_referral_data($order_id, $user_id);

    error_log("Referral points awarded for order ID $order_id.");
}
