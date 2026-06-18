<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap bookease-admin">

  <div class="be-header">
    <h1 class="be-header__title">
      <span class="dashicons dashicons-welcome-learn-more"></span>
      <?php esc_html_e( 'How to Use / Setup Guide', 'bookease' ); ?>
    </h1>
  </div>

  <div class="be-card" style="border-left: 4px solid var(--be-primary); margin-top: 1rem;">
    <h2 class="be-card__title" style="margin-bottom:0.75rem; font-size:1.15rem;">
      <?php esc_html_e( 'Getting Started & How to Use', 'bookease' ); ?>
    </h2>
    <p class="be-text-muted" style="margin-bottom: 1.5rem; line-height: 1.5;">
      <?php esc_html_e( 'Welcome to BookEase! Follow these simple steps to start receiving bookings on your WordPress site.', 'bookease' ); ?>
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">

      <div>
        <h4 style="margin: 0 0 0.5rem; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; color: var(--be-text);">
          <span style="background: var(--be-primary); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; flex-shrink: 0;">1</span>
          <?php esc_html_e( 'Display the Booking Form', 'bookease' ); ?>
        </h4>
        <p style="margin: 0; font-size: 0.85rem; color: var(--be-muted); line-height: 1.45;">
          <?php esc_html_e( 'Place the shortcode below on any page or post to display the booking interface to your visitors:', 'bookease' ); ?>
          <code style="display: block; margin-top: 0.5rem; padding: 0.4rem 0.6rem; background: #f3f4f6; border-radius: 4px; font-family: monospace; font-size: 0.85rem; color: #1f2937; border: 1px solid #e5e7eb;">[bookease_form]</code>
        </p>
      </div>

      <div>
        <h4 style="margin: 0 0 0.5rem; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; color: var(--be-text);">
          <span style="background: var(--be-primary); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; flex-shrink: 0;">2</span>
          <?php esc_html_e( 'Configure Times & Slots', 'bookease' ); ?>
        </h4>
        <p style="margin: 0; font-size: 0.85rem; color: var(--be-muted); line-height: 1.45;">
          <?php esc_html_e( 'Select timezone and set slot durations in Settings. You can toggle "Business Hours" for standard intervals, or enable "Custom Time Slots" to offer specific fixed times (e.g. 09:00, 13:30, 16:00) during the day.', 'bookease' ); ?>
        </p>
      </div>

      <div>
        <h4 style="margin: 0 0 0.5rem; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; color: var(--be-text);">
          <span style="background: var(--be-primary); color: #fff; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; flex-shrink: 0;">3</span>
          <?php esc_html_e( 'Block Dates & Holidays', 'bookease' ); ?>
        </h4>
        <p style="margin: 0; font-size: 0.85rem; color: var(--be-muted); line-height: 1.45;">
          <?php esc_html_e( 'Need to disable bookings on a public holiday or day off? Head to the ', 'bookease' ); ?>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=bookease-blocked-dates' ) ); ?>" style="color: var(--be-primary); text-decoration: underline; font-weight: 600;"><?php esc_html_e( 'Blocked Dates', 'bookease' ); ?></a>
          <?php esc_html_e( ' tab to add custom blocked date ranges and disable front-end selections.', 'bookease' ); ?>
        </p>
      </div>

    </div>
  </div>

</div>
