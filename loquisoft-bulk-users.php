<?php
/*
Plugin Name: Loquisoft Bulk Users Import/Export
Plugin URI: https://loquisoft.com
Description: A simple plugin to bulk import and export WordPress users with WooCommerce order and subscription creation
Version: 1.5.0
Author: Loquisoft
Author URI: https://loquisoft.com
License: GPL v2 or later
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add plugin activation hook
register_activation_hook(__FILE__, 'loquisoft_plugin_activate');

function loquisoft_plugin_activate() {
    if (!loquisoft_check_dependencies()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and activated.');
    }
}

// Check for required plugins
function loquisoft_check_dependencies() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Loquisoft Bulk Users requires WooCommerce to be installed and activated.</p></div>';
        });
        return false;
    }

    return true;
}

// Initialize plugin
add_action('plugins_loaded', function() {
    if (loquisoft_check_dependencies()) {
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', 'loquisoft_admin_scripts');
        
        // Add menu items
        add_action('admin_menu', 'loquisoft_bulk_users_menu');
        
        // Handle form submissions
        add_action('admin_init', 'loquisoft_handle_import');
        add_action('admin_init', 'loquisoft_handle_export');
        add_action('admin_init', 'loquisoft_handle_bulk_order_creation');
        
        // Add settings errors display
        add_action('admin_notices', 'loquisoft_admin_notices');
        
        // Check for stored messages
        add_action('admin_init', 'loquisoft_check_messages');
    }
});

// Check for stored success messages
function loquisoft_check_messages() {
    global $pagenow;
    
    if ($pagenow == 'admin.php' && isset($_GET['page']) && strpos($_GET['page'], 'loquisoft-bulk-users') !== false) {
        if (isset($_GET['import']) && $_GET['import'] === 'success') {
            $message = get_transient('loquisoft_import_message');
            if ($message) {
                add_settings_error(
                    'loquisoft_messages',
                    'loquisoft_message_import',
                    $message,
                    'success'
                );
                delete_transient('loquisoft_import_message');
            }
        }
        
        if (isset($_GET['orders']) && $_GET['orders'] === 'success') {
            $message = get_transient('loquisoft_order_message');
            if ($message) {
                add_settings_error(
                    'loquisoft_messages',
                    'loquisoft_message_order',
                    $message,
                    'success'
                );
                delete_transient('loquisoft_order_message');
            }
        }
    }
}

// Enqueue admin scripts and styles
function loquisoft_admin_scripts($hook) {
    if (strpos($hook, 'loquisoft-bulk-users') !== false) {
        // Add custom styles for the plugin interface
        wp_enqueue_style('loquisoft-admin-style', plugins_url('assets/css/admin-style.css', __FILE__));
        
        // If the CSS file doesn't exist yet, add inline styles
        $css_file = plugin_dir_path(__FILE__) . 'assets/css/admin-style.css';
        if (!file_exists($css_file)) {
            wp_add_inline_style('loquisoft-admin-style', '
                .loquisoft-wrap .card {
                    max-width: 100%;
                    padding: 20px;
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                    margin-bottom: 20px;
                }
                .loquisoft-wrap .option-section {
                    background: #f5f5f5;
                    padding: 15px;
                    margin: 10px 0;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    overflow-x: auto; /* Add scroll for very long content */
                }
                .loquisoft-wrap code {
                    word-break: normal;
                    white-space: nowrap;
                }
                .loquisoft-wrap .csv-format-info {
                    overflow-x: auto;
                    max-width: 100%;
                    padding: 10px 0;
                }
                .loquisoft-wrap .user-table {
                    margin-top: 20px;
                }
                .loquisoft-wrap #user-search {
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    margin-bottom: 15px;
                    width: 100%;
                    max-width: 400px;
                }
                .loquisoft-wrap .wp-list-table td, 
                .loquisoft-wrap .wp-list-table th {
                    padding: 10px;
                }
            ');
        }
        
        wp_enqueue_script('loquisoft-admin-script', plugins_url('assets/js/admin-script.js', __FILE__), array('jquery'), '1.0', true);
    }
}

// Add menu items
function loquisoft_bulk_users_menu() {
    add_menu_page(
        'Bulk Users',
        'Bulk Users',
        'manage_options',
        'loquisoft-bulk-users',
        'loquisoft_bulk_users_page',
        'dashicons-groups',
        30
    );

    add_submenu_page(
        'loquisoft-bulk-users',
        'Create Orders',
        'Create Orders',
        'manage_options',
        'loquisoft-create-orders',
        'loquisoft_create_orders_page'
    );
}

