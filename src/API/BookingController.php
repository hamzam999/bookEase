<?php

namespace BookEase\API;

use BookEase\Models\Booking;
use BookEase\Services\AvailabilityService;
use BookEase\Services\BookingService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles all BookEase REST API endpoints.
 *
 * Base: /wp-json/bookease/v1/
 */
class BookingController extends \WP_REST_Controller {

    protected $namespace = 'bookease/v1';
    protected $rest_base = 'bookings';

    public function __construct(
        private readonly BookingService     $booking_svc,
        private readonly AvailabilityService $availability,
    ) {}

    public function register_routes(): void {

        // GET|POST /bookings
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'admin_permission' ],
                'args'                => $this->get_collection_params(),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_item' ],
                'permission_callback' => '__return_true',
                'args'                => $this->get_item_schema_args(),
            ],
        ] );

        // GET|PUT|DELETE /bookings/{id}
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_item' ],
                'permission_callback' => [ $this, 'admin_permission' ],
                'args'                => [ 'id' => [ 'validate_callback' => 'is_numeric' ] ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_item' ],
                'permission_callback' => [ $this, 'admin_permission' ],
                'args'                => $this->get_item_schema_args( false ),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_item' ],
                'permission_callback' => [ $this, 'admin_permission' ],
            ],
        ] );

        // POST /bookings/{id}/cancel
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/cancel', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'cancel_item' ],
            'permission_callback' => [ $this, 'admin_permission' ],
        ] );

        // GET /availability
        register_rest_route( $this->namespace, '/availability', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'check_availability' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'date' => [
                    'required'          => true,
                    'validate_callback' => fn( $v ) => (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    public function get_items( $request ) {
        global $bookease_repo;
        $repo   = new \BookEase\Repositories\BookingRepository();
        $result = $repo->list( [
            'search'   => $request->get_param( 'search' )   ?? '',
            'status'   => $request->get_param( 'status' )   ?? '',
            'per_page' => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
            'page'     => (int) ( $request->get_param( 'page' )     ?? 1  ),
        ] );

        $items = array_map( fn( Booking $b ) => $b->to_array(), $result['items'] );

        $response = new \WP_REST_Response( $items, 200 );
        $response->header( 'X-WP-Total',      $result['total'] );
        $response->header( 'X-WP-TotalPages', (int) ceil( $result['total'] / 20 ) );
        return $response;
    }

    public function get_item( $request ) {
        $repo    = new \BookEase\Repositories\BookingRepository();
        $booking = $repo->find( (int) $request->get_param( 'id' ) );
        if ( ! $booking ) {
            return new \WP_Error( 'not_found', __( 'Booking not found.', 'bookease' ), [ 'status' => 404 ] );
        }
        return new \WP_REST_Response( $booking->to_array(), 200 );
    }

    public function create_item( $request ) {
        $data   = $request->get_json_params() ?: $request->get_params();
        $result = $this->booking_svc->create( $data );

        if ( is_wp_error( $result ) ) {
            return new \WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 422 ] );
        }
        return new \WP_REST_Response( $result->to_array(), 201 );
    }

    public function update_item( $request ) {
        $id     = (int) $request->get_param( 'id' );
        $data   = $request->get_json_params() ?: $request->get_params();
        $result = $this->booking_svc->update( $id, $data );

        if ( is_wp_error( $result ) ) {
            return new \WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 422 ] );
        }
        return new \WP_REST_Response( $result->to_array(), 200 );
    }

    public function delete_item( $request ) {
        $result = $this->booking_svc->delete( (int) $request->get_param( 'id' ) );
        if ( is_wp_error( $result ) ) {
            return new \WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 422 ] );
        }
        return new \WP_REST_Response( null, 204 );
    }

    public function cancel_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $repo    = new \BookEase\Repositories\BookingRepository();
        $booking = $repo->find( (int) $request->get_param( 'id' ) );
        if ( ! $booking ) {
            return new \WP_Error( 'not_found', __( 'Booking not found.', 'bookease' ), [ 'status' => 404 ] );
        }
        $result = $this->booking_svc->cancel_by_token( $booking->cancel_token );
        if ( is_wp_error( $result ) ) {
            return new \WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 422 ] );
        }
        return new \WP_REST_Response( $result->to_array(), 200 );
    }

    public function check_availability( \WP_REST_Request $request ): \WP_REST_Response {
        $date  = $request->get_param( 'date' );
        $slots = $this->availability->get_slots( $date );
        return new \WP_REST_Response( [
            'date'      => $date,
            'bookable'  => $this->availability->is_date_bookable( $date ),
            'slots'     => $slots,
        ], 200 );
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    public function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    // ── Schema helpers ────────────────────────────────────────────────────────

    public function get_collection_params(): array {
        return [
            'search'   => [ 'type' => 'string', 'default' => '' ],
            'status'   => [ 'type' => 'string', 'enum' => array_merge( [ '' ], Booking::valid_statuses() ) ],
            'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
            'page'     => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
        ];
    }

    private function get_item_schema_args( bool $required = true ): array {
        return [
            'customer_name'  => [ 'type' => 'string', 'required' => $required, 'sanitize_callback' => 'sanitize_text_field' ],
            'customer_email' => [ 'type' => 'string', 'required' => $required, 'sanitize_callback' => 'sanitize_email', 'format' => 'email' ],
            'customer_phone' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'service_date'   => [ 'type' => 'string', 'required' => $required, 'format' => 'date' ],
            'start_time'     => [ 'type' => 'string', 'required' => $required ],
            'end_time'       => [ 'type' => 'string', 'required' => $required ],
            'notes'          => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
        ];
    }
}
