<?php
// Create a meta box for adding or subtracting points
function add_remove_points_meta_box()
{
    add_meta_box(
        'add-remove-points-meta-box',
        __('Add or Subtract Points', 'add-remove-points'),
        'display_add_remove_points_meta_box',
        'shop_order',
        'side',
        'core'
    );
}
add_action('add_meta_boxes', 'add_remove_points_meta_box');


// Function to display the meta box content
function display_add_remove_points_meta_box($post)
{
    $order_id = $post->ID;
?>
    <div class="custom-points-add-form">
        <input type="hidden" id="order_id" name="order_id" value="<?php echo esc_attr($order_id); ?>">

        <!-- Add Points Section -->
        <label for="points_to_add"><?php _e('Points Amount to Add', 'add-remove-points'); ?></label>
        <input style="margin-bottom: 8px;width: 100%;" type="number" id="points_to_add" name="points_to_add" value="0" min="0">
        <br>

        <!-- Drop-down for Add Points Reason (Required) -->
        <label for="points_reason"><?php _e('Select Point Refund Type', 'add-remove-points'); ?></label>
        <select id="points_reason" name="points_reason" style="width: 100%;">
            <option value=""><?php _e('Select a type', 'add-remove-points'); ?></option>
            <option value="Dry Damaged"><?php _e('Dry Damaged', 'add-remove-points'); ?></option>
            <option value="Frozen Damaged"><?php _e('Frozen Damaged', 'add-remove-points'); ?></option>
            <option value="Expired"><?php _e('Expired', 'add-remove-points'); ?></option>
            <option value="Missing"><?php _e('Missing', 'add-remove-points'); ?></option>
            <option value="Mismatch"><?php _e('Mismatch', 'add-remove-points'); ?></option>
        </select>
        <br><br>

        <!-- Reason for Adding Points (Textarea) -->
        <label for="points_to_add_reason"><?php _e('Reason for Adding Points', 'add-remove-points'); ?></label>
        <textarea style="margin-bottom: 8px;width: 100%;" id="points_to_add_reason" name="points_to_add_reason"></textarea>
        <br>

        <!-- Add Points Button -->
        <button style="background: #3f51b5;color: #ffffff;padding: 3px 6px;width: 100%;border:0;" type="button" id="add_points_button" class="button"><?php _e('(+)Add Points', 'add-remove-points'); ?></button>
        <br><br>

        <!-- Subtract Points Section -->
        <label for="points_to_subtract"><?php _e('Points Amount to Subtract', 'add-remove-points'); ?></label>
        <input style="margin-bottom: 8px;width: 100%;" type="number" id="points_to_subtract" name="points_to_subtract" value="0" min="0">
        <br>

        <label for="points_to_subtract_reason"><?php _e('Reason for Subtracting', 'add-remove-points'); ?></label>
        <textarea style="margin-bottom: 8px;width: 100%;" id="points_to_subtract_reason" name="points_to_subtract_reason"></textarea>

        <!-- Subtract Points Button -->
        <button style="background: #c6170a;color: #ffffff;padding: 3px 6px;width: 100%;border:0;" type="button" id="subtract_points_button" class="button"><?php _e('(-)Subtract Points', 'add-remove-points'); ?></button>
    </div>

<?php
}

// Function to handle point addition/subtraction AJAX request
function handle_points_change()
{
    error_log('Received POST data: ' . print_r($_POST, true));
    check_ajax_referer('add_remove_points_nonce', 'security');

    if (isset($_POST['points_to_add'], $_POST['points_to_subtract'], $_POST['order_id'])) {
        $points_to_add = intval($_POST['points_to_add']);
        $points_to_subtract = intval($_POST['points_to_subtract']);
        $order_id = intval($_POST['order_id']);
        $points_to_add_reason = sanitize_text_field($_POST['points_to_add_reason']);
        $points_to_subtract_reason = sanitize_text_field($_POST['points_to_subtract_reason']);
        $points_reason = sanitize_text_field($_POST['points_reason']);

        $points_changed = $points_to_add - $points_to_subtract;

        if ($points_changed !== 0) {
            // Get the user ID associated with the order
            $user_id = get_post_meta($order_id, '_customer_user', true);

            if ($user_id) {
                // Update user meta with the new points
                $current_points = get_user_meta($user_id, 'customer_points', true);
                $updated_points = ($current_points !== '') ? max(0, $current_points + $points_changed) : max(0, $points_changed);
                update_user_meta($user_id, 'customer_points', $updated_points);

                // Get the current user's display name or login
                $current_user = wp_get_current_user();
                $current_user_id = $current_user->ID;
                $user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

                // Insert a row in the database with the points change
                global $wpdb;
                $table_name = $wpdb->prefix . 'custom_points_table';

                if ($points_changed > 0) {
                    $insert_data = array(
                        'used_id' => $user_id,
                        'mvt_date' => current_time('mysql'),
                        'points_moved' => $points_changed,
                        'new_total' => $updated_points,
                        'commentar' => 'Added ' . abs($points_changed) . ' points by Customer Representative.',
                        'points_reason' => $points_reason,
                        'order_id' => $order_id,
                        'given_by' => $current_user_id,
                    );
                } else {
                    $insert_data = array(
                        'used_id' => $user_id,
                        'mvt_date' => current_time('mysql'),
                        'points_moved' => $points_changed,
                        'new_total' => $updated_points,
                        'commentar' => 'Subtracted ' . abs($points_changed) . ' points by Customer Representative.',
                        'order_id' => $order_id,
                        'given_by' => $current_user_id,
                    );
                }

                $wpdb->insert($table_name, $insert_data);

                // Generate order note after adding or subtracting the points
                $order = wc_get_order($order_id);
                if ($order) {
                    $added_or_deducted_text = ($points_changed > 0) ? 'Added' : 'Deducted';
                    $add_or_deduct_amount = abs($points_changed);

                    $note = sprintf(
                        __('<strong>Points %s: %s</strong><br>', 'woocommerce'),
                        $added_or_deducted_text,
                        $add_or_deduct_amount
                    );

                    if ($points_changed > 0) {
                        $note .= sprintf(__('Reason Type: %s<br>Reason: %s<br>', 'woocommerce'), $points_reason, $points_to_add_reason);
                    } else {
                        // Check if the subtraction reason is empty
                        if (empty($points_to_subtract_reason)) {
                            error_log('Subtraction reason is empty.');
                        } else {
                            $note .= sprintf(__('Reason: %s<br>', 'woocommerce'), $points_to_subtract_reason);
                        }
                    }

                    // Add the note to the order
                    $order->add_order_note(
                        $note,
                        true, // Is Customer Note (Show the Email Note to Customer Also)
                        true  // Add Username for Admin Log
                    );
                }

                // Return a success response
                wp_send_json_success('Points updated successfully');
            } else {
                wp_send_json_error('User ID not found for this order');
            }
        } else {
            wp_send_json_error('No change in points');
        }
    } else {
        wp_send_json_error('Invalid data received');
    }
    // Log errors if any
    wp_send_json_error('Invalid data received');
}

add_action('wp_ajax_handle_points_change', 'handle_points_change');
add_action('wp_ajax_nopriv_handle_points_change', 'handle_points_change');




// Function to enqueue CSS styles
function enqueue_custom_styles()
{
?>
    <style>
        .custom-points-add-form #add_points_button:hover {
            background: #6270b9 !important;
        }

        .custom-points-add-form #subtract_points_button:hover {
            background: #d51f12 !important;
        }
    </style>
<?php
}
add_action('admin_head', 'enqueue_custom_styles');
