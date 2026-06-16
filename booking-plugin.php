<?php
/**
 * Plugin Name:       BookEase – Booking & Reservation System
 * Plugin URI:        https://yoursite.com/bookease
 * Description:       A white-label, commercial-quality booking and reservation plugin for hotels, restaurants, clinics, rentals and service businesses.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Agency
 * Author URI:        https://yoursite.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bookease
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'BOOKEASE_VERSION',     '1.0.0' );
define( 'BOOKEASE_FILE',        __FILE__ );
define( 'BOOKEASE_PATH',        plugin_dir_path( __FILE__ ) );
define( 'BOOKEASE_URL',         plugin_dir_url( __FILE__ ) );
define( 'BOOKEASE_ASSETS_URL',  BOOKEASE_URL . 'assets/' );
define( 'BOOKEASE_DB_VERSION',  '1.0.0' );

// ── Autoloader ───────────────────────────────────────────────────────────────
spl_autoload_register( function ( string $class ): void {
    $prefix = 'BookEase\\';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
    $file     = BOOKEASE_PATH . 'src/' . $relative . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// ── Plugin entry ─────────────────────────────────────────────────────────────
function bookease(): \BookEase\Plugin {
    static $instance = null;
    if ( null === $instance ) {
        $instance = new \BookEase\Plugin();
    }
    return $instance;
}

register_activation_hook( __FILE__,   [ 'BookEase\Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BookEase\Installer', 'deactivate' ] );

add_action( 'plugins_loaded', function (): void {
    load_plugin_textdomain( 'bookease', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    bookease()->init();
} );
