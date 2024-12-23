<?php

// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu page
function custom_user_list_menu()
{
    add_menu_page(
        'User Points',
        'User Points',
        'edit_shop_orders',
        'custom-user-points-list',
        'custom_user_list_points_page',
        // Add point or gift dashicon
        'dashicons-awards',
        6
    );
}

add_action('admin_menu', 'custom_user_list_menu');

// Callback function to display user list
function custom_user_list_points_page()
{
?>
    <div class="wrap">
        <h2>User List</h2>

        <form method="post">
            <?php
            session_start();
            // Create an instance of the WP_List_Table class
            $user_list_table = new Custom_User_List_Table();
            $user_list_table->prepare_items();

            // Display the user list table
            $user_list_table->search_box('Search Users', 'user');

            // Display the user list table
            $user_list_table->display();
            ?>

            <div class="add-subtract-points-form">
                <label for="points_value">
                    Add/Subtract Points
                    <i class="fa-solid fa-coins"></i>
                </label>
                <input type="number" id="points_value" name="points_value" title="Please use a negative value if you want to subtract points" />
                <input type="submit" name="apply_points" class="button button-primary" value="Apply"></br>
            </div>

            <?php
            $error_message = '';
            $success_message = '';
            // Process the form submission
            if (isset($_POST['apply_points'])) {
                $selected_users = isset($_POST['user']) ? $_POST['user'] : array();
                $points_value = isset($_POST['points_value']) ? intval($_POST['points_value']) : 0;

                // Check if at least one user is selected
                if (empty($selected_users)) {
                    $error_message = 'Please select at least one user.';
                } else {
                    foreach ($selected_users as $user_id) {
                        $current_points = get_user_meta($user_id, 'customer_points', true);

                        $new_points = $current_points + $points_value;
                        $success_message = ($points_value > 0 ? 'Added ' : 'Subtracted ') . abs($points_value) . ' points successfully. ';
                        update_user_meta($user_id, 'customer_points', $new_points);

                        // Insert a row in the database with the points change
                        $order_id = intval($_POST['order_id']);
                        // Get the current user's display name or login
                        $current_user = wp_get_current_user();
                        $current_user_id = $current_user->ID;
                        $user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'custom_points_table';

                        $insert_data = array(
                            'used_id' => $user_id,
                            'mvt_date' => current_time('mysql'),
                            'points_moved' => $points_value,
                            'new_total' => $new_points,
                            'commentar' => ($points_value > 0 ? 'Added ' : 'Subtracted ') . abs($points_value) . ' points by Customer Representative ',
                            'order_id' => $order_id,
                            'given_by' => $current_user_id,
                        );

                        $wpdb->insert($table_name, $insert_data);
                    }
                    // Store success message in session
                    $_SESSION['success_message'] = $success_message;

                    // Redirect to the same page after processing the form
                    $current_page_url = add_query_arg(array());
                    wp_redirect($current_page_url);
                    exit;
                }
            }
            // Display error message
            if (!empty($error_message)) {
                echo '<p class="error-msg">' . esc_html($error_message) . '</p>';
            }
            // Display success message after redirect
            if (!empty($_SESSION['success_message'])) {
                echo '<p class ="success-msg">' . esc_html($_SESSION['success_message']) . '</p>';
                // Clear the success message from the session to avoid displaying it again on subsequent page loads
                unset($_SESSION['success_message']);
            }
            ?>

        </form>
    </div>

    <div class="loader"></div>
    <div class="history-popup" id="points-history-popup">
        <!-- Popup content goes here -->
        <div class="history-popup-content">
            <span class="close" onclick="closePopup()">&times;</span>
            <h3 class="user-email">Point History of <span id="user-email-placeholder"></span></h3>
            <div id="points-history-content">
            </div>
        </div>
    </div>
    <script>
        // Function to close the modal
        function closePopup() {
            jQuery('#points-history-popup').hide();
        }

        jQuery.noConflict();
        jQuery(document).ready(function($) {
            function formatDate(dateString) {
                var options = {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                var formattedDate = new Date(dateString).toLocaleDateString('en-US', options);
                return formattedDate;
            }

            // Click event for the points history icon
            $('.show-history').on('click', function() {

                // Show loader
                $('.loader').show();
                $('body').css('overflow', 'hidden');
                var userId = $(this).data('user-id');

                // Use AJAX to fetch the user email
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_user_email',
                        user_id: userId,
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update the user email placeholder
                            $('#user-email-placeholder').text(response.data.email);
                        } else {
                            console.error('Error fetching user email:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    },
                    complete: function() {
                        $('.loader').hide();
                        $('body').css('overflow', 'auto');
                    }
                });

                // Use AJAX to fetch and display points history
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_user_points_history',
                        user_id: userId,
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (response.data && response.data.length > 0) {
                                // Create a table header
                                var tableHtml = '<table class="table table-striped">';
                                tableHtml += '<thead>';
                                tableHtml += '<tr>';
                                tableHtml += '<th>Comment</th>';
                                tableHtml += '<th>Points Moved</th>';
                                tableHtml += '<th>New Total</th>';
                                tableHtml += '<th>Date</th>';
                                tableHtml += '</tr>';
                                tableHtml += '</thead>';

                                // Create a table body
                                tableHtml += '<tbody>';

                                // Iterate over each data item and create a table row
                                $.each(response.data, function(index, item) {
                                    tableHtml += '<tr>';
                                    tableHtml += '<td>' + item.commentar + '</td>';
                                    tableHtml += '<td>' + item.points_moved + '</td>';
                                    tableHtml += '<td>' + item.new_total + '</td>';
                                    tableHtml += '<td>' + formatDate(item.mvt_date) + '</td>';
                                    tableHtml += '</tr>';
                                });

                                tableHtml += '</tbody>';
                                tableHtml += '</table>';

                                // Display the table in the modal
                                $('#points-history-content').html(tableHtml);
                            } else {
                                // Display a message when there's no data
                                $('#points-history-content').html('<p style="padding:5px;">No points history found.</p>');
                            }
                            openPopup();
                        } else {
                            console.error('Error fetching points history:', response);
                        }
                    },
                });
            });

            function openPopup() {
                $('#points-history-popup').show();
            }
        });
    </script>
    <style>
        .add-subtract-points-form {
            border: 1px solid #585656;
            padding: 10px;
            display: inline-block;
            background: #898989;
            font-weight: 500;
            margin-bottom: 10px;
            color: #fdfdfd;
        }

        .error-msg {
            background: #ff5722;
            color: #ffffff;
            border-left: 6px solid #b53006;
            padding: 15px;
            font-size: 16px;
        }

        .success-msg {
            background: #4caf50;
            color: #ffffff;
            border-left: 6px solid #008c3e;
            padding: 15px;
            font-size: 16px;
        }

        .show-history {
            color: #2196f3;
            font-size: 16px;
            cursor: pointer;
        }

        /* Modal styles */
        .history-popup {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0, 0, 0);
            background-color: rgba(0, 0, 0, 0.4);
        }

        .history-popup-content {
            background-color: #fefefe;
            margin: 15% auto;
            border: 1px solid #888;
            width: 40%;
            text-align: left;
            min-height: 140px;
        }

        .history-popup-content table {
            width: 100%;
        }

        .history-popup-content table th,
        td {
            padding: 1px 5px;
        }

        .history-popup-content h3 {
            margin: 0;
            padding: 5px;
            background: #dedede;
            font-size: 14px;
            font-weight: 700;
        }

        .history-popup-content table tbody tr:nth-child(odd) {
            background-color: #f2f2f2;
        }

        .close {
            color: #fff;
            float: right;
            font-size: 26px;
            font-weight: bold;
            background: #000000;
            padding: 5px;
        }

        .close:hover,
        .close:focus {
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            background: #484747;
        }

        .loader {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: auto;
            display: none;
            position: fixed;
            top: 40%;
            left: 50%;
            z-index: 9999;
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
<?php
}

