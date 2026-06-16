<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$blocked_table = $wpdb->prefix . 'bookease_blocked_dates';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$blocked_dates = $wpdb->get_results( "SELECT * FROM `{$blocked_table}` ORDER BY blocked_date ASC", ARRAY_A ) ?: [];
?>
<div class="wrap bookease-admin">

  <div class="be-header">
    <h1 class="be-header__title">
      <span class="dashicons dashicons-dismiss"></span>
      <?php esc_html_e( 'Blocked Dates', 'bookease' ); ?>
    </h1>
  </div>

  <div class="be-settings-grid">
    <div class="be-card">
      <h2 class="be-card__title"><?php esc_html_e( 'Block a Date', 'bookease' ); ?></h2>
      <p class="be-text-muted"><?php esc_html_e( 'Blocked dates will be unavailable for new bookings.', 'bookease' ); ?></p>
      <div id="be-blocked-msg" class="be-notice" hidden></div>
      <div class="be-field">
        <label><?php esc_html_e( 'Date', 'bookease' ); ?></label>
        <input type="date" id="be-block-date" class="be-input">
      </div>
      <div class="be-field">
        <label><?php esc_html_e( 'Reason (optional)', 'bookease' ); ?></label>
        <input type="text" id="be-block-reason" class="be-input" placeholder="<?php esc_attr_e( 'e.g. Public holiday', 'bookease' ); ?>">
      </div>
      <button class="button be-btn be-btn--primary" id="be-add-blocked"><?php esc_html_e( 'Block Date', 'bookease' ); ?></button>
    </div>

    <div class="be-card">
      <h2 class="be-card__title"><?php esc_html_e( 'Blocked Dates', 'bookease' ); ?></h2>
      <div id="be-blocked-list">
        <?php if ( empty( $blocked_dates ) ) : ?>
          <p class="be-text-muted"><?php esc_html_e( 'No dates are currently blocked.', 'bookease' ); ?></p>
        <?php else : ?>
          <ul class="be-blocked-dates-ul">
            <?php foreach ( $blocked_dates as $row ) : ?>
              <li class="be-blocked-item" data-date="<?php echo esc_attr( $row['blocked_date'] ); ?>">
                <span class="be-blocked-date"><?php echo esc_html( $row['blocked_date'] ); ?></span>
                <?php if ( $row['reason'] ) : ?>
                  <span class="be-blocked-reason"><?php echo esc_html( $row['reason'] ); ?></span>
                <?php endif; ?>
                <button class="be-unblock button-link-delete" data-date="<?php echo esc_attr( $row['blocked_date'] ); ?>">
                  <?php esc_html_e( 'Remove', 'bookease' ); ?>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
