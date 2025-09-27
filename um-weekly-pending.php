<?php
/*
Plugin Name: UM pending accounts reminder
Description: Weekly notification about pending user accounts in Ultimate Member.
Author: Mateusz Dudkiewicz
Version: 1.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add weekly cron schedule
add_filter('cron_schedules', 'um_weekly_add_cron_schedule');
function um_weekly_add_cron_schedule($schedules) {
    $schedules['weekly'] = array(
        'interval' => 7 * 24 * 60 * 60, // 7 days
        'display' => __('Once Weekly')
    );
    return $schedules;
}

// Schedule event on plugin activation
register_activation_hook(__FILE__, 'um_weekly_pending_activate');
function um_weekly_pending_activate() {
    // Set default cron settings
    if (!get_option('um_weekly_pending_cron_day')) {
        update_option('um_weekly_pending_cron_day', 'Monday');
    }
    if (!get_option('um_weekly_pending_cron_time')) {
        update_option('um_weekly_pending_cron_time', '10:00');
    }
    
    // Schedule cron job
    if (!wp_next_scheduled('um_pending_notify_cron')) {
        $day = get_option('um_weekly_pending_cron_day', 'Monday');
        $time = get_option('um_weekly_pending_cron_time', '10:00');
        wp_schedule_event(strtotime("next {$day} {$time}"), 'weekly', 'um_pending_notify_cron');
        um_weekly_log('Cron scheduled for next ' . $day . ' ' . $time);
    }
    
    // Set default email template content if not already set
    um_weekly_set_default_email_content();
}

// Set default email template content after UM is loaded
add_action('um_after_email_templates_init', 'um_weekly_set_default_email_content');
add_action('admin_init', 'um_weekly_set_default_email_content');

// Set default email template content
function um_weekly_set_default_email_content() {
    // Make sure UM is available
    if (!class_exists('UM') || !function_exists('UM')) {
        return;
    }
    
    // Get theme directory for email templates
    $theme_dir = get_stylesheet_directory();
    $email_dir = $theme_dir . '/ultimate-member/email/';
    $template_file = $email_dir . 'weekly_pending_notification.php';
    
    // Create directories if they don't exist
    if (!file_exists($email_dir)) {
        wp_mkdir_p($email_dir);
        um_weekly_log('Created email template directory: ' . $email_dir);
    }
    
    // Create template file if it doesn't exist
    if (!file_exists($template_file)) {
        $default_template_content = '<div style="max-width: 560px; padding: 20px; background: #ffffff; border-radius: 5px; margin: 40px auto; font-family: Open Sans,Helvetica,Arial; font-size: 15px; color: #666;">
    <div style="color: #444444; font-weight: normal;">
        <div style="text-align: center; font-weight: 600; font-size: 26px; padding: 10px 0; border-bottom: solid 3px #eeeeee;">{site_name}</div>
        <div style="clear: both;"> </div>
    </div>
    
    <div style="padding: 0 30px 30px 30px; border-bottom: 3px solid #eeeeee;">
        <div style="padding: 30px 0; font-size: 24px; text-align: center; line-height: 40px;">Weekly notification: {pending_count} user account(s) are waiting for approval on {site_name}.</div>
        <div> </div>
        <div style="padding: 10px 0 50px 0; text-align: center;">To review pending users, click: <a style="color: #3ba1da; text-decoration: none;" href="{admin_url}">{admin_url}</a></div>
    </div>
    <div> </div>
    
    <div style="padding: 0 30px 30px 30px; border-bottom: 3px solid #eeeeee;">
        <div style="padding: 0 0 15px 0;">
            <div style="background: #eee; color: #444; padding: 12px 15px; border-radius: 3px; font-weight: bold; font-size: 16px;">Pending Users List:<br /><br />{pending_users_list}</div>
        </div>
    </div>
    
    <div style="color: #999; padding: 20px 30px;">
        <div>Thank you!</div>
        <div>{site_name} Administration Team</div>
        <div style="margin-top: 15px; font-size: 12px;">
            <strong>Available Placeholders:</strong><br>
            • {site_name} - Website name<br>
            • {pending_count} - Number of pending users<br>
            • {pending_users_list} - List of pending users<br>
            • {admin_url} - Direct link to review users
        </div>
    </div>
</div>';

        // Write the template file
        if (file_put_contents($template_file, $default_template_content)) {
            um_weekly_log('Default email template file created: ' . $template_file);
        } else {
            um_weekly_log('Failed to create email template file: ' . $template_file);
        }
    }
    
    // Also set the subject in options (this part still uses options)
    $subject = UM()->options()->get('weekly_pending_notification_sub');
    if (empty($subject)) {
        $default_subject = '[{site_name}] {pending_count} pending user account(s) need review';
        UM()->options()->update('weekly_pending_notification_sub', $default_subject);
        um_weekly_log('Default email subject set: ' . $default_subject);
    }
    
    // Make sure the email notification is enabled by default
    $email_on = UM()->options()->get('weekly_pending_notification_on');
    if ($email_on === '' || is_null($email_on)) {
        UM()->options()->update('weekly_pending_notification_on', 1);
        um_weekly_log('Email notification enabled by default');
    }
}

// Clear scheduled event on plugin deactivation
register_deactivation_hook(__FILE__, 'um_weekly_pending_deactivate');
function um_weekly_pending_deactivate() {
    $timestamp = wp_next_scheduled('um_pending_notify_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'um_pending_notify_cron');
        um_weekly_log('Cron unscheduled');
    }
}

// Hook for cron
add_action('um_pending_notify_cron', 'um_pending_notify_do');

// Add UM email template
add_filter('um_email_notifications', 'um_weekly_add_email_template');
function um_weekly_add_email_template($emails) {
    $emails['weekly_pending_notification'] = array(
        'key' => 'weekly_pending_notification',
        'title' => __('Weekly Pending Users Notification', 'ultimate-member'),
        'subject' => '[{site_name}] {pending_count} pending user account(s) need review',
        'body' => 'Hello Administrator,<br><br>

There are <strong>{pending_count}</strong> user account(s) pending approval on {site_name}.<br><br>

<strong>Pending Users:</strong><br>
{pending_users_list}<br>

Please review these accounts by visiting the admin panel:<br>
<a href="{admin_url}">Review Pending Users</a><br><br>

This is an automated weekly notification.<br><br>

Best regards,<br>
{site_name}',
        'description' => __('Email sent weekly to administrators about pending user accounts', 'ultimate-member'),
        'recipient' => 'admin',
        'default_active' => true
    );
    return $emails;
}

// Perform pending account notification
function um_pending_notify_do() {
    um_weekly_log('Starting pending accounts check...');
    
    // Check if Ultimate Member is active
    if (!class_exists('UM') || !function_exists('UM')) {
        um_weekly_log('Ultimate Member plugin not found or not active');
        return;
    }

    // Get all users with pending status
    $pending_users = get_users(array(
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'account_status',
                'value' => array('pending', 'awaiting_admin_review', 'awaiting_admin_approval'),
                'compare' => 'IN'
            ),
            array(
                'key' => 'um_account_status',
                'value' => array('pending', 'awaiting_admin_review', 'awaiting_admin_approval'),
                'compare' => 'IN'
            )
        )
    ));

    $pending_count = count($pending_users);
    
    if ($pending_count === 0) {
        um_weekly_log('No pending accounts found.');
        return;
    }

    // Build pending users list for email template
    $pending_users_list = '';
    foreach ($pending_users as $user) {
        $user_info = get_userdata($user->ID);
        $pending_users_list .= sprintf("• %s (%s) - Registered: %s<br>", 
            esc_html($user_info->display_name), 
            esc_html($user_info->user_email), 
            date('Y-m-d', strtotime($user_info->user_registered))
        );
    }

    // Get administrators
    $admins = get_users(array('role' => 'administrator'));
    
    // Check if UM email template is enabled
    $email_on = UM()->options()->get('weekly_pending_notification_on');
    
    if ($email_on) {
        // Add filters for custom placeholders before sending emails
        add_filter('um_template_tags_patterns_hook', 'um_weekly_add_placeholders');
        add_filter('um_template_tags_replaces_hook', 'um_weekly_replace_placeholders');
        
        // Store data globally for the filters
        global $um_weekly_email_data;
        $um_weekly_email_data = array(
            'pending_count' => $pending_count,
            'pending_users_list' => $pending_users_list,
            'admin_url' => admin_url('users.php?um_user_status=awaiting_admin_review'),
            'site_name' => get_bloginfo('name')
        );
        
        // Send using UM's email system
        foreach ($admins as $admin) {
            try {
                // Send the email using UM's mail system
                UM()->mail()->send($admin->user_email, 'weekly_pending_notification');
                
                um_weekly_log('UM email sent to admin: ' . $admin->user_email);
            } catch (Exception $e) {
                um_weekly_log('UM email failed for ' . $admin->user_email . ': ' . $e->getMessage());
                
                // Fallback to wp_mail
                um_weekly_send_fallback_email($admin, $pending_count, $pending_users_list);
            }
        }
        
        // Remove filters after sending
        remove_filter('um_template_tags_patterns_hook', 'um_weekly_add_placeholders');
        remove_filter('um_template_tags_replaces_hook', 'um_weekly_replace_placeholders');
        
    } else {
        // Use fallback email if UM email template is disabled
        foreach ($admins as $admin) {
            um_weekly_send_fallback_email($admin, $pending_count, $pending_users_list);
        }
    }

    um_weekly_log($pending_count . ' pending accounts found. Notifications sent to ' . count($admins) . ' admin(s).');
}

// Add custom placeholders to UM's template system
function um_weekly_add_placeholders($placeholders) {
    $placeholders[] = '{pending_count}';
    $placeholders[] = '{pending_users_list}';
    $placeholders[] = '{admin_url}';
    $placeholders[] = '{site_name}';
    return $placeholders;
}

// Replace custom placeholders with actual data
function um_weekly_replace_placeholders($replacements) {
    global $um_weekly_email_data;
    
    if (isset($um_weekly_email_data)) {
        $replacements[] = $um_weekly_email_data['pending_count'];
        $replacements[] = $um_weekly_email_data['pending_users_list'];
        $replacements[] = $um_weekly_email_data['admin_url'];
        $replacements[] = $um_weekly_email_data['site_name'];
    } else {
        // Fallback values
        $replacements[] = '0';
        $replacements[] = 'No pending users';
        $replacements[] = admin_url('users.php');
        $replacements[] = get_bloginfo('name');
    }
    
    return $replacements;
}

// Fallback email function
function um_weekly_send_fallback_email($admin, $pending_count, $pending_users_list) {
    $site_name = get_bloginfo('name');
    $admin_url = admin_url('users.php?um_user_status=awaiting_admin_review');
    
    $subject = sprintf('[%s] %d pending user account(s) need review', $site_name, $pending_count);
    
    $message = "Hello " . $admin->display_name . ",\n\n";
    $message .= sprintf("There are %d user account(s) pending approval on %s.\n\n", $pending_count, $site_name);
    $message .= "Pending users:\n";
    $message .= strip_tags(str_replace('<br>', "\n", $pending_users_list));
    $message .= "\nPlease review these accounts at: " . $admin_url . "\n\n";
    $message .= "Best regards,\n" . $site_name . " Administration Team";
    
    wp_mail($admin->user_email, $subject, $message);
    um_weekly_log('Fallback email sent to admin: ' . $admin->user_email);
}

// Logging function
function um_weekly_log($message) {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/um-weekly-pending.log';
    $log_entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    
    // Ensure the uploads directory is writable
    if (is_writable($upload_dir['basedir'])) {
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Add admin menu for viewing logs and settings
add_action('admin_menu', 'um_weekly_pending_admin_menu');
function um_weekly_pending_admin_menu() {
    add_options_page(
        'UM Weekly Pending Settings',
        'UM Weekly Pending',
        'manage_options',
        'um-weekly-pending',
        'um_weekly_pending_admin_page'
    );
}

// Display admin page with settings and logs
function um_weekly_pending_admin_page() {
    // Handle form submissions
    if (isset($_POST['clear_um_log']) && current_user_can('manage_options')) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/um-weekly-pending.log';
        if (file_exists($log_file)) {
            unlink($log_file);
            echo '<div class="updated notice"><p>Log cleared successfully.</p></div>';
        }
    }
    
    if (isset($_POST['test_notification']) && current_user_can('manage_options')) {
        um_pending_notify_do();
        echo '<div class="updated notice"><p>Test notification sent! Check the log below.</p></div>';
    }
    
    if (isset($_POST['reschedule_cron']) && current_user_can('manage_options')) {
        // Get form data
        $cron_day = sanitize_text_field($_POST['cron_day']);
        $cron_time = sanitize_text_field($_POST['cron_time']);
        
        // Validate inputs
        $valid_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        if (!in_array($cron_day, $valid_days)) {
            $cron_day = 'Monday';
        }
        
        // Validate time format (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $cron_time)) {
            $cron_time = '10:00';
        }
        
        // Clear existing schedule
        $timestamp = wp_next_scheduled('um_pending_notify_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'um_pending_notify_cron');
        }
        
        // Calculate next occurrence
        $next_occurrence = strtotime("next {$cron_day} {$cron_time}");
        
        // Reschedule with new settings
        wp_schedule_event($next_occurrence, 'weekly', 'um_pending_notify_cron');
        
        // Save settings
        update_option('um_weekly_pending_cron_day', $cron_day);
        update_option('um_weekly_pending_cron_time', $cron_time);
        
        echo '<div class="updated notice"><p>Cron job rescheduled for ' . $cron_day . ' at ' . $cron_time . '.</p></div>';
    }
    
    if (isset($_POST['reset_email_template']) && current_user_can('manage_options')) {
        // Force reset the email template to defaults
        $theme_dir = get_stylesheet_directory();
        $template_file = $theme_dir . '/ultimate-member/email/weekly_pending_notification.php';
        
        // Remove existing template file if it exists
        if (file_exists($template_file)) {
            unlink($template_file);
            um_weekly_log('Existing email template file removed: ' . $template_file);
        }
        
        // Clear subject from options
        UM()->options()->update('weekly_pending_notification_sub', '');
        
        // Re-initialize with default content
        um_weekly_set_default_email_content();
        echo '<div class="updated notice"><p>Email template reset to default content. Check UM Email Settings.</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>UM Weekly Pending Settings</h1>
        
        <div class="card">
            <h2>Plugin Status</h2>
            <table class="form-table">
                <tr>
                    <th>Ultimate Member Status:</th>
                    <td><?php echo class_exists('UM') ? '<span style="color: green;">✓ Active</span>' : '<span style="color: red;">✗ Not Found</span>'; ?></td>
                </tr>
                <tr>
                    <th>Next Scheduled Run:</th>
                    <td>
                        <?php 
                        $next_run = wp_next_scheduled('um_pending_notify_cron');
                        if ($next_run) {
                            echo date('Y-m-d H:i:s', $next_run) . ' (' . human_time_diff($next_run, current_time('timestamp')) . ')';
                        } else {
                            echo '<span style="color: red;">Not scheduled</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Current Pending Users:</th>
                    <td>
                        <?php
                        if (class_exists('UM')) {
                            $pending_users = get_users(array(
                                'meta_query' => array(
                                    'relation' => 'OR',
                                    array(
                                        'key' => 'account_status',
                                        'value' => array('pending', 'awaiting_admin_review', 'awaiting_admin_approval'),
                                        'compare' => 'IN'
                                    ),
                                    array(
                                        'key' => 'um_account_status',
                                        'value' => array('pending', 'awaiting_admin_review', 'awaiting_admin_approval'),
                                        'compare' => 'IN'
                                    )
                                )
                            ));
                            echo count($pending_users);
                        } else {
                            echo 'N/A (UM not active)';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Email Template Status:</th>
                    <td>
                        <?php 
                        $email_on = UM()->options()->get('weekly_pending_notification_on');
                        $subject = UM()->options()->get('weekly_pending_notification_sub');
                        
                        // Check if template file exists
                        $theme_dir = get_stylesheet_directory();
                        $template_file = $theme_dir . '/ultimate-member/email/weekly_pending_notification.php';
                        $template_exists = file_exists($template_file);
                        
                        if ($email_on) {
                            echo '<span style="color: green;">✓ Enabled</span>';
                        } else {
                            echo '<span style="color: orange;">⚠ Disabled</span>';
                        }
                        
                        if (empty($subject)) {
                            echo ' <span style="color: red;">(Subject missing)</span>';
                        }
                        
                        if (!$template_exists) {
                            echo ' <span style="color: red;">(Template file missing)</span>';
                        } else {
                            echo ' <span style="color: green;">(Template file exists)</span>';
                        }
                        ?>
                        <br><small><a href="<?php echo admin_url('admin.php?page=um_options&tab=email&email=weekly_pending_notification'); ?>" target="_blank">Configure in UM Email Settings →</a></small>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>Available Email Template Placeholders</h2>
            <p>When customizing your email template in UM Settings, you can use these placeholders:</p>
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 3px; font-family: monospace;">
                <strong>Available Placeholders:</strong><br>
                • <code>{site_name}</code> - Your website name<br>
                • <code>{pending_count}</code> - Number of pending users<br>
                • <code>{pending_users_list}</code> - List of pending users with details<br>
                • <code>{admin_url}</code> - Direct link to review pending users<br>
                • <code>{logo}</code> - Site logo URL
            </div>
            <p><small>Copy and paste these placeholders into your email template. They will be automatically replaced with actual values when emails are sent.</small></p>
        </div>

        <div class="card">
            <h2>Cron Job Schedule</h2>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">Day of Week</th>
                        <td>
                            <select name="cron_day">
                                <?php 
                                $saved_day = get_option('um_weekly_pending_cron_day', 'Monday');
                                $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
                                foreach ($days as $day) {
                                    echo '<option value="' . $day . '"' . selected($saved_day, $day, false) . '>' . $day . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Time</th>
                        <td>
                            <input type="time" name="cron_time" value="<?php echo esc_attr(get_option('um_weekly_pending_cron_time', '10:00')); ?>" />
                            <p class="description">24-hour format (e.g., 10:00 for 10:00 AM, 14:30 for 2:30 PM)</p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="reschedule_cron" value="1">
                <input type="submit" class="button button-primary" value="Update Cron Schedule">
            </form>
        </div>

        <div class="card">
            <h2>Actions</h2>
            <form method="post" style="display: inline;">
                <input type="hidden" name="test_notification" value="1">
                <input type="submit" class="button button-primary" value="Send Test Notification" title="This will send test emails to all administrators on the site, not to recipients configured in UM email template settings.">
            </form>
            
            <form method="post" style="display: inline; margin-left: 10px;">
                <input type="hidden" name="reset_email_template" value="1">
                <input type="submit" class="button button-primary" value="Reset Email Template" title="This will reset the email template file and subject to default content. Any customizations will be lost." onclick="return confirm('This will reset the email template to default content. Continue?');">
            </form>
        </div>

        <div class="card">
            <h2>Recent Log Entries</h2>
            <?php
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/um-weekly-pending.log';

            if (file_exists($log_file)) {
                $log_lines = file($log_file);
                $recent_logs = array_slice(array_reverse($log_lines), 0, 20); // Show last 20 entries
                
                echo '<div style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;">';
                foreach ($recent_logs as $line) {
                    echo '<div style="font-family: monospace; font-size: 12px;">' . esc_html(trim($line)) . '</div>';
                }
                echo '</div>';
                
                echo '<form method="post" style="margin-top: 10px;">';
                echo '<input type="hidden" name="clear_um_log" value="1">';
                echo '<input type="submit" class="button button-secondary" value="Clear Log" onclick="return confirm(\'Are you sure you want to clear the log?\');">';
                echo '</form>';
            } else {
                echo '<p>No log file found yet. The plugin will create one when it runs for the first time.</p>';
            }
            ?>
        </div>
    </div>
    <?php
}