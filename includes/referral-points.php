<?php

if (!defined('ABSPATH')) {
    exit;
}

// Generate referral code based on first name and hashed ID
function generate_new_referral_code($user_id)
{
    // Get the user's first name
    $last_name = get_user_meta($user_id, 'last_name', true);
    $display_name = get_userdata($user_id)->display_name;

    // Clean up spaces from last name and display name
    $last_name = trim(str_replace(' ', '', $last_name));
    $display_name = trim(str_replace(' ', '', $display_name));

    // Check if first name exists
    if (!empty($last_name)) {
        $name_prefix = strtoupper(substr($last_name, 0, 3)); // First 3 letters of first name
    } else {
        $name_prefix = strtoupper(substr($display_name, 0, 3)); // First 3 letters of display name
    }

    // Generate the referral code
    $hashed_id = strtoupper(substr(md5($user_id . 'secure_key'), 0, 3)); // First 3 characters of hash

    // Combine to form the referral code
    $referral_code = $name_prefix . '-' . $hashed_id;

    return $referral_code;
}


// reusable function for referral code generation
function get_or_generate_referral_code($user_id)
{
    $referral_code = get_user_meta($user_id, 'custom_referral_code', true);

    // If no referral code exists, generate a new one
    if (!$referral_code) {
        $referral_code = generate_new_referral_code($user_id);

        // Save the referral code in user meta
        update_user_meta($user_id, 'custom_referral_code', $referral_code);
    }

    return $referral_code;
}

// Unified Referral Code Generation Function
function ensure_user_referral_code($user_id)
{
    // Retrieve the current referral code
    $referral_code = get_user_meta($user_id, 'custom_referral_code', true);

    // Fetch the user's first name
    $user_info = get_userdata($user_id);
    $last_name = $user_info->last_name;
    $last_name = trim(str_replace(' ', '', $last_name));

    // Extract the first three letters of the first name (default to "USR" if not available)
    $name_part = $last_name ? strtoupper(substr($last_name, 0, 3)) : 'USR';

    // Generate a 3-character hash based on the user ID
    $hashed_id = strtoupper(substr(md5($user_id . 'secure_key'), 0, 3));

    // Combine to create the new referral code
    $new_referral_code = $name_part . '-' . $hashed_id;

    // Check if the referral code is in the old format (10-character hash)
    if ($referral_code && preg_match('/^[A-Z0-9]{10}$/', $referral_code)) {
        // Update to the new format
        update_user_meta($user_id, 'custom_referral_code', $new_referral_code);
    }

    // If no referral code exists, generate and save a new one
    if (!$referral_code) {
        update_user_meta($user_id, 'custom_referral_code', $new_referral_code);
    }
}



// Homepage Hook Update
add_action('template_redirect', 'update_referral_code_on_homepage');
function update_referral_code_on_homepage()
{
    if (is_user_logged_in() && is_front_page()) {
        ensure_user_referral_code(get_current_user_id());
    }
}


// Display referral link in the WooCommerce account dashboard
add_action('woocommerce_account_dashboard', 'display_referral_link_in_account');
function display_referral_link_in_account()
{
    $user_id = get_current_user_id();
    $referral_code = get_or_generate_referral_code($user_id); // Retrieves the updated referral code
    $referral_link = home_url('/?ref=' . $referral_code);

    echo '<p>Your Referral Link: <a href="' . esc_url($referral_link) . '">' . esc_html($referral_link) . '</a></p>';
    echo '<p>Your Referral Code: ' . esc_html($referral_code) . '</p>';
}


// Generate and store referral code for new users
add_action('user_register', 'generate_referral_code_for_new_user');
function generate_referral_code_for_new_user($user_id)
{
    // Generate the referral code
    $referral_code = generate_new_referral_code($user_id);

    // Save it in the correct meta key
    if ($referral_code) {
        update_user_meta($user_id, 'custom_referral_code', $referral_code);
    }
}


