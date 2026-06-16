/* global bookease_admin, jQuery */
( function ( $ ) {
  'use strict';

  const API = {
    post( action, data, done, fail ) {
      $.post( bookease_admin.ajaxUrl, Object.assign( { action, nonce: bookease_admin.nonce }, data ) )
        .done( res => {
          if ( res.success ) done( res.data );
          else ( fail || defaultFail )( res.data?.message );
        } )
        .fail( () => ( fail || defaultFail )( bookease_admin.i18n.error ) );
    },
  };

  function defaultFail( msg ) {
    alert( msg || bookease_admin.i18n.error );
  }

  // ── Bookings page ───────────────────────────────────────────────────────────
  if ( $( '#be-bookings-table' ).length ) {
    initBookingsPage();
  }

  function initBookingsPage() {
    let page      = 1;
    let totalPages = 1;

    function load() {
      const search   = $( '#be-search' ).val();
      const dateFrom = $( '#be-date-from' ).val();
      const dateTo   = $( '#be-date-to' ).val();
      const status   = $( '#be-status-filter' ).val();

      $( '#be-bookings-body' ).html( '<tr class="be-loading-row"><td colspan="6">' + bookease_admin.i18n.loading + '</td></tr>' );

      API.post( 'bookease_list_bookings', { search, date_from: dateFrom, date_to: dateTo, status, page }, data => {
        renderTable( data.bookings );
        totalPages = data.total_pages;
        renderPagination( page, totalPages );
      } );
    }

    function renderTable( bookings ) {
      if ( ! bookings.length ) {
        $( '#be-bookings-body' ).html( '<tr><td colspan="6" style="text-align:center;color:#6b7280;padding:2rem;">No bookings found.</td></tr>' );
        return;
      }

      const rows = bookings.map( b => `
        <tr data-id="${b.id}">
          <td><code>${esc(b.booking_ref)}</code></td>
          <td>
            <div style="font-weight:600;">${esc(b.customer_name)}</div>
            <div style="font-size:.8rem;color:#6b7280;">${esc(b.customer_email)}</div>
          </td>
          <td>${esc(b.service_date)}</td>
          <td>${esc(b.start_time.substr(0,5))} – ${esc(b.end_time.substr(0,5))}</td>
          <td><span class="be-badge be-badge--${esc(b.status)}">${esc(b.status)}</span></td>
          <td>
            <button class="be-btn be-btn--sm" data-action="view"   data-id="${b.id}">View</button>
            <button class="be-btn be-btn--sm" data-action="edit"   data-id="${b.id}" data-booking='${JSON.stringify(b)}'>Edit</button>
            <button class="be-btn be-btn--sm be-btn--danger" data-action="delete" data-id="${b.id}">Delete</button>
          </td>
        </tr>` ).join('');

      $( '#be-bookings-body' ).html( rows );
    }

    function renderPagination( cur, total ) {
      const $p = $( '#be-pagination' );
      if ( total <= 1 ) { $p.html(''); return; }
      let html = `<button class="be-page-btn" data-page="${cur-1}" ${cur===1?'disabled':''}>‹</button>`;
      for ( let i = 1; i <= total; i++ ) {
        html += `<button class="be-page-btn ${i===cur?'be-page-btn--active':''}" data-page="${i}">${i}</button>`;
      }
      html += `<button class="be-page-btn" data-page="${cur+1}" ${cur===total?'disabled':''}>›</button>`;
      $p.html(html);
    }

    // Pagination clicks.
    $( '#be-pagination' ).on( 'click', '.be-page-btn:not([disabled])', function() {
      page = +$( this ).data('page');
      load();
    } );

    // Filter.
    $( '#be-apply-filters' ).on( 'click', () => { page = 1; load(); } );
    $( '#be-search' ).on( 'keypress', e => { if ( e.which===13 ) { page=1; load(); } } );

    // Table actions.
    $( '#be-bookings-table' ).on( 'click', '[data-action]', function() {
      const action = $( this ).data('action');
      const id     = +$( this ).data('id');

      if ( action === 'delete' ) {
        if ( ! confirm( bookease_admin.i18n.confirmDelete ) ) return;
        API.post( 'bookease_delete_booking', { id }, () => load() );
      }

      if ( action === 'view' ) openViewModal( id );
      if ( action === 'edit' ) openEditModal( id, JSON.parse( $( this ).attr('data-booking') ) );
    } );

    // Add booking button.
    $( '#be-add-booking' ).on( 'click', () => openEditModal( 0, null ) );

    // Load on init.
    load();

    // Expose for AJAX fallback (server returns full data).
    window.beLoadBookings = load;

    // Date change in modal → load slots.
    $( '#be-f-date' ).on( 'change', function () {
      const date = $( this ).val();
      $( '#be-f-slot' ).html( '<option>' + bookease_admin.i18n.loading + '</option>' );
      if ( ! date ) return;
      API.post( 'bookease_admin_get_slots', { date }, data => {
        const opts = data.slots.length
          ? data.slots.map( s => `<option value="${s.start}|${s.end}" ${!s.available?'disabled':''}>${s.start.substr(0,5)} – ${s.end.substr(0,5)}</option>` ).join('')
          : '<option disabled>No slots available</option>';
        $( '#be-f-slot' ).html( opts );
      } );
    } );

    // Save booking (add/edit).
    $( '#be-modal-save' ).on( 'click', function () {
      const editingId = +$( '#be-editing-id' ).val();
      const slotVal   = $( '#be-f-slot' ).val() || '';
      const [ start, end ] = slotVal.split('|');

      const payload = {
        customer_name:  $( '#be-f-name' ).val(),
        customer_email: $( '#be-f-email' ).val(),
        customer_phone: $( '#be-f-phone' ).val(),
        service_date:   $( '#be-f-date' ).val(),
        start_time:     start,
        end_time:       end,
        notes:          $( '#be-f-notes' ).val(),
      };

      const action = editingId ? 'bookease_update_booking' : 'bookease_create_booking';
      if ( editingId ) payload.id = editingId;

      $( '#be-modal-msg' ).hide();
      API.post( action, payload, () => {
        closeModal( 'be-booking-modal' );
        load();
      }, msg => {
        $( '#be-modal-msg' ).text(msg).addClass('be-notice--error').show();
      } );
    } );

    // Modal close.
    $( '#be-modal-cancel, #be-booking-modal .be-modal__backdrop, #be-booking-modal .be-modal__close' ).on( 'click', () => closeModal('be-booking-modal') );
    $( '#be-view-close,  #be-view-modal .be-modal__backdrop,   #be-view-modal .be-modal__close'   ).on( 'click', () => closeModal('be-view-modal') );

    // Status actions in view modal.
    $( '#be-view-modal [data-action]' ).on( 'click', function() {
      const status = $( this ).data('action');
      const id     = +$( '#be-view-modal' ).data('booking-id');
      API.post( 'bookease_update_status', { id, status }, () => {
        closeModal('be-view-modal');
        load();
      } );
    } );
  }

  // ── Settings page ───────────────────────────────────────────────────────────
  if ( $( '#be-save-settings' ).length ) {
    initSettingsPage();
  }

  function initSettingsPage() {
    // Toggle business-hours rows.
    $( '.be-hours-enabled' ).on( 'change', function () {
      const $row = $( this ).closest('.be-hours-row');
      const on   = $( this ).is(':checked');
      $row.find('.be-hours-open, .be-hours-close').prop('disabled', !on);
    } );

    $( '#be-save-settings' ).on( 'click', function () {
      const $btn = $( this ).prop('disabled', true).text( bookease_admin.i18n.loading );
      const msg  = $( '#be-settings-msg' );

      // Gather general settings.
      const payload = {};
      $( '.be-setting' ).each( function () {
        payload[ $( this ).data('key') ] = $( this ).val();
      } );

      // Business hours.
      const hours = {};
      $( '.be-hours-row' ).each( function () {
        const day = $( this ).data('day');
        hours[day] = {
          enabled: $( this ).find('.be-hours-enabled').is(':checked'),
          open:    $( this ).find('.be-hours-open').val(),
          close:   $( this ).find('.be-hours-close').val(),
        };
      } );
      payload.business_hours = JSON.stringify(hours);

      API.post( 'bookease_save_settings', payload, () => {
        msg.removeClass('be-notice--error').addClass('be-notice be-notice--success').text( bookease_admin.i18n.saved ).show();
        $btn.prop('disabled', false).text('Save Settings');
        setTimeout( () => msg.hide(), 3000 );
      }, errMsg => {
        msg.removeClass('be-notice--success').addClass('be-notice be-notice--error').text(errMsg).show();
        $btn.prop('disabled', false).text('Save Settings');
      } );
    } );
  }

  // ── Blocked Dates page ──────────────────────────────────────────────────────
  if ( $( '#be-add-blocked' ).length ) {
    initBlockedDatesPage();
  }

  function initBlockedDatesPage() {
    $( '#be-add-blocked' ).on( 'click', function () {
      const date   = $( '#be-block-date' ).val();
      const reason = $( '#be-block-reason' ).val();
      const msg    = $( '#be-blocked-msg' );

      if ( ! date ) { msg.text('Please select a date.').addClass('be-notice be-notice--error').show(); return; }

      API.post( 'bookease_save_blocked_date', { date, reason }, data => {
        msg.text('Date blocked.').removeClass('be-notice--error').addClass('be-notice be-notice--success').show();
        setTimeout( () => msg.hide(), 3000 );
        location.reload();
      } );
    } );

    $( document ).on( 'click', '.be-unblock', function () {
      const date = $( this ).data('date');
      if ( ! confirm('Remove block for ' + date + '?') ) return;
      API.post( 'bookease_remove_blocked_date', { date }, () => location.reload() );
    } );
  }

  // ── Calendar page ───────────────────────────────────────────────────────────
  if ( $( '#be-calendar' ).length ) {
    initCalendar();
  }

  function initCalendar() {
    let year  = new Date().getFullYear();
    let month = new Date().getMonth() + 1;
    let view  = 'month';

    function load() {
      $( '#be-calendar-loading' ).show();
      $( '#be-calendar' ).hide();

      const start = `${year}-${String(month).padStart(2,'0')}-01`;
      const end   = `${year}-${String(month).padStart(2,'0')}-31`;

      API.post( 'bookease_calendar_data', { start, end }, data => {
        $( '#be-calendar-loading' ).hide();
        $( '#be-calendar' ).show();
        renderCalendar( data.events, year, month );
      } );
    }

    function renderCalendar( events, yr, mo ) {
      $( '#be-cal-label' ).text( new Date(yr, mo-1).toLocaleDateString('en-US',{month:'long',year:'numeric'}) );

      const eventsByDate = {};
      events.forEach( e => {
        const d = e.start.substr(0,10);
        if ( ! eventsByDate[d] ) eventsByDate[d] = [];
        eventsByDate[d].push(e);
      } );

      const firstDay    = new Date(yr, mo-1, 1).getDay();
      const daysInMonth = new Date(yr, mo, 0).getDate();
      const today       = new Date().toISOString().substr(0,10);

      const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      let html = '<div class="be-cal-grid">';
      weekdays.forEach( d => { html += `<div class="be-cal-header-cell">${d}</div>`; } );

      // Empty cells before month starts.
      for ( let i = 0; i < firstDay; i++ ) html += '<div class="be-cal-cell be-cal-cell--outside"></div>';

      for ( let d = 1; d <= daysInMonth; d++ ) {
        const date    = `${yr}-${String(mo).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = date === today ? ' be-cal-cell--today' : '';
        const dayEvts = eventsByDate[date] || [];
        const evtHtml = dayEvts.slice(0,3).map( e =>
          `<div class="be-cal-event" style="background:${e.color}" title="${e.title}" data-id="${e.extendedProps.id}">${e.title}</div>`
        ).join('') + ( dayEvts.length > 3 ? `<div class="be-cal-event" style="background:#94a3b8">+${dayEvts.length-3} more</div>` : '' );

        html += `<div class="be-cal-cell${isToday}" data-date="${date}">
          <div class="be-cal-day-num">${d}</div>
          ${evtHtml}
        </div>`;
      }
      html += '</div>';
      $( '#be-calendar' ).html(html);
    }

    // Event click → popover.
    $( '#be-calendar' ).on( 'click', '.be-cal-event', function(e) {
      e.stopPropagation();
      const id = +$( this ).data('id');
      // Fetch fresh booking details if needed – for now show in popover.
      const $pop = $( '#be-event-popover' );
      $( '#be-popover-ref' ).text( $( this ).text() );
      $pop.css({ top: e.pageY - 80, left: Math.min(e.pageX, $(window).width() - 320) }).removeAttr('hidden');
      $pop.data('booking-id', id);
    } );

    // Popover close.
    $( '.be-popover__close, #be-event-popover' ).on( 'click', function(e) {
      if ( e.target === this ) $( '#be-event-popover' ).attr('hidden','');
    } );

    // Approve/reject from popover.
    $( '#be-popover-approve' ).on( 'click', () => changeStatus( $( '#be-event-popover' ).data('booking-id'), 'approved' ) );
    $( '#be-popover-reject'  ).on( 'click', () => changeStatus( $( '#be-event-popover' ).data('booking-id'), 'rejected' ) );

    function changeStatus( id, status ) {
      API.post( 'bookease_update_status', { id, status }, () => {
        $( '#be-event-popover' ).attr('hidden','');
        load();
      } );
    }

    // Navigation.
    $( '#be-cal-prev' ).on( 'click', () => { if (--month < 1) { month=12; year--; } load(); } );
    $( '#be-cal-next' ).on( 'click', () => { if (++month >12) { month=1;  year++; } load(); } );

    $( '.be-cal-view-switcher .be-btn' ).on( 'click', function() {
      $( '.be-cal-view-switcher .be-btn' ).removeClass('be-btn--active');
      $( this ).addClass('be-btn--active');
      view = $( this ).data('view');
    } );

    load();
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────
  function openViewModal( id ) {
    $( '#be-view-modal' ).data('booking-id', id).removeAttr('hidden');
    API.post( 'bookease_get_booking', { id }, data => {
      const b = data.booking;
      $( '#be-view-content' ).html(`
        <table style="width:100%;border-collapse:collapse">
          ${row('Ref', b.booking_ref)} ${row('Name', b.customer_name)}
          ${row('Email', b.customer_email)} ${row('Phone', b.customer_phone)}
          ${row('Date', b.service_date)} ${row('Time', b.start_time.substr(0,5)+' – '+b.end_time.substr(0,5))}
          ${row('Status','<span class="be-badge be-badge--'+b.status+'">'+b.status+'</span>')}
          ${b.notes ? row('Notes', b.notes) : ''}
        </table>`);
    } );
  }

  function openEditModal( id, booking ) {
    $( '#be-editing-id' ).val( id || '' );
    $( '#be-modal-title' ).text( id ? 'Edit Booking' : 'Add Booking' );
    $( '#be-modal-msg' ).hide();

    if ( booking ) {
      $( '#be-f-name' ).val( booking.customer_name );
      $( '#be-f-email' ).val( booking.customer_email );
      $( '#be-f-phone' ).val( booking.customer_phone );
      $( '#be-f-date' ).val( booking.service_date ).trigger('change');
      $( '#be-f-notes' ).val( booking.notes );
    } else {
      $( '#be-f-name, #be-f-email, #be-f-phone, #be-f-date, #be-f-notes' ).val('');
    }

    $( '#be-booking-modal' ).removeAttr('hidden');
  }

  function closeModal( id ) {
    $( '#' + id ).attr('hidden','');
  }

  function row( label, value ) {
    return `<tr>
      <th style="text-align:left;padding:.5rem .75rem;background:#f9fafb;border:1px solid #e5e7eb;font-size:.8rem;">${esc(label)}</th>
      <td style="padding:.5rem .75rem;border:1px solid #e5e7eb;font-size:.875rem;">${value}</td>
    </tr>`;
  }

  function esc( str ) {
    if ( str == null ) return '';
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

} )( jQuery );
