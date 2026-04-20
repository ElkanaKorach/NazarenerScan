<?php
/**
 * Plugin Name:       NazarenerScan
 * Plugin URI:        https://github.com/elkanakorach/nazarenerscan
 * Description:       Ampelsystem für biblisch konforme Produktprüfung. Barcode scannen → Grün / Rot / Gelb. Eigene Spam-Absicherung, DSGVO-konform, REST API, E-Mail-Benachrichtigungen, vollständiges Admin-Backend.
 * Version:           2.0.0
 * Author:            NazarenerScan
 * Text Domain:       nazarener-scan
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NAZARENER_SCAN_VERSION',    '2.0.0' );
define( 'NAZARENER_SCAN_PATH',       plugin_dir_path( __FILE__ ) );
define( 'NAZARENER_SCAN_URL',        plugin_dir_url( __FILE__ ) );
define( 'NAZARENER_SCAN_DB_VERSION', '2.0' );

// ── Core includes ─────────────────────────────────────────────────────────────

require_once NAZARENER_SCAN_PATH . 'includes/class-database.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-hints.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-rate-limiter.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-captcha.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-mailer.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-ajax.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-frontend.php';
require_once NAZARENER_SCAN_PATH . 'includes/class-rest-api.php';

// ── Admin includes ────────────────────────────────────────────────────────────

if ( is_admin() ) {
	require_once NAZARENER_SCAN_PATH . 'admin/class-admin.php';
	require_once NAZARENER_SCAN_PATH . 'admin/class-products-table.php';
	require_once NAZARENER_SCAN_PATH . 'admin/class-submissions-table.php';
	require_once NAZARENER_SCAN_PATH . 'admin/class-activity-log-table.php';
	new NazarenerScan_Admin();
}

// ── Boot ──────────────────────────────────────────────────────────────────────

new NazarenerScan_Ajax();
new NazarenerScan_Frontend();
new NazarenerScan_REST_API();

// ── Lifecycle hooks ───────────────────────────────────────────────────────────

register_activation_hook( __FILE__, [ 'NazarenerScan_Database', 'install' ] );
register_deactivation_hook( __FILE__, [ 'NazarenerScan_Database', 'deactivate' ] );

// ── Cron: cleanup old submissions ─────────────────────────────────────────────

add_action( 'nazarener_daily_cleanup', function () {
	NazarenerScan_Database::cleanup_old_submissions();
} );

if ( ! wp_next_scheduled( 'nazarener_daily_cleanup' ) ) {
	wp_schedule_event( time(), 'daily', 'nazarener_daily_cleanup' );
}
