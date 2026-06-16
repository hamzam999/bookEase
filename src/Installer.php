<?php

namespace BookEase;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation, deactivation, and database installation.
 */
final class Installer {

    /** Run on plugin activation. */
    public static function activate(): void {
        self::create_tables();
        self::seed_default_settings();
        update_option( 'bookease_db_version', BOOKEASE_DB_VERSION );
        update_option( 'bookease_flush_rewrite', true );
    }

    /** Run on plugin deactivation. */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    /** Create all custom database tables. */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── Bookings ─────────────────────────────────────────────────────────
        $sql_bookings = "CREATE TABLE {$wpdb->prefix}bookease_bookings (
            id           BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_ref  VARCHAR(20)  NOT NULL,
            customer_name  VARCHAR(150) NOT NULL,
            customer_email VARCHAR(150) NOT NULL,
            customer_phone VARCHAR(30)  NOT NULL DEFAULT '',
            service_date DATE         NOT NULL,
            start_time   TIME         NOT NULL,
            end_time     TIME         NOT NULL,
            status       VARCHAR(20)  NOT NULL DEFAULT 'pending',
            notes        TEXT,
            cancel_token VARCHAR(64)  NOT NULL DEFAULT '',
            reschedule_token VARCHAR(64) NOT NULL DEFAULT '',
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   booking_ref  (booking_ref),
            KEY          service_date (service_date),
            KEY          status       (status),
            KEY          customer_email (customer_email)
        ) $charset_collate;";

        // ── Blocked dates ─────────────────────────────────────────────────────
        $sql_blocked = "CREATE TABLE {$wpdb->prefix}bookease_blocked_dates (
            id           BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            blocked_date DATE         NOT NULL,
            reason       VARCHAR(255) NOT NULL DEFAULT '',
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   blocked_date (blocked_date)
        ) $charset_collate;";

        // ── Settings ──────────────────────────────────────────────────────────
        $sql_settings = "CREATE TABLE {$wpdb->prefix}bookease_settings (
            id            BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key   VARCHAR(100) NOT NULL,
            setting_value LONGTEXT,
            PRIMARY KEY  (id),
            UNIQUE KEY   setting_key (setting_key)
        ) $charset_collate;";

        dbDelta( $sql_bookings );
        dbDelta( $sql_blocked );
        dbDelta( $sql_settings );
    }

    /** Insert sensible defaults on first activation. */
    private static function seed_default_settings(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'bookease_settings';

        $defaults = [
            'timezone'              => wp_timezone_string(),
            'slot_duration'         => 60,   // minutes
            'max_bookings_per_slot' => 1,
            'lead_time_hours'       => 2,
            'max_advance_days'      => 60,
            'business_hours'        => wp_json_encode( [
                'monday'    => [ 'open' => '09:00', 'close' => '17:00', 'enabled' => true ],
                'tuesday'   => [ 'open' => '09:00', 'close' => '17:00', 'enabled' => true ],
                'wednesday' => [ 'open' => '09:00', 'close' => '17:00', 'enabled' => true ],
                'thursday'  => [ 'open' => '09:00', 'close' => '17:00', 'enabled' => true ],
                'friday'    => [ 'open' => '09:00', 'close' => '17:00', 'enabled' => true ],
                'saturday'  => [ 'open' => '09:00', 'close' => '17:00', 'enabled' => false ],
                'sunday'    => [ 'open' => '09:00', 'close' => '17:00', 'enabled' => false ],
            ] ),
            'admin_email'    => get_option( 'admin_email' ),
            'email_from'     => get_option( 'blogname' ),
            'currency'       => 'USD',
        ];

        foreach ( $defaults as $key => $value ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO `{$table}` (setting_key, setting_value) VALUES (%s, %s)",
                    $key,
                    (string) $value
                )
            );
        }
    }
}