// Store referral ID in WooCommerce session or transient
add_action('wp_loaded', 'set_referral_id');
function set_referral_id()
{
    if (isset($_GET['ref'])) {
        $referral_code = sanitize_text_field($_GET['ref']);
        $user_id = get_user_id_from_referral_code($referral_code);

        if ($user_id) {
            if (WC()->session) {
                WC()->session->set('referral_id', $user_id);
                set_transient('referral_id_' . session_id(), $user_id, HOUR_IN_SECONDS * 24);
            }
        }
    }
}



// Helper function to extract user ID from referral code
function get_user_id_from_referral_code($referral_code)
{
    global $wpdb;

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
}


// Save Referral ID to Order meta when order is created
add_action('woocommerce_checkout_create_order', 'save_referral_id_to_order_meta', 10, 2);
function save_referral_id_to_order_meta($order, $data)
{
    $user_id = get_current_user_id();
    $referral_code = null;

    // Retrieve the referral code based on the user ID stored in session or meta
    if (is_user_logged_in()) {
        $referrer_id = get_user_meta($user_id, 'referrer_id', true);
        if ($referrer_id) {
            $referral_code = get_or_generate_referral_code($referrer_id);
        }
    }

    if (!$referral_code && WC()->session) {
        $referrer_id = WC()->session->get('referral_id');
        if ($referrer_id) {
            $referral_code = get_or_generate_referral_code($referrer_id);
        }
    }

    if (!$referral_code) {
        $referrer_id = get_transient('referral_id_' . session_id());
        if ($referrer_id) {
            $referral_code = get_or_generate_referral_code($referrer_id);
        }
    }

    if ($referral_code) {
        $order->update_meta_data('_referral_id', $referral_code);
    }
}

add_action('wp_loaded', 'set_referral_code_session_and_transient');
function set_referral_code_session_and_transient()
{
    if (isset($_GET['ref'])) {
        $referral_code = sanitize_text_field($_GET['ref']);
        $user_id = get_user_id_from_referral_code($referral_code);

        if ($user_id) {
            if (WC()->session) {
                WC()->session->set('referral_code', $referral_code);
            }
            set_transient('referral_code_' . session_id(), $referral_code, HOUR_IN_SECONDS * 24);
        }
    }
}

// If a new user registers, associate the referral code with their session
add_action('user_register', 'set_referral_code_after_registration');
function set_referral_code_after_registration($user_id)
{
    if (WC()->session) {
        $referral_code = WC()->session->get('referral_code');
        if ($referral_code) {
            update_user_meta($user_id, 'custom_referral_code', $referral_code);
        }
    } elseif ($referral_code = get_transient('referral_code_' . session_id())) {
        update_user_meta($user_id, 'custom_referral_code', $referral_code);
    }
}


add_action('woocommerce_before_order_notes', 'conditionally_add_autofilled_referral_id_field');
function conditionally_add_autofilled_referral_id_field($checkout)
{
    $user_id = get_current_user_id();
    $referral_code = '';

    // For logged-in users, check user meta first
    if ($user_id) {
        $referral_code = get_user_meta($user_id, 'referral_code', true);
    }

    // Check session and transient if not logged in or if no meta exists
    if (!$referral_code && WC()->session) {
        $referral_code = WC()->session->get('referral_code', '');
    }

    if (!$referral_code) {
        $referral_code = get_transient('referral_code_' . session_id());
    }

    // Add the referral code field and auto-fill it if available
    woocommerce_form_field('referral_id', [
        'type' => 'text',
        'class' => ['referral-id-field form-row-wide'],
        'label' => __('Enter Referral Code'),
        'placeholder' => __('Referral Code'),
        'description' => __('Use a referral code to earn bonus points!'),
        'default' => $referral_code, // Autofill if referral code exists
    ], $checkout->get_value('referral_id') ?: $referral_code);
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
    $referral_code = sanitize_text_field($_POST['referral_id'] ?? '');

    if ($referral_code) {
        $user_id = get_user_id_from_referral_code($referral_code);
        if ($user_id) {
            $order->update_meta_data('_referral_id', $referral_code); // Save referral code to order meta
        }
    }
}


