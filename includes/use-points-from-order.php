<?php
// Ensure the file is not accessed directly.

use Yoast\WP\SEO\Presenters\Admin\Alert_Presenter;

if (!defined('ABSPATH')) {
    exit;
}

// Hook into the order details page
add_action('woocommerce_order_item_add_action_buttons', 'display_use_points_button', 10, 1);

function display_use_points_button($item_id)
{
    // Check if the order is on hold
    $order = wc_get_order($item_id);
    if ($order && $order->has_status('on-hold')) {
?>
        <style>
            #loader-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.7);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }

            #loader {
                border: 10px solid #f3f3f3;
                border-top: 10px solid #3498db;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                position: fixed;
                top: 40%;
                left: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>
        <p class="add-items">
            <a class="button" id="use-points-order-item-btn">Use Points</a>
        </p>

        <div id="use-points-dialog" title="Use Points">
            <form id="use-points-form">
                <h3>
                    <span id="available-points">Available Points: <?php echo esc_html(get_user_meta($order->get_user_id(), 'customer_points', true)); ?></span>
                </h3>
                <p>
                    <label for="points-to-use">Points to Use:</label>
                    <input style="width: 100%;" type="number" id="points-to-use" name="points-to-use" min="0" max="<?php echo esc_attr(get_user_meta($order->get_user_id(), 'customer_points', true)); ?>">
                </p>
                <p style="padding-top: 15px;border-top: 1px dotted #ddd;">
                    <button type="button" id="apply-points-btn" class="button">Apply Points</button>
                </p>
            </form>
        </div>
        <!-- Loader Overlay -->
        <div id="loader-overlay">
            <div id="loader"></div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $("#use-points-order-item-btn").on("click", function() {
                    // Open the "Use Points" dialog for the selected order items
                    $("#use-points-dialog").dialog("open");
                });

                $("#use-points-dialog").dialog({
                    autoOpen: false,
                    hide: "puff",
                    show: "slide",
                    width: 400,
                    height: 250
                });

                $("#apply-points-btn").on("click", function() {
                    var pointsToUse = parseInt($("#points-to-use").val(), 10);

                    console.log("Points to Use:", pointsToUse);
                    if (Number.isInteger(pointsToUse) && pointsToUse > 0) {

                        showLoader();
                        // AJAX call to apply points as a coupon
                        $.ajax({
                            type: "POST",
                            url: ajaxurl,
                            data: {
                                action: 'apply_points_as_coupon_from_order',
                                order_id: '<?php echo esc_js($order->get_id()); ?>',
                                points_to_use: pointsToUse,
                                security: '<?php echo esc_js(wp_create_nonce('apply_points_as_coupon_from_order_nonce')); ?>',
                            },
                            success: function(response) {
                                // Trim the response to remove extra whitespace
                                response = response.trim();
                                console.log("AJAX Success Response:", response);
                                if (response === 'success') {
                                    location.reload(); // Reload the page
                                } else if (response === 'invalid_points') {
                                    alert("Invalid points value. Please enter a valid amount.");
                                } else {
                                    alert("Point generated coupon is already applied to this order.");
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                console.error("AJAX Error:", textStatus, errorThrown);
                                console.log(jqXHR.responseText);
                            },
                            complete: function() {
                                // Hide loader when the AJAX call is complete
                                hideLoader();
                                $("#use-points-dialog").dialog("close");
                            }
                        });
                    } else {
                        alert("Invalid points value. Please enter a valid positive amount.");
                    }
                });

                function showLoader() {
                    $("#loader-overlay").show();
                }

                // Function to hide loader
                function hideLoader() {
                    $("#loader-overlay").hide();
                }
            });
        </script>
<?php
    }
}



// Hook into the order update process
add_action('woocommerce_process_shop_order_meta', 'update_coupon_code_meta_on_order_update');

