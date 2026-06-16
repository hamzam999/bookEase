# BookEase – Booking & Reservation System

**Version:** 1.0.0  
**Requires WordPress:** 6.0+  
**Requires PHP:** 8.0+  
**License:** GPLv2 or later

---

## Overview

BookEase is a white-label, commercial-quality WordPress booking and reservation plugin built for agencies. It supports hotels, restaurants, clinics, rentals, and any service business that takes appointments.

---

## Installation

1. Upload the `booking-plugin` folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Navigate to **BookEase → Settings** to configure business hours, slot duration, and notifications.
4. Add `[bookease_form]` to any page or post to display the booking form.

---

## Architecture

```
booking-plugin/
├── booking-plugin.php          ← Plugin bootstrap + constants + autoloader
├── uninstall.php               ← Runs on plugin deletion (drops tables)
├── assets/
│   ├── css/
│   │   ├── admin.css           ← Admin UI styles
│   │   └── frontend.css        ← Frontend form styles
│   └── js/
│       ├── admin.js            ← Admin bookings table, calendar, settings
│       └── frontend.js         ← Multi-step booking form + slot loader
├── templates/
│   ├── admin/
│   │   ├── bookings.php        ← Bookings list page
│   │   ├── calendar.php        ← Calendar view page
│   │   ├── settings.php        ← Settings page
│   │   └── blocked-dates.php   ← Blocked dates management
│   └── frontend/
│       ├── booking-form.php    ← [bookease_form] shortcode output
│       └── reschedule.php      ← Customer reschedule page
├── languages/                  ← .pot translation file goes here
└── src/
    ├── Plugin.php              ← Core orchestrator (wires all modules)
    ├── Installer.php           ← Activation / DB table creation
    ├── Admin/
    │   └── Dashboard.php       ← Admin menus, AJAX handlers
    ├── Frontend/
    │   └── BookingForm.php     ← Shortcode + frontend AJAX
    ├── Services/
    │   ├── AvailabilityService.php  ← Slot generation, date validation
    │   ├── BookingService.php       ← Create/update/cancel/approve/reject
    │   └── EmailService.php         ← All transactional emails
    ├── Models/
    │   └── Booking.php         ← Immutable value object
    ├── Repositories/
    │   └── BookingRepository.php   ← All DB queries (prepared statements)
    └── API/
        └── BookingController.php   ← REST API (WP_REST_Controller)
```

---

## Database Schema

### `{prefix}bookease_bookings`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI | Primary key |
| booking_ref | VARCHAR(20) UNIQUE | Human-readable reference e.g. BK-3F7A1B2C |
| customer_name | VARCHAR(150) | |
| customer_email | VARCHAR(150) | |
| customer_phone | VARCHAR(30) | |
| service_date | DATE | |
| start_time | TIME | |
| end_time | TIME | |
| status | VARCHAR(20) | pending / approved / rejected / cancelled |
| notes | TEXT | Customer notes |
| cancel_token | VARCHAR(64) | One-click cancel token |
| reschedule_token | VARCHAR(64) | One-click reschedule token |
| created_at | DATETIME | |
| updated_at | DATETIME | Auto-updated |

### `{prefix}bookease_blocked_dates`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI | |
| blocked_date | DATE UNIQUE | |
| reason | VARCHAR(255) | Optional label |
| created_at | DATETIME | |

### `{prefix}bookease_settings`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI | |
| setting_key | VARCHAR(100) UNIQUE | |
| setting_value | LONGTEXT | |

---

## REST API

Base URL: `/wp-json/bookease/v1/`

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/bookings` | Admin | List bookings (search, status, pagination) |
| POST | `/bookings` | Public | Create a booking |
| GET | `/bookings/{id}` | Admin | Get single booking |
| PUT | `/bookings/{id}` | Admin | Update booking |
| DELETE | `/bookings/{id}` | Admin | Delete booking |
| POST | `/bookings/{id}/cancel` | Admin | Cancel booking |
| GET | `/availability?date=YYYY-MM-DD` | Public | Get available slots for a date |

---

## Shortcode

```
[bookease_form]
```

Place on any page. The form is fully AJAX-driven with a 3-step UX:
1. **Date & Time** – date picker + slot grid
2. **Your Details** – name, email, phone, notes
3. **Confirm** – summary review before submission

---

## Hooks & Filters

### Actions

```php
do_action( 'bookease_loaded', $plugin );          // Plugin fully loaded
do_action( 'bookease_booking_created', $booking );
do_action( 'bookease_booking_updated', $booking, $old_booking );
do_action( 'bookease_booking_approved', $booking, $old_booking );
do_action( 'bookease_booking_rejected', $booking, $old_booking );
do_action( 'bookease_booking_cancelled', $booking, $old_booking );
do_action( 'bookease_booking_rescheduled', $booking, $old_booking );
do_action( 'bookease_booking_deleted', $booking );
```

### Filters

```php
// Change default booking status on creation
add_filter( 'bookease_default_status', fn() => 'approved' );

// Modify available slots for a date
add_filter( 'bookease_available_slots', fn( $slots, $date ) => $slots, 10, 2 );

// Override whether a date is bookable
add_filter( 'bookease_is_date_bookable', fn( $bool, $date ) => $bool, 10, 2 );

// Email subjects & bodies
add_filter( 'bookease_email_subject_confirmation', fn( $subject, $booking ) => $subject, 10, 2 );
add_filter( 'bookease_email_body_confirmation',    fn( $body,    $booking ) => $body,    10, 2 );
// Types: confirmation | admin_notification | approval | cancellation | reschedule
```

---

## Settings Reference

| Key | Default | Description |
|-----|---------|-------------|
| timezone | WP timezone | Booking display timezone |
| slot_duration | 60 | Minutes per time slot |
| max_bookings_per_slot | 1 | Bookings allowed per slot |
| lead_time_hours | 2 | Hours before a slot before it hides |
| max_advance_days | 60 | How far ahead customers can book |
| business_hours | Mon–Fri 9–5 | JSON map of per-day hours |
| admin_email | WP admin email | Where new booking notifications go |
| email_from | Site name | "From" name on outgoing emails |

---

## Security

- All DB queries use `$wpdb->prepare()` — no raw SQL interpolation.
- All input is sanitized (`sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`).
- All output is escaped (`esc_html`, `esc_attr`, `esc_url`).
- Nonces on every AJAX action (`wp_create_nonce` / `check_ajax_referer`).
- Capability checks (`manage_options`) on all admin operations.
- Cancel/reschedule links use 32-char random tokens (`wp_generate_password`).

---

## Extending for New Industries

1. Add new settings via `bookease_settings` table or a separate options page.
2. Hook into `bookease_available_slots` to add service-specific logic.
3. Hook into `bookease_booking_created` to trigger custom workflows.
4. Add new email types by extending `EmailService`.

---

## Changelog

### 1.0.0
- Initial release
- Frontend multi-step booking form
- Admin bookings dashboard with search, filter, export
- Calendar view (month/week/day)
- Availability engine with slot generation
- Email notifications (confirmation, approval, cancellation, reschedule)
- Customer cancel/reschedule via secure token links
- Blocked dates management
- Settings page with business hours builder
- REST API
- PHP 8.0+ / WordPress 6.0+ compatibility
