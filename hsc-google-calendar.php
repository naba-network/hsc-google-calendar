<?php
/**
 * Plugin Name:       HSC Google Calendar
 * Description:       Shows free ice time slots from Google Calendar and makes them bookable on the club website.
 * Version:           0.1.5
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            HSC Hohenems
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hsc-google-calendar
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HSC_GCAL_VERSION', '0.1.5' );
define( 'HSC_GCAL_FILE', __FILE__ );
define( 'HSC_GCAL_GITHUB_REPO', 'naba-network/hsc-google-calendar' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/includes/class-updater.php';
require_once __DIR__ . '/includes/class-credentials.php';
require_once __DIR__ . '/includes/class-connection-result.php';
require_once __DIR__ . '/includes/class-connection-tester.php';
require_once __DIR__ . '/includes/class-event.php';
require_once __DIR__ . '/includes/class-event-filter.php';
require_once __DIR__ . '/includes/class-calendar-client.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-booking.php';
require_once __DIR__ . '/includes/class-booking-summary.php';
require_once __DIR__ . '/includes/class-booking-request.php';
require_once __DIR__ . '/includes/class-captcha.php';
require_once __DIR__ . '/includes/class-mail-message.php';
require_once __DIR__ . '/includes/class-mail-layout.php';
require_once __DIR__ . '/includes/class-booking-mailer.php';
require_once __DIR__ . '/includes/class-date-range.php';
require_once __DIR__ . '/includes/class-event-source.php';
require_once __DIR__ . '/includes/class-booking-controller.php';
require_once __DIR__ . '/includes/class-shortcodes.php';
require_once __DIR__ . '/includes/class-admin-page.php';

$hsc_gcal_updater = new \Hsc\GoogleCalendar\Updater(
	HSC_GCAL_FILE,
	HSC_GCAL_GITHUB_REPO,
	'hsc-google-calendar'
);
$hsc_gcal_updater->register();

$hsc_gcal_settings = new \Hsc\GoogleCalendar\Settings();
$hsc_gcal_client   = new \Hsc\GoogleCalendar\Calendar_Client(
	\Hsc\GoogleCalendar\Connection_Tester::wordpress_sender(),
	\Hsc\GoogleCalendar\Connection_Tester::with_wordpress_http()
);

$hsc_gcal_events = new \Hsc\GoogleCalendar\Event_Source( $hsc_gcal_settings, $hsc_gcal_client );

$hsc_gcal_mailer = \Hsc\GoogleCalendar\Booking_Mailer::with_wordpress_mail(
	$hsc_gcal_settings->mail_from(),
	$hsc_gcal_settings->mail_from_name(),
	$hsc_gcal_settings->mail_to()
);

( new \Hsc\GoogleCalendar\Admin_Page(
	plugin_basename( HSC_GCAL_FILE ),
	HSC_GCAL_VERSION,
	$hsc_gcal_updater,
	$hsc_gcal_settings,
	\Hsc\GoogleCalendar\Connection_Tester::with_wordpress_http(),
	$hsc_gcal_events,
	$hsc_gcal_mailer
) )->register();

( new \Hsc\GoogleCalendar\Shortcodes( $hsc_gcal_events, plugin_dir_url( HSC_GCAL_FILE ), HSC_GCAL_VERSION ) )->register();

( new \Hsc\GoogleCalendar\Booking_Controller(
	$hsc_gcal_events,
	new \Hsc\GoogleCalendar\Captcha( static fn(): string => wp_salt( 'auth' ) ),
	$hsc_gcal_mailer
) )->register();
