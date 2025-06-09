=== Loquisoft Bulk Users Import/Export ===
Contributors: loquisoft
Tags: users, import, export, csv, woocommerce, subscriptions
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple plugin to bulk import and export WordPress users with WooCommerce Subscription support.

== Description ==

Loquisoft Bulk Users Import/Export is a powerful tool for WordPress administrators who need to manage large numbers of users and their WooCommerce subscriptions efficiently.

### Key Features

* **Bulk User Import**: Import users from a CSV file with all their details
* **Bulk User Export**: Export all users to a CSV file for backup or migration
* **WooCommerce Subscription Assignment**: Automatically assign subscriptions to imported or existing users
* **User-Friendly Interface**: Simple, intuitive interface for all operations
* **Flexible Options**: Control how existing users are handled, notification emails, and more

### Requirements

* WordPress 5.0 or higher
* WooCommerce 3.0 or higher
* WooCommerce Subscriptions 2.0 or higher

### CSV Import Format

Expected CSV header format:
user_login,user_email,user_pass,first_name,last_name,display_name,role


* **user_login**: The username (required)
* **user_email**: The user's email address (required)
* **user_pass**: The user's password (optional - a random password will be generated if empty)
* **first_name**: The user's first name (optional)
* **last_name**: The user's last name (optional)
* **display_name**: The name displayed publicly (optional)
* **role**: The user role (optional - defaults to 'subscriber')

### Existing User Handling

When importing users, you have two options for handling existing users:
1. **Skip**: Users with matching email addresses will be skipped
2. **Update**: Users with matching email addresses will have their information updated

### Email Notifications

The plugin supports various email notification options:
* Send welcome emails to new users (using WordPress default notifications)
* Send subscription notifications (using WooCommerce Subscription emails)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/loquisoft-bulk-users` directory, or install the plugin through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to the 'Bulk Users' menu in your admin dashboard to use the plugin

== Frequently Asked Questions ==

= Can I import users without passwords? =

Yes. If no password is provided in the CSV or the password field is empty, the plugin will automatically generate a secure random password for each user.

= What happens if I import a user that already exists? =

You can choose to either skip existing users (identified by email address) or update their information with the data from the CSV.

= What user roles can I assign during import? =

You can assign any role that exists in your WordPress installation. If no role is specified, users will be assigned the 'subscriber' role by default.

= Can I export user passwords? =

Yes, but it's not recommended for security reasons. If you need to migrate users with their existing passwords, you can enable the "Include encrypted passwords" option during export. Note that these are encrypted hashes, not the actual passwords.

= How are subscriptions assigned to users? =

You can assign subscriptions during import by checking the "Assign subscription to imported users" option and selecting a subscription product. You can also bulk assign subscriptions to existing users from the "Assign Subscriptions" page.

= Do users get notified when they're imported or assigned a subscription? =

This is optional. You can choose whether to send welcome emails to new users and/or subscription notification emails when assigning subscriptions.

= Is the plugin compatible with multisite? =

The plugin is designed for single-site WordPress installations. While it may work on multisite, it has not been extensively tested in that environment.

== Screenshots ==

1. Main plugin interface showing import and export options
2. Subscription assignment interface showing user selection
3. CSV format guide with example

== Changelog ==

= 1.4.1 =
* Improved UI with wider containers for better display of CSV format
* Enhanced styling for better readability
* Added horizontal scrolling for tables on smaller screens

= 1.4.0 =
* Improved handling of missing passwords in CSV imports
* Added automatic generation of random passwords when needed
* Fixed notification display issues after redirects
* Added user-friendly notes about password handling

= 1.3.0 =
* Enhanced admin notifications for better feedback
* Improved error handling and reporting
* Fixed issues with subscription assignments

= 1.2.0 =
* Added options for handling existing users (skip or update)
* Improved subscription assignment process
* Enhanced user search functionality

= 1.1.0 =
* Added WooCommerce Subscription support
* Added bulk subscription assignment feature
* Improved CSV export formatting

= 1.0.0 =
* Initial release with basic user import/export functionality

== Upgrade Notice ==

= 1.4.1 =
UI improvements and better display of CSV format information.

= 1.4.0 =
Important update with improved password handling and fixes for notification displays.

= 1.3.0 =
Recommended update with enhanced feedback and error handling.

= 1.2.0 =
Adds important features for handling existing users.

= 1.1.0 =
Adds WooCommerce Subscription support.

