<?php
/**
 * Fires when the plugin is deleted from WordPress admin.
 * Removes all database tables and options created by BookEase.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = [
    $wpdb->prefix . 'bookease_bookings',
    $wpdb->prefix . 'bookease_blocked_dates',
    $wpdb->prefix . 'bookease_settings',
];

foreach ( $tables as $table ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

delete_option( 'bookease_db_version' );
delete_option( 'bookease_settings' );
delete_option( 'bookease_flush_rewrite' );
