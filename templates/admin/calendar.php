<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bookease-admin">
  <div class="be-header">
    <div class="be-header__left">
      <h1 class="be-header__title">
        <span class="dashicons dashicons-calendar"></span>
        <?php esc_html_e( 'Booking Calendar', 'bookease' ); ?>
      </h1>
    </div>
    <div class="be-header__actions">
      <div class="be-calendar-legend">
        <span class="be-legend-dot" style="background:#f59e0b;"></span> <?php esc_html_e( 'Pending', 'bookease' ); ?>
        <span class="be-legend-dot" style="background:#00a651;"></span> <?php esc_html_e( 'Approved', 'bookease' ); ?>
        <span class="be-legend-dot" style="background:#ef4444;"></span> <?php esc_html_e( 'Rejected', 'bookease' ); ?>
        <span class="be-legend-dot" style="background:#6b7280;"></span> <?php esc_html_e( 'Cancelled', 'bookease' ); ?>
      </div>
    </div>
  </div>

  <!-- Calendar controls -->
  <div class="be-cal-controls">
    <button class="button be-btn" id="be-cal-prev">&#8249;</button>
    <h2 class="be-cal-month-label" id="be-cal-label"></h2>
    <button class="button be-btn" id="be-cal-next">&#8250;</button>
    <div class="be-cal-view-switcher">
      <button class="button be-btn be-btn--active" data-view="month"><?php esc_html_e( 'Month', 'bookease' ); ?></button>
      <button class="button be-btn" data-view="week"><?php esc_html_e( 'Week', 'bookease' ); ?></button>
      <button class="button be-btn" data-view="day"><?php esc_html_e( 'Day', 'bookease' ); ?></button>
    </div>
  </div>

  <div id="be-calendar-container">
    <div id="be-calendar-loading" class="be-loading"><?php esc_html_e( 'Loading calendar…', 'bookease' ); ?></div>
    <div id="be-calendar"></div>
  </div>

  <!-- Detail popover -->
  <div class="be-popover" id="be-event-popover" hidden>
    <div class="be-popover__header">
      <strong id="be-popover-ref"></strong>
      <button class="be-popover__close">✕</button>
    </div>
    <div class="be-popover__body" id="be-popover-body"></div>
    <div class="be-popover__footer">
      <button class="button be-btn be-btn--sm be-btn--success" id="be-popover-approve"><?php esc_html_e( 'Approve', 'bookease' ); ?></button>
      <button class="button be-btn be-btn--sm be-btn--danger"  id="be-popover-reject"><?php esc_html_e( 'Reject', 'bookease' ); ?></button>
    </div>
  </div>
</div>
