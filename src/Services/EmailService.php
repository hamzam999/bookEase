<?php

namespace BookEase\Services;

use BookEase\Models\Booking;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all transactional emails for the booking lifecycle.
 * Templates are filterable so operators can customise them.
 */
class EmailService {

    /** Send booking confirmation to the customer. */
    public function send_confirmation( Booking $booking ): void {
        $this->send( 'confirmation', $booking->customer_email, $booking );
    }

    /** Send admin notification for a new booking. */
    public function send_admin_notification( Booking $booking ): void {
        $admin_email = $this->get_setting( 'admin_email', get_option( 'admin_email' ) );
        $this->send( 'admin_notification', $admin_email, $booking );
    }

    /** Send approval email to the customer. */
    public function send_approval( Booking $booking ): void {
        $this->send( 'approval', $booking->customer_email, $booking );
    }

    /** Send cancellation email to the customer. */
    public function send_cancellation( Booking $booking ): void {
        $this->send( 'cancellation', $booking->customer_email, $booking );
    }

    /** Send reschedule confirmation to the customer. */
    public function send_reschedule_confirmation( Booking $booking ): void {
        $this->send( 'reschedule', $booking->customer_email, $booking );
    }

    // ── Core send method ──────────────────────────────────────────────────────

    private function send( string $type, string $to, Booking $booking ): void {
        $template = $this->get_template( $type, $booking );

        $subject = apply_filters(
            "bookease_email_subject_{$type}",
            $template['subject'],
            $booking
        );

        $body = apply_filters(
            "bookease_email_body_{$type}",
            $template['body'],
            $booking
        );

        $from_name  = $this->get_setting( 'email_from', get_bloginfo( 'name' ) );
        $from_email = $this->get_setting( 'admin_email', get_option( 'admin_email' ) );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        ];

