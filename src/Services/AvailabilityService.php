<?php

namespace BookEase\Services;

use BookEase\Repositories\BookingRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Determines which time slots are open for a given date.
 */
class AvailabilityService {

    public function __construct( private readonly BookingRepository $repo ) {}

    /**
     * Return all time slots for a date with their availability.
     *
     * @return array<array{start: string, end: string, available: bool, remaining: int}>
     */
    public function get_slots( string $date ): array {
        if ( ! $this->is_date_bookable( $date ) ) {
            return [];
        }

        $settings   = $this->repo->get_all_settings();
        $duration   = (int) ( $settings['slot_duration']         ?? 60 );
        $max_per    = (int) ( $settings['max_bookings_per_slot'] ?? 1  );
        $hours_json = $settings['business_hours'] ?? '{}';
        $biz_hours  = json_decode( $hours_json, true ) ?: [];

        // Lead time: don't show past slots.
        $lead_hours  = (int) ( $settings['lead_time_hours'] ?? 2 );
        $earliest_ts = time() + ( $lead_hours * 3600 );

        // Custom slots override.
        $use_custom = ( $settings['use_custom_slots'] ?? '0' ) === '1';
        if ( $use_custom ) {
            $custom_slots_json = $settings['custom_slots'] ?? '[]';
            $custom_slots      = json_decode( $custom_slots_json, true ) ?: [];
            $slots = [];
            foreach ( $custom_slots as $slot ) {
                $start = sanitize_text_field( $slot['start'] ?? '' );
                $end   = sanitize_text_field( $slot['end'] ?? '' );
                if ( ! $start || ! $end ) {
                    continue;
                }

                $start_formatted = date( 'H:i:s', strtotime( $start ) );
                $end_formatted   = date( 'H:i:s', strtotime( $end ) );

                $ts     = strtotime( "{$date} {$start_formatted}" );
                $booked = $this->repo->count_in_slot( $date, $start_formatted, $end_formatted );
                $remain = max( 0, $max_per - $booked );
                $past   = ( $ts < $earliest_ts );

                $slots[] = [
                    'start'     => $start_formatted,
                    'end'       => $end_formatted,
                    'available' => $remain > 0 && ! $past,
                    'remaining' => $remain,
                ];
            }
            return apply_filters( 'bookease_available_slots', $slots, $date );
        }

        // Day of week in lowercase English.
        $day_name = strtolower( gmdate( 'l', strtotime( $date ) ) );
        $day_cfg  = $biz_hours[ $day_name ] ?? [];

        if ( empty( $day_cfg['enabled'] ) ) {
            return [];
        }

        $open  = $day_cfg['open']  ?? '09:00';
        $close = $day_cfg['close'] ?? '17:00';

        $open_ts  = strtotime( "{$date} {$open}" );
        $close_ts = strtotime( "{$date} {$close}" );
        $step     = $duration * 60;

        $slots = [];
        for ( $ts = $open_ts; ( $ts + $step ) <= $close_ts; $ts += $step ) {
            $start   = gmdate( 'H:i:s', $ts );
            $end     = gmdate( 'H:i:s', $ts + $step );
            $booked  = $this->repo->count_in_slot( $date, $start, $end );
            $remain  = max( 0, $max_per - $booked );
            $past    = ( $ts < $earliest_ts );

            $slots[] = [
                'start'     => $start,
                'end'       => $end,
                'available' => $remain > 0 && ! $past,
                'remaining' => $remain,
            ];
        }

        return apply_filters( 'bookease_available_slots', $slots, $date );
    }

    /** Whether a calendar date can accept new bookings at all. */
    public function is_date_bookable( string $date ): bool {
        $settings = $this->repo->get_all_settings();

        // Must be in the future (respecting lead time).
        $lead_hours  = (int) ( $settings['lead_time_hours'] ?? 2 );
        $date_ts     = strtotime( $date );
        if ( $date_ts < strtotime( 'today' ) ) {
            return false;
        }

        // Max advance days.
        $max_advance = (int) ( $settings['max_advance_days'] ?? 60 );
        if ( $date_ts > strtotime( "+{$max_advance} days" ) ) {
            return false;
        }

        // Blocked date check.
        if ( in_array( $date, $this->repo->get_blocked_dates(), true ) ) {
            return false;
        }

        // Business hours for this day.
        $hours_json = $settings['business_hours'] ?? '{}';
        $biz_hours  = json_decode( $hours_json, true ) ?: [];
        $day_name   = strtolower( gmdate( 'l', $date_ts ) );
        $day_cfg    = $biz_hours[ $day_name ] ?? [];

        if ( empty( $day_cfg['enabled'] ) ) {
            return false;
        }

        return apply_filters( 'bookease_is_date_bookable', true, $date );
    }

    /**
     * Returns whether a specific slot is still available.
     */
    public function is_slot_available( string $date, string $start, string $end, int $exclude_id = 0 ): bool {
        if ( ! $this->is_date_bookable( $date ) ) {
            return false;
        }

        $settings   = $this->repo->get_all_settings();
        $max_per    = (int) ( $settings['max_bookings_per_slot'] ?? 1 );
        $overlapping = $this->repo->count_overlapping( $date, $start, $end, $exclude_id );

        return $overlapping < $max_per;
    }

    /**
     * Return a map of YYYY-MM-DD => availability status for a month view.
     * Values: 'open' | 'full' | 'closed'
     */
    public function get_month_availability( int $year, int $month ): array {
        $days_in_month = (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) );
        $result        = [];

        for ( $d = 1; $d <= $days_in_month; $d++ ) {
            $date   = sprintf( '%04d-%02d-%02d', $year, $month, $d );
            $slots  = $this->get_slots( $date );

            if ( empty( $slots ) ) {
                $result[ $date ] = 'closed';
                continue;
            }

            $has_open = array_filter( $slots, fn( $s ) => $s['available'] );
            $result[ $date ] = $has_open ? 'open' : 'full';
        }

        return $result;
    }
}
