<?php
/**
 * Plugin Name:       HSC Google Calendar
 * Description:       Shows free ice time slots from Google Calendar and makes them bookable on the club website.
 * Version:           0.1.2
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

define( 'HSC_GCAL_VERSION', '0.1.2' );
define( 'HSC_GCAL_FILE', __FILE__ );
define( 'HSC_GCAL_GITHUB_REPO', 'naba-network/hsc-google-calendar' );

require_once __DIR__ . '/includes/class-updater.php';

require_once __DIR__ . '/includes/class-admin-page.php';

( new \Hsc\GoogleCalendar\Admin_Page( plugin_basename( HSC_GCAL_FILE ), HSC_GCAL_VERSION ) )->register();

( new \Hsc\GoogleCalendar\Updater(
	plugin_basename( HSC_GCAL_FILE ),
	HSC_GCAL_VERSION,
	HSC_GCAL_GITHUB_REPO
) )->register();