function update_coupon_code_meta_on_order_update($order_id)
{
    // Get the applied coupons for the order
    $applied_coupons = get_post_meta($order_id, '_applied_coupons', true);

    // If no coupons are applied, update the 'points_redemption_coupon_code' meta to blank
    if (empty($applied_coupons)) {
        update_post_meta($order_id, 'points_redemption_coupon_code', '');
    }
}




// Function to apply points as a coupon from order details page
add_action('wp_ajax_apply_points_as_coupon_from_order', 'apply_points_as_coupon_from_order');
add_action('wp_ajax_nopriv_apply_points_as_coupon_from_order', 'apply_points_as_coupon_from_order');

function apply_points_as_coupon_from_order()
{
    check_ajax_referer('apply_points_as_coupon_from_order_nonce', 'security');

    if (isset($_POST['points_to_use']) && isset($_POST['order_id'])) {
        $points_to_use = absint($_POST['points_to_use']);
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        $user_email = $order->get_billing_email();

        $user_points = get_user_meta($user_id, 'customer_points', true);
        $existing_coupon_code = get_post_meta($order_id, 'points_redemption_coupon_code', true);

        // Prevent duplicate coupon applications if points-based coupon is already applied
        if (preg_match('/^POINT-\w+$/', $existing_coupon_code)) {
            echo 'coupon_already_applied';
            wp_die();
        }

        // Transient lock to prevent multiple submissions
        if (get_transient('apply_coupon_lock_' . $order_id)) {
            echo 'already_processing';
            wp_die();
        }

        // Validate the points to use, ensuring the user has enough points
        if ($points_to_use > 0 && $points_to_use <= $user_points) {
            // Set the transient lock to prevent double submission for 10 seconds
            set_transient('apply_coupon_lock_' . $order_id, true, 10);

            // Double-check points again after locking
            $user_points_after_check = get_user_meta($user_id, 'customer_points', true);
            if ($points_to_use > $user_points_after_check) {
                echo 'insufficient_points_after_check';
                delete_transient('apply_coupon_lock_' . $order_id);
                wp_die();
            }

            // Generate a unique coupon code
            $coupon_code = 'POINT-' . uniqid();

            // Create a coupon object
            $coupon = new WC_Coupon();
            $new_coupon_id = wp_insert_post($coupon);
            $coupon->set_code($coupon_code);
            $coupon->set_discount_type('fixed_cart');
            $coupon->set_amount($points_to_use);
            $coupon->set_usage_limit(1);
            $coupon->set_usage_limit_per_user(1);
            $coupon->set_email_restrictions(array($user_email));
            $coupon->save();

            // Apply the coupon to the order
            $order->apply_coupon($coupon_code);

            // Add a note to the order indicating points were redeemed
            $order->add_order_note(sprintf(__('Used %s points on order #%s by Customer Representative.', 'your-textdomain'), $points_to_use, $order_id));

            // Update user meta with the points used and reduce points from the total
            update_user_meta($user_id, 'points_used_for_coupon', $points_to_use);
            $total_points = max(0, $user_points_after_check - $points_to_use); // Ensure points don't go negative
            update_user_meta($user_id, 'customer_points', $total_points);

            // Save the generated coupon code in post meta for the order
            update_post_meta($order_id, 'points_redemption_coupon_code', $coupon_code);
            update_post_meta($coupon->get_id(), 'is_point_redemption_coupon', 'yes');

            // Insert the transaction into the custom points table
            global $wpdb;
            $table_name = $wpdb->prefix . 'custom_points_table';
            $wpdb->insert($table_name, array(
                'used_id' => $user_id,
                'mvt_date' => current_time('mysql'),
                'points_moved' => -$points_to_use,
                'new_total' => $total_points,
                'commentar' => 'Used ' . $points_to_use . ' points on order #' . $order_id . ' by Customer Representative',
                'order_id' => $order_id,
                'given_by' => $user_id,
            ));

            // Clear the transient lock after processing
            delete_transient('apply_coupon_lock_' . $order_id);

            // Mark the points deduction as performed
            update_post_meta($order_id, 'points_deduction_performed', true);
            // Save changes to the order
            $order->save();

            // Send success response
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
