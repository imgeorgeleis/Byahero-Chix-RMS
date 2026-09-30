<?php
/**
 * Plugin Name: Byahero Chix RMS
 * Description: Byahero Chix Restaurant Management System - Master Data foundation.
 * Version: 0.2.0
 * Author: George L.
 * Author URI: https://www.imgeorgeleis.com
 */

if (!defined('ABSPATH')) exit;

define('BC_RMS_VERSION','0.2.0');
define('BC_RMS_DIR',plugin_dir_path(__FILE__));
define('BC_RMS_URL',plugin_dir_url(__FILE__));

require_once BC_RMS_DIR.'includes/class-bc-rms-db.php';
require_once BC_RMS_DIR.'includes/class-bc-rms-installer.php';
require_once BC_RMS_DIR.'admin/class-bc-rms-admin.php';

register_activation_hook(__FILE__,['BC_RMS_Installer','activate']);

add_action('plugins_loaded',function(){ if(is_admin()) BC_RMS_Admin::init(); });