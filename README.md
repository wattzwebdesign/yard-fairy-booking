# Yard Fairy Booking - WooCommerce Booking Plugin

A comprehensive WooCommerce booking plugin with Google Calendar integration and a beautiful front-end calendar interface.

## Features

- ✅ **Product-Based Booking System** - Convert any WooCommerce product into a bookable service
- ✅ **Google Calendar Integration** - Two-way sync with Google Calendar
- ✅ **Front-End Month Calendar** - Interactive calendar display for customers
- ✅ **Flexible Scheduling** - Set availability by day of week with custom time slots
- ✅ **Booking Management** - Full admin interface for managing bookings
- ✅ **Order Integration** - Bookings automatically created from WooCommerce orders
- ✅ **Time Slot Management** - Configurable duration, buffer times, and booking notices
- ✅ **Status Management** - Track booking status (Pending, Confirmed, Cancelled, Completed)

## Installation

1. **Upload Plugin**
   - Upload the `yard-fairy-booking` folder to `/wp-content/plugins/`
   - Or install via ZIP file in WordPress admin

2. **Install Dependencies**
   ```bash
   cd wp-content/plugins/yard-fairy-booking
   composer install
   ```

3. **Activate Plugin**
   - Go to Plugins in WordPress admin
   - Activate "Yard Fairy Booking"

4. **Configure Google Calendar** (See Google Calendar Setup section below)

## Usage

### Making Products Bookable

1. **Edit a Product** in WooCommerce
2. **Enable Bookable Checkbox** in the Product Data panel
3. **Configure Booking Settings:**
   - **Duration**: How long each booking lasts (in minutes)
   - **Buffer Time**: Gap between bookings (in minutes)
   - **Min Booking Notice**: How far in advance customers must book (in hours)
   - **Max Booking Advance**: How far ahead customers can book (in days)

4. **Set Availability:**
   - Enable specific days of the week
   - Set start and end times for each day

5. **Save Product**

### Customer Booking Flow

When customers view a bookable product:

1. A calendar widget appears on the product page
2. They select an available date from the month calendar
3. Available time slots appear for that date
4. They select a time slot
5. Add to cart and checkout as normal
6. Booking is automatically created when order is processed

### Managing Bookings

1. **View All Bookings** - Go to `Bookings` in WordPress admin
2. **Edit Bookings** - Click on any booking to edit details
3. **Change Status** - Update booking status (Pending → Confirmed → Completed)
4. **Sync to Google Calendar** - Click sync button in booking edit screen

## Google Calendar Setup

### 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable **Google Calendar API**

### 2. Create OAuth 2.0 Credentials

1. Navigate to `APIs & Services > Credentials`
2. Click `Create Credentials > OAuth client ID`
3. Select `Web application`
4. Add Authorized Redirect URI:
   ```
   https://yoursite.com/wp-admin/admin-ajax.php?action=yfb_google_oauth_callback
   ```
5. Copy **Client ID** and **Client Secret**

### 3. Configure Plugin

1. Go to `Bookings > Settings` in WordPress admin
2. Paste **Client ID** and **Client Secret**
3. Enter **Calendar ID** (use `primary` for main calendar)
4. Click **Save Changes**
5. Click **Authorize with Google**
6. Grant calendar permissions
7. You'll be redirected back - setup complete!

### Google Calendar Sync Features

- **Auto-Sync on Confirmation**: Bookings sync when status changes to "Confirmed"
- **Delete on Cancellation**: Calendar events removed when booking cancelled
- **Manual Sync**: Re-sync button in booking edit screen
- **Event Details**: Includes product name, customer info, and booking time

## Shortcode

Display booking calendar anywhere using:

```
[yfb_booking_calendar product_id="123"]
```

Replace `123` with your product ID.

## File Structure

```
yard-fairy-booking/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── includes/
│   ├── class-yfb-ajax-handler.php
│   ├── class-yfb-booking-post-type.php
│   ├── class-yfb-calendar-display.php
│   ├── class-yfb-google-calendar.php
│   ├── class-yfb-product-booking.php
│   └── class-yfb-settings.php
├── composer.json
├── README.md
└── yard-fairy-booking.php
```

## API Endpoints

### AJAX Actions

- `yfb_get_available_slots` - Get available time slots for a date
- `yfb_get_month_availability` - Get availability for entire month
- `yfb_sync_to_gcal` - Sync booking to Google Calendar
- `yfb_resync_to_gcal` - Re-sync existing booking

## Hooks & Filters

### Actions

```php
// Fired when booking status changes
do_action('yfb_booking_status_changed', $booking_id, $old_status, $new_status);

// Fired when booking created from order
do_action('yfb_booking_created_from_order', $booking_id, $order_id, $item_id);
```

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- Composer (for Google API client)

## Development

### Install Dependencies

```bash
composer install
```

### Code Structure

- **Post Type**: Custom post type `yfb_booking` for bookings
- **Meta Fields**: All booking data stored as post meta with `_yfb_` prefix
- **AJAX**: jQuery-based AJAX for calendar interactions
- **Google API**: Uses official Google API PHP client

## Support

For issues, feature requests, or questions:
- Create an issue on GitHub
- Contact support at your-email@example.com

## License

GPL v2 or later

## Credits

Developed by Your Name
Plugin URI: https://github.com/yourusername/yard-fairy-booking

---

**Note**: After installation, run `composer install` to download the Google API client library before configuring Google Calendar integration.