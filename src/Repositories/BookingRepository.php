<?php

namespace BookEase\Repositories;

use BookEase\Models\Booking;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all direct database interaction for bookings.
 * Uses prepared statements exclusively — no raw interpolation.
 */
class BookingRepository {

    private string $table;
    private string $blocked_table;
    private string $settings_table;

    public function __construct() {
        global $wpdb;
        $this->table          = $wpdb->prefix . 'bookease_bookings';
        $this->blocked_table  = $wpdb->prefix . 'bookease_blocked_dates';
        $this->settings_table = $wpdb->prefix . 'bookease_settings';
    }

    // ── Bookings ──────────────────────────────────────────────────────────────

    public function find( int $id ): ?Booking {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $id ),
            ARRAY_A
        );
        return $row ? new Booking( $row ) : null;
    }

    public function find_by_ref( string $ref ): ?Booking {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE booking_ref = %s", $ref ),
            ARRAY_A
        );
        return $row ? new Booking( $row ) : null;
    }

    public function find_by_cancel_token( string $token ): ?Booking {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE cancel_token = %s", $token ),
            ARRAY_A
        );
        return $row ? new Booking( $row ) : null;
    }

    public function find_by_reschedule_token( string $token ): ?Booking {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE reschedule_token = %s", $token ),
            ARRAY_A
        );
        return $row ? new Booking( $row ) : null;
    }

    /**
     * Count active bookings overlapping a specific date + time range.
     * Used for double-booking prevention.
     */
    public function count_overlapping( string $date, string $start, string $end, int $exclude_id = 0 ): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$this->table}`
                 WHERE service_date = %s
                   AND status NOT IN ('cancelled','rejected')
                   AND id != %d
                   AND start_time < %s
                   AND end_time   > %s",
                $date,
                $exclude_id,
                $end,
                $start
            )
        );
    }

    /**
     * Count bookings in a specific slot (exact match).
     */
    public function count_in_slot( string $date, string $start, string $end ): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$this->table}`
                 WHERE service_date = %s
                   AND start_time   = %s
                   AND end_time     = %s
                   AND status NOT IN ('cancelled','rejected')",
                $date, $start, $end
            )
        );
    }

    /** Return all bookings for a specific date. */
    public function get_by_date( string $date ): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$this->table}` WHERE service_date = %s ORDER BY start_time ASC",
                $date
            ),
            ARRAY_A
        );
        return array_map( fn( $r ) => new Booking( $r ), $rows ?: [] );
    }

    /** Return bookings in a date range (for calendar). */
    public function get_by_date_range( string $start_date, string $end_date ): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$this->table}`
                 WHERE service_date BETWEEN %s AND %s
                 ORDER BY service_date ASC, start_time ASC",
                $start_date, $end_date
            ),
            ARRAY_A
        );
        return array_map( fn( $r ) => new Booking( $r ), $rows ?: [] );
    }

    /**
     * Paginated list with optional search/filter.
     *
     * @return array{ items: Booking[], total: int }
     */
    public function list( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'search'     => '',
            'status'     => '',
            'date_from'  => '',
            'date_to'    => '',
            'per_page'   => 20,
            'page'       => 1,
            'orderby'    => 'created_at',
            'order'      => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where  = [ '1=1' ];
        $params = [];

        if ( $args['search'] ) {
            $like        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]     = '(customer_name LIKE %s OR customer_email LIKE %s OR booking_ref LIKE %s)';
            $params[]    = $like;
            $params[]    = $like;
            $params[]    = $like;
        }

        if ( $args['status'] && in_array( $args['status'], Booking::valid_statuses(), true ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        if ( $args['date_from'] ) {
            $where[]  = 'service_date >= %s';
            $params[] = $args['date_from'];
        }

        if ( $args['date_to'] ) {
            $where[]  = 'service_date <= %s';
            $params[] = $args['date_to'];
        }

        $where_sql = implode( ' AND ', $where );

        $allowed_orderby = [ 'id', 'booking_ref', 'customer_name', 'service_date', 'status', 'created_at', 'service_date_time' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

        if ( 'service_date_time' === $orderby ) {
            $orderby = "service_date {$order}, start_time {$order}";
            $order   = '';
        }

        $offset  = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];

        // Total count.
        $count_sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

        // Data query.
        $data_sql = "SELECT * FROM `{$this->table}` WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $all_params = array_merge( $params, [ (int) $args['per_page'], $offset ] );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $all_params ), ARRAY_A );

        return [
            'items' => array_map( fn( $r ) => new Booking( $r ), $rows ?: [] ),
            'total' => $total,
        ];
    }

    /** Insert a new booking. Returns the new ID or WP_Error. */
    public function insert( array $data ): int|\WP_Error {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->insert(
            $this->table,
            [
                'booking_ref'      => sanitize_text_field( $data['booking_ref'] ),
                'customer_name'    => sanitize_text_field( $data['customer_name'] ),
                'customer_email'   => sanitize_email( $data['customer_email'] ),
                'customer_phone'   => sanitize_text_field( $data['customer_phone'] ),
                'service_date'     => sanitize_text_field( $data['service_date'] ),
                'start_time'       => sanitize_text_field( $data['start_time'] ),
                'end_time'         => sanitize_text_field( $data['end_time'] ),
                'status'           => sanitize_text_field( $data['status'] ?? 'pending' ),
                'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
                'cancel_token'     => sanitize_text_field( $data['cancel_token'] ?? '' ),
                'reschedule_token' => sanitize_text_field( $data['reschedule_token'] ?? '' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            return new \WP_Error( 'db_insert_error', $wpdb->last_error );
        }

        return (int) $wpdb->insert_id;
    }

    /** Update an existing booking by ID. */
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;

        $allowed_fields = [ 'customer_name', 'customer_email', 'customer_phone', 'service_date', 'start_time', 'end_time', 'status', 'notes', 'cancel_token', 'reschedule_token' ];
        $update         = array_intersect_key( $data, array_flip( $allowed_fields ) );

        if ( empty( $update ) ) {
            return new \WP_Error( 'no_fields', __( 'No valid fields to update.', 'bookease' ) );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update( $this->table, $update, [ 'id' => $id ] );

        return false !== $result;
    }

    /** Permanently delete a booking. */
    public function delete( int $id ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (bool) $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );
    }

    // ── Blocked dates ──────────────────────────────────────────────────────────

    public function get_blocked_dates(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_col( "SELECT blocked_date FROM `{$this->blocked_table}` ORDER BY blocked_date ASC" ) ?: [];
    }

    public function add_blocked_date( string $date, string $reason = '' ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (bool) $wpdb->replace(
            $this->blocked_table,
            [ 'blocked_date' => $date, 'reason' => sanitize_text_field( $reason ) ],
            [ '%s', '%s' ]
        );
    }

    public function remove_blocked_date( string $date ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (bool) $wpdb->delete( $this->blocked_table, [ 'blocked_date' => $date ], [ '%s' ] );
    }

    // ── Settings ───────────────────────────────────────────────────────────────

    public function get_setting( string $key, mixed $default = null ): mixed {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $value = $wpdb->get_var(
            $wpdb->prepare( "SELECT setting_value FROM `{$this->settings_table}` WHERE setting_key = %s", $key )
        );
        return null !== $value ? $value : $default;
    }

    public function set_setting( string $key, mixed $value ): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->replace(
            $this->settings_table,
            [ 'setting_key' => $key, 'setting_value' => (string) $value ],
            [ '%s', '%s' ]
        );
    }

    public function get_all_settings(): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM `{$this->settings_table}`", ARRAY_A ) ?: [];
        return array_column( $rows, 'setting_value', 'setting_key' );
    }

    /** Export bookings as flat arrays (for CSV). */
    public function export_csv_rows( array $args = [] ): array {
        $result = $this->list( array_merge( $args, [ 'per_page' => PHP_INT_MAX, 'page' => 1 ] ) );
        return array_map( fn( Booking $b ) => $b->to_array(), $result['items'] );
    }
}
