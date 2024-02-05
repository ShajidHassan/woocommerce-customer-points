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
    <div class="">
        <input type="hidden" id="order_id" name="order_id" value="<?php echo esc_attr($order_id); ?>">
        <label for="points_to_add"><?php _e('Points Amount to Add:', 'add-remove-points'); ?></label>
        <input style="margin-bottom: 8px;" type="number" id="points_to_add" name="points_to_add" value="0" min="0">
        <button style="background: #08810d;color:#ffffff; padding: 2px 6px;" type="button" id="add_points_button" class="button"><?php _e('(+)Add Points', 'add-remove-points'); ?></button>
        <br><br>
        <label for="points_to_subtract"><?php _e('Points Amount to Subtract:', 'add-remove-points'); ?></label>
        <input style="margin-bottom: 8px;" type="number" id="points_to_subtract" name="points_to_subtract" value="0" min="0">
        <button style="background: #e72314; color:#ffffff; padding: 2px 6px;" type="button" id="subtract_points_button" class="button"><?php _e('(-)Subtract Points', 'add-remove-points'); ?></button>
    </div>
<?php
}

// Function to handle point addition/subtraction AJAX request
// Function to handle point addition/subtraction AJAX request
function handle_points_change()
{
    check_ajax_referer('add_remove_points_nonce', 'security');

    if (isset($_POST['points_to_add'], $_POST['points_to_subtract'], $_POST['order_id'])) {
        $points_to_add = intval($_POST['points_to_add']);
        $points_to_subtract = intval($_POST['points_to_subtract']);
        $order_id = intval($_POST['order_id']);
        

        $points_changed = $points_to_add - $points_to_subtract;

        if ($points_changed !== 0) {
            // Get the user ID associated with the order
            $user_id = get_post_meta($order_id, '_customer_user', true);

            if ($user_id) {
                // Update user meta with the new points

                $current_points = get_user_meta($user_id, 'customer_points', true);
                // $updated_points = max(0, $current_points + $points_changed);
                $updated_points = ($current_points !== '') ? max(0, $current_points + $points_changed) : max(0, $points_changed);
                update_user_meta($user_id, 'customer_points', $updated_points);

                // Get the current user's display name or login
                $current_user = wp_get_current_user();
                $current_user_id = $current_user->ID;
                $user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

                // Insert a row in the database with the points change
                global $wpdb;
                $table_name = $wpdb->prefix . 'custom_points_table';

                $insert_data = array(
                    'used_id' => $user_id,
                    'mvt_date' => current_time('mysql'),
                    'points_moved' => $points_changed,
                    'new_total' => $updated_points,
                    'commentar' => ($points_changed > 0 ? 'Added ' : 'Subtracted ') . abs($points_changed) . ' points by Customer Representative ',
                    'order_id' => $order_id,
                    'given_by' => $current_user_id,
                );

                $wpdb->insert($table_name, $insert_data);

                //Generate order note after adding or subtracting the points
                $order = wc_get_order($order_id);
                if ($order) {
                    $added_or_deducted_text = ($points_changed > 0) ? 'Added' : 'Deducted';
                    $add_or_deduct_amount = abs($points_changed);

                    $order->add_order_note(
                        sprintf(
                            __('Points %s: %s', 'woocommerce'),
                            $added_or_deducted_text,
                            $add_or_deduct_amount
                        ),
                        true, // Is Customer Note (Show the Email Note Customer Also)
                        true // Add Username for Admin Log
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
}

add_action('wp_ajax_handle_points_change', 'handle_points_change');
add_action('wp_ajax_nopriv_handle_points_change', 'handle_points_change');
