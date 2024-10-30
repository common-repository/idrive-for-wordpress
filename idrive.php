<?php
/*
 Plugin Name: IDrive for Wordpress
 Plugin URI: http://www.idrive.com/wordpress.htm
 Version: 1.2.1
 Author: IDrive Team
 Author URI: http://www.idrive.com
 Description: IDrive Plugin For Wordpress. Backs up complete Wordpress data - files as well as mysql contents - into user's personal 5GB IDrive Account. This is provided for FREE by IDrive - an online data backup service. <a href="options-general.php?page=idrive.php"> Click here </a> to get started after enabling the plugin.
 */

error_reporting(error_reporting() & ~E_NOTICE);

global $wpdb;
DEFINE('LOGS_TABLE', $wpdb->prefix . "idw_logs");
DEFINE('BACKUPSET_TABLE', $wpdb->prefix . "idw_backupset");
DEFINE('RESTORESET_TABLE', $wpdb->prefix . "idw_restoreset");

if ( !class_exists('IDriveWpPlugin') ) {
    
class IDriveWpPlugin {
    static $adminOptionName = "IDriveAdminOptions";
    
    private $wdc = null;

    private $errorMsg;

    /**
     * This is the activation hook
     * Initialize all database and other
     * options etc. here. This is registered
     * with register_activation_hook at the
     * end of the file
     */
    public function activate() {
        global $wpdb;
        // initialize the admin options
        $adminOptions = array (
                               'logged_in' => false,
                               'plugin_dir' => IDriveWpPlugin::TFP(ABSPATH . 'wp-content/plugins/' . dirname(plugin_basename(__FILE__))),
                               'plugin_url' => get_settings('siteurl').'/wp-content/plugins/'.dirname(plugin_basename(__FILE__)),
                               'idrive_username' => '',
                               'idrive_password' => '',
                               'username' => '',
                               'password' => '',
                               'total_quota' => 0,
                               'used_quota' => 0,
                               'webdav_server' => '',
                               'email_notification' => false,
                               'must_use_ssl' => false,
                               'backup_in_progress' => false, 
                               'total_files' => 0,
                               'num_files_to_backup' => 0,
                               'num_files_backup_success' => 0,
                               'last_backup_start_time' => 'No backups started!',
                               'last_backup_end_time' => 'No backups ended!',   
                               'last_backup_status' => -1,
                               'remote_backup_location' => '/wordpress_backup/',
                               'restore_in_progress' => false, 
                               'num_files_to_restore' => 0,
                               'num_files_restore_success' => 0,
                               'last_restore_start_time' => 'No restore started!',
                               'last_restore_end_time' => 'No restore ended!',  
                               'last_restore_status' => -1,
                               'backup_stage' => 0,
                               'backup_log_id' => -1,
                               'restore_stage' => 0,
                               'restore_log_id' => -1, 
                               'quota_recalculating' => false,
                               'last_file_transfer_time' => 0,
                               'exclusion_list' => array()
        );
        
        // update 1.0.1
        // support for multiple blogs to same account
        // use blog name for backup
        $blog_name = htmlspecialchars_decode(get_option('blogname'), ENT_QUOTES);
        if ( ! is_null ($blog_name) ){
                $blog_name = preg_replace('/\s/', '_', $blog_name);
                $adminOptions['remote_backup_location'] = "/$blog_name/";
        }
        update_option(IDriveWpPlugin::$adminOptionName, $adminOptions);

        // create database tables;
        // 1. logs table
        $query = "CREATE TABLE IF NOT EXISTS " .
        LOGS_TABLE . " (id smallint unsigned auto_increment,
                                            type varchar(20), 
                                            date_time varchar(64) not null, 
                                            details text, 
                                            summary varchar(100), 
                                            result smallint, 
                                            primary key(id) );";
        if ( $wpdb->query($wpdb->prepare($query)) === false ) {
            error_log("IDrive plugin for Wordpress: Could not create " . LOGS_TABLE);
        }
        else {
            $log = array('type' => 'install',
                         'date_time' => date(DATE_RFC822),
                         'details' => 'IDrive plugin for Wordpress activated succesfully.',
                         'summary' => 'IDrive plugin for Wordpress activated succesfully.',
                         'result' => 0);
            $wpdb->insert(LOGS_TABLE, $log);

            // 1.0.6 - increased log size
            $query = "ALTER TABLE " . LOGS_TABLE . " MODIFY details LONGTEXT";
            $wpdb->query($wpdb->prepare($query));
        }

        //2. backupset, list of files to backup
        $query = "CREATE TABLE IF NOT EXISTS " .
        BACKUPSET_TABLE . " (id SMALLINT UNSIGNED AUTO_INCREMENT,
                                            file_name VARCHAR(4096), 
                                            backup_timestamp BIGINT NOT NULL DEFAULT 0, 
                                            do_backup boolean not null default false, 
                                            primary key(id));";
        if ( $wpdb->query($wpdb->prepare($query)) === false ) {
            error_log("IDrive plugin for Wordpress: Could not create " . BACKUPSET_TABLE);
        }
        else {
            $query = "UPDATE " . BACKUPSET_TABLE . " SET backup_timestamp = 0;";
            $wpdb->query($wpdb->prepare($query));
        }

        //3. restoreset, list of files to restore
        $query = "CREATE TABLE IF NOT EXISTS " .
        RESTORESET_TABLE . " (id SMALLINT UNSIGNED AUTO_INCREMENT,
                                            file_name VARCHAR(4096), 
                                            do_restore boolean not null default false, 
                                            primary key(id));";
        if ( $wpdb->query($wpdb->prepare($query)) === false ) {
            error_log("IDrive plugin for Wordpress: Could not create " . RESTORESET_TABLE);
        }
    }

    /**
     * This method loads the header for the admin page
     * This is registerd in adminMenu() function to
     * call it automatically before loading the options page
     */
    public static function loadOptionsPageHeader() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        wp_enqueue_script('idw_validate', $adminOptions['plugin_url'] . "/js/idw_validate.js");
        wp_enqueue_script('idw_jquery', $adminOptions['plugin_url'] . "/js/idw_jquery.js");
        wp_enqueue_script('idw_tt', $adminOptions['plugin_url'] . "/js/tt_script.js");
        wp_enqueue_script('idw_misc', $adminOptions['plugin_url'] . "/js/misc.js");
    }
    
    /**
     * This adds the admin menu
     * This is registered with add_action() for
     * 'admin_menu' later at the end of the file
     */
    public function adminMenu(){
        global $idw_plugin;
        if ( !isset($idw_plugin) ){
            $idw_plugin = new IDriveWpPlugin();
        }

        // add the menu item under settings for the options page
        $optionsPageHook = add_options_page('IDrive Online Backup', 'IDrive', 9, basename(__FILE__), array(&$idw_plugin, 'pluginOptionsPage'));
        // add the header into the options page
        add_action("admin_print_scripts-$optionsPageHook", array(&$idw_plugin, 'loadOptionsPageHeader'));
    }


    /**
     * This creates the options page for the plugin
     *  depending on whether the user has logged in or not.
     */
    public function pluginOptionsPage() {
        // 1.0.3 check for operating system
        if ( preg_match('/windows/i', php_uname('s') ) ) {
            print ("<h3>Your wordpress blog seems to be hosted on a Windows server. IDrive Plug-in for Wordpress ".
                   "does not support Wordpress blogs hosted on a Windows environment. </h3>");
            die();
        }
        // 1.0.3 check for version
        if ( ! version_compare(phpversion(), '5.1', 'ge') ) {
            print ("<h3>PHP version on your server is " . phpversion() . ". IDrive Plug-in for Wordpress " .
                   "requires PHP version 5.1 or higher to function properly </h3>" );
            die();
        }

        // process login form
        if ( isset($_POST['idw_login']) && $_POST['idw_login'] == 'true' ){
            $this->doLogin();
        }
            
        // process logout
        else if ( isset($_POST['idw_logout']) ){
            $this->doLogout();
        }
            
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);

        if ( $adminOptions['logged_in'] ) {
            require(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/php/afterlogin.php'));
        }
        else {
            require(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/php/beforelogin.php'));
        }
    }

    /**
     * 
     */
    public function doLoginToIDriveWeb() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        
        print $adminOptions['idrive_username'] . '|||' . $adminOptions['idrive_password'];
        
        die();
    }
    
    /**
     * This method is called to login
     * This will also enable the scheduled backup and fill
     * up necessary information into database
     */
    private function doLogin() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
        
        $adminOptions['logged_in'] = false;
        $this->errorMsg = "";

        if ( isset($_POST['idrive_account_username']) && isset($_POST['idrive_account_password']) ){
            $username = $_POST['idrive_account_username'];
            $password = $_POST['idrive_account_password'];

            // login to idive.com
            $httpRequest = new WP_Http;
            $lU = 'kwws9,,ttt-jgqjuf-`ln,`dj.ajm,jgqjuff\dfw\jab`hvs\bmg\jgqjuf';
            $lU_ = "";
            for ( $i = 0; $i < strlen($lU); $i++ ){
                $lU_ .= chr(ord(substr($lU, $i, 1)) ^ 0x03);
            }

            $lU_ .= "?user=" . $username . "&passwd=" . $password;
            $httpResult = $httpRequest->request($lU_);

            if ( !is_wp_error($httpResult) && $httpResult['body'] != "" ){
                $resultParts = explode(":", $httpResult['body']);

                if ( $resultParts[0] == 'ibackup' ){
                    $adminOptions['idrive_username'] = $username;
                    $adminOptions['idrive_password'] = $password;
                    $adminOptions['username'] = $resultParts[1];
                    $adminOptions['password'] = $resultParts[2];
                    $adminOptions['total_quota'] = $resultParts[3];

                    // check for cancelled accounts
                    $pU = 'ossw=((ppp6)nefdlrw)dhj(d`n*eni(`bsXfddhrisXtsfsrt';
                    $pU_ = '';
                    for ($i = 0; $i < strlen($pU); $i++ ) {
                        $pU_ .= chr(ord(substr($pU, $i, 1)) ^ 0x07);
                    }
                    $pU_ .= '?user=' . $adminOptions['username'] . '&passwd=' . $adminOptions['password'];

                    unset($httpResult);
                    $httpResult = $httpRequest->request($pU_);

                    if ( !is_wp_error($httpResult) && $httpResult['body'] != "" ) {

                        $resultParts = explode (" ", $httpResult['body']);

                        if ( 'Cancelled' == $resultParts[0] ){
                            $this->errorMsg = "Error: You are trying to log-in using a cancelled IDrive account. " .
                                              "This account was cancelled on " . $resultParts[1] . ".";
                        }
                        else if ( 'Expired' == $resultParts[0] ){
                            $this->errorMsg = "Error: You are trying to log-in using an expired IDrive account. " .
                                              "This account expired on " . $resultParts[1] . ".";
                        }
                    }
                    else if ( !is_wp_error($httpResult) && $httpResult['body'] == "" ) {

                        $qU = 'mqqu?**rrr4+lgdfnpu+fjh*fbl(glk*b`qZijfdqljkZdiiZk`r';
                        $qU_ = '';
                        for ($i = 0; $i < strlen($qU); $i++ ) {
                            $qU_ .= chr(ord(substr($qU, $i, 1)) ^ 0x05);
                        }
                        $qU_ .= '?user=' . $adminOptions['username'] . '&passwd=' . $adminOptions['password'];

                        unset($httpResult);
                        $httpResult = $httpRequest->request($qU_);

                        if ( !is_wp_error($httpResult) && $httpResult['body'] != "" ) {
                            $resultParts = explode (" ", $httpResult['body']);

                            if ( preg_match('/(.*)(\.ibackup)(\.com)/', $resultParts[0], $matches) ){
                                $adminOptions['used_quota'] = ((int)$resultParts[4] * 1024);

                                $webdav_server = preg_replace('/www([0-9]*$)/', 'mac\\1', $matches[1]);
                                $webdav_server .= $matches[2] . $matches[3];
                                $adminOptions['webdav_server'] = $webdav_server;

                                $adminOptions['logged_in'] = true;
                                $adminOptions['backup_in_progress'] = false;
                                $adminOptions['restore_in_progress'] = false;

                                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                                    die();
                                }
                                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
                            }
                        }
                    }
                }
                else if ( $resultParts[0] == 'windows_user' ) {
                    $this->errorMsg = "Error: You are trying to log-in using an IDrive for Windows account." .
                                      " IDrive Plug-in for Wordpress does not support IDrive for Windows accounts." .
                                      " Please click on the <b>Create New IDrive Account</b> button below to create a new " .
                                      " IDrive account for Wordpress.";
                }
            }
        }

        if ( $adminOptions['logged_in'] === true ){
            // login successful. enable schedule here
            $dt=explode(':',date('d:m:Y:H:i:s',time()));
            $midnight=mktime(0,0,0,$dt[1],$dt[0]+1,$dt[2]);
            wp_clear_scheduled_hook('idw_cron_backup_hook');
            wp_schedule_event( $midnight, 'daily', 'idw_cron_backup_hook');
            
            unset($this->errorMsg);
        }
        else {
            if ( is_wp_error($httpResult)){
                $this->errorMsg = "An error occured. Please try again!";
            }else{
                if ( $this->errorMsg == "" ) {
                    $this->errorMsg = "Invalid username/password";
                }
            }
        }

        unset($httpRequest);
        unset($httpResult);
    }

    /*
     * This method is used to logout
     * This will remove all information from database like login
     * information etc and disable scheduled backup
     */
    private function doLogout() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

        // logout means we don't want backups to happen. disable everything
        // in database first
        $adminOptions['logged_in'] = false;
        $adminOptions['idrive_username'] = '';
        $adminOptions['idrive_password'] = '';
        $adminOptions['username'] = '';
        $adminOptions['password'] = '';
        $adminOptions['total_quota'] = 0;
        $adminOptions['used_quota'] = 0;
        $adminOptions['webdav_server'] = '';

        // disable backup schedule
        wp_clear_scheduled_hook('idw_do_backup_hook');
        wp_clear_scheduled_hook('idw_do_restore_hook');
        wp_clear_scheduled_hook('idw_cron_backup_hook');
        
        if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
            error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
            die();
        }
    }

    /**
     * This is used to set email notification option in database
     */
    public function setEmailNotification(){
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

        // update only if backup/restore is not in progress
        if ( $adminOptions['backup_in_progress'] ){
            print "Error : Backup in progress. Try again later.";
        }
        else if ( $adminOptions['restore_in_progress'] ) {
            print "Error : Restore in progress. Try again later.";
        }
        else {
            if ( $_POST['email_notification'] == 'true' ){
                $adminOptions['email_notification'] = true;
            }
            else{
                $adminOptions['email_notification'] = false;
            }

            if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                die();
            }
            print "Success.";
        }
        
        die();
    }

    public function addExclusion() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

        // update only if backup/restore is not in progress
        if ( $adminOptions['backup_in_progress'] ){
            print "Error : Backup in progress. Try again later.";
        }
        else if ( $adminOptions['restore_in_progress'] ) {
            print "Error : Restore in progress. Try again later.";
        }
        else {
            if ( $_POST['exclusion'] != '' ){
		        if ( ! isset($adminOptions['exclusion_list']) ) 
		            $adminOptions['exclusion_list'] = array();
 
                $index = array_search($_POST['exclusion'], $adminOptions['exclusion_list']);
                if ( $index !== FALSE ) {
                    print ("Error: Pattern already exists");
                    die();
                }

                $adminOptions['exclusion_list'][] = $_POST['exclusion'];

                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }

                print "Success.";
            }
            else {
                print ("Error: No pattern passed");
            }
        }
        
        die();
    }

    public function delExclusion() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

        // update only if backup/restore is not in progress
        if ( $adminOptions['backup_in_progress'] ){
            print "Error : Backup in progress. Try again later.";
        }
        else if ( $adminOptions['restore_in_progress'] ) {
            print "Error : Restore in progress. Try again later.";
        }
        else {
            if ( $_POST['exclusion'] != '' ){
                $index = array_search($_POST['exclusion'], $adminOptions['exclusion_list']);
                unset ($adminOptions['exclusion_list'][$index]);

                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                print "Success.";
            }
            else {
                print ("Error: No pattern passed");
            }
        }
        
        die();
    }

    private function sendEmailToIdriveUser($idrive_username, $subject, $msg) {
        $msg = urlencode($msg);
        $httpRequest = new WP_Http;
        
        $eU = 'jvvr8--uuu,kfpktg,amo-aek/`kl-kfpktgg]egv]wqgp]gockn]lgu,aek';
        $eU_ = "";
        for ( $i = 0; $i < strlen($eU); $i++ ){
                $eU_ .= chr(ord(substr($eU, $i, 1)) ^ 0x02);
        }
        $eU_ .= '?user=' . $idrive_username;

        $httpReply = $httpRequest->request($eU_);
        
        if (!is_wp_error($httpReply) && $httpReply['body'] != "") {
            $emailID = trim($httpReply['body']);
            
            $eU = 'ossw=((pbecfq)nefdlrw)dhj(d`n*eni(Ihsna~Xncunqb';
                $eU_ = "";
                for ( $i = 0; $i < strlen($eU); $i++ ){
                        $eU_ .= chr(ord(substr($eU, $i, 1)) ^ 0x07);
                }
                $eU_ .= '?toemail=' . $emailID . '&subject=' . $subject . '&content=' . $msg;
            
            $httpReply = $httpRequest->request($eU_);
            
            return $emailID;
        } else {
            return false;
        }
    }

    /**
     * This is used to set SSL notification option in database
     */
    public function setSSLOption(){
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

        // update only if backup/restore is not in progress
        if ( $adminOptions['backup_in_progress'] ) {
            print "Error : Backup in progress. Try again later.";
        }
        else if ( $adminOptions['restore_in_progress'] ) {
            print "Error : Restore in progress. Try again later.";
        }
        else {
            if ( $_POST['must_use_ssl'] == 'yes' )
            $adminOptions['must_use_ssl'] = true;
            else
            $adminOptions['must_use_ssl'] = false;
            
            if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                print ("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                die();
            }
            
            print "Success.";
        }
        
        die();
    }

    /**
     * This method is called when create new a/c button is pressed
     */
    public function createNewAccount() {
        global $wpdb;
        // get all the necessary variables
        if ( ! isset($_POST['username']) ){
            print "Error: Username not passed";
        }
        else if (! isset ($_POST['password']) ){
            print "Error: Password not passed";
        }
        else if (! isset($_POST['email_address']) ){
            print "Error: Email address not passed";
        }
        else if ( !isset ($_POST['firstname'])){
            print "Error: First Name not passed";
        }
        else if (! isset($_POST['lastname']) ){
            print "Error: Last Name not passed";
        }

        // create a/c
        $cU = 'ossw=((ppp)ncunqb)dhj(d`n*eni(fddhrist(dubfsbXncunqbXfddhrisXjfdX66ihq5767)d`n';
        $cu_ = "";
        for ( $i = 0; $i < strlen($cU); $i++ ){
            $cU_ .= chr(ord(substr($cU, $i, 1)) ^ 0x07);
        }
        $cU_ .= '?user=' . $_POST['username'] . '&passwd=' . $_POST['password'] . '&email=' . $_POST['email_address']
                . '&first=' . $_POST['firstname'] . '&last=' . $_POST['lastname'];
        $httpRequest = new WP_http();
        $httpResult = $httpRequest->request($cU_);

        if ( is_wp_error($httpResult) ) {
            print "An error occured. Please try again later!";
        }
        else if ( preg_match('/(error *)(.*)/i', $httpResult['body'], $matches) ){
            if ( preg_match('/duplicate username/i', $matches[2]) ){
                print ("Error: Username already exists");
            }
            else if (preg_match('/Invalid characters in Username/i', $matches[2])){
                print ("Error: Invalid character in username");
            }
            else {
                print ("Error: Please try again later");
            }
        }
        else if ( preg_match('/Account Creation Successful/i', $httpResult['body']) ){
            // cleanup bacukpset
            $query = "UPDATE " . BACKUPSET_TABLE . " SET backup_timestamp = 0;";
            $wpdb->query($wpdb->prepare($query));
            print "Success";
        }
        else {
            print "Error: unknown error";
        }
        
        die();
    }

    /*
     * This method is called when backup now button is clicked.
     * This is for initiating immediate backup
     * It will check if backup is already
     * in progress, and if not, initiate backup
     */
    public function backupNow() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

        // 1.0.3 - force next backup if current file transfer was killed by server
        $oneDay = 60 * 60 * 23;
        $lastFileTransferTime = time() - $adminOptions['last_file_transfer_time'];
        $forceBackup = ($lastFileTransferTime > $oneDay); 
        
        if ( $adminOptions['backup_in_progress'] && ! $forceBackup ){
            print ("Error : Backup is already in progress.");
        }
        else if ( $adminOptions['restore_in_progress'] && ! $forceBackup ) {
            print ("Error: Restore is in progress. Try backup after restore finishes.");
        }
        else {
            $adminOptions['backup_stage'] = 0;
            
            if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                die();
            }
            
            $this->doBackup();
        }
        
        die();
    }

    /**
     * This method is called by WP cron when schedule expires
     * this will check if backup is already in progress,
     * if not, it will start doBackup
     */
    public function cronBackup() {
        global $wpdb;

        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

        // 1.0.3 fix to force next backup if current file transfer was killed by server
        $oneDay = 60 * 60 * 23;
        $lastFileTransferTime = time() - $adminOptions['last_file_transfer_time'];
        $forceBackup = ($lastFileTransferTime > $oneDay); 
        
        if ( $adminOptions['backup_in_progress'] && ! $forceBackup ) {
            $dateString = date(DATE_RFC822);
            $log = array('type' => 'backup',
                         'date_time' => $dateString,
                         'details' => 'Could not start Scheduled Wordpress backup to IDrive as another Backup is already in progress.',
                         'summary' => 'Scheduled backup failed.',
                         'result' => 1);
            $wpdb->insert(LOGS_TABLE, $log);
            $wpdb->insert_id;
        }
        else if ( $adminOptions['restore_in_progress'] && ! $forceBackup ) {
            $dateString = date(DATE_RFC822);
            $log = array('type' => 'backup',
                         'date_time' => $dateString,
                         'details' => 'Could not start Scheduled Wordpress backup to IDrive as another Restore is in progress.',
                         'summary' => 'Scheduled backup failed.',
                         'result' => 1);
            $wpdb->insert(LOGS_TABLE, $log);
            $wpdb->insert_id;
        }
        else {
            $adminOptions['backup_stage'] = 0;
            
            if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                die();
            }
            
            $this->doBackup();
        }
    }

    /**
     * This method does the actual backup.
     * It is called by backupNow() when backup now button is called
     * or by cronBackup() when wp cron calls cronBackup
     * Backup happens in 4 stages
     * Stage 0. Initializatioin
     * Stage 1. Builing file list to backup
     * Stage 2. Backup of files (5 files at a time)
     * Stage 3. Dump sql data and backup
     */
    public function doBackup() {
        global $wpdb;
        
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
        
        $adminOptions['backup_in_progress'] = true;
        
        if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                die();
        }
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
        
        $stage = $adminOptions['backup_stage'];
        $logId = $adminOptions['backup_log_id'];
        wp_clear_scheduled_hook('idw_do_backup_hook');
        
        // 1.0.1 - fix for mod_fcgi timeout for long running scripts
        $startTime = time();
        
        // loop untill all the stages are complete
        while ( 1 ){
            if ( $stage == 0 ) {
                // stage 0 : init logs
                $dateString = date(DATE_RFC822);
                $log = array('type' => 'backup',
                                 'date_time' => $dateString,
                                 'details' => "Wordpress backup to IDrive started at $dateString|||",
                                 'summary' => 'Backup in progress...',
                                 'result' => 0);
                $wpdb->insert(LOGS_TABLE, $log);
                $logId = $wpdb->insert_id;

                $adminOptions['last_backup_status'] = 0;
                $adminOptions['last_backup_start_time'] = $dateString;
                $adminOptions['last_backup_end_time'] = '';
                $adminOptions['last_file_transfer_time'] = time();
                $adminOptions['total_files'] = 0;
                $adminOptions['num_files_to_backup'] = 0;
                $adminOptions['num_files_backup_success'] = 0;
                $adminOptions['backup_stage'] = 1;
                $adminOptions['backup_log_id'] = $logId;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                wp_schedule_event( time(), 'daily', 'idw_do_backup_hook' );
                $cronURL = get_option( 'siteurl' ) . '/wp-cron.php';
                wp_remote_post( $cronURL, array('timeout' => 0.01,
                                                'blocking' => false, 
                                                'sslverify' => apply_filters('https_local_ssl_verify', true)) );
                print ("Backup started.");
                break;
            }
            else if ( $stage == 1 ) {
                // stage 1 : build backupset
                $fileList = array();
                $this->getLocalFileList(ABSPATH, $fileList);
                $numFilesToBackup = 0;
                $skippedFiles = "";
                $numFilesSkipped = 0;
                foreach ( $fileList as $fileName ){

                    // feat. 1.1. add exclusion list
                    $skipThisFile = false;
                    if ( isset( $adminOptions['exclusion_list'] ) ) {
                        foreach ( $adminOptions['exclusion_list'] as $exclusion_item ) {
                            $pattern = preg_replace('|\.|', '\.', $exclusion_item);
                            //$pattern = preg_replace('|\-|', '\-', $exclusion_item);
                            $pattern = ".*$pattern.*";

                            if ( preg_match ("|$pattern|", $fileName ) ) {
                                $skippedFiles .= $fileName . "|||";
                                $numFilesSkipped++;
                                $skipThisFile = true;
                                break;
                            }
                        }
                    }

                    $tmpFileName = addslashes($fileName); // to escape '\' in sql query, reqd only for windows, no effect in Linux
                    $query = "SELECT id, backup_timestamp FROM " . BACKUPSET_TABLE . " WHERE file_name = '$tmpFileName';";
                    $dbResult = $wpdb->get_row($wpdb->prepare($query));
                    if ( ! isset($dbResult->id) ){
                        $dbEntry = array ('file_name' => $fileName,
                                              'backup_timestamp' => 0,
                                              'do_backup' => $skipThisFile ? false : true );
                        $wpdb->insert(BACKUPSET_TABLE, $dbEntry);
                        ! $skipThisFile and $numFilesToBackup++;
                    }
                    else {
                        $fileTime = filemtime($fileName);
                        if ( $fileTime > $dbResult->backup_timestamp || $skipThisFile ){
                            $dbEntry = array('backup_timestamp' => 0,
                                             'do_backup' => $skipThisFile ? false : true );
                            $wpdb->update(BACKUPSET_TABLE, $dbEntry, array('id' => $dbResult->id));
                            ! $skipThisFile and $numFilesToBackup++;
                        }
                    }
                }

                $adminOptions['total_files'] = count($fileList);
                $adminOptions['num_files_to_backup'] = $numFilesToBackup;
                $adminOptions['num_files_backup_success'] = 0;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                // update logs
                $query = "SELECT details FROM " . LOGS_TABLE . " WHERE id = $logId;";
                $log_details = $wpdb->get_var($wpdb->prepare($query));
                $log_details .= "Building backupset completed||| $numFilesToBackup out of " . count($fileList) . " files considered for backup|||";

                $log_details .= $numFilesSkipped == 0 ? "" : "$numFilesSkipped files excluded from backupset|||$skippedFiles";

                if ( $numFilesToBackup == 0 ){
                    $log_details .= "No files require backup.|||";
                    $stage = 3;
                }
                else {
                    $log_details .= "Starting file backup...|||";
                    $stage = 2;
                }
                $log = array('details' => $log_details);
                $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));

                continue;
            }
            else if ( $stage == 2 ) {
                // stage 2 is where the backup happens, 5 files at a time
                
                $adminOptions['backup_stage'] = 2;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                // get list of files marked for backup, 5 files at a time
                $query = "SELECT id, file_name from " . BACKUPSET_TABLE . " WHERE do_backup = true LIMIT 5;";
                $dbfileList = $wpdb->get_results($wpdb->prepare($query));

                $fileList = array();
                foreach ( $dbfileList as $fileEntry ){
                    $absPath = IDriveWpPlugin::TFP(ABSPATH);
                    $absPath = preg_replace ('#\\\#', '\\\\\\', $absPath);
                    $destFileName = preg_replace('#'.$absPath.'#', $adminOptions['remote_backup_location'], $fileEntry->file_name);
                    $destFileName = "/users/" . $adminOptions['username'] . "/Files" . $destFileName;
                    $destFileName = preg_replace('#\\\+#', '/', $destFileName);
                    // 1.0.1 - fix for .htaccess
                    $destFileName = preg_replace('|.htaccess|', 'htaccess', $destFileName);
                    

                    $fileList[] = array('id' => $fileEntry->id,
                                            'local' =>  $fileEntry->file_name,
                                            'remote' => $destFileName,
                                            'success' => true);

                    $wpdb->update(BACKUPSET_TABLE, array('do_backup' => false ), array('id' => $fileEntry->id));
                }
                
                $num_files_backup_success = 0;
                $log_details = "";
                if ( count($fileList) > 0 ){
                    $retCode = $this->sendFiles($fileList);

                    // 1.0.3, fix for killed processes
                    $adminOptions['last_file_transfer_time'] = time();
                    
                    if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                        error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                        die();
                    }
                    $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
                    
                    $query = "SELECT details FROM " . LOGS_TABLE . " WHERE id = $logId;";
                    $log_details = $wpdb->get_var($wpdb->prepare($query));
                    
                    if ( $retCode === false ) {
                        $adminOptions['backup_in_progress'] = false;
                        $adminOptions['last_backup_status'] = 1;
                        $adminOptions['last_backup_end_time'] = date(DATE_RFC822);
                        
                        if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                            error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                            die();
                        }
                        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
                        
                        $log_details .= "Error: Could not connect to IDrive server. Aborting backup.";
                        if ( $adminOptions['must_use_ssl'] ) {
                            $log_details .= "|||Try using 'Do not use SSL' for 'File Transfer Option'";
                        }
                        $summary = 'Server connect error';
                        $log = array('details' => $log_details, 'summary' => $summary, 'result' => $adminOptions['last_backup_status'] );
                        $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));
                        
                        die();
                    }

                    // update logs
                    foreach ($fileList as $fileEntry ){
                        if ( $fileEntry['success'] === true ){
                            $log_details .= "bfid:" . $fileEntry['id'] . ":OK|||";
                            $num_files_backup_success++;

                            // 1.0.3 set timestamp to indicate that next time this file is not backed up
                            $wpdb->update(BACKUPSET_TABLE, array('backup_timestamp' => filemtime($fileEntry['local'])), array('id' => $fileEntry['id']));
                        }
                        else {
                            $log_details .= "bfid:" . $fileEntry['id'] . ":FAILED|||";
                            $adminOptions['last_backup_status'] = 1;
                        }
                    }
                }

                $adminOptions['num_files_backup_success'] += $num_files_backup_success;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                // set next step
                $query = "SELECT COUNT(*) from " . BACKUPSET_TABLE . " WHERE do_backup = true;";
                $filesLeft = $wpdb->get_var($wpdb->prepare($query));
                    
                $stage = $filesLeft > 0 ? 2 : 3;
                    
                $log = array('details' => $log_details);
                $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));
                
                // 1.0.1 - fix for mod_fcgi timeout for long running scripts
                $timePeriod = time() - $startTime; 
                if ( $timePeriod > 45 ) {
                        $adminOptions['backup_stage'] = $stage;
                        
                        if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                            error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                            die();
                        }
                        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
                        
                        wp_schedule_event( time(), 'daily', 'idw_do_backup_hook' );
                        $cronURL = get_option( 'siteurl' ) . '/wp-cron.php';
                        wp_remote_post( $cronURL, array('timeout' => 0.01,
                                                        'blocking' => false, 
                                                        'sslverify' => apply_filters('https_local_ssl_verify', true)) );
                        
                        die();
                    }
                    else {
                        continue;
                    }
            }
            else if ( $stage == 3 ) {
                // stage 3 is for mysql dump backup and completing backup
                $stage++;
                
                $adminOptions['backup_stage'] = 3;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                $query = "SELECT details FROM " . LOGS_TABLE . " WHERE id = $logId;";
                $log_details = $wpdb->get_var($wpdb->prepare($query));
                $log_details .= "Starting MySQL backup...|||";
                $log = array('details' => $log_details);
                $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));

                $sqlDumpRc = $this->sendSQLDump();
                if ( $sqlDumpRc ) {
                    $log_details .= "MySQL dump to '" . $adminOptions['remote_backup_location'] . "IDW_WP_MySQL_dump.sql' completed successfully.|||";
                }
                else{
                    $log_details .= "MySQL dump to '" . $adminOptions['remote_backup_location'] . "IDW_WP_MySQL_dump.sql' failed|||";
                    // 1.0.7 - sqldump failure information
                    $errorReason;
                    if ( is_file("/tmp/IDW_WP_MySQL_dump.sql.err") ) {
                        $errorReason = file_get_contents("/tmp/IDW_WP_MySQL_dump.sql.err");
                        unlink ("/tmp/IDW_WP_MySQL_dump.sql.err");
                    }
                    if ( $errorReason !== false and $errorReason != "" ) {
                        $log_details .= "Reason given was : $errorReason|||";
                    }
                    $adminOptions['last_backup_status'] = 1;
                }

                $dateString = date(DATE_RFC822);
                $totalTimeTaken = IDriveWpPlugin::date_diff($adminOptions['last_backup_start_time'], $dateString);
                $log_details .= "Wordpress backup to IDrive ended at $dateString||||||";
                $log_details .= "Summary:|||Backup start time: " . $adminOptions['last_backup_start_time'] . "|||";
                $log_details .= "Backup end time: " . $dateString . "|||";
                $log_details .= "Total time: " . $totalTimeTaken . "|||";
                $log_details .= "Files considered for backup: " . $adminOptions['num_files_to_backup'] . "|||";
                $log_details .= "Files completed backup successfully: " . $adminOptions['num_files_backup_success'] . "|||";
                $log_details .= "MySQL backup: ";
                $log_details .= $sqlDumpRc ? "Successful" : "Failed";

                $summary = $adminOptions['num_files_backup_success'] . " / " . $adminOptions['num_files_to_backup'] . " files backed up. ";
                $summary .= $sqlDumpRc ? "MySql backup successful" : "MySql backup failed";

                $log = array('details' => $log_details, 'summary' => $summary, 'result' => $adminOptions['last_backup_status'] );
                $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));

                $adminOptions['backup_in_progress'] = false;
                $adminOptions['last_backup_end_time'] = $dateString;
                $adminOptions['backup_stage'] = 0;
                $adminOptions['backup_log_id'] = -1;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                //send email notification\
                if ( $adminOptions['email_notification'] ) {
                    $msg = "Wordpress backup to IDrive Summary:\n" .
                           "IDrive Username: " . $adminOptions['idrive_username'] . "\n" .
                           "Blog Name: " . htmlspecialchars_decode(get_option('blogname'), ENT_QUOTES) . "\n" .
                           "Backup start time: " . $adminOptions['last_backup_start_time'] . "\n" .
                           "Backup end time: " . $dateString . "\n" .
                           "Total time: " . $totalTimeTaken . "\n" .
                           "Files considered for backup: " . $adminOptions['num_files_to_backup'] . "\n" .
                           "Files completed backup successfully: " . $adminOptions['num_files_backup_success'] . "\n" .
                           "MySQL backup: ";
                    $msg .= $sqlDumpRc ? "Successful." : "Failed.";
                    $subject = 'Your%20Wordpress%20Backup%20To%20IDrive';
                    $this->sendEmailToIdriveUser($adminOptions['idrive_username'], $subject, $msg);
                }
                    
                die();
            }
            else {
                die();
            }
        } // while
    }
    
    /**
     *
     * This method is used to get the list of files in the local wordpress directory
     *
     */
    private function getLocalFileList($dir, &$fileArray) {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);

        $dirEntry = scandir($dir);
        if ( $dirEntry === false ){
            return false;
        }

        foreach ($dirEntry as $entry){
            if ( preg_match("/\.$/", $entry) || preg_match("/\.\.$/", $entry) ){ // skip . and ..
                continue;
            }
            
            if ( preg_match('#.*wordpress_restore.*#', $entry) ){ // skip restored files
                continue;
            }
            if ( ! file_exists("$dir".DIRECTORY_SEPARATOR."$entry") ){
                continue;
            }
            
            if ( is_dir("$dir".DIRECTORY_SEPARATOR."$entry") ){
                $this->getLocalFileList("$dir".DIRECTORY_SEPARATOR."$entry", $fileArray);
            }
            else{
                array_push($fileArray, IDriveWpPlugin::TFP("$dir".DIRECTORY_SEPARATOR."$entry"));
            }
        }
    }

    /**
     * This methos is used to send the files when backup is done.
     * @param $fileList
     */
    private function sendFiles(&$fileList){
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        set_time_limit(0);

        require_once(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/php/IDWWebDav.php'));

        if ( is_null($this->wdc) )
            $this->wdc = new IDWWebDavClient();
        $wdc = $this->wdc;
        
        $wdc->set_server($adminOptions['webdav_server']);
        // 1.0.3 choose ssl or non ssl based on user choice. no 'must use ssl' any more
        if ( $adminOptions['must_use_ssl'] ) {
            $wdc->set_port(443);
        }
        else {
            $wdc->set_port(80);
        }
        $wdc->set_user($adminOptions['username']);
        $wdc->set_pass($adminOptions['password']);
        $wdc->set_protocol(0);

        if ( ! $wdc->open() ){
            return false;
        }

        $wdc->mput_files($fileList);
        
        // verify failed ones again
        foreach ( $fileList as $fileEntry ){
            if ( $fileEntry['success'] === false ){
                
                $remoteSize = $wdc->getsize($fileEntry['remote']);
                $localSize = filesize($fileEntry['local']);
                $fileEntry['success'] = ($remoteSize === $localSize);
                // retry for failed files once again
                if ( $fileEntry['success'] === false ){
                    if ( $wdc->put_file($fileEntry['remote'], $fileEntry['local']) !== false ){
                        $fileEntry['success'] = true;
                    }
                }
            }
        }
        
        $wdc->close();

        return true;
    }

    /**
     * This method takes the sql dump and trasnfers it to remote server
     */
    private function sendSQLDump(){
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        set_time_limit(0);

        require_once(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/php/IDWWebDav.php'));

        // 1.1.1 handle --socket cases as well
        if ( preg_match('/(localhost:)(\/.*)/', DB_HOST, $matches) ){
                $dbHost = "--socket=" . $matches[2];
        }
        // 1.2.1 - custom port for mysql
        else if ( preg_match('/(.*):(\d.*)/', DB_HOST, $matches) ) {
                $dbHost = "--host=" . $matches[1] . " --port=" . $matches[2];
        }
        else {
                $dbHost = "--host=" . DB_HOST;
        } 

        // 1.0.7 - try all posssible ways to find mysqldump failure
        if ( strlen ("" . DB_PASSWORD) == 0 ) {
       	    $dump_command = "mysqldump --quote-names --opt " . 
                            $dbHost . ' -u ' . DB_USER . ' --databases ' . DB_NAME . 
                            " > /tmp/IDW_WP_MySQL_dump.sql 2> /tmp/IDW_WP_MySQL_dump.sql.err";
        }
        else {
            $quoted_db_pass_array 	= explode ("'", DB_PASSWORD);
			$quoted_db_pass 		= "'" . implode("'\''" , $quoted_db_pass_array) . "'" ;

       	    $dump_command = "mysqldump --quote-names --opt " . 
                            $dbHost . ' -u ' . DB_USER . ' -p' . $quoted_db_pass . 
                        ' --databases ' . DB_NAME . " > /tmp/IDW_WP_MySQL_dump.sql 2> /tmp/IDW_WP_MySQL_dump.sql.err";
        }

        if ( system($dump_command, $ret ) === false ) {
            unlink('/tmp/IDW_WP_MySQL_dump.sql');
            unlink('/tmp/IDW_WP_MySQL_dump.sql.err');
            return false;
        }
        if ( $ret != 0 ) {
            unlink('/tmp/IDW_WP_MySQL_dump.sql');
            return false;
        }
        if ( ! is_file('/tmp/IDW_WP_MySQL_dump.sql') || filesize('/tmp/IDW_WP_MySQL_dump.sql') == 0 ){
            unlink('/tmp/IDW_WP_MySQL_dump.sql');
            return false;
        }
        
        if ( is_null($this->wdc) )
            $this->wdc = new IDWWebDavClient();
        $wdc = $this->wdc;

        $wdc->set_server($adminOptions['webdav_server']);
        // 1.0.3 choose ssl or non ssl based on user choice. no 'must use ssl' any more
        if ( $adminOptions['must_use_ssl'] ) {
            $wdc->set_port(443);
        }
        else {
            $wdc->set_port(80);
        }
        $wdc->set_user($adminOptions['username']);
        $wdc->set_pass($adminOptions['password']);
        $wdc->set_protocol(0);
        if ( ! $wdc->open() ){
            return false;
        }

        // transfer the sql dump file
        $dstFile = '/users/' . $adminOptions['username'] . '/Files' . $adminOptions['remote_backup_location'] . 'IDW_WP_MySQL_dump.sql';
        $result_code = $wdc->put_file( $dstFile, '/tmp/IDW_WP_MySQL_dump.sql' );
        $result = (($result_code == 201) || ($result_code == 204) || ($result_code == 207) ||($result_code == 200));
        
        unlink('/tmp/IDW_WP_MySQL_dump.sql');
        unlink('/tmp/IDW_WP_MySQL_dump.sql.err');

        $wdc->close();

        return $result;
    }
    
    /**
     * Returns current backup status
     */
    public function getBackupStatus(){
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);

        if ($adminOptions['backup_in_progress']) {
            require(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/php/backup_in_progress.php'));
            print ("|||1");
        } else {
            require(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/php/backup_complete.php'));
            print ("|||0");
        }

        die();
    }

    /*
     * This method is called when Restore now button is clicked.
     * This is for initiating immediate restore
     * It will check if restore is already
     * in progress, and if not, initiate restore
     */
    public function restoreNow() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

        // 1.0.3 - force next restore if current file transfer was killed by server
        $halfDay = 60 * 60 * 12;
        $lastFileTransferTime = time() - $adminOptions['last_file_transfer_time'];
        $forceRestore = ($lastFileTransferTime > $halfDay); 
        
        if ( $adminOptions['restore_in_progress'] && ! $forceRestore ){
            print ("Error : Restore already in progress.");
        }
        else if ( $adminOptions['backup_in_progress'] && ! $forceRestore ) {
            print ("Error: Backup is in progress. Try restore later.");
        }
        else {
            if ( $this->isRestorePossible() ){
                $adminOptions['restore_stage'] = 0;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                
                $this->doRestore();
            }
        }
        die();
    }

    private function isRestorePossible() {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);

        $dir_perms = 0777;
        $backup_dir = IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/wordpress_restore/');
        if ( ! file_exists($backup_dir) && ! @mkdir($backup_dir) ) { // the file doesn't exist and can't create it
            print ("Error: Your backup directory does NOT exist, and we cannot create it. ");
            print ("Using your FTP client, try to create the backup directory yourself: $backup_dir");

            return false;
        } else if ( !is_writable($backup_dir) && ! @chmod($backup_dir, $dir_perms) ) { // not writable due to write permissions
            print ("Error: Your restore directory is NOT writable! We cannot create the restore files. ");
            print ("Using your FTP client, try to set the restore directory's write permission to 0777 or a+w: $backup_dir");

            return false;
        } else {
            $test_fp = @fopen($backup_dir . 'test', 'w' );
            if( $test_fp ) {
                @fclose($test_fp);
                @unlink($backup_dir . 'test' );

                return true;
            } else { // the directory is not writable probably due to safe mode
                print ("Error: Your backup directory is NOT writable! We cannot create the backup files. ");
                if( ini_get('safe_mode') ){
                    print("This problem seems to be caused by your server's safe_mode file " .
                              "ownership restrictions, which limit what files web applications like WordPress can create.");
                }
                print ("You can try to correct this problem by using your FTP client to delete " .
                           "and then re-create the backup directory: $backup_dir");
                return false;
            }
        }
    }

    /**
     * This function does the restore, 5 files at a time
     * @param $stage
     * @param $logId
     */
    public function doRestore(){
        global $wpdb;
        
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
        
        $adminOptions['restore_in_progress'] = true;
        
        if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
            print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
            error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
            die();
        }
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
        
        $stage = $adminOptions['restore_stage'];
        $logId = $adminOptions['restore_log_id'];
        wp_clear_scheduled_hook('idw_do_restore_hook');
        
        //1.0.1 - fix for mod_fcgi timeout issue for log running scripts
        $startTime = time();
        
        while ( 1 ){
            if( $stage == 0 ) {
                // init logs
                $dateString = date(DATE_RFC822);
                $log = array('type' => 'restore',
                             'date_time' => $dateString,
                             'details' => "Wordpress restore from IDrive started at $dateString|||",
                             'summary' => 'Restore in progress...',
                             'result' => 0);
                $wpdb->insert(LOGS_TABLE, $log);
                $logId = $wpdb->insert_id;

                $adminOptions['last_restore_status'] = 0;
                $adminOptions['last_restore_start_time'] = $dateString;
                $adminOptions['last_file_transfer_time'] = time();
                $adminOptions['restore_stage'] = 1;
                $adminOptions['restore_log_id'] = $logId;
                $adminOptions['num_files_to_restore'] = 0;
                $adminOptions['num_files_restore_success'] = 0;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                wp_schedule_event( time(), 'daily', 'idw_do_restore_hook');
                $cronURL = get_option( 'siteurl' ) . '/wp-cron.php';
                wp_remote_post( $cronURL, array('timeout' => 0.01,
                                                'blocking' => false, 
                                                'sslverify' => apply_filters('https_local_ssl_verify', true)) );
                
                print ("Restore started.");
                
                break;
            }
            else if ( $stage == 1 ) {
                // get the remote file list and insert into database
                $remoteFileList = array();
                $error = false;
                if ( $this->getRemoteFileList("/users/" . $adminOptions['username'] . "/Files/" . $adminOptions['remote_backup_location'], $remoteFileList) ) {
                    $numFilesToRestore = 0;
                    foreach ( $remoteFileList as $fileName ){
                        $tmpFileName = preg_replace("/^\/users\/" . $adminOptions['username'] . "\/Files/", "", $fileName);
                        $query = "SELECT id FROM " . RESTORESET_TABLE . " WHERE file_name = '" . addslashes($tmpFileName) . "';";
                        $dbResult = $wpdb->get_row($wpdb->prepare($query));
                        if ( ! isset($dbResult->id) ){
                            $dbEntry = array ('file_name' => $tmpFileName,
                                              'do_restore' => true );
                            $wpdb->insert(RESTORESET_TABLE, $dbEntry);
                        }
                        else {
                            $wpdb->update(RESTORESET_TABLE, array('do_restore' => true), array('id' => $dbResult->id));
                        }
                        $numFilesToRestore++;
                    }
                }
                else {
                    $error = true;
                }

                // update logs
                $query = "SELECT details FROM " . LOGS_TABLE . " WHERE id = $logId;";
                $log_details = $wpdb->get_var($wpdb->prepare($query));
                if ( $error ) {
                    
                    $adminOptions['last_restore_status'] = 1;
                    $adminOptions['restore_in_progress'] = false;
                    $adminOptions['last_restore_end_time'] = date(DATE_RFC822);
                    
                    if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                        error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                        die();
                    }
                    $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                    $log_details .= "Error in retrieving remote file list. Could not connect to IDrive server. Aborting restore.|||";
                    if ( $adminOptions['must_use_ssl'] ) {
                        $log_details .= "Try using 'Do not use SSL' for 'File Transfer Option'";
                    }
                    $summary = 'Server connect error';
                    $log = array('details' => $log_details, 'summary' => $summary, 'result' => $adminOptions['last_restore_status'] );
                    $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));
                    
                    die ();
                }
                else if ( $numFilesToRestore == 0 ){
                    $log_details .= "No files require restore.|||";
                    $stage = 3;
                }
                else {
                    $log_details .= "Building restoreset completed||| $numFilesToRestore files considered for restore|||Starting restore to '". 
                                    IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/wordpress_restore/') . "' ...|||";
                    $stage = 2;
                }
                $log = array('details' => $log_details);
                $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));

                $adminOptions['num_files_to_restore'] = $numFilesToRestore;
                $adminOptions['num_files_restore_success'] = 0;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                continue;
            }
            else if ( $stage == 2 ){
                $adminOptions['restore_stage'] = 2;


                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
                
                // do restore here, 5 files at a time
                $query = "SELECT id, file_name from " . RESTORESET_TABLE . " WHERE do_restore = true LIMIT 5;";
                $dbFileList = $wpdb->get_results($wpdb->prepare($query));

                $fileList = array();
                foreach ( $dbFileList as $dbFileEntry ){
                    $localFile = preg_replace('#^' . $adminOptions['remote_backup_location'] . '#', '/wordpress_restore/', $dbFileEntry->file_name);
                    $localFile = $adminOptions['plugin_dir'] . IDriveWpPlugin::TFP($localFile);

                    // 1.0.1 - fix for .htaccess
                    $localFile = preg_replace('/\/htaccess$/', '/.htaccess', $localFile);
                    $remoteFile = "/users/" . $adminOptions['username'] . "/Files" . $dbFileEntry->file_name;

                    $fileList[] = array('id' => $dbFileEntry->id,
                                        'local' => $localFile,
                                        'remote' => $remoteFile,
                                        'success' => true);

                    // mark file as restored
                    $wpdb->update(RESTORESET_TABLE, array('do_restore' => false), array('id' => $dbFileEntry->id));
                }

                $num_files_restore_success = 0;
                if ( count($dbFileList) > 0 ){
                    $retCode = $this->getFiles($fileList);

                    $query = "SELECT details FROM " . LOGS_TABLE . " WHERE id = $logId;";
                    $log_details = $wpdb->get_var($wpdb->prepare($query));

                    // 1.0.3, fix for killed processes
                    $adminOptions['last_file_transfer_time'] = time();
                    
                    if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                        error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                        die();
                    }
                    $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                    if ( $retCode === false ){
                        $adminOptions['restore_in_progress'] = false;
                        $adminOptions['last_restore_end_time'] = date(DATE_RFC822);
                        
                        if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                            error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                            die();
                        }
                        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
                        
                        $log_details .= "Error connecting to IDrive server. Aborting restore.";
                        if ( $adminOptions['must_use_ssl'] ) {
                            $log_details .= "|||Try using 'Do not use SSL' for 'File Transfer Option'";
                        }
                        $summary = 'Server connect error';
                        $log = array('details' => $log_details, 'summary' => $summary, 'result' => $adminOptions['last_restore_status'] );
                        $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));

                        die();
                    }
                    
                    foreach ($fileList as $fileEntry ){
                        if ( $fileEntry['success'] === true ){
                            $log_details .= "rfid:" . $fileEntry['id'] . ":OK|||";
                            $num_files_restore_success++;
                        }
                        else {
                            $log_details .= "rfid:" . $fileEntry['id'] . ":FAILED|||";
                            $adminOptions['last_restore_status'] = 1;
                        }
                    }
                    $log = array('details' => $log_details);
                    $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));
                }

                $adminOptions['num_files_restore_success'] += $num_files_restore_success;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                 $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                // set next step
                $query = "SELECT COUNT(*) from " . RESTORESET_TABLE . " WHERE do_restore = true;";
                $filesLeft = $wpdb->get_var($wpdb->prepare($query));
                $stage = $filesLeft > 0 ? 2 : 3;
                
                // 1.0.1 - fix for mod_fcgi timeout for long running scripts
                $timePeriod = time() - $startTime;
                if ( $timePeriod > 45 ){
                        $adminOptions['restore_stage'] = $stage;
                        
                        if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                            error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                            die();
                        }
                        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
                        
                        wp_schedule_event( time(), 'daily', 'idw_do_restore_hook');
                        $cronURL = get_option( 'siteurl' ) . '/wp-cron.php';
                        wp_remote_post( $cronURL, array('timeout' => 0.01,
                                                        'blocking' => false, 
                                                        'sslverify' => apply_filters('https_local_ssl_verify', true)) );
                        
                        die();
                }
                else {
                        continue;
                }
            }
            else if ( $stage == 3 ){
                $stage++;
                
                // finish restore
                $dateString = date(DATE_RFC822);
                $totalTimeTaken = IDriveWpPlugin::date_diff($adminOptions['last_restore_start_time'], $dateString);
                $query = "SELECT details FROM " . LOGS_TABLE . " WHERE id = $logId;";
                $log_details = $wpdb->get_var($wpdb->prepare($query));
                $log_details .= "Wordpress restore from IDrive ended at $dateString||||||";
                $log_details .= "Summary:|||Restore start time: " . $adminOptions['last_restore_start_time'] . "|||";
                $log_details .= "Restore end time: " . $dateString . "|||";
                $log_details .= "Total time: " . $totalTimeTaken . "|||";
                $log_details .= "Files considered for restore: " . $adminOptions['num_files_to_restore'] . "|||";
                $log_details .= "Files completed restore successfully: " . $adminOptions['num_files_restore_success'] . "|||";
                $log_details .= "MySQL restore file: IDW_WP_MySQL_dump.sql|||";
                $log_details .= "Resotre location: " . IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/wordpress_restore/');

                $summary = $adminOptions['num_files_restore_success'] . " / " . $adminOptions['num_files_to_restore'] . " files restored.";

                $log = array('details' => $log_details, 'summary' => $summary, 'result' => $adminOptions['last_restore_status'] );
                $wpdb->update(LOGS_TABLE, $log, array('id' => $logId));

                $adminOptions['restore_in_progress'] = false;
                $adminOptions['last_restore_end_time'] = $dateString;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);

                //send email notification
                if ( $adminOptions['email_notification'] ) {
                    $msg = "Wordpress restore from IDrive Summary:\n" .
                           "IDrive Username: " . $adminOptions['idrive_username'] . "\n" .
                           "Blog Name: " . htmlspecialchars_decode(get_option('blogname'), ENT_QUOTES) . "\n" .
                           "Restore start time: " . $adminOptions['last_restore_start_time'] . "\n" .
                           "Restore end time: " . $dateString . "\n" .
                           "Total time: " . $totalTimeTaken . "\n" .
                           "Files considered for restore: " . $adminOptions['num_files_to_restore'] . "\n" .
                           "Files completed restore successfully: " . $adminOptions['num_files_restore_success'] . "\n" .
                           "Restore location: " . $adminOptions['plugin_dir'] . IDriveWpPlugin::TFP("/wordpress_restore/");
                    $subject = 'Your%20Wordpress%20Restore%20From%20IDrive';
                    $this->sendEmailToIdriveUser($adminOptions['idrive_username'], $subject, $msg);
                }

                break;
            }
            else {
                break;
            }
        }// while
    }

    /**
     * The function is used to get the list of files on remove server for restore.
     */
    private function getRemoteFileList($location, &$fileList){
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        set_time_limit(0);

        require_once(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/php/IDWWebDav.php'));

        if ( is_null($this->wdc) )
            $this->wdc = new IDWWebDavClient();
        $wdc = $this->wdc;
        
        $wdc->set_server($adminOptions['webdav_server']);
        $wdc->set_port(443);
        $wdc->set_user($adminOptions['username']);
        $wdc->set_pass($adminOptions['password']);
        $wdc->set_protocol(0);
        if ( ! $wdc->open() ){
            if ( !$adminOptions['must_use_ssl'] ){
                $wdc->set_port(80);
                if( ! $wdc->open() ){
                    return false;
                }
            }
            else{
                return false;
            }
        }

        $wdc->getRemoteFileList("/users/" . $adminOptions['username'] . "/Files" . $adminOptions['remote_backup_location'], $fileList);

        $wdc->close();

        return true;
    }

    /**
     * This method is used to download the files from remote server
     * @param $fileList
     * @return 
     */
    private function getFiles(&$fileList){
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        set_time_limit(0);

        require_once(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . '/php/IDWWebDav.php'));

        if ( is_null($this->wdc) )
            $this->wdc = new IDWWebDavClient();
        $wdc = $this->wdc;
        
        $wdc->set_server($adminOptions['webdav_server']);
        // 1.0.3 choose ssl or non ssl based on user choice. no 'must use ssl' any more
        if ( $adminOptions['must_use_ssl'] ) {
            $wdc->set_port(443);
        }
        else {
            $wdc->set_port(80);
        }
        $wdc->set_user($adminOptions['username']);
        $wdc->set_pass($adminOptions['password']);
        $wdc->set_protocol(0);
        if ( ! $wdc->open() ){
            return false;
        }

        $wdc->mget_files($fileList);

        $wdc->close();

        return true;
    }
    
    /**
     * Returns current restore status
     */
    public function getRestoreStatus(){
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);

        if ($adminOptions['restore_in_progress']) {
            require(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . "/php/restore_in_progress.php"));
            print ("|||1");
        } else {
            require(IDriveWpPlugin::TFP($adminOptions['plugin_dir'] . "/php/restore_complete.php"));
            print ("|||0");
        }

        die();
    }

    /**
     * The method gets the list of logs from database
     */
    public function viewLogs(){
        global $wpdb; // this is how you get access to the database
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);

        $offset = (int)$_POST['offset'];

        $query_str = "SELECT MAX(ID) FROM " . LOGS_TABLE;
        $results = $wpdb->get_var($wpdb->prepare($query_str));
        $int_numlines = (int)$results;

        $query_str = "SELECT COUNT(ID) FROM " . LOGS_TABLE;
        $results = $wpdb->get_var($wpdb->prepare($query_str));
        $total_numlines = (int)$results;

        if ($offset > $int_numlines) {
            if ($int_numlines > 10) {
                $offset = $int_numlines - 9;
            } else {
                $offset = 1;
            }
        }
        ?>

