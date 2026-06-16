<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$settings_table = $wpdb->prefix . 'bookease_settings';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$rows     = $wpdb->get_results( "SELECT setting_key, setting_value FROM `{$settings_table}`", ARRAY_A );
$settings = array_column( $rows ?: [], 'setting_value', 'setting_key' );

$biz_hours = json_decode( $settings['business_hours'] ?? '{}', true ) ?: [];
$days      = [ 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' ];
?>
<div class="wrap bookease-admin">

  <div class="be-header">
    <h1 class="be-header__title">
      <span class="dashicons dashicons-admin-settings"></span>
      <?php esc_html_e( 'Settings', 'bookease' ); ?>
    </h1>
  </div>

  <div id="be-settings-msg" class="be-notice" hidden></div>

  <div class="be-settings-grid">

    <!-- General -->
    <div class="be-card">
      <h2 class="be-card__title"><?php esc_html_e( 'General', 'bookease' ); ?></h2>
      <div class="be-field">
        <label><?php esc_html_e( 'Timezone', 'bookease' ); ?></label>
        <select name="timezone" class="be-input be-setting" data-key="timezone">
          <?php echo wp_timezone_choice( $settings['timezone'] ?? wp_timezone_string() ); ?>
        </select>
      </div>
      <div class="be-field">
        <label><?php esc_html_e( 'Slot duration (minutes)', 'bookease' ); ?></label>
        <input type="number" class="be-input be-setting" data-key="slot_duration" min="15" step="15"
               value="<?php echo esc_attr( $settings['slot_duration'] ?? 60 ); ?>">
      </div>
      <div class="be-field">
        <label><?php esc_html_e( 'Max bookings per slot', 'bookease' ); ?></label>
        <input type="number" class="be-input be-setting" data-key="max_bookings_per_slot" min="1"
               value="<?php echo esc_attr( $settings['max_bookings_per_slot'] ?? 1 ); ?>">
      </div>
      <div class="be-field">
        <label><?php esc_html_e( 'Booking lead time (hours)', 'bookease' ); ?></label>
        <input type="number" class="be-input be-setting" data-key="lead_time_hours" min="0"
               value="<?php echo esc_attr( $settings['lead_time_hours'] ?? 2 ); ?>">
      </div>
      <div class="be-field">
        <label><?php esc_html_e( 'Max advance booking (days)', 'bookease' ); ?></label>
        <input type="number" class="be-input be-setting" data-key="max_advance_days" min="1"
               value="<?php echo esc_attr( $settings['max_advance_days'] ?? 60 ); ?>">
      </div>
    </div>

    <!-- Business Hours -->
    <div class="be-card">
      <h2 class="be-card__title"><?php esc_html_e( 'Business Hours', 'bookease' ); ?></h2>
      <div class="be-hours-grid">
        <?php foreach ( $days as $day ) :
            $cfg     = $biz_hours[ $day ] ?? [];
            $enabled = ! empty( $cfg['enabled'] );
            $open    = $cfg['open']  ?? '09:00';
            $close   = $cfg['close'] ?? '17:00';
        ?>
        <div class="be-hours-row" data-day="<?php echo esc_attr( $day ); ?>">
          <label class="be-toggle">
            <input type="checkbox" class="be-hours-enabled" <?php checked( $enabled ); ?>>
            <span class="be-toggle__slider"></span>
          </label>
          <span class="be-hours-day"><?php echo esc_html( ucfirst( $day ) ); ?></span>
          <input type="time" class="be-input be-hours-open"  value="<?php echo esc_attr( $open  ); ?>" <?php disabled( ! $enabled ); ?>>
          <span>–</span>
          <input type="time" class="be-input be-hours-close" value="<?php echo esc_attr( $close ); ?>" <?php disabled( ! $enabled ); ?>>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Email -->
    <div class="be-card">
      <h2 class="be-card__title"><?php esc_html_e( 'Email', 'bookease' ); ?></h2>
      <div class="be-field">
        <label><?php esc_html_e( 'Admin notification email', 'bookease' ); ?></label>
        <input type="email" class="be-input be-setting" data-key="admin_email"
               value="<?php echo esc_attr( $settings['admin_email'] ?? get_option( 'admin_email' ) ); ?>">
      </div>
      <div class="be-field">
        <label><?php esc_html_e( 'From name', 'bookease' ); ?></label>
        <input type="text" class="be-input be-setting" data-key="email_from"
               value="<?php echo esc_attr( $settings['email_from'] ?? get_bloginfo( 'name' ) ); ?>">
      </div>
    </div>

  </div><!-- .be-settings-grid -->

  <div class="be-settings-footer">
    <button class="button be-btn be-btn--primary be-btn--lg" id="be-save-settings">
      <?php esc_html_e( 'Save Settings', 'bookease' ); ?>
    </button>
  </div>

</div>