// Ensure the referral code is cleared from the session and transient after the order is processed
add_action('woocommerce_thankyou', 'clear_referral_code_after_order');
function clear_referral_code_after_order($order_id)
{
    if (WC()->session) {
        WC()->session->__unset('referral_code');
    }
    delete_transient('referral_code_' . session_id());
}


// Clear referral data after use
function clear_referral_data($order_id, $user_id)
{
    if (WC()->session && WC()->session->has_session()) {
        WC()->session->__unset('referral_id');
    } else {
        delete_transient('referral_id_' . session_id());
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
        return;
    }

    $user_id = $order->get_user_id();
    $referral_id = $order->get_meta('_referral_id');
    $referral_id_meta = $order->get_meta('_referral_id');

    // Process referral ID
    $referral_user_id = null;
    if ($referral_id_meta) {
        $referral_code = $referral_id_meta; // Use the full referral code
        $referral_user_id = get_user_id_by_referral_code($referral_code);
        // Log the results of the conversion
    }

    if (!$referral_user_id || !$user_id) {
        return;
    }

    $orders = wc_get_orders(['customer_id' => $user_id, 'status' => 'completed', 'limit' => -1]);
    if (count($orders) > 1) {
        return;
    }

    $referral_points = get_option('woo_referral_points', 200);
    $referred_user_points = get_option('woo_referred_user_points', 100);

    $referrer_points = intval(get_user_meta($referral_user_id, 'customer_points', true) ?: 0);
    $referred_points = intval(get_user_meta($user_id, 'customer_points', true) ?: 0);

    // Get names for comment purposes
    $referrer_data = get_userdata($referral_user_id); // Use referral_user_id
    $referrer_name = $referrer_data ? $referrer_data->display_name : 'Unknown Referrer';

    $referred_user_name = get_userdata($user_id)->display_name;

    // Insert points records in custom points table
    $referrer_insert = $wpdb->insert($table_name, [
        'used_id' => $referral_user_id,
        'stack' => 'referral_bonus',
        'mvt_date' => current_time('mysql'),
        'points_moved' => $referral_points,
        'new_total' => $referrer_points + $referral_points,
        'commentar' => "Referral bonus points for referring $referred_user_name",
        'origin' => 'referral',
        'order_id' => $order_id,
        'given_by' => $referral_user_id,
    ]);

    $referred_insert = $wpdb->insert($table_name, [
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

    if ($referrer_insert && $referred_insert) {
        update_user_meta($referral_user_id, 'customer_points', $referrer_points + $referral_points);
        update_user_meta($user_id, 'customer_points', $referred_points + $referred_user_points);
        update_post_meta($order_id, '_referral_points_awarded', true);
        clear_referral_data($order_id, $user_id);

    } else {
        error_log("Failed to insert referral points for order ID $order_id.");
    }
}

// Helper function to extract user ID from referral code
function get_user_id_by_referral_code($referral_code)
{
    global $wpdb;

    // Ensure referral code is not empty
    if (empty($referral_code)) {
        return null; // or return false, depending on your use case
    }

    // Lookup the user who has the referral code
    $referrer_user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'custom_referral_code' AND meta_value = %s",
        $referral_code
    ));

    if ($referrer_user_id) {
        return $referrer_user_id; // Return the user ID of the referrer
    } else {
        return null; // or return false
    }
}



// Hook into the REST API customer endpoint
add_action('rest_api_init', 'add_completed_orders_to_customer_api');

function add_completed_orders_to_customer_api()
{
    // Add a custom field for 'total_completed_orders' to the customer endpoint
    register_rest_field(
        'customer',
        'total_completed_orders',
        array(
            'get_callback' => 'get_completed_orders_for_customer',
            'update_callback' => null,
            'schema' => null,
        )
    );
}

// Function to calculate total completed orders for a specific customer
function get_completed_orders_for_customer($object)
{
    $user_id = $object['id'];

    // Use wc_get_orders to fetch completed orders for the specific user
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status' => 'completed',
        'limit' => -1, // Fetch all orders
        'return' => 'ids', // Fetch only the IDs for efficiency
    ]);

    // Return the count of completed orders
    return count($orders);
}
