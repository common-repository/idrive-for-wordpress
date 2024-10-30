=== IDrive for Wordpress ===
Contributors: idrivee
Donate link: http://www.idrive.com
Tags: idrive, online backup, backup, online storage
Requires at least: 2.8.5
Tested up to: 3.2.1
Stable tag: 1.2.1

Backup your Wordpress blog data into IDrive online account.

== Description ==

IDrive Plug-in for Wordpress is an easy to use backup utility, designed specifically 
to backup Wordpress blog data into your IDrive online backup account. It provides 
scheduled as well as immediate backup of Wordpress blog data including files and MySQL database dump. 

Install and activate the IDrive Plug-in for Wordpress. Through the Plug-in, 
create a new IDrive online backup account to start backing up your Wordpress data.

Features

* Immediate backup of Wordpress blog data, both files as well as MySQL data dump, into your IDrive online backup account
* Scheduled backups occur after 12 midnight every day
* Immediate restore of backed up data from your IDrive online backup account
* Smart backup - Only the first backup transfers entire Wordpress content, subsequent backups are incremental where only the modified data is backed up
* Easy restore - Restore via plugin, Web Interface or [Windows Restore application](http://www.idrive.com/wordpress.htm  "IDrive Website") . Snapshots allow restoring historical data.
* Automatic notification via email on backup / restore status
* Secure transfer of data to IDrive server using SSL. Non SSL transfer option is also available for non SSL servers
* Detailed logging of backup / restore operations

System Requirement

* Wordpress 2.8.5 or higher hosted on a Linux / Unix system. Wordpress blog hosted on a Windows system is not supported
* PHP 5.1 or higher
* Javascript enabled web browser - IE, Safari, Firefox, Chrome
* Admin access to Wordpress dashboard to activate the Plug-in

== Installation ==

1. Unzip and upload contents of file `idrive-for-wordpress.XXX.zip` into the `/wp-content/plugins/` directory or use wordpress to install the plugin directly
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Select 'IDrive' from 'Settings' menu in Wordpress
1. Create an IDrive for Wordpress account using 'Create New IDrive Account'

Note: IDrive For Wordpress works only with PHP 5.1 or higher. PHP 4 is not supported.

== Frequently Asked Questions ==

= Can I use a regular IDrive online backup account to backup Wordpress data? =

No, You need to install and activate the Wordpress Plug-in. Through the Plug-in, create a new IDrive online backup account and start backup of your Wordpress data.

= How many wordpress blogs can be used per IDrive account? =

You can backup as many blogs as you want to the same IDrive account as long as each blog has a different blog name. Data from each blog will go into individual directories identified by the blog name.

= What data is backed up by IDrive Plug-in for Wordpress and where? =

IDrive Plug-in for Wordpress backs up all files under Wordpress installation directory, including all themes, pictures, Plug-in's etc. All these files are backed up into /your_blog_name/ directory inside your IDrive account.
Apart from the Wordpress files, MySQLdump of entire Wordpress database is also backed up. The dump file, IDW_WP_MySQL_dump.sql is backed up into /your_blog_name/ directory.

= When does scheduled backup take place? Is backup schedule configurable? =

Backup Schedule is not configurable. The IDrive Plug-in for Wordpress uses the Wordpress cron to schedule backups, which happen after 12 midnight every day.

= How do I restore backed up data? =

Data backed up by the IDrive Plug-in for WordPress can be restored in the following ways:

* Use the 'Restore Now' button in the Plug-in to restore only the latest backed up data into the server where your WordPress blog is hosted. All the restored files will go under the wordpress_restore/ directory inside the installation directory of IDrive Plug-in for WordPress. Once restore is complete, view logs to find the complete path of the restore location.
* Use the IDrive web interface to download individual files/database dump file (IDW_WP_MySQL_dump.sql) from your IDrive online backup account. For this, click 'Visit IDrive Web' button in the Plug-in to directly log into your IDrive online backup account via the IDrive web interface or visit www.idrive.com and login using your IDrive for Wordpress Plug-in user name and password.
* Use the **IDrive for WordPress - Windows Restore** application to download individual files / database dump file (IDW_WP_MySQL_dump.sql) from your IDrive for WordPress account.

= How do I restore MySQL database dump? =

IDrive Plug-in for Wordpress takes the MySQLdump of entire Wordpress database into the file IDW_WP_MySQL_dump.sql and transfers it to /your_blog_name/ directory in your IDrive online backup account. To restore MySQL database, download the file using the IDrive web interface, or use IDrive for Wordpress Plug-in and merge the dump file into your database using the below command:

mysql -u [username] -p [password] [database_to_restore] < IDW_WP_MySQL_dump.sql

= How do I disable scheduled backups? =

Scheduled backups work only if you stay logged in to your IDrive online backup account using the IDrive for Wordpress Plug-in. To disable scheduled backups, log off from the Plug-in using the 'LOGOUT' button.

= How do I use the SSL option for file transfer? =

IDrive for Wordpress Plug-in by default does not use SSL to transfer Wordpress data to the IDrive online backup account. In the configuration interface of the Plug-in, select 'Use SSL' under 'File Transfer Option' to enable SSL transfer. However, for this to work, PHP on the blog hosting server must be compiled with Open SSL extension. If you select 'Use SSL', but SSL support is not available on the blog server, IDrive Plug-in for Wordpress will not transfer any data from your blog server into your IDrive online backup account.
'Do not use SSL', as the name suggests, transfers data to you IDrive online backup account without SSL.

= How do I restore my blog to a previous date? =

You can use the IDrive for Wordpress - Windows Restore application to restore your blog to any date upto 10* days in the past.
Download and install the IDrive for Wordpress - windows restore application onto a windows system and login using your
IDrive for Wordpress username and password. Click on "Snapshots" button and restore data from any available snapshot onto your
windows system. Trasnfer the restore data onto you blog server to restore your blog site to a previous date.

Note: .htaccess/.htpasswd files are automatically renamed to htaccess/htpasswd during backup. You have to 
manually rename them to .htaccesss/.htpasswd on your blog server after restore.

Snapshots are also available via the IDrive web interface at www.idrive.com

\* The number of days for which Snapshots are stored may change without notice.

= What are snapshots? =

Your IDrive for WordPress online account retains copies of your wordPress data for the last 10*  days using the snapshots technology. A snapshot is an image of the backup data taken at a particular point of time, once everyday.

What this means is that you will have the last 10* days of your wordPress data in your account in the form of snapshots. Each snapshot will have data from the last backup performed for that particular day. This is useful if your wordPress blog data is corrupted by any means (hacks, bugs etc). You can restore any file, database dump, or even the entire blog from any previous snapshot and start your blog again.

Snapshots show up as folders having names like 'BackUp_nightly.0_', 'BackUp_nightly.9_' etc. 'BackUp_nightly.0' represents yesterday's snapshot and 'BackUp_nightly.9' represents the snapshot taken 10 days back.

The additional storage requirements for Snapshots have no impact on your account quota usage.

\* The number of days for which Snapshots are stored may change without notice.

== Screenshots ==

1. Login
2. Main User Interface
3. View Logs

== Changelog ==

= 1.2.1 =
* Support for mysqldump --port option

= 1.2 =
* Support for 5 GB free account.
* Bug fix to allow mysqldump with password containg special characters.

= 1.1.1 =
* Fixed bug to allow database backup on sites where DB_HOST is a socket file.
* Check for non existant exclusion_list index to avoid php NOTICE

= 1.1.0 =
* Implemented exclusion list
* Check for non existant soft link targets.
* Fixed issue with backup when wordpress 'siteurl' and 'home' are different

= 1.0.7 =
* More checks and error reporting for mysql backup
* Updated snapshots

= 1.0.6 =
* CPU Resource utilization optimization during backup
* Increased log capacity

= 1.0.5 =
* Introduced error checking for database updates

= 1.0.4 =
* Bug fix for blog name with single quote
* Javascript check

= 1.0.3 =
* Bug fixes for incremental file transfer
* Introduced hosting server OS check and PHP version check
* Check for IDrive windows account login.
* Automatically resume backup during next scheduled backup if current backup doesn't finish gracefully.
* Allow non SSL transfer by default.

= 1.0.2 =
* Updated files sent per batch to 5 and script timeout to 45 seconds for mod_fcgid timeout issue on slow shared hosting servers.

= 1.0.1 =
* Added feature to backup multiple blogs to same IDrive account
* Fixed .htaccess file backup
* Fixed php timeout issue with mod_fcgi configured servers
* Minor UI updates.

= 1.0.0 =
* First major release

= 0.1 =
* First release

== Upgrade Notice ==
* None