class Custom_User_List_Table extends WP_List_Table
{
    function __construct()
    {
        parent::__construct(array(
            'singular' => 'user',
            'plural'   => 'users',
            'ajax'     => false,
        ));
    }

    function get_columns()
    {
        return array(
            'cb'             => '<input type="checkbox" />',
            'user_email'     => 'Email',
            'customer_points' => 'User"s Total Points',
            'points-history' => 'Points History',
        );
    }

    function prepare_items()
    {
        $per_page = 30;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $paged = $this->get_pagenum();

        $args = array(
            'number' => $per_page,
            'paged'  => $paged,

            'meta_key' => 'customer_points', // Replace with your custom meta key
            'orderby' => 'meta_value_num',   // Sort by meta value as a number
            'order'   => 'DESC',             // Choose ASC or DESC as per your requirement

        );

        // Handle search
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $args['search'] = '*' . esc_attr($_REQUEST['s']) . '*';
        }

        $users = new WP_User_Query($args);
        $total_items = $users->get_total();

        $data = array();
        foreach ($users->get_results() as $user) {

            $display_name = !empty($user->display_name) ? $user->display_name : '(No Name)';
    
            // Get the edit user link
            $user_edit_link = get_edit_user_link($user->ID);

            $data[] = array(
                'ID'             => $user->ID,
                'user_email'     => $user->user_email,
                'user_edit_link' => $user_edit_link, // Add the profile edit link
                'display_name'   => $display_name,  // Add display name
                'customer_points' => get_user_meta($user->ID, 'customer_points', true),
            );

        }

