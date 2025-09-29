# Ultimate Member Weekly Pending Accounts Reminder
This plugin for WordPress and Ultimate Member helps administrators stay on top of user registrations by sending weekly email reminders about accounts awaiting approval.

## Features
* **Weekly Email Reminders**: Automatically sends a summary email every week listing all pending user accounts.
* **Customizable Email Content**: Easily modify the email template to suit your needs.
* **Seamless Integration**: Works with the Ultimate Member plugin to track and manage user registrations.

## Installation
1. Click Green Code button, and download as ZIP.
2. In your Wordpress Admin panel add new plugin and upload this zip. Activate it.
3. Use plugin and enjoy!

## Usage
Once activated, the plugin will create an email template in UM Email settings: Weekly Pending Users Notification - it's fully customizable in settings panel. 
It also created weekly cron job, default is Monday 10:00 (Local Time).
In Settings "UM Weekly Pending" option will appear for settings, logging and troubleshooting. You may change Cron Job schedule there.
Once configured - it will send a weekly email to the Weekly Pending Users Notification template recipients with a list of all pending user accounts.

Available Email Placeholders:
* {site_name} - Your website name
* {pending_count} - Number of pending users
* {pending_users_list} - List of pending users with details
* {admin_url} - Direct link to review pending users
* {logo} - Site logo URL


## Requirements
* WordPress version 5.0 or higher
* Ultimate Member plugin installed and activated

## License
This plugin is licensed under the BSD2.

## Change Log:
### Version 1.1:
* Improved Logging
* Added testing buttons
* Fixed template recipients usage (instead of site admins)
* Minor improvements


### Version 1.0
* Necessity pushed me to create this plugin

---

This plugin is ideal for administrators who want to ensure timely review and approval of user registrations. By receiving weekly reminders, you can maintain an organized and efficient user management process.
