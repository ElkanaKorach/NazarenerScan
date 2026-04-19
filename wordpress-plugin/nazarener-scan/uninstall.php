<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nazarener_products" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nazarener_submissions" );

delete_option( 'nazarener_scan_settings' );
delete_option( 'nazarener_scan_db_version' );