        wp_mail( $to, $subject, $this->wrap_html( $body, $subject ), $headers );
    }

    // ── Template builder ──────────────────────────────────────────────────────

    private function get_template( string $type, Booking $booking ): array {
        $site   = get_bloginfo( 'name' );
        $cancel_url     = $this->build_token_url( 'cancel',     $booking->cancel_token );
        $reschedule_url = $this->build_token_url( 'reschedule', $booking->reschedule_token );

        $common = $this->common_vars( $booking );

        return match ( $type ) {
            'confirmation' => [
                'subject' => sprintf(
                    /* translators: %1$s booking ref, %2$s site name */
                    __( 'Booking Confirmed – %1$s | %2$s', 'bookease' ),
                    $booking->booking_ref, $site
                ),
                'body' => $this->interpolate(
                    $this->template_confirmation(),
                    array_merge( $common, [
                        '{{cancel_url}}'     => esc_url( $cancel_url ),
                        '{{reschedule_url}}' => esc_url( $reschedule_url ),
                    ] )
                ),
            ],
            'admin_notification' => [
                'subject' => sprintf( __( 'New Booking – %s', 'bookease' ), $booking->booking_ref ),
                'body'    => $this->interpolate( $this->template_admin(), $common ),
            ],
            'approval' => [
                'subject' => sprintf( __( 'Your Booking is Approved – %s', 'bookease' ), $booking->booking_ref ),
                'body'    => $this->interpolate( $this->template_approval(), $common ),
            ],
            'cancellation' => [
                'subject' => sprintf( __( 'Booking Cancelled – %s', 'bookease' ), $booking->booking_ref ),
                'body'    => $this->interpolate( $this->template_cancellation(), $common ),
            ],
            'reschedule' => [
                'subject' => sprintf( __( 'Booking Rescheduled – %s', 'bookease' ), $booking->booking_ref ),
                'body'    => $this->interpolate( $this->template_reschedule(), $common ),
            ],
            default => [ 'subject' => '', 'body' => '' ],
        };
    }

    // ── Template definitions (overridable via filters) ─────────────────────────

    private function template_confirmation(): string {
        return '<h2>' . esc_html__( 'Booking Received', 'bookease' ) . '</h2>
<p>' . esc_html__( 'Hi {{name}}, your booking has been received and is pending confirmation.', 'bookease' ) . '</p>
{{booking_table}}
<p><a href="{{cancel_url}}">' . esc_html__( 'Cancel this booking', 'bookease' ) . '</a> &nbsp;|&nbsp;
<a href="{{reschedule_url}}">' . esc_html__( 'Reschedule', 'bookease' ) . '</a></p>';
    }

    private function template_admin(): string {
        return '<h2>' . esc_html__( 'New Booking Received', 'bookease' ) . '</h2>
<p>' . esc_html__( 'A new booking has been submitted.', 'bookease' ) . '</p>
{{booking_table}}
<p><a href="{{admin_url}}">' . esc_html__( 'Manage in Dashboard', 'bookease' ) . '</a></p>';
    }

    private function template_approval(): string {
        return '<h2>' . esc_html__( 'Your Booking is Confirmed!', 'bookease' ) . '</h2>
<p>' . esc_html__( 'Hi {{name}}, great news — your booking has been approved.', 'bookease' ) . '</p>
{{booking_table}}';
    }

    private function template_cancellation(): string {
        return '<h2>' . esc_html__( 'Booking Cancelled', 'bookease' ) . '</h2>
<p>' . esc_html__( 'Hi {{name}}, your booking {{ref}} has been cancelled.', 'bookease' ) . '</p>';
    }

    private function template_reschedule(): string {
        return '<h2>' . esc_html__( 'Booking Rescheduled', 'bookease' ) . '</h2>
<p>' . esc_html__( 'Hi {{name}}, your booking has been rescheduled successfully.', 'bookease' ) . '</p>
{{booking_table}}';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function common_vars( Booking $b ): array {
        $date_fmt = get_option( 'date_format', 'F j, Y' );
        return [
            '{{name}}'       => esc_html( $b->customer_name ),
            '{{email}}'      => esc_html( $b->customer_email ),
            '{{ref}}'        => esc_html( $b->booking_ref ),
            '{{date}}'       => esc_html( wp_date( $date_fmt, strtotime( $b->service_date ) ) ),
            '{{start_time}}' => esc_html( substr( $b->start_time, 0, 5 ) ),
            '{{end_time}}'   => esc_html( substr( $b->end_time,   0, 5 ) ),
            '{{status}}'     => esc_html( ucfirst( $b->status ) ),
            '{{notes}}'      => nl2br( esc_html( $b->notes ) ),
            '{{booking_table}}' => $this->booking_table_html( $b ),
            '{{admin_url}}'  => esc_url( admin_url( 'admin.php?page=bookease&booking_id=' . $b->id ) ),
        ];
    }

    private function booking_table_html( Booking $b ): string {
        $rows = [
            __( 'Booking Ref', 'bookease' )  => esc_html( $b->booking_ref ),
            __( 'Date', 'bookease' )          => esc_html( $b->service_date ),
            __( 'Time', 'bookease' )          => esc_html( substr( $b->start_time, 0, 5 ) . ' – ' . substr( $b->end_time, 0, 5 ) ),
            __( 'Name', 'bookease' )          => esc_html( $b->customer_name ),
            __( 'Email', 'bookease' )         => esc_html( $b->customer_email ),
            __( 'Phone', 'bookease' )         => esc_html( $b->customer_phone ),
            __( 'Status', 'bookease' )        => esc_html( ucfirst( $b->status ) ),
        ];

        $html = '<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">';
        foreach ( $rows as $label => $value ) {
            $html .= "<tr><th align=\"left\" style=\"background:#f4f4f4;border:1px solid #ddd;\">{$label}</th>
                          <td style=\"border:1px solid #ddd;\">{$value}</td></tr>";
        }
        return $html . '</table>';
    }

    private function interpolate( string $template, array $vars ): string {
        return str_replace( array_keys( $vars ), array_values( $vars ), $template );
    }

    private function wrap_html( string $body, string $title ): string {
        $site = esc_html( get_bloginfo( 'name' ) );
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>{$title}</title></head>
<body style='font-family:sans-serif;color:#333;max-width:600px;margin:auto;padding:20px;'>
<div style='background:#0073aa;padding:16px;border-radius:6px 6px 0 0;'>
  <h1 style='color:#fff;margin:0;font-size:1.2rem;'>{$site}</h1>
</div>
<div style='padding:24px;border:1px solid #ddd;border-top:none;border-radius:0 0 6px 6px;'>
{$body}
</div>
<p style='font-size:0.8rem;color:#999;text-align:center;margin-top:12px;'>
  " . esc_html__( 'This email was sent by', 'bookease' ) . " {$site}
</p>
</body></html>";
    }

    private function build_token_url( string $action, string $token ): string {
        return add_query_arg( [ 'bookease_action' => $action, 'token' => $token ], home_url( '/' ) );
    }

    private function get_setting( string $key, string $default = '' ): string {
        global $wpdb;
        $table = $wpdb->prefix . 'bookease_settings';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $value = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM `{$table}` WHERE setting_key = %s", $key ) );
        return $value ?? $default;
    }
}
