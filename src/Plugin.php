<?php

namespace BookEase;

defined( 'ABSPATH' ) || exit;

/**
 * Core orchestrator. Instantiates and wires up all modules.
 */
final class Plugin {

    private bool $initialized = false;

    /** Boot the plugin. */
    public function init(): void {
        if ( $this->initialized ) {
            return;
        }
        $this->initialized = true;

        // Core services (always loaded).
        $repository  = new Repositories\BookingRepository();
        $avail       = new Services\AvailabilityService( $repository );
        $email       = new Services\EmailService();
        $booking_svc = new Services\BookingService( $repository, $avail, $email );

        // Shortcode + frontend.
        $frontend = new Frontend\BookingForm( $booking_svc, $avail, $repository );
        $frontend->register();

        // REST API.
        $api = new API\BookingController( $booking_svc, $avail );
        add_action( 'rest_api_init', [ $api, 'register_routes' ] );

        // Admin-only modules.
        if ( is_admin() ) {
            $admin = new Admin\Dashboard( $repository, $booking_svc );
            $admin->register();
        }

        // Flush rewrite rules once after activation.
        if ( get_option( 'bookease_flush_rewrite' ) ) {
            flush_rewrite_rules();
            delete_option( 'bookease_flush_rewrite' );
        }

        do_action( 'bookease_loaded', $this );
    }
}
