<?php

namespace BookEase\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object representing a single booking.
 */
final class Booking {

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public readonly int    $id;
    public readonly string $booking_ref;
    public readonly string $customer_name;
    public readonly string $customer_email;
    public readonly string $customer_phone;
    public readonly string $service_date;   // Y-m-d
    public readonly string $start_time;     // H:i:s
    public readonly string $end_time;       // H:i:s
    public readonly string $status;
    public readonly string $notes;
    public readonly string $cancel_token;
    public readonly string $reschedule_token;
    public readonly string $created_at;
    public readonly string $updated_at;

    public function __construct( array $data ) {
        $this->id               = (int) ( $data['id']               ?? 0 );
        $this->booking_ref      = $data['booking_ref']      ?? '';
        $this->customer_name    = $data['customer_name']    ?? '';
        $this->customer_email   = $data['customer_email']   ?? '';
        $this->customer_phone   = $data['customer_phone']   ?? '';
        $this->service_date     = $data['service_date']     ?? '';
        $this->start_time       = $data['start_time']       ?? '';
        $this->end_time         = $data['end_time']         ?? '';
        $this->status           = $data['status']           ?? self::STATUS_PENDING;
        $this->notes            = $data['notes']            ?? '';
        $this->cancel_token     = $data['cancel_token']     ?? '';
        $this->reschedule_token = $data['reschedule_token'] ?? '';
        $this->created_at       = $data['created_at']       ?? '';
        $this->updated_at       = $data['updated_at']       ?? '';
    }

    /** Return a new Booking with updated fields. */
    public function with( array $changes ): self {
        $data = [
            'id'               => $this->id,
            'booking_ref'      => $this->booking_ref,
            'customer_name'    => $this->customer_name,
            'customer_email'   => $this->customer_email,
            'customer_phone'   => $this->customer_phone,
            'service_date'     => $this->service_date,
            'start_time'       => $this->start_time,
            'end_time'         => $this->end_time,
            'status'           => $this->status,
            'notes'            => $this->notes,
            'cancel_token'     => $this->cancel_token,
            'reschedule_token' => $this->reschedule_token,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
        return new self( array_merge( $data, $changes ) );
    }

    public function to_array(): array {
        return [
            'id'               => $this->id,
            'booking_ref'      => $this->booking_ref,
            'customer_name'    => $this->customer_name,
            'customer_email'   => $this->customer_email,
            'customer_phone'   => $this->customer_phone,
            'service_date'     => $this->service_date,
            'start_time'       => $this->start_time,
            'end_time'         => $this->end_time,
            'status'           => $this->status,
            'notes'            => $this->notes,
            'cancel_token'     => $this->cancel_token,
            'reschedule_token' => $this->reschedule_token,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }

    public static function valid_statuses(): array {
        return [ self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED ];
    }
}