// Debug logging function
function loquisoft_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

// Get all products
function loquisoft_get_all_products() {
    $all_products = array();
    
    $args = array(
        'status' => 'publish',
        'limit' => -1,
    );
    
    $products = wc_get_products($args);
    
    foreach ($products as $product) {
        // Add product type info to help with UI display
        $type = $product->get_type();
        $label = $product->get_name();
        
        // Add product type indicator for subscription products
        if ($type === 'subscription' || $type === 'variable-subscription') {
            $label .= ' [Subscription]';
        }
        
        $all_products[$product->get_id()] = array(
            'name' => $label,
            'type' => $type
        );
    }
    
    return $all_products;
}

// Check if product is a subscription
function loquisoft_is_subscription_product($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return false;
    
    return $product->is_type(array('subscription', 'variable-subscription'));
}

// Create subscription for user
function loquisoft_create_subscription($user_id, $product_id, $order_status = 'completed', $send_notifications = false) {
    if (!function_exists('wcs_create_subscription')) {
        loquisoft_log('WC Subscriptions function not available');
        return false;
    }

    try {
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type(array('subscription', 'variable-subscription'))) {
            loquisoft_log('Invalid product type');
            return false;
        }
        
        // Get user info
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            loquisoft_log('Invalid user ID');
            return false;
        }
        
        // Create an order first
        $order = wc_create_order(array(
            'customer_id' => $user_id,
            'status' => $order_status
        ));

        if (is_wp_error($order)) {
            loquisoft_log('Order creation failed: ' . $order->get_error_message());
            return false;
        }

        // Add the subscription product to order
        $order->add_product($product, 1);
        
        // Add customer data to order
        $order->set_billing_first_name($user_info->first_name);
        $order->set_billing_last_name($user_info->last_name);
        $order->set_billing_email($user_info->user_email);
        
        // Set payment method to "none"
        $order->set_payment_method('none');
        $order->set_payment_method_title('None');
        
        // Calculate totals
        $order->calculate_totals();
        
        // Add order note
        $order->add_order_note('Order created via Loquisoft Bulk Users plugin.');
        
        // Save the order
        $order->save();
        
        // Now create the subscription using WC Subscriptions API
        $subscription_data = array(
            'customer_id'      => $user_id,
            'status'           => 'active',
            'billing_period'   => WC_Subscriptions_Product::get_period($product),
            'billing_interval' => WC_Subscriptions_Product::get_interval($product),
            'start_date'       => gmdate('Y-m-d H:i:s'),
            'order_id'         => $order->get_id(),  // Associate with the order
            'parent_id'        => $order->get_id(),  // Set the parent order
        );

        // Create the subscription
        $subscription = wcs_create_subscription($subscription_data);

        if (is_wp_error($subscription)) {
            loquisoft_log('Subscription creation failed: ' . $subscription->get_error_message());
            return false;
        }

        // Add product to subscription
        $item_id = $subscription->add_product(
            $product,
            1,
            array(
                'total' => $product->get_price()
            )
        );

        if (!$item_id) {
            loquisoft_log('Failed to add product to subscription');
            $subscription->delete(true);
            return false;
        }

        // Add customer billing info
        $subscription->set_billing_first_name($user_info->first_name);
        $subscription->set_billing_last_name($user_info->last_name);
        $subscription->set_billing_email($user_info->user_email);
        
        // Calculate totals
        $subscription->calculate_totals();

        // Update status to active
        $subscription->update_status('active');

        // Link this subscription with the order
        if (function_exists('wcs_add_subscription_to_order')) {
            wcs_add_subscription_to_order($order, $subscription);
        }
        
        // Mark the order as subscription-related
        update_post_meta($order->get_id(), '_contains_subscription', 'true');
        
        // Add note
        $subscription->add_order_note('Subscription created via Loquisoft Bulk Users plugin.');
        $order->add_order_note('This order has a subscription assigned via Loquisoft Bulk Users plugin.');

        // Save subscription
        $subscription->save();

        // Send notifications if requested
        if ($send_notifications) {
            // Send order notifications
            if (function_exists('wc_emails')) {
                $emails = wc_emails()->get_emails();
                
                if ($order_status === 'completed' && isset($emails['WC_Email_Customer_Completed_Order'])) {
                    $emails['WC_Email_Customer_Completed_Order']->trigger($order->get_id());
                } elseif ($order_status === 'processing' && isset($emails['WC_Email_Customer_Processing_Order'])) {
                    $emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
                }
                
                // Admin new order notification
                if (isset($emails['WC_Email_New_Order'])) {
                    $emails['WC_Email_New_Order']->trigger($order->get_id());
                }
            }
            
            // Send subscription notifications
            $mailer = WC()->mailer();
            $mails = $mailer->get_emails();
            if (isset($mails['WCS_Email_New_Subscription']) && $mails['WCS_Email_New_Subscription']) {
                $mails['WCS_Email_New_Subscription']->trigger($subscription->get_id());
            }
        }

        loquisoft_log('Subscription and order created successfully for user ' . $user_id);
        return true;

    } catch (Exception $e) {
        loquisoft_log('Subscription creation failed: ' . $e->getMessage());
        return false;
    }
}