        $this->items = $data;

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ));
    }

    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'user_email':
            $email_link = '<a href="' . esc_url($item['user_edit_link']) . '" target="_blank">' . esc_html($item[$column_name]) . '</a>';
            // Combine the email link with the display name
            return $email_link . ' (' . esc_html($item['display_name']) . ')';
            case 'customer_points':
                return $item[$column_name];
            case 'points-history':
                $user_id = $item['ID'];
                $icon_html = '<i class="fa-solid fa-clock-rotate-left show-history" data-user-id="' . esc_attr($user_id) . '" title="Points History"></i>';
                return $icon_html;
            default:
                return print_r($item, true);
        }
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="user[]" value="%s" />',
            $item['ID']
        );
    }

    function get_sortable_columns()
    {
        return array(
            'user_email'     => array('user_email', false),
            // 'customer_points' => array('customer_points', false),
        );
    }
}

// AJAX function to get user points history
add_action('wp_ajax_get_user_points_history', 'get_user_points_history');
function get_user_points_history()
{
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    // error_log('User ID: ' . $user_id); // Check the user ID

    if ($user_id > 0) {
        $points_history = get_user_points_history_function($user_id);

        if ($points_history !== false) {
            wp_send_json_success($points_history);
        } else {
            wp_send_json_error(array('message' => 'No points history found.'));
        }
    } else {
        wp_send_json_error(array('message' => 'User ID not found.'));
    }
    // error_log('Function called');
}



// Modify the following function according to how you retrieve user points history
function get_user_points_history_function($user_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_points_table';

    // Get points data for the specific user
    $points_data = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT points_moved, new_total, commentar, mvt_date, given_by 
            FROM $table_name 
            WHERE used_id = %d 
            ORDER BY mvt_date DESC, id DESC 
            LIMIT 15",
            $user_id
        ),
        ARRAY_A  // Add this line to ensure that the result is an associative array
    );

    foreach ($points_data as $key => $row) {
        if (is_serialized($row['commentar'])) {
            // Unserialize the commentar data
            $unserialized_commentar = maybe_unserialize($row['commentar']);

            // Check if the unserialization was successful and it's an array with at least 3 elements
            if (is_array($unserialized_commentar) && count($unserialized_commentar) >= 3) {
                // Modify the commentar data
                $modified_commentar = sprintf(
                    $unserialized_commentar[0],
                    $unserialized_commentar[1],
                    $unserialized_commentar[2]
                );

                // Update the commentar in the original array
                $points_data[$key]['commentar'] = $modified_commentar;
            }
        }
    }

    // Check if there is data
    if ($points_data) {
        return $points_data;
    } else {
        return array(); // Return an empty array if no data is found
    }
}

add_action('wp_ajax_get_user_email', 'get_user_email');
function get_user_email()
{
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($user_id > 0) {
        $user_data = get_userdata($user_id);

        if ($user_data) {
            wp_send_json_success(array('email' => $user_data->user_email));
        } else {
            wp_send_json_error(array('message' => 'User not found.'));
        }
    } else {
        wp_send_json_error(array('message' => 'User ID not found.'));
    }
}


// Enqueue styles for the admin area
function enqueue_plugin_styles_admin()
{
    // Enqueue Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1', 'all');
}

add_action('admin_enqueue_scripts', 'enqueue_plugin_styles_admin');



function enqueue_plugin_scripts()
{
    wp_enqueue_script('jquery');
}

add_action('admin_enqueue_scripts', 'enqueue_plugin_scripts');
