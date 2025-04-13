<?php
/*
 Plugin Name: Bank IFCS Codes
 Plugin URI: https://yourwebsite.com
 Description: A plugin to display bank IFCS codes with cascading dropdowns for Bank Name, State, City/District, and Bank Branch.
 Version: 1.0
 Author: Shrey Pathak
 License: GPL2
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Admin menu
function bank_ifcs_add_admin_menu() {
    add_menu_page(
        'Bank IFCS Codes',          // Page title
        'Bank IFCS Codes',          // Menu title
        'manage_options',           // Capability
        'bank-ifcs-settings',       // Menu slug
        'bank_ifcs_settings_page',  // Callback function
        'dashicons-admin-settings'  // Icon
    );
}
add_action('admin_menu', 'bank_ifcs_add_admin_menu');

// Admin page callback
function bank_ifcs_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle form submission (CSV upload and mapping)
    if (isset($_POST['bank_ifcs_submit'])) {
        if (!empty($_FILES['csv_file']['name'])) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . basename($_FILES['csv_file']['name']);

            if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $file_path)) {
                $csv_data = array_map('str_getcsv', file($file_path));
                $headers = array_shift($csv_data); // First row is headers

                // Store CSV data and headers in options
                update_option('bank_ifcs_csv_data', $csv_data);
                update_option('bank_ifcs_csv_headers', $headers);
                update_option('bank_ifcs_column_mapping', $_POST['column_mapping']);

                add_settings_error('bank_ifcs_messages', 'bank_ifcs_message', __('CSV file uploaded and mapping saved successfully.', 'bank-ifcs'), 'success');
            } else {
                add_settings_error('bank_ifcs_messages', 'bank_ifcs_message', __('Error uploading file.', 'bank-ifcs'), 'error');
            }
        }
    }

    // Get stored data
    $csv_data = get_option('bank_ifcs_csv_data', array());
    $csv_headers = get_option('bank_ifcs_csv_headers', array());
    $column_mapping = get_option('bank_ifcs_column_mapping', array());

    // Display admin page
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <?php settings_errors('bank_ifcs_messages'); ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('bank_ifcs_action', 'bank_ifcs_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="csv_file">Upload CSV File</label></th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description">CSV should have columns: Bank Name, Branch Name, City/District, State, IFSC Code.</p>
                    </td>
                </tr>
                <?php if (!empty($csv_headers)): ?>
                    <tr>
                        <th>Map CSV Columns</th>
                        <td>
                            <?php foreach (array('bank_name', 'branch_name', 'city_district', 'state', 'ifsc_code') as $field): ?>
                                <label><?php echo ucfirst(str_replace('_', ' ', $field)); ?>:</label>
                                <select name="column_mapping[<?php echo $field; ?>]" required>
                                    <option value="">Select Column</option>
                                    <?php foreach ($csv_headers as $index => $header): ?>
                                        <option value="<?php echo $index; ?>" <?php selected($column_mapping[$field], $index); ?>><?php echo esc_html($header); ?></option>
                                    <?php endforeach; ?>
                                </select><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <?php submit_button('Update', 'primary', 'bank_ifcs_submit'); ?>
        </form>

        <?php if (!empty($csv_data)): ?>
            <h2>Preview Data</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <?php foreach ($csv_headers as $header): ?>
                            <th><?php echo esc_html($header); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($csv_data as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo esc_html($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// Frontend shortcode and scripts remain the same, but we'll update the JavaScript to use CSV data
function bank_ifcs_enqueue_scripts() {
    wp_enqueue_style('bank-ifcs-style', plugins_url('style.csv', __FILE__));
    wp_enqueue_script('jquery');
    wp_enqueue_script('bank-ifcs-dropdowns', plugins_url('dropdown-functionality.js', __FILE__), array('jquery'), '1.0', true);

    // Pass PHP data to JavaScript
    $csv_data = get_option('bank_ifcs_csv_data', array());
    $csv_headers = get_option('bank_ifcs_csv_headers', array());
    $column_mapping = get_option('bank_ifcs_column_mapping', array());

    wp_localize_script('bank-ifcs-dropdowns', 'bank_ifcs_data', array(
        'csv_data' => $csv_data,
        'csv_headers' => $csv_headers,
        'column_mapping' => $column_mapping
    ));
}
add_action('wp_enqueue_scripts', 'bank_ifcs_enqueue_scripts');

// Existing shortcode function
function bank_ifcs_dropdowns_shortcode() {
    ob_start();
    ?>
    <div class="bank-ifcs-container">
        <label for="bank_name">Bank Name:</label>
        <select id="bank_name" name="bank_name" class="bank-ifcs-dropdown">
            <option value="">Select Bank</option>
        </select>

        <label for="state">State:</label>
        <select id="state" name="state" class="bank-ifcs-dropdown" disabled>
            <option value="">Select State</option>
        </select>

        <label for="city">City/District:</label>
        <select id="city" name="city" class="bank-ifcs-dropdown" disabled>
            <option value="">Select City/District</option>
        </select>

        <label for="branch">Bank Branch:</label>
        <select id="branch" name="branch" class="bank-ifcs-dropdown" disabled>
            <option value="">Select Branch</option>
        </select>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bank_ifcs_dropdowns', 'bank_ifcs_dropdowns_shortcode');

// Add CSS for the table (same as before)
function bank_ifcs_add_table_styles() {
    ?>
    <style>
        .bank-details-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .bank-details-table th, .bank-details-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .bank-details-table th {
            background-color: #0073aa;
            color: white;
        }

        @media (max-width: 480px) {
            .bank-details-table {
                font-size: 14px;
            }

            .bank-details-table th, .bank-details-table td {
                padding: 8px;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'bank_ifcs_add_table_styles');
