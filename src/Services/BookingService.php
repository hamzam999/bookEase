<?php

namespace BookEase\Services;

use BookEase\Models\Booking;
use BookEase\Repositories\BookingRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Core business-logic service for booking lifecycle management.
 */
class BookingService {

    public function __construct(
        private readonly BookingRepository  $repo,
        private readonly AvailabilityService $availability,
        private readonly EmailService        $email,
    ) {}

    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * Create a new booking from validated data.
     *
     * @param array $data { customer_name, customer_email, customer_phone, service_date, start_time, end_time, notes }
     * @return Booking|\WP_Error
     */
    public function create( array $data ): Booking|\WP_Error {
        $validation = $this->validate_booking_data( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Double-booking guard.
        if ( ! $this->availability->is_slot_available( $data['service_date'], $data['start_time'], $data['end_time'] ) ) {
            return new \WP_Error(
                'slot_unavailable',
                __( 'The selected time slot is no longer available. Please choose another.', 'bookease' )
            );
        }

        $insert_data = array_merge( $data, [
            'booking_ref'      => $this->generate_ref(),
            'status'           => apply_filters( 'bookease_default_status', Booking::STATUS_PENDING ),
            'cancel_token'     => wp_generate_password( 32, false ),
            'reschedule_token' => wp_generate_password( 32, false ),
        ] );

        $id = $this->repo->insert( $insert_data );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $booking = $this->repo->find( $id );
        if ( ! $booking ) {
            return new \WP_Error( 'booking_not_found', __( 'Booking created but could not be retrieved.', 'bookease' ) );
        }

        do_action( 'bookease_booking_created', $booking );
        $this->email->send_confirmation( $booking );
        $this->email->send_admin_notification( $booking );

        return $booking;
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /** Update mutable fields on an existing booking (admin). */
    public function update( int $id, array $data ): Booking|\WP_Error {
        $booking = $this->repo->find( $id );
        if ( ! $booking ) {
            return new \WP_Error( 'not_found', __( 'Booking not found.', 'bookease' ) );
        }

        // If the date/time changed, re-validate availability.
        $date_changed = isset( $data['service_date'] ) && $data['service_date'] !== $booking->service_date;
        $time_changed = isset( $data['start_time'] ) && $data['start_time'] !== $booking->start_time;

        if ( $date_changed || $time_changed ) {
            $new_date  = $data['service_date'] ?? $booking->service_date;
            $new_start = $data['start_time']   ?? $booking->start_time;
            $new_end   = $data['end_time']     ?? $booking->end_time;

            if ( ! $this->availability->is_slot_available( $new_date, $new_start, $new_end, $id ) ) {
                return new \WP_Error( 'slot_unavailable', __( 'That slot is no longer available.', 'bookease' ) );
            }
        }

        $result = $this->repo->update( $id, $data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $updated = $this->repo->find( $id );
        do_action( 'bookease_booking_updated', $updated, $booking );

        return $updated;
    }

    // ── Status transitions ────────────────────────────────────────────────────

    public function approve( int $id ): Booking|\WP_Error {
        return $this->transition_status( $id, Booking::STATUS_APPROVED, 'bookease_booking_approved' );
    }

    public function reject( int $id ): Booking|\WP_Error {
        return $this->transition_status( $id, Booking::STATUS_REJECTED, 'bookease_booking_rejected' );
    }

    public function cancel_by_token( string $token ): Booking|\WP_Error {
        $booking = $this->repo->find_by_cancel_token( $token );
        if ( ! $booking ) {
            return new \WP_Error( 'invalid_token', __( 'Invalid or expired cancellation link.', 'bookease' ) );
        }
        if ( Booking::STATUS_CANCELLED === $booking->status ) {
            return new \WP_Error( 'already_cancelled', __( 'This booking has already been cancelled.', 'bookease' ) );
        }
        return $this->transition_status( $booking->id, Booking::STATUS_CANCELLED, 'bookease_booking_cancelled' );
    }

    public function reschedule_by_token( string $token, string $new_date, string $new_start, string $new_end ): Booking|\WP_Error {
        $booking = $this->repo->find_by_reschedule_token( $token );
        if ( ! $booking ) {
            return new \WP_Error( 'invalid_token', __( 'Invalid or expired reschedule link.', 'bookease' ) );
        }

        if ( ! $this->availability->is_slot_available( $new_date, $new_start, $new_end, $booking->id ) ) {
            return new \WP_Error( 'slot_unavailable', __( 'That slot is no longer available.', 'bookease' ) );
        }

        $result = $this->repo->update( $booking->id, [
            'service_date' => $new_date,
            'start_time'   => $new_start,
            'end_time'     => $new_end,
            'status'       => Booking::STATUS_PENDING,
        ] );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $updated = $this->repo->find( $booking->id );
        $this->email->send_reschedule_confirmation( $updated );
        do_action( 'bookease_booking_rescheduled', $updated, $booking );

        return $updated;
    }

    /** Permanently delete a booking record. */
    public function delete( int $id ): bool|\WP_Error {
        $booking = $this->repo->find( $id );
        if ( ! $booking ) {
            return new \WP_Error( 'not_found', __( 'Booking not found.', 'bookease' ) );
        }
        $deleted = $this->repo->delete( $id );
        if ( $deleted ) {
            do_action( 'bookease_booking_deleted', $booking );
        }
        return $deleted;
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /** Stream a CSV download (call before any output). */
    public function export_csv( array $filters = [] ): void {
        $rows = $this->repo->export_csv_rows( $filters );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="bookease-export-' . gmdate( 'Y-m-d' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        if ( ! empty( $rows ) ) {
            fputcsv( $out, array_keys( $rows[0] ) );
            foreach ( $rows as $row ) {
                fputcsv( $out, $row );
            }
        }
        fclose( $out );
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function transition_status( int $id, string $status, string $hook ): Booking|\WP_Error {
        $booking = $this->repo->find( $id );
        if ( ! $booking ) {
            return new \WP_Error( 'not_found', __( 'Booking not found.', 'bookease' ) );
        }

        $this->repo->update( $id, [ 'status' => $status ] );
        $updated = $this->repo->find( $id );

        // Fire email for the given transition.
        match ( $status ) {
            Booking::STATUS_APPROVED  => $this->email->send_approval( $updated ),
            Booking::STATUS_CANCELLED => $this->email->send_cancellation( $updated ),
            default                   => null,
        };

        do_action( $hook, $updated, $booking );
        return $updated;
    }

    private function validate_booking_data( array $data ): true|\WP_Error {
        if ( empty( $data['customer_name'] ) ) {
            return new \WP_Error( 'missing_name', __( 'Name is required.', 'bookease' ) );
        }
        if ( empty( $data['customer_email'] ) || ! is_email( $data['customer_email'] ) ) {
            return new \WP_Error( 'invalid_email', __( 'A valid email address is required.', 'bookease' ) );
        }
        if ( empty( $data['service_date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['service_date'] ) ) {
            return new \WP_Error( 'invalid_date', __( 'A valid date is required.', 'bookease' ) );
        }
        if ( empty( $data['start_time'] ) || empty( $data['end_time'] ) ) {
            return new \WP_Error( 'missing_time', __( 'Please select a time slot.', 'bookease' ) );
        }
        return true;
    }

    private function generate_ref(): string {
        return strtoupper( 'BK-' . substr( uniqid( '', true ), -8 ) );
    }
}
