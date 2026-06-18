/* global bookease, jQuery */
( function ( $ ) {
  'use strict';

  // ── Helpers ──────────────────────────────────────────────────────────────────
  function show( $el ) { $el.removeAttr( 'hidden' ); }
  function hide( $el ) { $el.attr( 'hidden', '' ); }

  // ── Multi-Instance Form Setup ────────────────────────────────────────────────
  $( '.bookease-wrap' ).each( function () {
    const $container = $( this );

    // Ignore reschedule standalone container
    if ( $container.find( '#be-rs-token' ).length ) {
      return;
    }

    const state = {
      step:      1,
      date:      '',
      startTime: '',
      endTime:   '',
      name:      '',
      email:     '',
      phone:     '',
      notes:     '',
    };

    const form = {
      date:       () => $container.find( '#be-date' ),
      time:       () => $container.find( '#be-time' ),
      slotField:  () => $container.find( '#be-slot-field' ),
      slots:      () => $container.find( '#be-slots' ),
      startTime:  () => $container.find( '#be-start-time' ),
      endTime:    () => $container.find( '#be-end-time' ),
      next1:      () => $container.find( '#be-next-1' ),
      next2:      () => $container.find( '#be-next-2' ),
      back1:      () => $container.find( '#be-back-1' ),
      back2:      () => $container.find( '#be-back-2' ),
      submit:     () => $container.find( '#be-submit' ),
      error:      () => $container.find( '#be-submit-error' ),
      name:       () => $container.find( '#be-name' ),
      email:      () => $container.find( '#be-email' ),
      phone:      () => $container.find( '#be-phone' ),
      notes:      () => $container.find( '#be-notes' ),
    };

    function goToStep( n ) {
      state.step = n;
      hide( $container.find( '[id^="be-step-"]' ) );

      if ( n === 'success' ) {
        show( $container.find( '#be-step-success' ) );
        return;
      }
      show( $container.find( '#be-step-' + n ) );

      // Update step indicators
      $container.find( '.be-step' ).each( function () {
        const s = +$( this ).data( 'step' );
        $( this ).removeClass( 'be-step--active be-step--done' );
        if ( s < n )   $( this ).addClass( 'be-step--done' );
        if ( s === n ) $( this ).addClass( 'be-step--active' );
      } );
    }

    form.date().on( 'change', function () {
      const date = $( this ).val();
      state.date      = date;
      state.startTime = '';
      state.endTime   = '';
      form.startTime().val( '' );
      form.endTime().val( '' );
      form.next1().prop( 'disabled', true );

      if ( ! date ) { hide( form.slotField() ); return; }

      show( form.slotField() );
      form.slots().html( '<p class="be-slots__loading">' + bookease.i18n.loading + '</p>' );

      $.post( bookease.ajaxUrl, {
        action: 'bookease_get_slots',
        nonce:  bookease.nonce,
        date,
      }, function ( res ) {
        if ( ! res.success || ! res.data.slots.length ) {
          form.slots().html( '<p class="be-slots__loading">' + bookease.i18n.noSlots + '</p>' );
          return;
        }
        renderSlots( res.data.slots );
      } );
    } );

    function renderSlots( slots ) {
      const html = slots.map( s => {
        const label    = s.start.substr(0,5) + ' – ' + s.end.substr(0,5);
        const remain   = s.remaining > 1 ? `<span class="be-slot__remain">${s.remaining} left</span>` : '';
        const avail    = s.available ? '' : ' be-slot--unavailable';
        const disabled = s.available ? '' : ' disabled';
        return `<button type="button" class="be-slot${avail}" data-start="${s.start}" data-end="${s.end}"${disabled}>
          ${label}${remain}
        </button>`;
      } ).join( '' );
      form.slots().html( html );
    }

    form.slots().on( 'click', '.be-slot:not([disabled])', function () {
      form.slots().find( '.be-slot' ).removeClass( 'be-slot--selected' );
      $( this ).addClass( 'be-slot--selected' );
      state.startTime = $( this ).data( 'start' );
      state.endTime   = $( this ).data( 'end' );
      form.startTime().val( state.startTime );
      form.endTime().val( state.endTime );
      form.next1().prop( 'disabled', false );
    } );

    function resetForm() {
      state.step      = 1;
      state.date      = '';
      state.startTime = '';
      state.endTime   = '';
      state.name      = '';
      state.email     = '';
      state.phone     = '';
      state.notes     = '';

      form.date().val( '' );
      form.startTime().val( '' );
      form.endTime().val( '' );
      form.name().val( '' );
      form.email().val( '' );
      form.phone().val( '' );
      form.notes().val( '' );

      hide( form.slotField() );
      form.slots().html( '' );
      form.next1().prop( 'disabled', true );

      hide( form.error() );
      form.error().text( '' );

      $container.find( '#be-sum-date' ).text( '' );
      $container.find( '#be-sum-time' ).text( '' );
      $container.find( '#be-sum-name' ).text( '' );
      $container.find( '#be-sum-email' ).text( '' );
      $container.find( '#be-sum-phone' ).text( '' );
      $container.find( '#be-sum-notes' ).text( '' );
      show( $container.find( '#be-sum-phone-row' ) );
      show( $container.find( '#be-sum-notes-row' ) );

      goToStep( 1 );
    }

    $container.on( 'click', '#be-add-another-booking', function() {
      resetForm();
    } );

    form.next1().on( 'click', function () {
      if ( ! state.date || ! state.startTime ) return;
      goToStep( 2 );
    } );

    form.back1().on( 'click', function () { goToStep( 1 ); } );

    form.next2().on( 'click', function () {
      const name  = form.name().val().trim();
      const email = form.email().val().trim();

      if ( ! name )                  { form.name().focus();  return; }
      if ( ! isValidEmail( email ) ) { form.email().focus(); return; }

      state.name  = name;
      state.email = email;
      state.phone = form.phone().val().trim();
      state.notes = form.notes().val().trim();

      $container.find( '#be-sum-date' ).text( state.date );
      $container.find( '#be-sum-time' ).text( state.startTime.substr(0,5) + ' – ' + state.endTime.substr(0,5) );
      $container.find( '#be-sum-name' ).text( state.name );
      $container.find( '#be-sum-email' ).text( state.email );
      if ( state.phone ) { $container.find( '#be-sum-phone' ).text( state.phone ); } else { hide( $container.find( '#be-sum-phone-row' ) ); }
      if ( state.notes ) { $container.find( '#be-sum-notes' ).text( state.notes ); } else { hide( $container.find( '#be-sum-notes-row' ) ); }

      goToStep( 3 );
    } );

    form.back2().on( 'click', function () { goToStep( 2 ); } );

    form.submit().on( 'click', function () {
      const $btn = $( this );
      $btn.prop( 'disabled', true ).text( bookease.i18n.submitting );
      hide( form.error() );

      $.post( bookease.ajaxUrl, {
        action:     'bookease_submit',
        nonce:      bookease.nonce,
        name:       state.name,
        email:      state.email,
        phone:      state.phone,
        date:       state.date,
        start_time: state.startTime,
        end_time:   state.endTime,
        notes:      state.notes,
      }, function ( res ) {
        $btn.prop( 'disabled', false ).text( 'Confirm Booking' );

        if ( ! res.success ) {
          form.error().text( res.data.message || bookease.i18n.error );
          show( form.error() );
          return;
        }

        $container.find( '#be-success-message' ).text( res.data.message );
        $container.find( '#be-success-ref' ).text( res.data.booking_ref );
        goToStep( 'success' );
      } ).fail( function () {
        $btn.prop( 'disabled', false ).text( 'Confirm Booking' );
        form.error().text( bookease.i18n.error );
        show( form.error() );
      } );
    } );

    goToStep( 1 );
  } );


  // ── Reschedule mode ──────────────────────────────────────────────────────────
  if ( typeof bookease !== 'undefined' && bookease.mode === 'reschedule' ) {
    initReschedule();
  }

  function initReschedule() {
    $( '#be-rs-date' ).on( 'change', function () {
      const date = $( this ).val();
      $( '#be-rs-slots' ).html( '<p class="be-slots__loading">' + bookease.i18n.loading + '</p>' );
      $( '#be-rs-slot-wrap' ).removeAttr( 'hidden' );
      $( '#be-rs-submit' ).prop( 'disabled', true );
      $( '#be-rs-start' ).val( '' );
      $( '#be-rs-end' ).val( '' );


      $.post( bookease.ajaxUrl, { action: 'bookease_get_slots', nonce: bookease.nonce, date }, function ( res ) {
        if ( ! res.success || ! res.data.slots.length ) {
          $( '#be-rs-slots' ).html( '<p class="be-slots__loading">' + bookease.i18n.noSlots + '</p>' );
          return;
        }
        const html = res.data.slots.map( s => {
          const avail = s.available ? '' : ' be-slot--unavailable';
          const dis   = s.available ? '' : ' disabled';
          return `<button class="be-slot${avail}" data-start="${s.start}" data-end="${s.end}"${dis}>${s.start.substr(0,5)}–${s.end.substr(0,5)}</button>`;
        } ).join( '' );
        $( '#be-rs-slots' ).html( html );
      } );
    } );

    $( document ).on( 'click', '#be-rs-slots .be-slot:not([disabled])', function () {
      $( '#be-rs-slots .be-slot' ).removeClass( 'be-slot--selected' );
      $( this ).addClass( 'be-slot--selected' );
      const start = $( this ).data( 'start' );
      $( '#be-rs-start' ).val( start );
      $( '#be-rs-end' ).val(   $( this ).data( 'end'   ) );
      $( '#be-rs-submit' ).prop( 'disabled', false );
    } );



    $( '#be-rs-submit' ).on( 'click', function () {
      const $btn = $( this ).prop( 'disabled', true );
      $.post( bookease.ajaxUrl, {
        action:    'bookease_reschedule',
        nonce:     bookease.nonce,
        token:     $( '#be-rs-token' ).val(),
        new_date:  $( '#be-rs-date' ).val(),
        new_start: $( '#be-rs-start' ).val(),
        new_end:   $( '#be-rs-end' ).val(),
      }, function ( res ) {
        if ( res.success ) {
          $( '#be-rs-success' ).text( res.data.message || 'Rescheduled successfully.' ).removeAttr( 'hidden' );
        } else {
          $( '#be-rs-error' ).text( res.data.message || 'Error. Try again.' ).removeAttr( 'hidden' );
          $btn.prop( 'disabled', false );
        }
      } );
    } );
  }

  // ── Utilities ────────────────────────────────────────────────────────────────
  function isValidEmail( v ) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( v );
  }

} )( jQuery );
