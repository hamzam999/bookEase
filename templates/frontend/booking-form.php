<?php
/**
 * Frontend booking form template.
 * Rendered via the [bookease_form] shortcode.
 */
defined('ABSPATH') || exit;
?>
<div class="bookease-wrap" id="bookease-form-wrap">
  <noscript>
    <div class="be-notice be-notice--error"
      style="display: block; margin-bottom: 1.5rem; padding: 1rem; background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 0.375rem; font-size: 0.875rem;">
      <?php esc_html_e('JavaScript is required to book appointments. Please enable JavaScript in your browser settings to continue.', 'bookease'); ?>
    </div>
  </noscript>

  <!-- Step indicators -->
  <div class="be-steps" aria-label="<?php esc_attr_e('Booking steps', 'bookease'); ?>">
    <div class="be-step be-step--active" data-step="1">
      <div class="be-step__num">1</div>
      <div class="be-step__label"><?php esc_html_e('Date & Time', 'bookease'); ?></div>
    </div>
    <div class="be-step__line"></div>
    <div class="be-step" data-step="2">
      <div class="be-step__num">2</div>
      <div class="be-step__label"><?php esc_html_e('Your Details', 'bookease'); ?></div>
    </div>
    <div class="be-step__line"></div>
    <div class="be-step" data-step="3">
      <div class="be-step__num">3</div>
      <div class="be-step__label"><?php esc_html_e('Confirm', 'bookease'); ?></div>
    </div>
  </div>

  <div class="be-form-container">

    <!-- Step 1: Date & Slot -->
    <div class="be-pane" id="be-step-1">
      <h2 class="be-pane__title"><?php esc_html_e('Choose a Date & Time', 'bookease'); ?></h2>

      <div class="be-field">
        <label for="be-date" class="be-label">
          <?php esc_html_e('Date', 'bookease'); ?> <span class="be-req">*</span>
        </label>
        <input type="date" id="be-date" class="be-input"
          min="<?php echo esc_attr(gmdate('Y-m-d', strtotime('+1 day'))); ?>" required aria-required="true">
      </div>


      <div class="be-field" id="be-slot-field" hidden>
        <label class="be-label"><?php esc_html_e('Available Times', 'bookease'); ?> <span
            class="be-req">*</span></label>
        <div class="be-slots" id="be-slots" role="group"
          aria-label="<?php esc_attr_e('Available time slots', 'bookease'); ?>">
          <p class="be-slots__loading"><?php esc_html_e('Loading slots…', 'bookease'); ?></p>
        </div>
        <input type="hidden" id="be-start-time" name="start_time">
        <input type="hidden" id="be-end-time" name="end_time">
      </div>

      <div class="be-pane__nav">
        <button type="button" class="be-btn be-btn--primary" id="be-next-1" disabled>
          <?php esc_html_e('Continue', 'bookease'); ?> →
        </button>
      </div>
    </div>

    <!-- Step 2: Customer details -->
    <div class="be-pane" id="be-step-2" hidden>
      <h2 class="be-pane__title"><?php esc_html_e('Your Details', 'bookease'); ?></h2>

      <div class="be-field">
        <label for="be-name" class="be-label"><?php esc_html_e('Full Name', 'bookease'); ?> <span
            class="be-req">*</span></label>
        <input type="text" id="be-name" class="be-input" autocomplete="name" required aria-required="true">
      </div>

      <div class="be-field">
        <label for="be-email" class="be-label"><?php esc_html_e('Email Address', 'bookease'); ?> <span
            class="be-req">*</span></label>
        <input type="email" id="be-email" class="be-input" autocomplete="email" required aria-required="true">
      </div>

      <div class="be-field">
        <label for="be-phone" class="be-label"><?php esc_html_e('Phone Number', 'bookease'); ?></label>
        <input type="tel" id="be-phone" class="be-input" autocomplete="tel">
      </div>

      <div class="be-field">
        <label for="be-notes" class="be-label"><?php esc_html_e('Notes (optional)', 'bookease'); ?></label>
        <textarea id="be-notes" class="be-input" rows="3"
          placeholder="<?php esc_attr_e('Any special requests or information…', 'bookease'); ?>"></textarea>
      </div>

      <div class="be-pane__nav">
        <button type="button" class="be-btn be-btn--ghost" id="be-back-1">←
          <?php esc_html_e('Back', 'bookease'); ?></button>
        <button type="button" class="be-btn be-btn--primary"
          id="be-next-2"><?php esc_html_e('Review Booking', 'bookease'); ?> →</button>
      </div>
    </div>

    <!-- Step 3: Confirm -->
    <div class="be-pane" id="be-step-3" hidden>
      <h2 class="be-pane__title"><?php esc_html_e('Confirm Your Booking', 'bookease'); ?></h2>

      <div class="be-summary" id="be-summary">
        <div class="be-summary__row"><span><?php esc_html_e('Date', 'bookease'); ?></span><strong
            id="be-sum-date"></strong></div>
        <div class="be-summary__row"><span><?php esc_html_e('Time', 'bookease'); ?></span><strong
            id="be-sum-time"></strong></div>
        <div class="be-summary__row"><span><?php esc_html_e('Name', 'bookease'); ?></span><strong
            id="be-sum-name"></strong></div>
        <div class="be-summary__row"><span><?php esc_html_e('Email', 'bookease'); ?></span><strong
            id="be-sum-email"></strong></div>
        <div class="be-summary__row" id="be-sum-phone-row">
          <span><?php esc_html_e('Phone', 'bookease'); ?></span><strong id="be-sum-phone"></strong>
        </div>
        <div class="be-summary__row" id="be-sum-notes-row">
          <span><?php esc_html_e('Notes', 'bookease'); ?></span><strong id="be-sum-notes"></strong>
        </div>
      </div>

      <div class="be-notice be-notice--error" id="be-submit-error" hidden></div>

      <div class="be-pane__nav">
        <button type="button" class="be-btn be-btn--ghost" id="be-back-2">←
          <?php esc_html_e('Back', 'bookease'); ?></button>
        <button type="button" class="be-btn be-btn--primary" id="be-submit">
          <?php esc_html_e('Confirm Booking', 'bookease'); ?>
        </button>
      </div>
    </div>

    <!-- Step 4: Success -->
    <div class="be-pane be-pane--success" id="be-step-success" hidden>
      <div class="be-success-icon">✓</div>
      <h2 class="be-pane__title"><?php esc_html_e('Booking Submitted!', 'bookease'); ?></h2>
      <p id="be-success-message">
        <?php esc_html_e('Thank you! Check your email for confirmation details.', 'bookease'); ?>
      </p>
      <p class="be-booking-ref" style="margin-bottom: 2rem;">
        <?php esc_html_e('Booking Reference:', 'bookease'); ?>
        <strong id="be-success-ref"></strong>
      </p>
      <div style="display: flex; justify-content: center;">
        <button type="button" class="be-btn be-btn--primary" id="be-add-another-booking">
          <?php esc_html_e('Add New Booking', 'bookease'); ?>
        </button>
      </div>
    </div>

  </div><!-- .be-form-container -->
</div><!-- .bookease-wrap -->