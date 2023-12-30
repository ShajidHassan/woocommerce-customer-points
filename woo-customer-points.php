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


// Hook into WooCommerce checkout before billing and shipping section
add_action('woocommerce_before_checkout_form', 'display_points_input_field');

function display_points_input_field()
{
    $current_user = wp_get_current_user();
    $user_points = get_user_meta($current_user->ID, 'customer_points', true);

    // Check if the field hasn't been displayed yet
    if ($user_points && !did_action('custom_points_field_displayed')) {
?>
        <div class="custom-points-field" style="margin-bottom: 30px;">
            <?php
            ?>
            <h4><?php esc_html_e('Available Points:', 'woo-customer-points'); ?> <?php echo esc_html($user_points); ?></h4>
            <div class="points-row">
                <p>
                    <label for="points_to_use"><?php esc_html_e('Points to Use:', 'woo-customer-points'); ?></label>
                    <input type="number" id="points_to_use" name="points_to_use" value="0" min="0" max="<?php echo esc_attr($user_points); ?>">
                </p>
                <button type="button" id="use_all_points" class="button"><?php esc_html_e('Use All', 'woo-customer-points'); ?></button>
                <button type="button" id="apply_points" class="button"><?php esc_html_e('Apply Points', 'woo-customer-points'); ?></button>
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

            // Check if a coupon for points redemption already exists
            $existing_coupon = new WC_Coupon($coupon_code);

            if ($existing_coupon->get_id()) {
                // Update the existing coupon instead of creating a new one
                $coupon_id = $existing_coupon->get_id();
            } else {
                // Create a new coupon
                $new_coupon = new WC_Coupon();
                $new_coupon->set_code($coupon_code);
                $new_coupon->set_discount_type('fixed_cart');
                $new_coupon->set_amount($points_to_use);
                $new_coupon->set_individual_use(true);
                $new_coupon->set_meta_data('is_point_redemption_coupon', 'yes'); // Set metadata for points redemption coupon
                $new_coupon->save();

                $coupon_id = $new_coupon->get_id();
            }

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
    $current_points = get_user_meta($user_id, 'customer_points', true);

    // Get the points used from the meta field
    $points_used = get_user_meta($user_id, 'points_used_for_coupon', true);
    $points_used = $points_used ? intval($points_used) : 0;

    // Calculate the remaining points after deduction
    $total_points = max(0, $current_points - $points_used);
    error_log("Current Point: $current_points");
    error_log("Point Used: $points_used");
    error_log("Total Point: $total_points");

    update_user_meta($user_id, 'customer_points', $total_points); // Update the user's points
}

// Hook into order placement
add_action('woocommerce_new_order', 'deduct_points_after_order');




// Enqueue the JavaScript file
add_action('wp_enqueue_scripts', 'enqueue_custom_js');

function enqueue_custom_js()
{
    wp_enqueue_script('custom-points-handler', plugin_dir_url(__FILE__) . 'points-handler.js', array('jquery'), '1.0', true);

    // Localize necessary variables
    wp_localize_script('custom-points-handler', 'wc_checkout_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