// Create order for user
function loquisoft_create_order($user_id, $product_id, $order_status = 'completed', $send_notifications = false) {
    try {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            loquisoft_log('Invalid product');
            return false;
        }

        // Get user info
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            loquisoft_log('Invalid user ID');
            return false;
        }
        
        // Check if it's a subscription product
        $is_subscription = $product->is_type(array('subscription', 'variable-subscription'));
        
        // For subscription products, create a subscription instead of an order
        if ($is_subscription && function_exists('wcs_create_subscription')) {
            return loquisoft_create_subscription($user_id, $product_id, $order_status, $send_notifications);
        }

        // For regular products, create an order
        $order = wc_create_order(array(
            'customer_id' => $user_id,
            'status' => $order_status
        ));

        if (is_wp_error($order)) {
            loquisoft_log('Order creation failed: ' . $order->get_error_message());
            return false;
        }

        // Add product to order
        $order->add_product($product, 1);
        
        // Add customer data
        $order->set_billing_first_name($user_info->first_name);
        $order->set_billing_last_name($user_info->last_name);
        $order->set_billing_email($user_info->user_email);
        
        // Set payment method to "none"
        $order->set_payment_method('none');
        $order->set_payment_method_title('None');
        
        // Calculate totals
        $order->calculate_totals();
        
        // Add order note
        $order->add_order_note('Order created via Loquisoft Bulk Users plugin.');
        
        // Save the order
        $order->save();
        
        // Send notifications if requested
        if ($send_notifications) {
            // This will trigger the appropriate WooCommerce email
            if (function_exists('wc_emails')) {
                $emails = wc_emails()->get_emails();
                
                if ($order_status === 'completed' && isset($emails['WC_Email_Customer_Completed_Order'])) {
                    $emails['WC_Email_Customer_Completed_Order']->trigger($order->get_id());
                } elseif ($order_status === 'processing' && isset($emails['WC_Email_Customer_Processing_Order'])) {
                    $emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
                }
                
                // Also notify admin of new order
                if (isset($emails['WC_Email_New_Order'])) {
                    $emails['WC_Email_New_Order']->trigger($order->get_id());
                }
            }
        }
        
        loquisoft_log('Order created successfully for user ' . $user_id);
        return true;
        
    } catch (Exception $e) {
        loquisoft_log('Order creation failed: ' . $e->getMessage());
        return false;
    }
}

