<?php

namespace BookEase\Frontend;

use BookEase\Services\AvailabilityService;
use BookEase\Services\BookingService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the [bookease_form] shortcode and handles frontend AJAX actions.
 */
class BookingForm {

    public function __construct(
        private readonly BookingService     $booking_svc,
        private readonly AvailabilityService $availability,
        private readonly \BookEase\Repositories\BookingRepository $repo,
    ) {}

    public function register(): void {
        add_shortcode( 'bookease_form', [ $this, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX handlers.
        add_action( 'wp_ajax_bookease_get_slots',      [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_nopriv_bookease_get_slots', [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_bookease_submit',         [ $this, 'ajax_submit' ] );
        add_action( 'wp_ajax_nopriv_bookease_submit',  [ $this, 'ajax_submit' ] );
        add_action( 'wp_ajax_bookease_reschedule',         [ $this, 'ajax_reschedule' ] );
        add_action( 'wp_ajax_nopriv_bookease_reschedule',  [ $this, 'ajax_reschedule' ] );

        // Token-based actions (cancel / reschedule).
        add_action( 'template_redirect', [ $this, 'handle_token_actions' ] );
    }

    // ── Shortcode ─────────────────────────────────────────────────────────────

    public function render_shortcode( array $atts ): string {
        $atts = shortcode_atts( [], $atts, 'bookease_form' );
        wp_enqueue_style( 'bookease-frontend' );
        wp_enqueue_script( 'bookease-frontend' );
        ob_start();
        include BOOKEASE_PATH . 'templates/frontend/booking-form.php';
        return ob_get_clean();
    }

    // ── Asset enqueueing ──────────────────────────────────────────────────────

    public function enqueue_assets(): void {
        wp_register_style(
            'bookease-frontend',
            BOOKEASE_ASSETS_URL . 'css/frontend.css',
            [],
            BOOKEASE_VERSION
        );

        wp_register_script(
            'bookease-frontend',
            BOOKEASE_ASSETS_URL . 'js/frontend.js',
            [ 'jquery' ],
            BOOKEASE_VERSION,
            true
        );

        wp_localize_script( 'bookease-frontend', 'bookease', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'bookease_frontend' ),
            'slotDuration' => (int) $this->repo->get_setting( 'slot_duration', 60 ),
            'i18n'         => [
                'selectDate'    => __( 'Select a date first', 'bookease' ),
                'noSlots'       => __( 'No available slots for this date.', 'bookease' ),
                'loading'       => __( 'Loading…', 'bookease' ),
                'submitting'    => __( 'Submitting…', 'bookease' ),
                'error'         => __( 'Something went wrong. Please try again.', 'bookease' ),
            ],
        ] );
    }

    // ── AJAX: get slots ───────────────────────────────────────────────────────

    public function ajax_get_slots(): void {
        check_ajax_referer( 'bookease_frontend', 'nonce' );

        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid date.', 'bookease' ) ] );
        }

        $slots = $this->availability->get_slots( $date );
        wp_send_json_success( [ 'slots' => $slots ] );
    }

    // ── AJAX: submit booking ──────────────────────────────────────────────────

    public function ajax_submit(): void {
        check_ajax_referer( 'bookease_frontend', 'nonce' );

        $data = [
            'customer_name'  => sanitize_text_field( wp_unslash( $_POST['name']  ?? '' ) ),
            'customer_email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'customer_phone' => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
            'service_date'   => sanitize_text_field( wp_unslash( $_POST['date']  ?? '' ) ),
            'start_time'     => sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) ),
            'end_time'       => sanitize_text_field( wp_unslash( $_POST['end_time']   ?? '' ) ),
            'notes'          => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
        ];

        $result = $this->booking_svc->create( $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'     => __( 'Your booking has been submitted! Check your email for confirmation.', 'bookease' ),
            'booking_ref' => $result->booking_ref,
        ] );
    }

    // ── AJAX: reschedule booking ──────────────────────────────────────────────

    public function ajax_reschedule(): void {
        check_ajax_referer( 'bookease_frontend', 'nonce' );

        $token     = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        $new_date  = sanitize_text_field( wp_unslash( $_POST['new_date'] ?? '' ) );
        $new_start = sanitize_text_field( wp_unslash( $_POST['new_start'] ?? '' ) );
        $new_end   = sanitize_text_field( wp_unslash( $_POST['new_end'] ?? '' ) );

        if ( ! $token || ! $new_date || ! $new_start || ! $new_end ) {
            wp_send_json_error( [ 'message' => __( 'Missing required reschedule parameters.', 'bookease' ) ] );
        }

        $result = $this->booking_svc->reschedule_by_token( $token, $new_date, $new_start, $new_end );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'Your booking has been rescheduled successfully.', 'bookease' ),
        ] );
    }

    // ── Token actions (cancel / reschedule links) ─────────────────────────────

    public function handle_token_actions(): void {
        $action = sanitize_text_field( wp_unslash( $_GET['bookease_action'] ?? '' ) );
        $token  = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );

        if ( ! $action || ! $token ) {
            return;
        }

        if ( 'cancel' === $action ) {
            $result = $this->booking_svc->cancel_by_token( $token );
            $msg    = is_wp_error( $result )
                ? $result->get_error_message()
                : __( 'Your booking has been cancelled.', 'bookease' );

            wp_die( esc_html( $msg ), esc_html__( 'Booking Cancelled', 'bookease' ), [ 'response' => 200 ] );
        }

        if ( 'reschedule' === $action ) {
            // Show a reschedule form.
            include BOOKEASE_PATH . 'templates/frontend/reschedule.php';
            exit;
        }
    }
}
