<?php

/**
 * Plugin Name: Woo Customer Points
 * Description: A plugin to manage customer points in WooCommerce.
 * Version: 2.0.2
 * Author: Mirailit Limited
 * Author URI: https://mirailit.com/
 * Text Domain: woo-customer-points
 * Domain Path: /languages
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include the files for different functionalities
include_once plugin_dir_path(__FILE__) . 'includes/database.php';
include_once plugin_dir_path(__FILE__) . 'includes/display-order-points.php';
include_once plugin_dir_path(__FILE__) . 'includes/custom-order-points.php';
include_once plugin_dir_path(__FILE__) . 'includes/user-points-page.php';
include_once plugin_dir_path(__FILE__) . 'includes/use-points-from-order.php';
include_once plugin_dir_path(__FILE__) . 'includes/point-summary-page.php';
include_once plugin_dir_path(__FILE__) . 'includes/admin/settings-page.php';
include_once plugin_dir_path(__FILE__) . 'includes/referral-points.php';


// Hook into order placement
// add_action('woocommerce_new_order', 'award_points_on_order', 10, 1);
add_action('woocommerce_order_status_completed', 'award_points_on_order', 10, 1);

function award_points_on_order($order_id)
{

    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    // Check if points have already been awarded for this order
    if (get_post_meta($order_id, '_points_awarded', true)) {
        return; // Points already awarded, so exit the function
    }

    // Check if the user exists
    if (!$user_id) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_points_table';

    // Get the count of the user's completed orders
    $completed_order_count = count(wc_get_orders(array(
        'customer_id' => $user_id,
        'status' => 'completed',
    )));

    // Get settings values
    $first_order_points = get_option('woo_first_order_points', 300);
    $points_per_currency_spent = get_option('woo_points_per_currency_spent', 1);
    $currency_unit_for_points = get_option('woo_currency_unit_for_points', 100);

    if ($completed_order_count === 1) {
        // For the first completed order, award 300 points plus points based on amount spent
        $order_total = $order->get_total();
        $points_earned = floor($order_total / $currency_unit_for_points) * $points_per_currency_spent;
        $total_points = $points_earned + $first_order_points;
        update_user_meta($user_id, 'customer_points', $total_points);

        // Insert the first row for getting points for the first order
        $insert_data_first_order = array(
            'used_id' => $user_id,
            'mvt_date' => current_time('mysql'),
            'points_moved' => $first_order_points,
            'new_total' => $first_order_points,
            'commentar' => 'Awarded ' . $first_order_points . ' points for first order #' . $order_id,
            'order_id' => $order_id,
            'given_by' => $user_id,
        );

        $wpdb->insert($table_name, $insert_data_first_order);

        // Insert the second row for getting points based on amount spent
        $insert_data_points_based_on_spent = array(
            'used_id' => $user_id,
            'mvt_date' => current_time('mysql'),
            'points_moved' => $points_earned,
            'new_total' => $total_points,
            'commentar' => 'Get ' . $points_earned . ' points from the order #' . $order_id,
            'order_id' => $order_id,
            'given_by' => $user_id,
        );

        $wpdb->insert($table_name, $insert_data_points_based_on_spent);
    } elseif ($completed_order_count > 1) {
        // Calculate points for subsequent orders
        $order_total = floatval($order->get_total());
        $points_earned = floor($order_total / $currency_unit_for_points) * $points_per_currency_spent;
        $current_points = intval(get_user_meta($user_id, 'customer_points', true));
        $total_points = $current_points + $points_earned;
        update_user_meta($user_id, 'customer_points', $total_points);

        $insert_data = array(
            'used_id' => $user_id,
            'mvt_date' => current_time('mysql'),
            'points_moved' => $points_earned,
            'new_total' => $total_points,
            'commentar' => 'Get ' . $points_earned . ' points from the order #' . $order_id,
            'order_id' => $order_id,
            'given_by' => $user_id,
        );

        $wpdb->insert($table_name, $insert_data);
    }
    // Mark the order as processed to avoid duplicate points
    update_post_meta($order_id, '_points_awarded', true);
}



// Shortcode to display user points
add_shortcode('display_user_points', 'display_user_points_function');

function display_user_points_function($atts)
{
    $atts = shortcode_atts(
        array(
            'section' => '',
        ),
        $atts,
        'display_user_points'
    );

    // Check if the user is logged in
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $points = get_user_meta($user_id, 'customer_points', true);

        // Check if user has points
        if ($points === '') {
            $points = 0;
        }

        // Display points only
        if ($atts['section'] === 'points') {
            return esc_html($points);
        }

        // Display both label and points
        return '<h3><strong>' . esc_html__('Available Points:', 'woo-customer-points') . '</strong> ' . esc_html($points) . '</h3>';
    } else {
        // Display points only when not logged in
        if ($atts['section'] === 'points') {
            return esc_html('');
        }

        // Display login message
        return '<h4>' . esc_html__('Please login to view your points.', 'woo-customer-points') . '</h4>';
    }
}


// Hook into WooCommerce checkout before billing and shipping section
add_action('woocommerce_before_checkout_form', 'display_points_input_field');
// Hook into the 'woocommerce_before_cart' action to add the form before the cart table
add_action('woocommerce_after_cart_table', 'display_points_input_field');

function display_points_input_field()
{
    $current_user = wp_get_current_user();
    $user_points = get_user_meta($current_user->ID, 'customer_points', true);

    // Check if the field hasn't been displayed yet
    if ($user_points && !did_action('custom_points_field_displayed')) {
        ?>
        <div class="custom-points-field">
            <?php
            ?>
            <h4><b>
                    <?php esc_html_e('Available Points:', 'woo-customer-points'); ?>
                    <?php echo esc_html($user_points); ?>
                </b></h4>
            <div class="points-row row">
                <div class="col-md-5">
                    <p>
                        <label for="points_to_use">
                            <?php esc_html_e('Points to Use:', 'woo-customer-points'); ?>
                        </label>
                        <input class="input-text" type="number" id="points_to_use" name="points_to_use" value="0" min="0"
                            max="<?php echo esc_attr($user_points); ?>">
                    </p>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button type="button" id="use_all_points"
                        class="button"><?php esc_html_e('Use All', 'woo-customer-points'); ?></button>
                    <button type="button" id="apply_points"
                        class="button"><?php esc_html_e('Apply Points', 'woo-customer-points'); ?></button>
                </div>
            </div>
        </div>
        <?php
        // Set an action hook to mark that the field has been displayed
        do_action('custom_points_field_displayed');
    }
}


// Function to apply points as a coupon code
add_action('wp_ajax_apply_points_as_coupon', 'apply_points_as_coupon');
add_action('wp_ajax_nopriv_apply_points_as_coupon', 'apply_points_as_coupon');

function apply_points_as_coupon()
{
    if (isset($_POST['points_to_use'])) {
        $points_to_use = intval($_POST['points_to_use']);
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $user_email = $current_user->user_email;
        $user_points = get_user_meta($current_user->ID, 'customer_points', true);

        $existing_coupon_code = get_user_meta($user_id, 'points_redemption_coupon_code', true);

        if ($points_to_use <= $user_points && $points_to_use > 0) {
            if (empty($existing_coupon_code)) {
                $coupon_code = 'POINT-' . uniqid();

                // Create a coupon array
                $coupon = array(
                    'post_title' => $coupon_code,
                    'post_content' => '',
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_type' => 'shop_coupon'
                );

                // Insert the coupon into the database
                $new_coupon_id = wp_insert_post($coupon);

                // Load the coupon by ID
                $new_coupon = new WC_Coupon($new_coupon_id);

                // Set coupon data
                $new_coupon->set_discount_type('fixed_cart');
                $new_coupon->set_amount($points_to_use);
                // $new_coupon->set_individual_use(true);

                $new_coupon->set_usage_limit(1);
                $new_coupon->set_usage_limit_per_user(1);
                // Restrict coupon to user's email
                $new_coupon->set_email_restrictions(array($user_email));

                $new_coupon->save();

                // Set metadata for the coupon using update_post_meta()
                update_post_meta($new_coupon_id, 'is_point_redemption_coupon', 'yes');
                update_user_meta($user_id, 'points_redemption_coupon_code', $coupon_code);


                // Apply the coupon to the cart
                WC()->cart->apply_coupon($coupon_code);

                // Save the points used in a user meta field
                update_user_meta($user_id, 'points_used_for_coupon', $points_to_use);

                echo 'success';
                wp_die();
            } else {
                // Update the existing coupon
                $existing_coupon = new WC_Coupon($existing_coupon_code);
                $existing_coupon->set_amount($points_to_use);
                $existing_coupon->set_usage_limit(1);
                $existing_coupon->set_usage_limit_per_user(1);
                // Restrict coupon to user's email
                $existing_coupon->set_email_restrictions(array($user_email));
                $existing_coupon->save();

                // Apply the updated coupon to the cart
                WC()->cart->apply_coupon($existing_coupon_code);

                update_post_meta($existing_coupon->get_id(), 'is_point_redemption_coupon', 'yes');
                // Update the points used in a user meta field
                update_user_meta($user_id, 'points_used_for_coupon', $points_to_use);

                echo 'success';
                wp_die();
            }
        } else {
            echo 'invalid_points';
            wp_die();
        }
    } else {
        echo 'missing_data';
        wp_die();
    }
}

// Hook into order completion event to delete coupon
add_action('woocommerce_order_status_processing', 'delete_generated_coupons_after_order', 10, 1);
// Hook into order on-hold event
add_action('woocommerce_order_status_on-hold', 'delete_generated_coupons_after_order', 10, 1);
// Hook into order cancelled event
add_action('woocommerce_order_status_cancelled', 'delete_generated_coupons_after_order', 10, 1);

function delete_generated_coupons_after_order($order_id)
{
    // Get coupons associated with the order
    $args = array(
        'post_type' => 'shop_coupon',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'is_point_redemption_coupon',
                'value' => 'yes',
            ),
        ),
    );

    $coupons = get_posts($args);

    foreach ($coupons as $coupon) {
        // Delete the coupon
        wp_delete_post($coupon->ID, true);
    }
}


//Function to deduct points after the order is placed
function deduct_points_after_order($order_id)
{
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $order_count = wc_get_customer_order_count($user_id);
    // This works for web order but for rest api order gives error
    // $applied_coupons = WC()->cart->get_applied_coupons();
    // get applied coupons from order
    // this doesnt return any coupon used just returns empty array
    // $applied_coupons = $order->get_coupon_codes();

    $applied_coupons = $order->get_items('coupon');

    if ($order_count > 1) {

        // Check if the deduction has already been performed
        $deduction_performed = get_post_meta($order_id, 'points_deduction_performed', true);
        if (!$deduction_performed) {
            foreach ($applied_coupons as $coupon) {
                $coupon_code = $coupon->get_code();
                $coupon_discount = $coupon->get_discount();

                $coupon_code_lower = strtolower($coupon_code);
                if (strpos($coupon_code_lower, 'point-') === 0) {

                    $current_points = get_user_meta($user_id, 'customer_points', true);
                    $current_points = $current_points ? intval($current_points) : 0;

                    $points_used = get_user_meta($user_id, 'points_used_for_coupon', true);
                    $points_used = $points_used ? intval($points_used) : 0;

                    $total_points = max(0, $current_points - $points_used);

                    update_user_meta($user_id, 'customer_points', $total_points);

                    global $wpdb;
                    $table_name = $wpdb->prefix . 'custom_points_table';

                    $insert_data = array(
                        'used_id' => $user_id,
                        'mvt_date' => current_time('mysql'),
                        'points_moved' => -$points_used,
                        'new_total' => $total_points,
                        'commentar' => 'Used ' . $points_used . ' points from the order #' . $order_id,
                        'order_id' => $order_id,
                        'given_by' => $user_id,
                    );

                    $wpdb->insert($table_name, $insert_data);

                    // Mark that the deduction has been performed
                    update_post_meta($order_id, 'points_deduction_performed', true);

                    break;
                }
            }
        }
    }
}
//Hook into order placement
add_action('woocommerce_order_status_processing', 'deduct_points_after_order', 10, 1);



// Hook into order cancellation
add_action('woocommerce_order_status_cancelled', 'return_points_after_order_cancellation', 10, 1);

// Function to return points after order cancellation
function return_points_after_order_cancellation($order_id)
{
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    // Retrieve applied coupons from the order
    $applied_coupons = $order->get_items('coupon');

    foreach ($applied_coupons as $coupon) {
        $coupon_code = $coupon->get_code();

        // Check if the coupon code starts with 'point-'
        if (strpos(strtolower($coupon_code), 'point-') === 0) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'custom_points_table';

            $order_points_moved = $wpdb->get_var($wpdb->prepare("SELECT points_moved FROM $table_name WHERE order_id = %d", $order_id));
            $order_points_moved = $order_points_moved ? abs(intval($order_points_moved)) : 0;


            // Check if the order has been canceled before
            $canceled_orders = get_user_meta($user_id, 'canceled_orders', true);
            $canceled_orders = $canceled_orders ? json_decode($canceled_orders, true) : array();

            if (!in_array($order_id, $canceled_orders)) {
                // If the order hasn't been canceled before, return the points
                $current_points = get_user_meta($user_id, 'customer_points', true);
                $current_points = $current_points ? intval($current_points) : 0;

                // Calculate the new total points after returning the used points for the specific order
                $new_total_points = $current_points + $order_points_moved;

                // Update user meta with the new total points
                update_user_meta($user_id, 'customer_points', $new_total_points);

                // Update the list of canceled orders
                $canceled_orders[] = $order_id;
                update_user_meta($user_id, 'canceled_orders', json_encode($canceled_orders));

                // Insert a record into the custom points table for the points return
                global $wpdb;
                $table_name = $wpdb->prefix . 'custom_points_table';

                $insert_data = array(
                    'used_id' => $user_id,
                    'mvt_date' => current_time('mysql'),
                    'points_moved' => $order_points_moved,
                    'new_total' => $new_total_points,
                    'commentar' => 'Returned ' . $order_points_moved . ' points for the canceled order #' . $order_id,
                    'order_id' => $order_id,
                    'given_by' => $user_id,
                );

                $wpdb->insert($table_name, $insert_data);
                break;
            }
        }
    }
}



// Enqueue the JavaScript file conditionally
add_action('wp_enqueue_scripts', 'enqueue_custom_js');

function enqueue_custom_js()
{
    if (is_checkout()) {
        wp_enqueue_script('custom-points-handler', plugin_dir_url(__FILE__) . 'js/points-handler.js', array('jquery'), '1.2', true);

        // Localize necessary variables with a unique name
        wp_localize_script(
            'custom-points-handler',
            'custom_wc_checkout_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
            )
        );
    }
}



function enqueue_points_change_script($hook)
{
    global $post;

    //if ('shop_order' === $post->post_type && 'post.php' === $hook) {
    wp_enqueue_script(
        'custom-points-handler',
        plugin_dir_url(__FILE__) . 'js/custom-points-handler.js',
        array('jquery'),
        '1.1',
        true
    );

    // Localize necessary variables
    wp_localize_script(
        'custom-points-handler',
        'ajax_object',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('add_remove_points_nonce')
        )
    );
    //}
}
add_action('admin_enqueue_scripts', 'enqueue_points_change_script');


// Enqueue custom script for the cart widget
function enqueue_custom_cart_script()
{
    wp_enqueue_script('custom-cart-script', plugin_dir_url(__FILE__) . 'js/custom-cart-script.js', array('jquery'), '1.0', true);

    // Localize necessary variables (if needed)
    wp_localize_script(
        'custom-cart-script',
        'cart_ajax_params',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
        )
    );
}
add_action('wp_enqueue_scripts', 'enqueue_custom_cart_script');




// Enqueue CSS file
function enqueue_custom_css()
{
    wp_enqueue_style('custom-style', plugin_dir_url(__FILE__) . 'css/style.css');
}
add_action('wp_enqueue_scripts', 'enqueue_custom_css');