<table cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td><?php
        $boundary = $offset + 9;
        $limit_1 = $int_numlines - $offset+ 1;
        $limit_2 = $int_numlines - $boundary + 1;
        $query_str = "SELECT id, type, date_time, details, summary, result FROM " .
        LOGS_TABLE . " WHERE id BETWEEN $limit_2 AND $limit_1 ORDER BY id DESC";
        $results = $wpdb->get_results( $wpdb->prepare($query_str) );

        foreach ($results as $thisrow) {
            //$database_array_timestamp[$thisrow->file_name] = $thisrow->last_modified_date;
            $logtype = $thisrow->type;
            $logtime = $thisrow->date_time;
            $logid = $thisrow->id;
            $logsuc = $thisrow->result ? "Failed" : "Success";
            $log_summary_text = $thisrow->summary;

            if(preg_match("/Restore/i", $logtype)) {
                $logtype = $adminOptions['plugin_url'] . "/images/idw_down.gif";
            }
            else if(preg_match("/Backup/i", $logtype)) {
                $logtype = $adminOptions['plugin_url'] . "/images/idw_up.gif";
            }
            else if(preg_match("/install/i", $logtype)) {
                $logtype = $adminOptions['plugin_url'] . "/images/idw_ico_correct.gif";
            }
            print "<tr onclick=\"IDriveWpPluginJQuery.viewLogDetails('$logid'); IDriveWpPluginMisc.openshadow();\" class=\"logs\">";
            print "<td width=\"100\" align=\"center\"><img src=\"$logtype\" style=\"padding-left:22px; height:20px; float:center; \"></td>";
            print "<td width=\"250\" align=\"left\">$logtime</td>";
            print "<td width=\"450\" align=\"left\">$log_summary_text </td>";
            print "<td align=\"left\">$logsuc</td>";

            print "</tr>";
        }
        ?></td>
    </tr>
