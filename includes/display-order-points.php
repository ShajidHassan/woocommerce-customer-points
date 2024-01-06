<?php

// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}


// Add meta box for displaying order points details
add_action('add_meta_boxes', 'add_order_points_meta_box');

function add_order_points_meta_box()
{
    add_meta_box(
        'order_points_meta_box',
        __('Order Points Details', 'display-order-points'),
        'display_order_points_meta_box_content',
        'shop_order',
        'normal',
        'default'
    );
}

function display_order_points_meta_box_content($post)
{
    $order = wc_get_order($post->ID);
    $user_id = $order->get_user_id();
    $customer_id = $order->get_customer_id();
    $customer = new WC_Customer($customer_id);
    $customer_email = $customer->get_email();
    $current_points = get_user_meta($user_id, 'customer_points', true);

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_points_table';

    // Get points data for the current user
    $points_data = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT points_moved, new_total, commentar,mvt_date FROM $table_name WHERE used_id = %d ORDER BY mvt_date DESC, id DESC",
            $user_id
        )
    );

    if ($points_data) {
        echo '<p><strong>' . __('Current Points of the', 'display-order-points') . ' ' . esc_html($customer_email) . ':</strong> <b style="color: green;font-size:18px;">' . esc_html($current_points) . '</b></p>';
?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><b><?php esc_html_e('Comments', 'display-order-points'); ?></b></th>
                    <th><b><?php esc_html_e('Points Moved', 'display-order-points'); ?></b></th>
                    <th><b><?php esc_html_e('New Total', 'display-order-points'); ?></b></th>
                    <th><b><?php esc_html_e('Date', 'display-order-points'); ?></b></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($points_data as $data) {
                    $points_moved = $data->points_moved;
                    $new_total = $data->new_total;
                    $commentar = $data->commentar;
                    $mvt_date = $data->mvt_date;
                ?>
                    <tr>
                        <td><?php echo esc_html($commentar); ?></td>
                        <td><?php echo esc_html($points_moved); ?></td>
                        <td><?php echo esc_html($new_total); ?></td>
                        <td><?php echo esc_html($mvt_date); ?></td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
<?php
    } else {
        echo esc_html__('No points data available for this user.', 'display-order-points');
    }
}
