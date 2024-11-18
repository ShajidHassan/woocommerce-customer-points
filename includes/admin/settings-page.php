<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add a submenu page under your plugin's menu
function woocommerce_customer_points_create_settings_menu()
{
    add_submenu_page(
        'custom-user-points-list',  // Parent slug
        'Settings',                 // Page title
        'Settings',                 // Menu title
        'edit_shop_orders',         // Capability
        'woo-customer-points-settings',  // Menu slug
        'woo_customer_points_settings_page_content'  // Callback function
    );
}
add_action('admin_menu', 'woocommerce_customer_points_create_settings_menu');

// Register settings
function woo_customer_points_register_settings()
{
    // Register settings for first order points
    register_setting('woo_customer_points_settings_group', 'woo_first_order_points');

    // Register settings for points per currency spent
    register_setting('woo_customer_points_settings_group', 'woo_points_per_currency_spent');

    // Register settings for currency unit for points calculation
    register_setting('woo_customer_points_settings_group', 'woo_currency_unit_for_points');

    // Settings for referral points
    register_setting('woo_customer_points_settings_group', 'woo_referral_points');
    register_setting('woo_customer_points_settings_group', 'woo_referred_user_points');
}
add_action('admin_init', 'woo_customer_points_register_settings');

// Display the settings page with shortcode information
function woo_customer_points_settings_page_content()
{
?>
    <div class="wrap">
        <h1><?php esc_html_e('WooCommerce Customer Points Settings', 'woo-customer-points'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('woo_customer_points_settings_group'); ?>
            <?php do_settings_sections('woo_customer_points_settings_group'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('First Order Points', 'woo-customer-points'); ?></th>
                    <td>
                        <input type="number" name="woo_first_order_points" value="<?php echo esc_attr(get_option('woo_first_order_points', 300)); ?>" min="0" />
                        <p class="description"><?php esc_html_e('Points awarded for the first order.', 'woo-customer-points'); ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Points Per Currency Spent', 'woo-customer-points'); ?></th>
                    <td>
                        <input type="number" name="woo_points_per_currency_spent" value="<?php echo esc_attr(get_option('woo_points_per_currency_spent', 1)); ?>" min="0" />
                        <p class="description"><?php esc_html_e('Points earned per currency unit spent. e.g., earn 1 point for each 100 yen spent.', 'woo-customer-points'); ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Currency Unit for Points Calculation', 'woo-customer-points'); ?></th>
                    <td>
                        <input type="number" name="woo_currency_unit_for_points" value="<?php echo esc_attr(get_option('woo_currency_unit_for_points', 100)); ?>" min="1" />
                        <p class="description"><?php esc_html_e('Currency amount required to earn points. e.g., 100 yen = 1 point.', 'woo-customer-points'); ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Referral Points (Referrer)', 'woo-customer-points'); ?></th>
                    <td>
                        <input type="number" name="woo_referral_points" value="<?php echo esc_attr(get_option('woo_referral_points', 200)); ?>" min="0" />
                        <p class="description"><?php esc_html_e('Points awarded to the referrer when a referred user completes their first order.', 'woo-customer-points'); ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Referral Points (Referred User)', 'woo-customer-points'); ?></th>
                    <td>
                        <input type="number" name="woo_referred_user_points" value="<?php echo esc_attr(get_option('woo_referred_user_points', 100)); ?>" min="0" />
                        <p class="description"><?php esc_html_e('Points awarded to the referred user upon completing their first order.', 'woo-customer-points'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <!-- Shortcode Information Section -->
        <hr>
        <h2><?php esc_html_e('How to Display User Points', 'woo-customer-points'); ?></h2>
        <p><?php esc_html_e('Use the following shortcode to display the user points on any page or post:', 'woo-customer-points'); ?></p>
        <pre><code>[display_user_points]</code></pre>
        <p><?php esc_html_e('Optional attribute:', 'woo-customer-points'); ?></p>
        <ul>
            <li><strong>section="points"</strong> - <?php esc_html_e('Displays only the points without any label.', 'woo-customer-points'); ?></li>
        </ul>
        <p><?php esc_html_e('Example:', 'woo-customer-points'); ?></p>
        <pre><code>[display_user_points section="points"]</code></pre>
        <p><?php esc_html_e('This shortcode will display the user\'s points if they are logged in. Otherwise, it will prompt them to log in.', 'woo-customer-points'); ?></p>
    </div>
<?php
}