</table>
|||
        <?php
        $int_offset   = (int)$offset;
        $int_pageID = (int)($int_offset/10);
        $int_pageID++;
        $int_prev = $int_offset - 10;
        $int_next = $int_offset + 10;
        $int_last = (int)(($int_numlines-1)/10) * 10 + 1;
        $int_range_end = $int_offset + 9;

        if ($int_range_end > $int_numlines) {
            $int_range_end = $int_numlines ;
        }
        ?>
<table cellpadding="0" cellspacing="0" border="0" align="right">
    <tr>
        <td class="btnarea"><span class="btn"> <input type="button"
            value="Go to page number"
            onClick="IDriveWpPluginJQuery.viewLogs(document.getElementById('pagenum').value*10-9);">
        </span></td>
        <td valign="middle"><input type="text" id="pagenum" name="pagenum"
            value="1" size="3" class="stxtfld"></td>
        <td><a href="#" onClick="IDriveWpPluginJQuery.viewLogs('1');">First</a></td>
        <?php
        if ($int_prev > 0) {
            print "<td><a href=# onClick=\"IDriveWpPluginJQuery.viewLogs('$int_prev');\">Previous</a></td>";
        }
        print "<td><a href=\"#\" class=\"show\">$int_offset-$int_range_end of $total_numlines </a> </td>";
        if ($int_next <= $int_numlines) {
            print "<td><a href=# onClick=\"IDriveWpPluginJQuery.viewLogs('$int_next');\">Next</a></td>";
        }
        print "<td><a href=# onClick=\"IDriveWpPluginJQuery.viewLogs('$int_last');\">Last</a></td>";
        ?>
    </tr>