// Handle the import process
function loquisoft_handle_import() {
    if (!isset($_POST['import_users'])) {
        return;
    }
    
    if (!check_admin_referer('loquisoft_import_users_nonce', 'loquisoft_import_nonce')) {
        wp_die('Security check failed.');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    // Get options
    $create_order = isset($_POST['create_order']) && $_POST['create_order'] == '1';
    $order_product_id = isset($_POST['order_product_id']) ? intval($_POST['order_product_id']) : 0;
    $order_status = isset($_POST['order_status']) ? sanitize_text_field($_POST['order_status']) : 'completed';
    $send_notifications = isset($_POST['send_notifications']) && $_POST['send_notifications'] == '1';
    $send_order_notifications = isset($_POST['send_order_notifications']) && $_POST['send_order_notifications'] == '1';

    $existing_user_handling = isset($_POST['existing_user_handling']) ? $_POST['existing_user_handling'] : 'skip';

    $file = $_FILES['users_csv'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        add_settings_error('loquisoft_messages', 'loquisoft_message', 'Error uploading file.', 'error');
        return;
    }

    $handle = fopen($file['tmp_name'], 'r');
    
    // Skip header row
    $headers = fgetcsv($handle);
    
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $errors = 0;
    $orders_created = 0;
    $subscriptions_created = 0;

    // Map CSV header indexes
    $mapping = array(
        'user_login' => array_search('user_login', $headers),
        'user_email' => array_search('user_email', $headers),
        'user_pass' => array_search('user_pass', $headers),
        'first_name' => array_search('first_name', $headers),
        'last_name' => array_search('last_name', $headers),
        'display_name' => array_search('display_name', $headers),
        'role' => array_search('role', $headers)
    );

    while (($data = fgetcsv($handle)) !== FALSE) {
        $username = $mapping['user_login'] !== false ? sanitize_user($data[$mapping['user_login']]) : '';
        $email = $mapping['user_email'] !== false ? sanitize_email($data[$mapping['user_email']]) : '';
        
        // Improved password handling
        if ($mapping['user_pass'] !== false) {
            // Password column exists in CSV
            $password = isset($data[$mapping['user_pass']]) && !empty(trim($data[$mapping['user_pass']])) 
                ? $data[$mapping['user_pass']] 
                : wp_generate_password();
        } else {
            // No password column in CSV
            $password = wp_generate_password();
        }
        
        $first_name = $mapping['first_name'] !== false ? sanitize_text_field($data[$mapping['first_name']]) : '';
        $last_name = $mapping['last_name'] !== false ? sanitize_text_field($data[$mapping['last_name']]) : '';
        $display_name = $mapping['display_name'] !== false ? sanitize_text_field($data[$mapping['display_name']]) : '';
        $role = $mapping['role'] !== false ? sanitize_text_field($data[$mapping['role']]) : 'subscriber';

        if (!$username || !$email) {
            $errors++;
            continue;
        }

        $existing_user_id = email_exists($email);
        $user_id = null;

        if ($existing_user_id) {
            // Handle existing users based on chosen option
            if ($existing_user_handling === 'skip') {
                $skipped++;
                continue;
            } elseif ($existing_user_handling === 'update') {
                // Update existing user
                $user_data = array(
                    'ID' => $existing_user_id,
                    'user_login' => $username, // Note: Changing usernames can cause issues, so this might not change
                    'user_email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $display_name,
                    'role' => $role
                );
                
                // Only update password if specified and not placeholder
                if (!empty($password) && $password !== '*****') {
                    $user_data['user_pass'] = $password;
                }

                $user_id = wp_update_user($user_data);
                
                if (!is_wp_error($user_id)) {
                    $updated++;
                } else {
                    $errors++;
                    loquisoft_log('User update failed: ' . $user_id->get_error_message());
                    continue;
                }
            }
        } else {
            // Create new user - ensure password is set
            if (empty($password)) {
                $password = wp_generate_password();
            }
            
            $user_data = array(
                'user_login' => $username,
                'user_email' => $email,
                'user_pass' => $password,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $display_name,
                'role' => $role
            );

            $user_id = wp_insert_user($user_data);

            if (!is_wp_error($user_id)) {
                $imported++;
                
                // Send new user notification if requested
                if ($send_notifications) {
                    wp_new_user_notification($user_id, null, 'both');
                }
            } else {
                $errors++;
                loquisoft_log('User creation failed: ' . $user_id->get_error_message());
                continue;
            }
        }
        
        // Create order if option is selected
        if ($user_id && $create_order && $order_product_id > 0) {
            $is_subscription = loquisoft_is_subscription_product($order_product_id);
            $success = loquisoft_create_order($user_id, $order_product_id, $order_status, $send_order_notifications);
            
            if ($success) {
                if ($is_subscription) {
                    $subscriptions_created++;
                } else {
                    $orders_created++;
                }
            }
        }
    }

    fclose($handle);

    $message = sprintf(
        'Import complete. New users: %d, Updated users: %d, Skipped users: %d, Orders created: %d, Subscriptions created: %d, Errors: %d',
        $imported,
        $updated,
        $skipped,
        $orders_created,
        $subscriptions_created,
        $errors
    );

    // Store message in transient for display after redirect
    set_transient('loquisoft_import_message', $message, 60);
    
    // Redirect to prevent form resubmission
    wp_redirect(add_query_arg('import', 'success', $_SERVER['HTTP_REFERER']));
    exit;
}

// Handle Export
function loquisoft_handle_export() {
    if (!isset($_POST['export_users'])) {
        return;
    }
    
    if (!check_admin_referer('loquisoft_export_users_nonce', 'loquisoft_export_nonce')) {
        wp_die('Security check failed.');
    }
    
    $users = get_users();
    $include_passwords = isset($_POST['include_passwords']);
    $headers = "user_login,user_email,user_pass,first_name,last_name,display_name,role\n";
    
    $csv_data = $headers;

    foreach ($users as $user) {
        $data = array(
            $user->user_login,
            $user->user_email,
            $include_passwords ? $user->user_pass : '*****',
            get_user_meta($user->ID, 'first_name', true),
            get_user_meta($user->ID, 'last_name', true),
            $user->display_name,
            implode(',', $user->roles)
        );
        
        $csv_data .= implode(',', array_map('loquisoft_escape_csv', $data)) . "\n";
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="wordpress_users_' . date('Y-m-d') . '.csv"');
    echo $csv_data;
    exit();
}

function loquisoft_escape_csv($value) {
    if (preg_match('/[,"]/', $value)) {
        $value = '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

// Handle bulk order creation
function loquisoft_handle_bulk_order_creation() {
    if (!isset($_POST['create_orders'])) {
        return;
    }
    
    if (!check_admin_referer('loquisoft_create_orders_nonce')) {
        wp_die('Security check failed.');
    }
    
    $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $order_status = isset($_POST['order_status']) ? sanitize_text_field($_POST['order_status']) : 'completed';
    $send_notifications = isset($_POST['send_order_notifications']) && $_POST['send_order_notifications'] == '1';
    
    if (empty($user_ids)) {
        add_settings_error('loquisoft_messages', 'loquisoft_message', 'Please select at least one user.', 'error');
        return;
    }

    if ($product_id <= 0) {
        add_settings_error('loquisoft_messages', 'loquisoft_message', 'Please select a product.', 'error');
        return;
    }

    $success_orders = 0;
    $success_subscriptions = 0;
    $errors = 0;
    $is_subscription = loquisoft_is_subscription_product($product_id);

    foreach ($user_ids as $user_id) {
        $success = loquisoft_create_order($user_id, $product_id, $order_status, $send_notifications);
        if ($success) {
            if ($is_subscription) {
                $success_subscriptions++;
            } else {
                $success_orders++;
            }
        } else {
            $errors++;
        }
    }

    $message = sprintf(
        'Process complete. Orders created: %d. Subscriptions created: %d. Failed: %d.',
        $success_orders,
        $success_subscriptions,
        $errors
    );
    
    // Store message in transient for display after redirect
    set_transient('loquisoft_order_message', $message, 60);
    
    // Redirect to prevent form resubmission
    wp_redirect(add_query_arg('orders', 'success', $_SERVER['HTTP_REFERER']));
    exit;
}

// Create the main admin page
function loquisoft_bulk_users_page() {
    if (!loquisoft_check_dependencies()) return;
    
    $all_products = loquisoft_get_all_products();
    ?>
    <div class="wrap loquisoft-wrap">
        <h1>Loquisoft Bulk Users Import/Export</h1>
        
        <?php settings_errors('loquisoft_messages'); ?>
        
        <div class="card" style="max-width: 100%;">
            <h2>Export Users</h2>
            <form method="post" action="">
                <?php wp_nonce_field('loquisoft_export_users_nonce', 'loquisoft_export_nonce'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="include_passwords" value="1">
                        Include encrypted passwords (for migration purposes only)
                    </label>
                </p>
                <input type="submit" name="export_users" class="button button-primary" value="Export Users to CSV">
            </form>
        </div>

        <div class="card" style="margin-top: 20px; max-width: 100%;">
            <h2>Import Users</h2>
            <form method="post" enctype="multipart/form-data" action="">
                <?php wp_nonce_field('loquisoft_import_users_nonce', 'loquisoft_import_nonce'); ?>
                <input type="file" name="users_csv" accept=".csv" required>
                
                <div class="option-section" style="margin-top: 15px;">
                    <h3>CSV Format</h3>
                    <div class="csv-format-info">
                        <p>Expected CSV header format:</p>
                        <code style="font-size: 14px; padding: 8px; background: #f0f0f0; display: inline-block; margin: 5px 0;">user_login,user_email,user_pass,first_name,last_name,display_name,role</code>
                        <p><small>Note: If password is missing or empty, a random password will be generated automatically.</small></p>
                    </div>
                </div>

                <div class="option-section">
                    <h3>Existing User Handling</h3>
                    <p>
                        <label>
                            <input type="radio" name="existing_user_handling" value="skip" checked>
                            Skip existing users (identifying by email)
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="radio" name="existing_user_handling" value="update">
                            Update existing users with new data
                        </label>
                    </p>
                </div>

                <div class="option-section">
                    <h3>Email Notifications</h3>
                    <p>
                        <label>
                            <input type="checkbox" name="send_notifications" value="1">
                            Send welcome email to new users
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="send_order_notifications" value="1">
                            Send order/subscription notification emails
                        </label>
                    </p>
                </div>
                
                <div class="option-section">
                    <h3>Order/Subscription Options</h3>
                    <p>
                        <label>
                            <input type="checkbox" name="create_order" value="1">
                            Create order for imported users
                        </label>
                    </p>
                    <div id="order_options" style="display: none;">
                        <p>
                            <label>Select Product:</label><br>
                            <select name="order_product_id" style="min-width: 250px;">
                                <option value="">Select a product...</option>
                                <?php
                                foreach ($all_products as $id => $info) {
                                    echo "<option value='{$id}'>{$info['name']}</option>";
                                }
                                ?>
                            </select>
                        </p>
                        <p>
                            <label>Order Status:</label><br>
                            <select name="order_status" style="min-width: 250px;">
                                <option value="completed">Completed</option>
                                <option value="processing">Processing</option>
                                <option value="on-hold">On Hold</option>
                            </select>
                            <p><small>Note: Status applies to regular orders and subscription orders. Subscriptions will be created as active.</small></p>
                        </p>
                    </div>
                </div>

                <p style="margin-top: 15px;">
                    <input type="submit" name="import_users" class="button button-primary" value="Import Users">
                </p>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('input[name="create_order"]').change(function() {
            $('#order_options').toggle(this.checked);
        });
    });
    </script>
    <?php
}

// Create the create orders page
function loquisoft_create_orders_page() {
    if (!loquisoft_check_dependencies()) return;

    $all_products = loquisoft_get_all_products();
    
    $users = get_users();
    ?>
    <div class="wrap loquisoft-wrap">
        <h1>Create Orders for Users</h1>
        
        <?php settings_errors('loquisoft_messages'); ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('loquisoft_create_orders_nonce'); ?>
            
            <div class="card" style="max-width: 100%;">
                <h2>Select Product and Order Options</h2>
                <p>
                    <label>Select Product:</label><br>
                    <select name="product_id" required style="min-width: 300px;">
                        <option value="">Select a product...</option>
                        <?php
                        foreach ($all_products as $id => $info) {
                            echo "<option value='{$id}'>{$info['name']}</option>";
                        }
                        ?>
                    </select>
                </p>
                <p>
                    <label>Order Status:</label><br>
                    <select name="order_status" style="min-width: 300px;">
                        <option value="completed">Completed</option>
                        <option value="processing">Processing</option>
                        <option value="on-hold">On Hold</option>
                    </select>
                    <p><small>Note: Status applies to regular orders and subscription orders. Subscriptions will be created as active.</small></p>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="send_order_notifications" value="1">
                        Send order/subscription notification emails
                    </label>
                </p>
            </div>

            <div class="card" style="margin-top: 20px; max-width: 100%;">
                <h2>Select Users</h2>
                <p>
                    <input type="text" id="user-search" placeholder="Search users..." style="width: 100%; max-width: 400px;">
                </p>
                <div style="overflow-x: auto; max-width: 100%;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><input type="checkbox" id="select-all-users"></th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Name</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><input type="checkbox" name="user_ids[]" value="<?php echo $user->ID; ?>"></td>
                                <td><?php echo $user->user_login; ?></td>
                                <td><?php echo $user->user_email; ?></td>
                                <td><?php echo $user->first_name . ' ' . $user->last_name; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="submit">
                <input type="submit" name="create_orders" class="button button-primary" value="Create Orders/Subscriptions">
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Select all users functionality
        $('#select-all-users').change(function() {
            $('input[name="user_ids[]"]').prop('checked', this.checked);
        });
        
        // User search functionality
        $('#user-search').on('keyup', function() {
            var searchValue = $(this).val().toLowerCase();
            $('#users-table-body tr').each(function() {
                var rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.indexOf(searchValue) > -1);
            });
        });
    });
    </script>
    <?php
}

// Display admin notices
function loquisoft_admin_notices() {
    settings_errors('loquisoft_messages');
}
