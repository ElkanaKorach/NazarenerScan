<?php
/**
 * Plugin Name:       NazarenerScan
 * Plugin URI:        https://github.com/elkanakorach/nazarenerscan
 * Description:       Ampelsystem für biblisch konforme Produktprüfung. Barcode scannen → Grün/Rot/Gelb. Nutzer können unbekannte Produkte einreichen, Admin verwaltet die Datenbank.
 * Version:           1.0.0
 * Author:            NazarenerScan
 * Text Domain:       nazarener-scan
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NAZARENER_SCAN_VERSION', '1.0.0' );
define( 'NAZARENER_SCAN_PATH', plugin_dir_path( __FILE__ ) );
define( 'NAZARENER_SCAN_URL', plugin_dir_url( __FILE__ ) );
define( 'NAZARENER_SCAN_DB_VERSION', '1.0' );

require_once NAZARENER_SCAN_PATH . 'includes/class-database.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-hints.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-ajax.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-frontend.php';

if ( is_admin() ) {
	require_once NAZARENER_SCAN_PATH . 'admin/class-admin.php';
	require_once NAZARENER_SCAN_PATH . 'admin/class-products-table.php';
	require_once NAZARENER_SCAN_PATH . 'admin/class-submissions-table.php';
	new NazarenerScan_Admin();
}

new NazarenerScan_Ajax();
new NazarenerScan_Frontend();

register_activation_hook( __FILE__, [ 'NazarenerScan_Database', 'install' ] );
register_deactivation_hook( __FILE__, [ 'NazarenerScan_Database', 'deactivate' ] );
