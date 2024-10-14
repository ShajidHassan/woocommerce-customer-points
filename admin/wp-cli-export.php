<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}



// Download All User Customer Points from Meta Table using WP CLI from My 
if (defined('WP_CLI') && WP_CLI) {
    class Export_User_Data_Command {
        /**
         * Exports all users' email, ID, and points to a CSV file.
         *
         * ## OPTIONS
         *
         * <file>
         * : The name of the file to export to.
         *
         * ## EXAMPLES
         *
         *     wp export_user_data points-exportjan16.csv
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args) {
            // Get the filename from the arguments
            $filename = $args[0];

            WP_CLI::log("Fetching all users...");

            // Get all users
            $users = get_users();

            WP_CLI::log("Fetched " . count($users) . " users.");

            // Prepare an array to hold all user data
            $all_user_data = array();

            // Loop over each user
            foreach ($users as $user) {
                WP_CLI::log("Fetching points for user with ID " . $user->ID . "...");

                // Get the points for the user from MyRewards Plugin
                $points = get_user_meta($user->ID, 'lws_wre_points_default', true);

                WP_CLI::log("Fetched points for user with ID " . $user->ID . ".");

                // Add the user data to the array
                $all_user_data[] = array(
                    'ID' => $user->ID,
                    'email' => $user->user_email,
                    'points' => $points,
                );
            }

            WP_CLI::log("Writing data to file...");

            // Open a file for writing
            $file = fopen($filename, 'w');

            // Write the column headers
            fputcsv($file, array_keys($all_user_data[0]));

            // Write the data
            foreach ($all_user_data as $row) {
                fputcsv($file, $row);
            }

            // Close the file
            fclose($file);

            WP_CLI::log("Data written to file.");

            WP_CLI::success("Exported user data to {$filename}");
        }
    }

    WP_CLI::add_command('export_user_data', 'Export_User_Data_Command');
}

// Import Customer Points from CSV or from old plugin meta
if (defined('WP_CLI') && WP_CLI) {
    class Update_User_Points_Command {
        /**
         * Updates the customer_points meta key for each user from a CSV file or from the existing lws_wre_points_default meta key.
         *
         * ## OPTIONS
         *
         * <source>
         * : The source of the points data. Can be 'csv' or 'meta'.
         *
         * [<file>]
         * : The name of the CSV file to import from. Required if source is 'csv'.
         *
         * ## EXAMPLES
         *
         *     wp update_user_points csv points-export.csv
         *     wp update_user_points meta
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args) {
            // Get the source from the arguments
            $source = $args[0];

            if ($source === 'csv') {
                // Get the filename from the arguments
                $filename = $args[1];

                WP_CLI::log("Reading points data from CSV file...");

                // Open the CSV file for reading
                if (($handle = fopen($filename, "r")) !== FALSE) {
                    // Loop over each row in the file
                    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                        // Get the user ID and points from the row
                        $user_id = $data[0];
                        // Customer points in 3rd column
                        $points = $data[2];

                        // Update the user meta
                        update_user_meta($user_id, 'customer_points', $points);

                        WP_CLI::log("Updated points for user with ID {$user_id}.");
                    }

                    // Close the CSV file
                    fclose($handle);
                }
            } else if ($source === 'meta') {
                WP_CLI::log("Reading points data from user meta...");

                // Get all users
                $users = get_users();

                // Loop over each user
                foreach ($users as $user) {
                    // Get the points from the existing meta key
                    $points = get_user_meta($user->ID, 'lws_wre_points_default', true);

                    // Update the user meta
                    update_user_meta($user->ID, 'customer_points', $points);

                    WP_CLI::log("Updated points for user with ID {$user->ID}.");
                }
            } else {
                WP_CLI::error("Invalid source. Source must be 'csv' or 'meta'.");
            }

            WP_CLI::success("Updated user points.");
        }
    }

    WP_CLI::add_command('update_user_points', 'Update_User_Points_Command');
}