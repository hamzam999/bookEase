<?php

namespace BookEase\Admin;

use BookEase\Models\Booking;
use BookEase\Repositories\BookingRepository;
use BookEase\Services\BookingService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all WordPress admin menus and handles admin-side AJAX.
 */
class Dashboard {

    public function __construct(
        private readonly BookingRepository $repo,
        private readonly BookingService    $booking_svc,
    ) {}

    public function register(): void {
        add_action( 'admin_menu',             [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );

        // Admin AJAX actions.
        add_action( 'wp_ajax_bookease_admin_get_slots',   [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_bookease_list_bookings',     [ $this, 'ajax_list_bookings' ] );
        add_action( 'wp_ajax_bookease_get_booking',       [ $this, 'ajax_get_booking' ] );
        add_action( 'wp_ajax_bookease_update_status',     [ $this, 'ajax_update_status' ] );
        add_action( 'wp_ajax_bookease_delete_booking',    [ $this, 'ajax_delete_booking' ] );
        add_action( 'wp_ajax_bookease_save_settings',     [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_bookease_save_blocked_date', [ $this, 'ajax_save_blocked_date' ] );
        add_action( 'wp_ajax_bookease_remove_blocked_date', [ $this, 'ajax_remove_blocked_date' ] );
        add_action( 'wp_ajax_bookease_calendar_data',     [ $this, 'ajax_calendar_data' ] );
        add_action( 'wp_ajax_bookease_create_booking',    [ $this, 'ajax_create_booking' ] );
        add_action( 'wp_ajax_bookease_update_booking',    [ $this, 'ajax_update_booking' ] );

        // CSV export (non-AJAX, uses ob_clean trick).
        add_action( 'admin_post_bookease_export_csv', [ $this, 'handle_csv_export' ] );
    }

    // ── Menus ─────────────────────────────────────────────────────────────────

    public function register_menus(): void {
        add_menu_page(
            __( 'BookEase', 'bookease' ),
            __( 'BookEase', 'bookease' ),
            'manage_options',
            'bookease',
            [ $this, 'page_bookings' ],
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page( 'bookease', __( 'All Bookings', 'bookease' ), __( 'All Bookings', 'bookease' ), 'manage_options', 'bookease',              [ $this, 'page_bookings' ] );
        add_submenu_page( 'bookease', __( 'Calendar',     'bookease' ), __( 'Calendar',     'bookease' ), 'manage_options', 'bookease-calendar',     [ $this, 'page_calendar' ] );
        add_submenu_page( 'bookease', __( 'Settings',     'bookease' ), __( 'Settings',     'bookease' ), 'manage_options', 'bookease-settings',     [ $this, 'page_settings' ] );
        add_submenu_page( 'bookease', __( 'Blocked Dates','bookease' ), __( 'Blocked Dates','bookease' ), 'manage_options', 'bookease-blocked-dates', [ $this, 'page_blocked_dates' ] );
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public function page_bookings(): void {
        $this->check_permission();
        include BOOKEASE_PATH . 'templates/admin/bookings.php';
    }

    public function page_calendar(): void {
        $this->check_permission();
        include BOOKEASE_PATH . 'templates/admin/calendar.php';
    }

    public function page_settings(): void {
        $this->check_permission();
        include BOOKEASE_PATH . 'templates/admin/settings.php';
    }

    public function page_blocked_dates(): void {
        $this->check_permission();
        include BOOKEASE_PATH . 'templates/admin/blocked-dates.php';
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'bookease' ) ) {
            return;
        }

        wp_enqueue_style( 'bookease-admin', BOOKEASE_ASSETS_URL . 'css/admin.css', [], BOOKEASE_VERSION );

        wp_enqueue_script(
            'bookease-admin',
            BOOKEASE_ASSETS_URL . 'js/admin.js',
            [ 'jquery' ],
            BOOKEASE_VERSION,
            true
        );

        wp_localize_script( 'bookease-admin', 'bookease_admin', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'bookease_admin' ),
            'exportUrl' => admin_url( 'admin-post.php?action=bookease_export_csv' ),
            'statuses'  => Booking::valid_statuses(),
            'i18n'      => [
                'confirmDelete'   => __( 'Delete this booking permanently?', 'bookease' ),
                'confirmStatus'   => __( 'Change status to', 'bookease' ),
                'saved'           => __( 'Saved.', 'bookease' ),
                'error'           => __( 'Error. Please try again.', 'bookease' ),
                'loading'         => __( 'Loading…', 'bookease' ),
            ],
        ] );
    }

    // ── AJAX handlers ─────────────────────────────────────────────────────────

    public function ajax_update_status(): void {
        $this->verify_admin_nonce();
        $id     = (int)    ( $_POST['id']     ?? 0 );
        $status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

        if ( ! in_array( $status, Booking::valid_statuses(), true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid status.', 'bookease' ) ] );
        }

        $result = match ( $status ) {
            Booking::STATUS_APPROVED  => $this->booking_svc->approve( $id ),
            Booking::STATUS_REJECTED  => $this->booking_svc->reject( $id ),
            Booking::STATUS_CANCELLED => $this->booking_svc->cancel_by_token(
                $this->repo->find( $id )?->cancel_token ?? ''
            ),
            default => $this->booking_svc->update( $id, [ 'status' => $status ] ),
        };

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'booking' => $result->to_array() ] );
    }

    public function ajax_delete_booking(): void {
        $this->verify_admin_nonce();
        $id     = (int) ( $_POST['id'] ?? 0 );
        $result = $this->booking_svc->delete( $id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => __( 'Booking deleted.', 'bookease' ) ] );
    }

    public function ajax_save_settings(): void {
        $this->verify_admin_nonce();

        $allowed = [
            'timezone', 'slot_duration', 'max_bookings_per_slot', 'lead_time_hours',
            'max_advance_days', 'admin_email', 'email_from', 'business_hours',
        ];

        foreach ( $allowed as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $value = wp_unslash( $_POST[ $key ] );
                if ( 'business_hours' === $key ) {
                    // Already JSON-encoded from JS.
                    $value = sanitize_text_field( $value );
                } else {
                    $value = sanitize_text_field( $value );
                }
                $this->repo->set_setting( $key, $value );
            }
        }

        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'bookease' ) ] );
    }

    public function ajax_save_blocked_date(): void {
        $this->verify_admin_nonce();
        $date   = sanitize_text_field( wp_unslash( $_POST['date']   ?? '' ) );
        $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid date.', 'bookease' ) ] );
        }

        $this->repo->add_blocked_date( $date, $reason );
        wp_send_json_success( [ 'dates' => $this->repo->get_blocked_dates() ] );
    }

    public function ajax_remove_blocked_date(): void {
        $this->verify_admin_nonce();
        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
        $this->repo->remove_blocked_date( $date );
        wp_send_json_success( [ 'dates' => $this->repo->get_blocked_dates() ] );
    }

    public function ajax_calendar_data(): void {
        $this->verify_admin_nonce();
        $start = sanitize_text_field( wp_unslash( $_POST['start'] ?? gmdate( 'Y-m-01' ) ) );
        $end   = sanitize_text_field( wp_unslash( $_POST['end']   ?? gmdate( 'Y-m-t' ) ) );

        $bookings = $this->repo->get_by_date_range( $start, $end );
        $events   = array_map( fn( Booking $b ) => [
            'id'    => $b->id,
            'title' => sprintf( '%s – %s', $b->customer_name, $b->booking_ref ),
            'start' => $b->service_date . 'T' . $b->start_time,
            'end'   => $b->service_date . 'T' . $b->end_time,
            'color' => $this->status_color( $b->status ),
            'extendedProps' => $b->to_array(),
        ], $bookings );

        wp_send_json_success( [ 'events' => $events ] );
    }

    public function ajax_create_booking(): void {
        $this->verify_admin_nonce();

        $data = [
            'customer_name'  => sanitize_text_field( wp_unslash( $_POST['customer_name']  ?? '' ) ),
            'customer_email' => sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ),
            'customer_phone' => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) ),
            'service_date'   => sanitize_text_field( wp_unslash( $_POST['service_date']   ?? '' ) ),
            'start_time'     => sanitize_text_field( wp_unslash( $_POST['start_time']     ?? '' ) ),
            'end_time'       => sanitize_text_field( wp_unslash( $_POST['end_time']       ?? '' ) ),
            'notes'          => sanitize_textarea_field( wp_unslash( $_POST['notes']      ?? '' ) ),
        ];

        $result = $this->booking_svc->create( $data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [ 'booking' => $result->to_array() ] );
    }

    public function ajax_update_booking(): void {
        $this->verify_admin_nonce();
        $id = (int) ( $_POST['id'] ?? 0 );

        $data = array_filter( [
            'customer_name'  => sanitize_text_field( wp_unslash( $_POST['customer_name']  ?? '' ) ),
            'customer_email' => sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ),
            'customer_phone' => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) ),
            'service_date'   => sanitize_text_field( wp_unslash( $_POST['service_date']   ?? '' ) ),
            'start_time'     => sanitize_text_field( wp_unslash( $_POST['start_time']     ?? '' ) ),
            'end_time'       => sanitize_text_field( wp_unslash( $_POST['end_time']       ?? '' ) ),
            'notes'          => sanitize_textarea_field( wp_unslash( $_POST['notes']      ?? '' ) ),
        ] );

        $result = $this->booking_svc->update( $id, $data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [ 'booking' => $result->to_array() ] );
    }

    public function ajax_get_slots(): void {
        $this->verify_admin_nonce();
        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );

        global $bookease_availability;
        // Resolve availability service from global or re-instantiate.
        $avail  = new \BookEase\Services\AvailabilityService( $this->repo );
        $slots  = $avail->get_slots( $date );
        wp_send_json_success( [ 'slots' => $slots ] );
    }

    public function handle_csv_export(): void {
        $this->check_permission();
        check_admin_referer( 'bookease_export' );
        $this->booking_svc->export_csv();
    }

    // ── Utilities ─────────────────────────────────────────────────────────────


    public function ajax_list_bookings(): void {
        $this->verify_admin_nonce();
        $result = $this->repo->list( [
            'search'    => sanitize_text_field( wp_unslash( $_POST['search']    ?? '' ) ),
            'status'    => sanitize_text_field( wp_unslash( $_POST['status']    ?? '' ) ),
            'date_from' => sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) ),
            'date_to'   => sanitize_text_field( wp_unslash( $_POST['date_to']   ?? '' ) ),
            'per_page'  => 20,
            'page'      => max( 1, (int) ( $_POST['page'] ?? 1 ) ),
        ] );
        wp_send_json_success( [
            'bookings'    => array_map( fn( $b ) => $b->to_array(), $result['items'] ),
            'total'       => $result['total'],
            'total_pages' => (int) ceil( $result['total'] / 20 ),
        ] );
    }

    public function ajax_get_booking(): void {
        $this->verify_admin_nonce();
        $booking = $this->repo->find( (int) ( $_POST['id'] ?? 0 ) );
        if ( ! $booking ) wp_send_json_error( [ 'message' => __( 'Booking not found.', 'bookease' ) ] );
        wp_send_json_success( [ 'booking' => $booking->to_array() ] );
    }

    private function check_permission(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'bookease' ) );
        }
    }

    private function verify_admin_nonce(): void {
        if ( ! check_ajax_referer( 'bookease_admin', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'bookease' ) ], 403 );
        }
        $this->check_permission();
    }

    private function status_color( string $status ): string {
        return match ( $status ) {
            'approved'  => '#00a651',
            'pending'   => '#f59e0b',
            'rejected'  => '#ef4444',
            'cancelled' => '#6b7280',
            default     => '#3b82f6',
        };
    }
}

    // This method must be appended to the Dashboard class - added via registration
