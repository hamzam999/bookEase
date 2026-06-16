<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bookease-admin">

  <div class="be-header">
    <div class="be-header__left">
      <h1 class="be-header__title">
        <span class="dashicons dashicons-calendar-alt"></span>
        <?php esc_html_e( 'All Bookings', 'bookease' ); ?>
      </h1>
    </div>
    <div class="be-header__actions">
      <button class="button be-btn be-btn--primary" id="be-add-booking">
        + <?php esc_html_e( 'Add Booking', 'bookease' ); ?>
      </button>
      <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=bookease_export_csv&' . wp_create_nonce( 'bookease_export' ) . '=1&_wpnonce=' . wp_create_nonce( 'bookease_export' ) ) ); ?>"
         class="button be-btn be-btn--secondary">
        ↓ <?php esc_html_e( 'Export CSV', 'bookease' ); ?>
      </a>
    </div>
  </div>

  <!-- Filters -->
  <div class="be-filters">
    <input type="text"   id="be-search"      class="be-input" placeholder="<?php esc_attr_e( 'Search name, email, ref…', 'bookease' ); ?>">
    <input type="date"   id="be-date-from"   class="be-input" placeholder="<?php esc_attr_e( 'From date', 'bookease' ); ?>">
    <input type="date"   id="be-date-to"     class="be-input" placeholder="<?php esc_attr_e( 'To date', 'bookease' ); ?>">
    <select id="be-status-filter" class="be-input">
      <option value=""><?php esc_html_e( 'All statuses', 'bookease' ); ?></option>
      <option value="pending"><?php esc_html_e( 'Pending', 'bookease' ); ?></option>
      <option value="approved"><?php esc_html_e( 'Approved', 'bookease' ); ?></option>
      <option value="rejected"><?php esc_html_e( 'Rejected', 'bookease' ); ?></option>
      <option value="cancelled"><?php esc_html_e( 'Cancelled', 'bookease' ); ?></option>
    </select>
    <button class="button be-btn" id="be-apply-filters"><?php esc_html_e( 'Filter', 'bookease' ); ?></button>
  </div>

  <!-- Table -->
  <div class="be-table-wrap">
    <table class="be-table widefat" id="be-bookings-table">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Ref', 'bookease' ); ?></th>
          <th><?php esc_html_e( 'Customer', 'bookease' ); ?></th>
          <th><?php esc_html_e( 'Date', 'bookease' ); ?></th>
          <th><?php esc_html_e( 'Time', 'bookease' ); ?></th>
          <th><?php esc_html_e( 'Status', 'bookease' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'bookease' ); ?></th>
        </tr>
      </thead>
      <tbody id="be-bookings-body">
        <tr class="be-loading-row">
          <td colspan="6"><?php esc_html_e( 'Loading…', 'bookease' ); ?></td>
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
      <h2 class="be-modal__title" id="be-modal-title"><?php esc_html_e( 'Add Booking', 'bookease' ); ?></h2>
      <div id="be-modal-msg" class="be-notice" hidden></div>

      <div class="be-form-grid">
        <div class="be-field">
          <label for="be-f-name"><?php esc_html_e( 'Name', 'bookease' ); ?> <span class="be-req">*</span></label>
          <input type="text" id="be-f-name" class="be-input" required>
        </div>
        <div class="be-field">
          <label for="be-f-email"><?php esc_html_e( 'Email', 'bookease' ); ?> <span class="be-req">*</span></label>
          <input type="email" id="be-f-email" class="be-input" required>
        </div>
        <div class="be-field">
          <label for="be-f-phone"><?php esc_html_e( 'Phone', 'bookease' ); ?></label>
          <input type="tel" id="be-f-phone" class="be-input">
        </div>
        <div class="be-field">
          <label for="be-f-date"><?php esc_html_e( 'Date', 'bookease' ); ?> <span class="be-req">*</span></label>
          <input type="date" id="be-f-date" class="be-input" required>
        </div>
        <div class="be-field">
          <label for="be-f-slot"><?php esc_html_e( 'Time Slot', 'bookease' ); ?> <span class="be-req">*</span></label>
          <select id="be-f-slot" class="be-input" required>
            <option value=""><?php esc_html_e( 'Select date first', 'bookease' ); ?></option>
          </select>
        </div>
        <div class="be-field be-field--full">
          <label for="be-f-notes"><?php esc_html_e( 'Notes', 'bookease' ); ?></label>
          <textarea id="be-f-notes" class="be-input" rows="3"></textarea>
        </div>
      </div>

      <div class="be-modal__footer">
        <button class="button be-btn" id="be-modal-cancel"><?php esc_html_e( 'Cancel', 'bookease' ); ?></button>
        <button class="button be-btn be-btn--primary" id="be-modal-save"><?php esc_html_e( 'Save Booking', 'bookease' ); ?></button>
      </div>
      <input type="hidden" id="be-editing-id" value="">
    </div>
  </div>

  <!-- View Modal -->
  <div class="be-modal" id="be-view-modal" hidden>
    <div class="be-modal__backdrop"></div>
    <div class="be-modal__box">
      <button class="be-modal__close" aria-label="Close">✕</button>
      <h2 class="be-modal__title"><?php esc_html_e( 'Booking Details', 'bookease' ); ?></h2>
      <div id="be-view-content"></div>
      <div class="be-modal__footer">
        <button class="button be-btn" id="be-view-close"><?php esc_html_e( 'Close', 'bookease' ); ?></button>
        <button class="button be-btn be-btn--success" data-action="approved"><?php esc_html_e( 'Approve', 'bookease' ); ?></button>
        <button class="button be-btn be-btn--danger"  data-action="rejected"><?php esc_html_e( 'Reject', 'bookease' ); ?></button>
      </div>
    </div>
  </div>

</div><!-- .wrap -->
