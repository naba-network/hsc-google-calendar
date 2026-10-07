<?php
/**
 * The [HSC-Event-Booking-List] and [HSC-Event-Booking-Summary] shortcodes.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

use RuntimeException;

/**
 * Renders the bookable event list (with booking dialog) and the summary of booked events.
 */
final class Shortcodes {

	public const TAG_LIST    = 'HSC-Event-Booking-List';
	public const TAG_SUMMARY = 'HSC-Event-Booking-Summary';

	private const HANDLE       = 'hsc-gcal-booking';
	private const TEMPLATE_DIR = 'templates/';

	/**
	 * Creates the shortcodes.
	 *
	 * @param Event_Source $events     Calendar events.
	 * @param string       $plugin_url Plugin URL with trailing slash, for assets.
	 * @param string       $version    Plugin version, used as asset version.
	 */
	public function __construct(
		private readonly Event_Source $events,
		private readonly string $plugin_url,
		private readonly string $version
	) {}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_shortcode( self::TAG_LIST, array( $this, 'render_list' ) );
		add_shortcode( self::TAG_SUMMARY, array( $this, 'render_summary' ) );
	}

	/**
	 * Renders the bookable events grouped by month.
	 */
	public function render_list(): string {
		$this->enqueue_style();

		try {
			$events = $this->events->free_events( time() );
		} catch ( RuntimeException | \InvalidArgumentException $e ) {
			return $this->render_template( 'notice', array( 'message' => $this->visitor_error( $e ) ) );
		}

		wp_enqueue_script( self::HANDLE, $this->plugin_url . 'assets/booking.js', array(), $this->version, array( 'in_footer' => true ) );

		$months = array();
		foreach ( $events as $event ) {
			$key = $event->month_key();
			if ( null !== $key ) {
				$months[ $key ][] = $event;
			}
		}
		ksort( $months );

		return $this->render_template(
			'booking-list',
			array(
				'months'   => $months,
				'config'   => array(
					'bookingUrl'      => rest_url( Booking_Controller::REST_NAMESPACE . Booking_Controller::ROUTE_BOOKING ),
					'captchaUrl'      => rest_url( Booking_Controller::REST_NAMESPACE . Booking_Controller::ROUTE_CAPTCHA ),
					'messages'        => Booking_Controller::messages(),
					'maxParticipants' => Booking_Request::MAX_PARTICIPANTS,
				),
				'labeller' => fn( string $key ): string => $this->month_label( $key ),
			)
		);
	}

	/**
	 * Renders the booked events with totals per month. Protect the page with a WordPress password if needed.
	 */
	public function render_summary(): string {
		$this->enqueue_style();

		try {
			$now     = time();
			$summary = new Booking_Summary( $this->events->bookings( $now ), $this->events->free_slots( $now ) );
		} catch ( RuntimeException | \InvalidArgumentException $e ) {
			return $this->render_template( 'notice', array( 'message' => $this->visitor_error( $e ) ) );
		}

		return $this->render_template(
			'booking-summary',
			array(
				'summary'  => $summary,
				'now'      => $now,
				'labeller' => fn( string $key ): string => $this->month_label( $key ),
			)
		);
	}

	/**
	 * Localised month name and year for a "Y-m" key.
	 *
	 * @param string $key Month key.
	 */
	private function month_label( string $key ): string {
		// Noon UTC on the 15th is the same calendar month in every time zone.
		return wp_date( 'F Y', (int) strtotime( $key . '-15 12:00:00 UTC' ) );
	}

	/**
	 * Message for visitors; editors additionally see the technical reason.
	 *
	 * @param \Throwable $error What went wrong.
	 */
	private function visitor_error( \Throwable $error ): string {
		$message = __( 'Die Termine können gerade nicht angezeigt werden.', 'hsc-google-calendar' );
		if ( current_user_can( 'manage_options' ) ) {
			$message .= ' ' . $error->getMessage();
		}

		return $message;
	}

	/**
	 * Enqueues the stylesheet once.
	 */
	private function enqueue_style(): void {
		wp_enqueue_style( self::HANDLE, $this->plugin_url . 'assets/booking.css', array(), $this->version );
	}

	/**
	 * Renders a template file from templates/ and returns its output.
	 *
	 * @param string               $name Template name without extension.
	 * @param array<string, mixed> $view Variables for the template, available there as $view.
	 */
	private function render_template( string $name, array $view ): string {
		$file = dirname( HSC_GCAL_FILE ) . '/' . self::TEMPLATE_DIR . $name . '.php';

		ob_start();
		( static function ( string $file, array $view ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $view is used by the included template.
			include $file;
		} )( $file, $view );

		return (string) ob_get_clean();
	}
}
