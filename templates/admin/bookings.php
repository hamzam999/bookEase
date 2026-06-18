<?php defined('ABSPATH') || exit; ?>
<div class="wrap bookease-admin">

  <div class="be-header">
    <div class="be-header__left">
      <h1 class="be-header__title">
        <span class="dashicons dashicons-calendar-alt"></span>
        <?php esc_html_e('All Bookings', 'bookease'); ?>
      </h1>
    </div>
    <div class="be-header__actions">
      <button class="button be-btn be-btn--primary" id="be-add-booking">
        + <?php esc_html_e('Add Booking', 'bookease'); ?>
      </button>
      <a href="<?php echo esc_url(admin_url('admin-post.php?action=bookease_export_csv&' . wp_create_nonce('bookease_export') . '=1&_wpnonce=' . wp_create_nonce('bookease_export'))); ?>"
        class="button be-btn be-btn--secondary">
        ↓ <?php esc_html_e('Export CSV', 'bookease'); ?>
      </a>
    </div>
  </div>

  <!-- Filters -->
  <div class="be-filters-card"
    style="background: #fff; padding: 1.25rem; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
    <div
      style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end;">

      <div class="be-filter-group" style="display: flex; flex-direction: column; gap: 0.375rem;">
        <label for="be-search"
          style="font-size: 0.875rem; font-weight: 600; color: #374151;"><?php esc_html_e('Search', 'bookease'); ?></label>
        <input type="text" id="be-search" class="be-input"
          placeholder="<?php esc_attr_e('Name, email, ref…', 'bookease'); ?>">
      </div>

      <div class="be-filter-group" style="display: flex; flex-direction: column; gap: 0.375rem;">
        <label for="be-date-from"
          style="font-size: 0.875rem; font-weight: 600; color: #374151;"><?php esc_html_e('From Date', 'bookease'); ?></label>
        <input type="date" id="be-date-from" class="be-input">
      </div>

      <div class="be-filter-group" style="display: flex; flex-direction: column; gap: 0.375rem;">
        <label for="be-date-to"
          style="font-size: 0.875rem; font-weight: 600; color: #374151;"><?php esc_html_e('To Date', 'bookease'); ?></label>
        <input type="date" id="be-date-to" class="be-input">
      </div>

      <div class="be-filter-group" style="display: flex; flex-direction: column; gap: 0.375rem;">
        <label for="be-status-filter"
          style="font-size: 0.875rem; font-weight: 600; color: #374151;"><?php esc_html_e('Status', 'bookease'); ?></label>
        <select id="be-status-filter" class="be-input">
          <option value=""><?php esc_html_e('All statuses', 'bookease'); ?></option>
          <option value="pending"><?php esc_html_e('Pending', 'bookease'); ?></option>
          <option value="approved"><?php esc_html_e('Approved', 'bookease'); ?></option>
          <option value="rejected"><?php esc_html_e('Rejected', 'bookease'); ?></option>
          <option value="cancelled"><?php esc_html_e('Cancelled', 'bookease'); ?></option>
        </select>
      </div>

      <div class="be-filter-group" style="display: flex; flex-direction: column; gap: 0.375rem;">
        <label for="be-sort-by"
          style="font-size: 0.875rem; font-weight: 600; color: #374151;"><?php esc_html_e('Sort By', 'bookease'); ?></label>
        <select id="be-sort-by" class="be-input">
          <option value="latest_booking" selected><?php esc_html_e('Latest Bookings', 'bookease'); ?></option>
          <option value="date_time_desc"><?php esc_html_e('Date & Time (Newest First)', 'bookease'); ?></option>
          <option value="date_time_asc"><?php esc_html_e('Date & Time (Oldest First)', 'bookease'); ?></option>
        </select>
      </div>

    </div>

    <div
      style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f3f4f6;">

      <div class="be-per-page-wrap" style="display: inline-flex; align-items: center; gap: 0.5rem;">
        <label for="be-per-page"
          style="font-size: 0.875rem; color: #4b5563; font-weight: 500;"><?php esc_html_e('Rows:', 'bookease'); ?></label>
        <select id="be-per-page" class="be-input" style="width: auto; padding-right: 1.5rem;">
          <option value="10" selected>10</option>
          <option value="20">20</option>
          <option value="30">30</option>
          <option value="custom"><?php esc_html_e('Custom…', 'bookease'); ?></option>
        </select>
        <input type="number" id="be-per-page-custom" class="be-input" min="1"
          placeholder="<?php esc_attr_e('Qty', 'bookease'); ?>" style="width: 70px; display: none;">
      </div>
      <div style="display: flex; gap: 0.5rem;">
        <button class="button be-btn" id="be-apply-filters"><?php esc_html_e('Filter', 'bookease'); ?></button>
        <button class="button be-btn" id="be-clear-filters"
          style="background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; box-shadow: none;"><?php esc_html_e('Clear Filters', 'bookease'); ?></button>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="be-table-wrap">
    <table class="be-table widefat" id="be-bookings-table">
      <thead>
        <tr>
          <th><?php esc_html_e('S.No.', 'bookease'); ?></th>
          <th><?php esc_html_e('Reference No.', 'bookease'); ?></th>
          <th><?php esc_html_e('Customer', 'bookease'); ?></th>
          <th><?php esc_html_e('Date', 'bookease'); ?></th>
          <th><?php esc_html_e('Time', 'bookease'); ?></th>
          <th><?php esc_html_e('Status', 'bookease'); ?></th>
          <th><?php esc_html_e('Actions', 'bookease'); ?></th>
        </tr>
      </thead>
      <tbody id="be-bookings-body">
        <tr class="be-loading-row">
          <td colspan="7"><?php esc_html_e('Loading…', 'bookease'); ?></td>
        </tr>
      </tbody>
    </table>
    <div class="be-pagination" id="be-pagination"></div>
  </div>

  <!-- Add / Edit Modal -->
  <div class="be-modal" id="be-booking-modal" hidden>
    <div class="be-modal__backdrop"></div>
    <div class="be-modal__box">
      <button class="be-modal__close" aria-label="Close">✕</button>
      <h2 class="be-modal__title" id="be-modal-title"><?php esc_html_e('Add Booking', 'bookease'); ?></h2>
      <div id="be-modal-msg" class="be-notice" hidden></div>

      <div class="be-form-grid">
        <div class="be-field">
          <label for="be-f-name"><?php esc_html_e('Name', 'bookease'); ?> <span class="be-req">*</span></label>
          <input type="text" id="be-f-name" class="be-input" required>
        </div>
        <div class="be-field">
          <label for="be-f-email"><?php esc_html_e('Email', 'bookease'); ?> <span class="be-req">*</span></label>
          <input type="email" id="be-f-email" class="be-input" required>
        </div>
        <div class="be-field">
          <label for="be-f-phone"><?php esc_html_e('Phone', 'bookease'); ?></label>
          <input type="tel" id="be-f-phone" class="be-input">
        </div>
        <div class="be-field">
          <label for="be-f-date"><?php esc_html_e('Date', 'bookease'); ?> <span class="be-req">*</span></label>
          <input type="date" id="be-f-date" class="be-input" required>
        </div>
        <div class="be-field">
          <label for="be-f-slot"><?php esc_html_e('Time Slot', 'bookease'); ?> <span class="be-req">*</span></label>
          <select id="be-f-slot" class="be-input" required>
            <option value=""><?php esc_html_e('Select date first', 'bookease'); ?></option>
          </select>
        </div>
        <div class="be-field be-field--full">
          <label for="be-f-notes"><?php esc_html_e('Notes', 'bookease'); ?></label>
          <textarea id="be-f-notes" class="be-input" rows="3"></textarea>
        </div>
      </div>

      <div class="be-modal__footer">
        <button class="button be-btn" id="be-modal-cancel"><?php esc_html_e('Cancel', 'bookease'); ?></button>
        <button class="button be-btn be-btn--primary"
          id="be-modal-save"><?php esc_html_e('Save Booking', 'bookease'); ?></button>
      </div>
      <input type="hidden" id="be-editing-id" value="">
    </div>
  </div>

  <!-- View Modal -->
  <div class="be-modal" id="be-view-modal" hidden>
    <div class="be-modal__backdrop"></div>
    <div class="be-modal__box">
      <button class="be-modal__close" aria-label="Close">✕</button>
      <h2 class="be-modal__title"><?php esc_html_e('Booking Details', 'bookease'); ?></h2>
      <div id="be-view-content"></div>
      <div class="be-modal__footer">
        <button class="button be-btn" id="be-view-close"><?php esc_html_e('Close', 'bookease'); ?></button>
        <button class="button be-btn be-btn--success"
          data-action="approved"><?php esc_html_e('Approve', 'bookease'); ?></button>
        <button class="button be-btn be-btn--danger"
          data-action="rejected"><?php esc_html_e('Reject', 'bookease'); ?></button>
      </div>
    </div>
  </div>

</div><!-- .wrap -->