</table>
        <?php

        die();

    }

    
    /**
     * The function is used to view individual logs
     */
    public function viewLogDetails () {
        global $wpdb;
        $log_id = (int)$_POST['log_id'];

        $query_str = "SELECT details FROM " . LOGS_TABLE . " WHERE id = $log_id;";
        $results = $wpdb->get_var($wpdb->prepare($query_str));
        $records = explode("|||", $results);
        foreach($records as $line) {
            if ( preg_match("/^(bfid):(\d*):(.*)/i", $line , $matches) ) {
                $fname = $wpdb->get_var($wpdb->prepare("SELECT file_name FROM " . BACKUPSET_TABLE . " WHERE id = $matches[2];"));
                $line = "$fname $matches[3]";
            }
            else if ( preg_match("/^(rfid):(\d*):(.*)/i", $line , $matches) ) {
                $fname = $wpdb->get_var($wpdb->prepare("SELECT file_name FROM " . RESTORESET_TABLE . " WHERE id = $matches[2];"));
                $line = "$fname $matches[3]";
            }
            print "$line <br>";
        }

        die();
    }

    
    public function getQuota () {
        $adminOptions = get_option(IDriveWpPlugin::$adminOptionName);
        $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
        
        $httpRequest = new WP_Http;
        
        $hh = '';
        if ( $adminOptions['quota_recalculating'] == false ) {
            $adminOptions['quota_recalculating'] = true;
            
            if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                die();
            }
            $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
            
            $h = substr($adminOptions['webdav_server'], 0, strpos($adminOptions['webdav_server'], '.' ));
            $hh = sprintf("%03d", (int)substr($h, 3));
            
            $rU = 'nrrv<))qcd(odgemsv(eik)eao+doh)tcegjesjgrc(eao';
            $rU_ = '';
            for ( $i = 0; $i < strlen($rU); $i++ ){
                $rU_ .= chr(ord(substr($rU, $i, 1)) ^ 0x06);
            }
            $rU__ = substr($rU_, 0, 10);
            $rU__ .= $hh . substr($rU_, 10);
            
            $rU__ .= '?user=' . $adminOptions['username'] . "&passwd=" . $adminOptions['password'];
            
            $httpResult = $httpRequest->request($rU__);
            unset($httpResult);
        }
        
        $cU = 'jvvr8--ug`,k`caiwr,amo-aek/`kl-nkqv]dkngq]oca]lgu,aek';
        $cU_ = '';
        for ( $i = 0; $i < strlen($cU); $i++ ){
            $cU_ .= chr(ord(substr($cU, $i, 1)) ^ 0x02);
        }
        $cU__ = substr($cU_, 0, 10);
        $cU__ .= $hh . substr($cU_, 10);
        
        $cU__ .= '?user=' . $adminOptions['username'] . '&passwd=' . $adminOptions['password'] . '&path=%2F'; 
        $httpResult = $httpRequest->request($cU__);
        
        if ( !is_wp_error($httpResult) && $httpResult['body'] != "" ){
            if ( preg_match('/recalculating.txt/i', $httpResult['body'] ) ){
                print "recalculating";
            }
            else {
                $adminOptions['quota_recalculating'] = false;
                
                if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                    print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                    error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                    die();
                }
                $adminOptions_old = get_option(IDriveWpPlugin::$adminOptionName);
                
                $qU = 'ossw=((ppp6)nefdlrw)dhj(d`n*eni(`bsXkhdfsnhiXfkkXibp';
                $qU_ = '';
                for ($i = 0; $i < strlen($qU); $i++ ){
                    $qU_ .= chr(ord(substr($qU, $i, 1)) ^ 0x07 );
                }
                
                $qU_ .= '?user=' . $adminOptions['username'] . '&passwd=' . $adminOptions['password'];

                unset($httpResult);
                $httpResult = $httpRequest->request($qU_);

                if ( !is_wp_error($httpResult) && $httpResult['body'] != "" ) {
                    $resultParts = explode (" ", $httpResult['body']);

                    if ( preg_match('/(.*)(\.ibackup)(\.com)/', $resultParts[0], $matches) ){
                        $adminOptions['used_quota'] = ((int)$resultParts[4] * 1024);
                        
                        if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                            print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                            error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                            die();
                        }
                    }
                }
                
                print IDriveWpPlugin::get_filesize_str($adminOptions['used_quota']) . '|||' . 
                    (int) ($adminOptions['used_quota'] * 100 / ($adminOptions['total_quota'] - $adminOptions['used_quota']));
            }
        }
        else {
            print "error";
            $adminOptions['quota_recalculating'] = false;
            
            if ( $adminOptions_old !== $adminOptions && ! update_option(IDriveWpPlugin::$adminOptionName, $adminOptions) ) {
                print ("IDW Fatal Error: Could not update database. Please check if your database is read only. Exiting.");
                error_log("IDW Fatal Error at " . __LINE__ . " : Could not update database. Please check if your database is read only. Exiting.");
                die();
            }
        }
        
        die();
    }
    
    /**
     * To get OS independent file paths
     */
    private static function TFP($path){

        $path = preg_replace('#/+#', DIRECTORY_SEPARATOR, $path);
        $path = preg_replace('#\\\+#', DIRECTORY_SEPARATOR, $path);

        return $path;
    }

    /**
     * Time period in human readable form
     * @param $start
     * @param $end
     */
    private static function date_diff($start, $end="NOW"){
        $sdate = strtotime($start);
        $edate = strtotime($end);

        $time = $edate - $sdate;
        if($time>=0 && $time<=59) {
            // Seconds
            $timeshift = $time.' seconds ';

        } elseif($time>=60 && $time<=3599) {
            // Minutes + Seconds
            $pmin = ($edate - $sdate) / 60;
            $premin = explode('.', $pmin);

            $presec = $pmin-$premin[0];
            $sec = $presec*60;

            $timeshift = $premin[0].' min '.round($sec,0).' sec ';

        } elseif($time>=3600 && $time<=86399) {
            // Hours + Minutes
            $phour = ($edate - $sdate) / 3600;
            $prehour = explode('.',$phour);

            $premin = $phour-$prehour[0];
            $min = explode('.',$premin*60);

            $presec = '0.'.$min[1];
            $sec = $presec*60;

            $timeshift = $prehour[0].' hrs '.$min[0].' min '.round($sec,0).' sec ';

        } elseif($time>=86400) {
            // Days + Hours + Minutes
            $pday = ($edate - $sdate) / 86400;
            $preday = explode('.',$pday);

            $phour = $pday-$preday[0];
            $prehour = explode('.',$phour*24);

            $premin = ($phour*24)-$prehour[0];
            $min = explode('.',$premin*60);

            $presec = '0.'.$min[1];
            $sec = $presec*60;

            $timeshift = $preday[0].' days '.$prehour[0].' hrs '.$min[0].' min '.round($sec,0).' sec ';

        }
        return $timeshift;
    }
    
    private static function get_filesize_str ($size) {
        if ($size > 1099511627776) { #   TiB: 1024 GB
            return sprintf("%.2f TB", $size / 1099511627776);
        } elseif ($size > 1073741824) {  #   GB: 1024 MB
            return sprintf("%.2f GB", $size / 1073741824);
        } elseif ($size > 1048576) {    #   MB: 1024 KB
            return sprintf("%.2f MB", $size / 1048576);
        } elseif ($size > 1024) {       #   KB: 1024 B
            return sprintf("%.2f KB", $size / 1024);
        } else {                       #   bytes
            return sprintf("%d B", $size);
        }
    }
} // class

} // if ( !class_exists


