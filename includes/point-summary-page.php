<?php

// Ensure the file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Enqyeue Chart.js on the specific page
function enqueue_chartjs_on_specific_page()
{
    // Check if we are on the specific page (adjust the page slug accordingly)
    if (isset($_GET['page']) && $_GET['page'] === 'point-summary') {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }
}
add_action('admin_enqueue_scripts', 'enqueue_chartjs_on_specific_page');


// Enqueue Flatpickr in your theme's functions.php
function enqueue_flatpickr_assets()
{
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.13', true);
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
}
add_action('wp_enqueue_scripts', 'enqueue_flatpickr_assets');


// Add admin sub-menu page
add_action('admin_menu', 'add_point_summary_submenu');

function add_point_summary_submenu()
{
    add_submenu_page(
        'custom-user-points-list',
        'Point Summary',
        'Point Summary',
        'edit_shop_orders',
        'point-summary',
        'render_point_summary_page'
    );
}


// Callback function to display user list
function render_point_summary_page()
{
?>
    <div class="wrap">
        <h2>Point Summary</h2>
        <?php


        // Set default values for start and end dates
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01'); // First day of the current month
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d'); // Current date
        ?>
        <div class="point-summary">
            <form method="post" action="">
                <table class="form-table">
                    <tr style="margin: 0;">
                        <th style="width: 80px;vertical-align:middle" scope="row">Date Range</th>
                        <td>
                            <!-- Single input for date range -->
                            <input type="text" id="date-range-picker" name="date_range" placeholder="Select date range" required />
                            <input type="submit" name="filter_points" value="Filter" class="button-primary" />
                            <!-- Hidden inputs to capture start and end dates from the date range picker -->
                            <input type="hidden" name="start_date" value="<?php echo esc_attr($start_date); ?>" />
                            <input type="hidden" name="end_date" value="<?php echo esc_attr($end_date); ?>" />
                        </td>
                    </tr>
                </table>
            </form>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Initialize Flatpickr for the date range input
                    flatpickr("#date-range-picker", {
                        mode: "range",
                        dateFormat: "Y-m-d",
                        altInput: true,
                        altFormat: "M j, Y",
                        defaultDate: ["<?php echo esc_js($start_date); ?>", "<?php echo esc_js($end_date); ?>"],
                        onChange: function(selectedDates, dateStr, instance) {
                            // Ensure there are two dates selected
                            if (selectedDates.length === 2) {
                                // Get the start and end date from selectedDates
                                const startDate = new Date(selectedDates[0]);
                                const endDate = new Date(selectedDates[1]);

                                // Add 1 day to the start and end dates
                                startDate.setDate(startDate.getDate() + 1);
                                endDate.setDate(endDate.getDate() + 1);

                                // Format dates as YYYY-MM-DD
                                const formattedStartDate = startDate.toISOString().split('T')[0];
                                const formattedEndDate = endDate.toISOString().split('T')[0];

                                // Set the hidden input values
                                document.querySelector('input[name="start_date"]').value = formattedStartDate;
                                document.querySelector('input[name="end_date"]').value = formattedEndDate;
                            }
                        }
                    });
                });
            </script>


            <p>
                Showing results for date range:
                <?php
                // Create DateTime objects from the start and end dates
                $formatted_start_date = (new DateTime($start_date))->format('M j, Y');
                $formatted_end_date = (new DateTime($end_date))->format('M j, Y');

                // Display the formatted date range
                echo esc_html($formatted_start_date) . ' to ' . esc_html($formatted_end_date);
                ?>
            </p>

            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'custom_points_table';
            // Calculate total available points for all time
            $total_available_points = $wpdb->get_var("
            SELECT SUM(new_total)
            FROM $table_name
            ");
            ?>

            <h2>Total Available Points for Cashout: <span style="color:blue">
                    ¥<?php echo number_format($total_available_points); ?>
                </span>
            </h2>

        </div>
        <?php

        // If no filter is applied, fetch data for the default date range
        if (!isset($_POST['filter'])) {
            display_point_summary($start_date, $end_date);
        }
        // Check if the form has been submitted with a specific date range
        if (isset($_POST['filter'])) {
            $start_date = sanitize_text_field($_POST['start_date']);
            $end_date = sanitize_text_field($_POST['end_date']);
            display_point_summary($start_date, $end_date);
        }
        ?>
    </div>
<?php
}


