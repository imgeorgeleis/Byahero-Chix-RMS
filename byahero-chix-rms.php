<?php
/**
 * Byahero Chix RMS
 *
 * @package ByaheroChixRMS
 * @author  George L.
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Byahero Chix RMS
 * Plugin URI:        https://www.imgeorgeleis.com/byahero-chix-rms/
 * Description:       Restaurant management, recipe costing, inventory foundation, and POS platform for WordPress.
 * Version:           0.12.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            George L.
 * Author URI:        https://www.imgeorgeleis.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       byahero-chix-rms
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BC_RMS_VERSION', '0.12.2' );
define( 'BC_RMS_DB_VERSION', '0.12.0' );
define( 'BC_RMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BC_RMS_URL', plugin_dir_url( __FILE__ ) );

require_once BC_RMS_DIR . 'includes/class-bc-rms-db.php';
require_once BC_RMS_DIR . 'includes/class-bc-rms-installer.php';
require_once BC_RMS_DIR . 'includes/services/class-bc-rms-costing-service.php';
require_once BC_RMS_DIR . 'includes/services/class-bc-rms-product-service.php';
require_once BC_RMS_DIR . 'includes/services/class-bc-rms-pos-service.php';
require_once BC_RMS_DIR . 'includes/services/class-bc-rms-inventory-service.php';
require_once BC_RMS_DIR . 'includes/services/class-bc-rms-purchasing-service.php';
require_once BC_RMS_DIR . 'admin/class-bc-rms-admin.php';
require_once BC_RMS_DIR . 'public/class-bc-rms-frontend.php';

register_activation_hook( __FILE__, array( 'BC_RMS_Installer', 'activate' ) );

add_action( 'plugins_loaded', function () {
    BC_RMS_Installer::maybe_upgrade();
    load_plugin_textdomain( 'byahero-chix-rms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    BC_RMS_Frontend::init();
    if ( is_admin() ) { BC_RMS_Admin::init(); }
} );
