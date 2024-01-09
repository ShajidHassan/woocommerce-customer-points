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

// Include the files for different functionalities
include_once plugin_dir_path(__FILE__) . 'includes/database.php';
include_once plugin_dir_path(__FILE__) . 'includes/display-order-points.php';
include_once plugin_dir_path(__FILE__) . 'includes/custom-order-points.php';



// Hook into order placement
// add_action('woocommerce_new_order', 'award_points_on_order', 10, 1);
add_action('woocommerce_order_status_completed', 'award_points_on_order', 10, 1);

function award_points_on_order($order_id)
{
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $order_count = 0;

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_points_table';

    if ($user_id) {
        $order_count = wc_get_customer_order_count($user_id);
    }

    if ($order_count === 1) {
        // For the first order, award 300 points
        $order_total = $order->get_total();
        $points_earned = floor($order_total / 100); // 1 point for every 100 yen spent
        $total_points = $points_earned + 300;
        update_user_meta($user_id, 'customer_points', $total_points);

        // Insert the first row for getting 300 points for the first order
        $insert_data_first_order = array(
            'used_id' => $user_id,
            'mvt_date' => current_time('mysql'),
            'points_moved' => 300,
            'new_total' => 300,
            'commentar' => 'Get 300 points for the first order #' . $order_id,
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
    } elseif ($order_count > 1) {
        // Calculate points for subsequent orders
        $order_total = $order->get_total();
        $points_earned = floor($order_total / 100); // 1 point for every 100 yen spent
        $current_points = get_user_meta($user_id, 'customer_points', true);
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
            <h4><b><?php esc_html_e('Available Points:', 'woo-customer-points'); ?> <?php echo esc_html($user_points); ?></b></h4>
            <div class="points-row row">
                <div class="col-md-5">
                    <p>
                        <label for="points_to_use"><?php esc_html_e('Points to Use:', 'woo-customer-points'); ?></label>
                        <input class="input-text" type="number" id="points_to_use" name="points_to_use" value="0" min="0" max="<?php echo esc_attr($user_points); ?>">
                    </p>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button type="button" id="use_all_points" class="button"><?php esc_html_e('Use All', 'woo-customer-points'); ?></button>
                    <button type="button" id="apply_points" class="button"><?php esc_html_e('Apply Points', 'woo-customer-points'); ?></button>
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
        $user_points = get_user_meta($current_user->ID, 'customer_points', true);

        if ($points_to_use <= $user_points && $points_to_use > 0) {
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
            $new_coupon->set_individual_use(true);

            $new_coupon->set_usage_limit(1);

            // Save the coupon
            $new_coupon->save();

            // Set metadata for the coupon using update_post_meta()
            update_post_meta($new_coupon_id, 'is_point_redemption_coupon', 'yes');

            // Apply the coupon to the cart
            WC()->cart->apply_coupon($coupon_code);

            // Save the points used in a user meta field
            update_user_meta($user_id, 'points_used_for_coupon', $points_to_use);

            // Return success response
            echo 'success';
            wp_die();
        } else {
            echo 'invalid_points';
            wp_die();
        }
    } else {
        echo 'missing_data';
        wp_die();
    }
}



// Function to deduct points after the order is placed
function deduct_points_after_order($order_id)
{
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $order_count = wc_get_customer_order_count($user_id);

    if ($order_count > 1) {
        $current_points = get_user_meta($user_id, 'customer_points', true);

        // Get the points used from the meta field
        $points_used = get_user_meta($user_id, 'points_used_for_coupon', true);
        $points_used = $points_used ? intval($points_used) : 0;

        // Calculate the remaining points after deduction
        $total_points = max(0, $current_points - $points_used);

        update_user_meta($user_id, 'customer_points', $total_points); // Update the user's points

        // Insert a row for deducting points from the order
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_points_table';

        $insert_data = array(
            'used_id' => $user_id,
            'mvt_date' => current_time('mysql'),
            'points_moved' => -$points_used, // Deducted points
            'new_total' => $total_points,
            'commentar' => 'Used ' . $points_used . ' points from the order #' . $order_id,
            'order_id' => $order_id,
            'given_by' => $user_id,
        );

        $wpdb->insert($table_name, $insert_data);
    }
}

// Hook into order placement
add_action('woocommerce_new_order', 'deduct_points_after_order');




// Enqueue the JavaScript file
add_action('wp_enqueue_scripts', 'enqueue_custom_js');

function enqueue_custom_js()
{
    wp_enqueue_script('custom-points-handler', plugin_dir_url(__FILE__) . 'js/points-handler.js', array('jquery'), '1.0', true);

    // Localize necessary variables
    wp_localize_script('custom-points-handler', 'wc_checkout_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}



function enqueue_points_change_script($hook)
{
    global $post;

    if ('shop_order' === $post->post_type && 'post.php' === $hook) {
        wp_enqueue_script(
            'custom-points-handler',
            plugin_dir_url(__FILE__) . 'js/custom-points-handler.js',
            array('jquery'),
            '1.0',
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
    }
}
add_action('admin_enqueue_scripts', 'enqueue_points_change_script');


// Enqueue custom script for the cart widget
function enqueue_custom_cart_script()
{
    wp_enqueue_script('custom-cart-script', plugin_dir_url(__FILE__) . 'js/custom-cart-script.js', array('jquery'), '1.0', true);

    // Localize necessary variables (if needed)
    wp_localize_script('custom-cart-script', 'cart_ajax_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_cart_script');




// Enqueue CSS file
function enqueue_custom_css()
{
    wp_enqueue_style('custom-style', plugin_dir_url(__FILE__) . 'css/style.css');
}
add_action('wp_enqueue_scripts', 'enqueue_custom_css');