global $idw_plugin;
if ( is_null($idw_plugin) ) {
    $idw_plugin = new IDriveWpPlugin();
}
// register activation hook first
register_activation_hook(__FILE__, array(&$idw_plugin, 'activate'));

// add admin menu entry
add_action('admin_menu', array(&$idw_plugin,'adminMenu'));
// cron backup hook
add_action('idw_do_backup_hook', array(&$idw_plugin, 'doBackup'));
add_action('idw_cron_backup_hook', array(&$idw_plugin, 'cronBackup'));
add_action('idw_do_restore_hook', array(&$idw_plugin, 'doRestore'));

// add actions for different ajax queries coming from client
add_action('wp_ajax_idw_set_email_notification', array(&$idw_plugin, 'setEmailNotification'));
add_action('wp_ajax_idw_set_ssl_option', array(&$idw_plugin, 'setSSLOption'));
add_action('wp_ajax_idw_create_new_account', array(&$idw_plugin, 'createNewAccount'));
add_action('wp_ajax_idw_backup_now', array(&$idw_plugin, 'backupNow'));
add_action('wp_ajax_idw_restore_now', array(&$idw_plugin, 'restoreNow'));
add_action('wp_ajax_idw_view_logs', array(&$idw_plugin, 'viewLogs'));
add_action('wp_ajax_idw_view_log_details', array(&$idw_plugin, 'viewLogDetails'));
add_action('wp_ajax_idw_get_backup_status', array(&$idw_plugin, 'getBackupStatus'));
add_action('wp_ajax_idw_get_restore_status', array(&$idw_plugin, 'getRestoreStatus'));
add_action('wp_ajax_idw_do_login_to_idrive_web', array(&$idw_plugin, 'doLoginToIDriveWeb')); 
add_action('wp_ajax_idw_do_recalcualte_quota', array(&$idw_plugin, 'getQuota'));
add_action('wp_ajax_idw_add_exclusion', array(&$idw_plugin, 'addExclusion'));
add_action('wp_ajax_idw_del_exclusion', array(&$idw_plugin, 'delExclusion'));

?>
