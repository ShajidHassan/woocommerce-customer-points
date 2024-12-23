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
            "SELECT points_moved, new_total, commentar, points_reason, mvt_date, given_by FROM $table_name WHERE used_id = %d ORDER BY mvt_date DESC, id DESC",
            $user_id
        )
    );

    if ($points_data) {
        echo '<p><strong>' . __('Current Points of the', 'display-order-points') . ' ' . esc_html($customer_email) . ':</strong> <b style="color: green;font-size:16px;">' . esc_html($current_points) . '</b></p>';
?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><b>
                            <?php esc_html_e('Comments', 'display-order-points'); ?>
                        </b></th>
                    <th><b>
                            <?php esc_html_e('Points Moved', 'display-order-points'); ?>
                        </b></th>
                    <th><b>
                            <?php esc_html_e('New Total', 'display-order-points'); ?>
                        </b></th>
                    <th><b>
                            <?php esc_html_e('Points Refund Type', 'display-order-points'); ?>
                        </b></th>
                    <th><b>
                            <?php esc_html_e('Given By', 'display-order-points'); ?>
                        </b></th>
                    <th><b>
                            <?php esc_html_e('Date', 'display-order-points'); ?>
                        </b></th>
                </tr>
            </thead>


            <tbody>
                <?php
                foreach ($points_data as $data) {
                    $points_moved = $data->points_moved;
                    $new_total = $data->new_total;
                    $commentar = $data->commentar;
                    $points_reason = $data->points_reason;

                    // if $commentar is serialized, unserialize it
                    if (is_serialized($commentar)) {
                        $commentar = maybe_unserialize($commentar);
                        // If the unserialized data is an array and has at least 3 elements
                        if (is_array($commentar) && count($commentar) >= 3) {
                            // Use sprintf to replace the placeholders in the first element with the second and third elements
                            $commentar = sprintf($commentar[0], $commentar[1], $commentar[2]);
                        }
                    }

                    $mvt_date = $data->mvt_date;

                    // Fetching user data using given_by ID
                    $given_by_user = get_userdata($data->given_by);
                    $given_by_display_name = ($given_by_user) ? $given_by_user->display_name : esc_html__('Unknown User', 'display-order-points');

                    // Check if the user has the 'customer' role
                    $user_roles = isset($given_by_user->roles) ? $given_by_user->roles : array();
                    $is_customer = in_array('customer', $user_roles);
                    $display_name_style = $is_customer ? '' : 'color:#ff5722;font-weight:500;';
                ?>
                    <tr>
                        <td>
                            <?php echo esc_html($commentar); ?>
                        </td>
                        <td>
                            <?php echo esc_html($points_moved); ?>
                        </td>
                        <td>
                            <?php echo esc_html($new_total); ?>
                        </td>
                        <td>
                            <?php echo esc_html($points_reason); ?>
                        </td>
                        <td style="<?php echo esc_attr($display_name_style); ?>">
                            <?php echo esc_html($given_by_display_name); ?>
                        </td>
                        <td>
                            <?php echo esc_html($mvt_date); ?>
                        </td>
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



// Shortcode to show the point history on the user page
function display_points_history_on_user_page()
{
    ob_start();
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $current_points = get_user_meta($user_id, 'customer_points', true);

        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_points_table';


        // Calculate total referral points for the current user
        $total_referral_points = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(points_moved) 
                FROM $table_name 
                WHERE used_id = %d AND origin = %s",
                $user_id,
                'referral'
            )
        );

        echo '<h3><strong>Point History</strong></h3>';
        // Display total referral points on the right
        echo '<div style="text-align: left; margin-bottom: 10px;">';
        echo '<h5><strong>Total Referral Points:</strong> ' . esc_html($total_referral_points ?: 0) . '</h5>';
        echo '</div>';

        // Get points data for the current user
        $points_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT points_moved, new_total, commentar, mvt_date, given_by 
                FROM $table_name 
                WHERE used_id = %d 
                ORDER BY mvt_date DESC, id DESC 
                LIMIT 15",
                $user_id
            )
        );

        if ($points_data) {
        ?>
            <div style="overflow-x: auto;">
                <table class="table table-striped points-history-table">
                    <thead>
                        <tr>
                            <th><b><?php esc_html_e('Comments', 'display-order-points'); ?></b></th>
                            <th><b><?php esc_html_e('Points Added/Subtracted', 'display-order-points'); ?></b></th>
                            <th><b><?php esc_html_e('New Total', 'display-order-points'); ?></b></th>
                            <th><b><?php esc_html_e('Date', 'display-order-points'); ?></b></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($points_data as $data) {
                            $points_moved = $data->points_moved;
                            $points_color = ($points_moved > 0) ? 'green' : 'red';
                            $new_total = $data->new_total;
                            $commentar = $data->commentar;

                            // if $commentar is serialized, unserialize it
                            if (is_serialized($commentar)) {
                                $commentar = maybe_unserialize($commentar);
                                // If the unserialized data is an array and has at least 3 elements
                                if (is_array($commentar) && count($commentar) >= 3) {
                                    // Use sprintf to replace the placeholders in the first element with the second and third elements
                                    $commentar = sprintf($commentar[0], $commentar[1], $commentar[2]);
                                }
                            }

                            $mvt_date = $data->mvt_date;
                        ?>
                            <tr>
                                <td><?php echo esc_html($commentar); ?></td>
                                <td style="color: <?php echo $points_color; ?>;">
                                    <?php echo esc_html($points_moved); ?>
                                </td>
                                <td><?php echo esc_html($new_total); ?></td>
                                <td><?php echo esc_html(date('j F, Y', strtotime($mvt_date))); ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <style>
                .points-history-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0;
                    font-size: 14px;
                    text-align: left;
                }

                .points-history-table th,
                .points-history-table td {
                    padding: 10px 10px;
                    border: 1px solid #ddd;
                }

                .points-history-table tr:nth-child(even) {
                    background-color: #f9f9f9;
                }

                .points-history-table th {
                    background-color: #e0dede;
                    font-weight: 600;
                    font-size: 16px;
                }
            </style>
<?php
        } else {
            echo esc_html__('There is no points history. Please place an order to get the points.', 'display-order-points');
        }
    } else {
        echo esc_html__('Please log in to view your points history.', 'display-order-points');
    }

    return ob_get_clean();
}
add_shortcode('wcp_display_points_history', 'display_points_history_on_user_page');