function display_point_summary($start_date, $end_date)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_points_table';

    // Set default dates if not provided
    if (empty($start_date)) {
        $start_date = date('Y-m-01'); // First date of the current month
    } else {
        $start_date = sanitize_text_field($start_date);
        $start_date = date('Y-m-d', strtotime($start_date));
    }

    if (empty($end_date)) {
        $end_date = date('Y-m-d'); // Today's date
    } else {
        $end_date = sanitize_text_field($end_date);
        $end_date = date('Y-m-d', strtotime($end_date));
    }

    $end_date_plus_one = date('Y-m-d', strtotime($end_date . ' +1 day'));

    // Points gained by customers (earned from orders)
    $points_gained = $wpdb->get_results($wpdb->prepare(
        "SELECT u.display_name AS name, c.points_moved AS points, c.mvt_date AS move_date, c.order_id AS order_id
     FROM $table_name c
     INNER JOIN {$wpdb->users} u ON c.used_id = u.ID
     WHERE c.mvt_date >= %s AND c.mvt_date < %s
     AND c.points_moved > 0
     AND c.commentar LIKE 'Get % points from the order %'
     ORDER BY c.mvt_date DESC",
        $start_date,
        $end_date_plus_one
    ));

    // Points given by customer representatives (manually added)
    $points_given = $wpdb->get_results($wpdb->prepare(
        "SELECT u.display_name AS name, c.points_reason AS points_reason, c.points_moved AS points, c.mvt_date AS move_date, c.order_id AS order_id
     FROM $table_name c
     INNER JOIN {$wpdb->users} u ON c.given_by = u.ID
     WHERE c.mvt_date >= %s AND c.mvt_date < %s
     AND c.points_moved > 0
     AND c.commentar LIKE 'Added % points by Customer Representative%'
     ORDER BY c.mvt_date DESC",
        $start_date,
        $end_date_plus_one
    ));

    // Points used by customers
    $points_used = $wpdb->get_results($wpdb->prepare(
        "SELECT u.display_name AS name, c.points_moved AS points, c.mvt_date AS move_date, c.order_id AS order_id
     FROM $table_name c
     INNER JOIN {$wpdb->users} u ON c.used_id = u.ID
     WHERE c.mvt_date >= %s AND c.mvt_date < %s
     AND c.points_moved < 0
     ORDER BY c.mvt_date DESC",
        $start_date,
        $end_date_plus_one
    ));


    $total_coupons_used = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(CAST(om.meta_value AS DECIMAL(10,2))) 
         FROM {$wpdb->prefix}woocommerce_order_items oi
         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta om ON oi.order_item_id = om.order_item_id
         INNER JOIN {$wpdb->prefix}posts p ON oi.order_id = p.ID
         WHERE oi.order_item_type = 'coupon'
         AND om.meta_key = 'discount_amount'
         AND p.post_status IN ('wc-completed','wc-processing','wc-on-hold')
         AND p.post_date BETWEEN %s AND %s",
        $start_date,
        $end_date_plus_one
    ));

    // Handle cases when there's no coupon data available
    $total_coupons_used = $total_coupons_used ? $total_coupons_used : 0;

    // Calculate totals correctly
    $total_gained = array_sum(array_column($points_gained, 'points'));
    $total_given = array_sum(array_column($points_given, 'points'));
    $total_used = array_sum(array_column($points_used, 'points'));

    // Prepare data for the chart
    $chart_data = [
        'gained' => $total_gained,
        'given' => $total_given,
        'used' => $total_used,
        'coupons' => $total_coupons_used,
    ];

    // Fetch the top customers by points gained during the selected month
    $top_customers = $wpdb->get_results($wpdb->prepare(
        "SELECT u.display_name AS name, SUM(c.points_moved) AS total_points, c.used_id
         FROM $table_name c
         INNER JOIN {$wpdb->users} u ON c.used_id = u.ID
         WHERE c.mvt_date BETWEEN %s AND %s
         AND c.points_moved > 0
         AND c.commentar LIKE 'Get % points from the order %'
         GROUP BY c.used_id
         ORDER BY total_points DESC
         LIMIT 5",
        $start_date,
        $end_date_plus_one
    ));

    // Prepare top customers list
    $top_customers_list = '';
    if (!empty($top_customers)) {
        foreach ($top_customers as $customer) {
            $top_customers_list .= '<li>' . esc_html($customer->name) . ' - ' . esc_html(number_format($customer->total_points)) . ' Points</li>';
        }
    } else {
        // Provide more detailed feedback if no data is found
        $top_customers_list = '<li>No customers found for the selected month.</li>';
    }

    // Convert PHP array to JavaScript
    $chart_data_json = json_encode($chart_data);
