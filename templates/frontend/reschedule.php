<?php
/**
 * Reschedule form shown when customer visits the reschedule link.
 */
defined( 'ABSPATH' ) || exit;

$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php esc_html_e( 'Reschedule Booking', 'bookease' ); ?> | <?php bloginfo( 'name' ); ?></title>
  <?php wp_head(); ?>
</head>
<body class="bookease-standalone">
<div class="bookease-wrap" style="max-width:520px;margin:4rem auto;padding:2rem;">
  <h1><?php esc_html_e( 'Reschedule Your Booking', 'bookease' ); ?></h1>
  <p><?php esc_html_e( 'Select a new date and time slot below.', 'bookease' ); ?></p>

  <div class="be-field">
    <label for="be-rs-date" class="be-label"><?php esc_html_e( 'New Date', 'bookease' ); ?> <span class="be-req">*</span></label>
    <input type="date" id="be-rs-date" class="be-input" min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
  </div>


  <div class="be-field" id="be-rs-slot-wrap" hidden>
    <label class="be-label"><?php esc_html_e( 'Available Times', 'bookease' ); ?></label>
    <div class="be-slots" id="be-rs-slots"></div>
    <input type="hidden" id="be-rs-start">
    <input type="hidden" id="be-rs-end">
  </div>

  <div class="be-notice be-notice--error" id="be-rs-error" hidden></div>
  <div class="be-notice be-notice--success" id="be-rs-success" hidden></div>

  <button class="be-btn be-btn--primary" id="be-rs-submit" style="margin-top:1rem;" disabled>
    <?php esc_html_e( 'Confirm Reschedule', 'bookease' ); ?>
  </button>

  <input type="hidden" id="be-rs-token" value="<?php echo esc_attr( $token ); ?>">
</div>

<?php
wp_enqueue_style(  'bookease-frontend', BOOKEASE_ASSETS_URL . 'css/frontend.css', [], BOOKEASE_VERSION );
wp_enqueue_script( 'bookease-frontend', BOOKEASE_ASSETS_URL . 'js/frontend.js', ['jquery'], BOOKEASE_VERSION, true );
wp_localize_script( 'bookease-frontend', 'bookease', [
    'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
    'nonce'        => wp_create_nonce( 'bookease_frontend' ),
    'slotDuration' => isset( $this->repo ) ? (int) $this->repo->get_setting( 'slot_duration', 60 ) : 60,
    'mode'         => 'reschedule',
] );
wp_footer();
?>
</body>
</html>