?>
    <div class="chart-container">
        <div class="chart">
            <canvas id="pointsChart"></canvas>
        </div>
        <div class="chart-values">
            <h3>Points Earned from Orders: ¥<?php echo esc_html(number_format($chart_data['gained'])); ?></h3>
            <h3>Points Given by Customer Representative: ¥<?php echo esc_html(number_format($chart_data['given'])); ?></h3>
            <h3>Points Used: ¥<?php echo esc_html(number_format($chart_data['used'])); ?></h3>
            <h3>Total Coupons Amount: ¥ <?php echo esc_html(number_format($total_coupons_used)); ?> </h3>

            <!-- Display Top Customers -->
            <h4 style="margin-bottom: 0;">
                Top Customers of the
                <?php
                // Create DateTime objects from the start and end dates
                $formatted_start_date = (new DateTime($start_date))->format('M j, Y');
                $formatted_end_date = (new DateTime($end_date))->format('M j, Y');

                // Display the formatted date range
                echo esc_html($formatted_start_date) . ' to ' . esc_html($formatted_end_date);
                ?>
            </h4>
            <ul>
                <?php echo $top_customers_list; ?>
            </ul>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('pointsChart').getContext('2d');
            const chartData = <?php echo $chart_data_json; ?>;

            if (ctx && chartData) {
                const myChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Points Gained', 'Points Given', 'Points Used'],
                        datasets: [{
                            label: 'Points Overview',
                            data: [chartData.gained, chartData.given, chartData.used],
                            backgroundColor: ['#3f51b5', '#FFC107', '#009688'],
                            borderColor: ['#388E3C', '#FFA000', '#009688'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    font: {
                                        size: 14,
                                        weight: '500'
                                    }
                                }
                            },
                            tooltip: {
                                bodyFont: {
                                    size: 14,
                                    weight: '500'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Points Summary',
                                font: {
                                    size: 16,
                                    weight: '600'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 14,
                                        weight: '500'
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 14,
                                        weight: '500'
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                console.error('Chart data or context not found.');
            }
        });
    </script>

    <?php
    // Display the point summary data in an accordion
    ?>
    <div class="accordion">
        <h3 class="accordion-header">Points Earned from Orders <span class="accordion-icon">+</span></h3>
        <div class="accordion-content">
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Customer Name</th>
                        <th>Order ID</th>
                        <th>Points</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php $serial_number = 1; ?>
                    <?php foreach ($points_gained as $point): ?>
                        <tr>
                            <td style="width: 50px;"><?php echo esc_html($serial_number++); ?></td>
                            <td><?php echo esc_html($point->name); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $point->order_id . '&action=edit')); ?>" target="_blank">
                                    <?php echo esc_html($point->order_id); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($point->points); ?></td>
                            <td><?php echo esc_html(date('Y-m-d', strtotime($point->move_date))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th style="font-weight: 600; font-size:16px;color:blue;" colspan="3">Total</th>
                        <th style="font-weight: 600; font-size:16px;color:blue;" colspan="2"><?php echo esc_html($total_gained); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <h3 class="accordion-header">Points Given by Customer Reprsentative <span class="accordion-icon">+</span></h3>
        <div class="accordion-content">
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Representative Name</th>
                        <th>Order ID</th>
                        <th>Points</th>
                        <th>Reason Type</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php $serial_number = 1; ?>
                    <?php foreach ($points_given as $point): ?>
                        <tr>
                            <td style="width: 50px;"><?php echo esc_html($serial_number++); ?></td>
                            <td><?php echo esc_html($point->name); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $point->order_id . '&action=edit')); ?>" target="_blank">
                                    <?php echo esc_html($point->order_id); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($point->points); ?></td>
                            <td><?php echo esc_html($point->points_reason); ?></td>
                            <td><?php echo esc_html(date('Y-m-d', strtotime($point->move_date))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th style="font-weight: 600; font-size:16px;color:blue;" colspan="3">Total</th>
                        <th style="font-weight: 600; font-size:16px;color:blue;" colspan="2"><?php echo esc_html($total_given); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <h3 class="accordion-header">Points Used in Order <span class="accordion-icon">+</span></h3>
        <div class="accordion-content">
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Customer Name</th>
                        <th>Order ID</th>
                        <th>Points</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php $serial_number = 1; ?>
                    <?php foreach ($points_used as $point): ?>
                        <tr>
                            <td><?php echo esc_html($serial_number++); ?></td>
                            <td><?php echo esc_html($point->name); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $point->order_id . '&action=edit')); ?>" target="_blank">
                                    <?php echo esc_html($point->order_id); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($point->points); ?></td>
                            <td><?php echo esc_html(date('Y-m-d', strtotime($point->move_date))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th style="font-weight: 600; font-size:16px;color:blue;" colspan="3">Total</th>
                        <th style="font-weight: 600; font-size:16px;color:blue;" colspan="2"><?php echo esc_html($total_used); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <style>
        .point-summary {
            background: #fff;
            padding: 15px;
            box-shadow: 0 0 8px -2px rgba(0, 0, 0, .3);
            margin-bottom: 20px;
            border-left: 5px solid #007cba;
        }

        .point-summary p {
            margin: 10px 0;
            font-size: 15px;
            color: #228126;
        }

        .point-summary h2 {
            margin-bottom: 15px;
            font-weight: bold;
            margin-top: 10px;
        }

        .point-summary .button-primary {
            background: #007cba;
            height: 45px;
            width: 100px;
            font-size: 16px;
        }

        .point-summary .form-control {
            background: #fff;
            width: 200px;
            height: 45px;
        }

        .accordion-header {
            cursor: pointer;
            background-color: #fff;
            padding: 10px 15px;
            border: 1px solid #ddd;
            margin-top: 10px;
            margin-bottom: 0;
            font-weight: 600;
        }

        .accordion-content {
            display: none;
            padding: 15px;
            border: 1px solid #ddd;
            border-top: none;
            background-color: #fff;
        }

        .accordion-content table th {
            font-weight: 600;
            color: #000;
        }

        .accordion-icon {
            position: absolute;
            right: 40px;
            font-size: 20px;
            font-weight: bold;
            transition: transform 0.2s ease;
        }

        .accordion-header.active .accordion-icon {
            transform: rotate(180deg);
        }

        .chart-container {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding: 15px;
            background: #fff;
            color: #000000;
            box-shadow: 0 0 8px -2px rgba(0, 0, 0, .3);
        }

        .chart {
            flex: 1;
            min-width: 50%;
            height: 400px;
        }

        .chart-values {
            flex: 1;
            min-width: 30%;
            max-width: 30%;
            margin-top: 30px;
            margin-left: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
        }

        .chart-values h3 {
            margin: 10px 0;
            font-weight: 700;
        }

        .chart-values ul {
            list-style-type: none;
            padding: 0;
            margin: 5px 0;
        }

        .chart-values li {
            margin-bottom: 5px;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            $('.accordion-header').click(function() {
                $(this).next('.accordion-content').slideToggle();
                $(this).toggleClass('active');

                const icon = $(this).find('.accordion-icon');
                icon.text(icon.text() === '+' ? '−' : '+');
            });
        });
    </script>


<?php
}
