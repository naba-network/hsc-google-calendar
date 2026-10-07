<?php
/**
 * Loads events for the shortcodes.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

// Exception messages are internal; callers decide what visitors see.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

namespace Hsc\GoogleCalendar;

use InvalidArgumentException;
use RuntimeException;

/**
 * Google Calendar is the single source of truth: events are only read, never changed.
 * The raw event list is cached for 30 minutes so page views do not hit the API every time; saving the settings flushes it.
 */
final class Event_Source {

	private const CACHE_KEY        = 'hsc_gcal_events_';
	private const CACHE_GENERATION = 'hsc_gcal_cache_generation';
	private const CACHE_SECONDS    = 1800;

	/**
	 * Creates the source.
	 *
	 * @param Settings        $settings Calendar settings.
	 * @param Calendar_Client $client   Event loader.
	 */
	public function __construct(
		private readonly Settings $settings,
		private readonly Calendar_Client $client
	) {}

	/**
	 * Forgets the cached event lists, the next read asks the API again.
	 */
	public function flush(): void {
		update_option( self::CACHE_GENERATION, (string) microtime( true ), false );
	}

	/**
	 * Number of upcoming free events and of booked events, for the admin page.
	 *
	 * @param int $now Current unix timestamp.
	 *
	 * @return array{free: int, booked: int}
	 * @throws RuntimeException When the calendar is not configured or cannot be read.
	 */
	public function counts( int $now ): array {
		return array(
			'free'   => count( $this->free_events( $now ) ),
			'booked' => count( $this->bookings( $now ) ),
		);
	}

	/**
	 * Upcoming events that are still free to book (match the free pattern, are not booked and have not started).
	 *
	 * @param int $now Current unix timestamp.
	 *
	 * @return list<Event>
	 * @throws RuntimeException When the calendar is not configured or cannot be read.
	 */
	public function free_events( int $now ): array {
		return array_values(
			array_filter(
				$this->free_slots( $now ),
				static fn( Event $event ): bool => ( $event->start_time()?->getTimestamp() ?? 0 ) >= $now
			)
		);
	}

	/**
	 * All appointments in the date range that are still free, past ones included. Events that also match the
	 * booked pattern are left out, so an empty free pattern (matches everything) does not count bookings twice.
	 *
	 * @param int $now Current unix timestamp.
	 *
	 * @return list<Event>
	 * @throws RuntimeException When the calendar is not configured or cannot be read.
	 */
	public function free_slots( int $now ): array {
		$free   = Event_Filter::from_pattern( $this->settings->event_regex(), true )->apply( $this->all_events( $now ) );
		$booked = $this->settings->booked_regex();
		if ( '' === $booked ) {
			return $free;
		}

		$booked_filter = Event_Filter::from_pattern( $booked, true );

		return array_values( array_filter( $free, static fn( Event $event ): bool => ! $booked_filter->matches( $event ) ) );
	}

	/**
	 * A free event by ID, null when it is gone or no longer matches (e.g. renamed to booked).
	 *
	 * @param string $id  Event ID.
	 * @param int    $now Current unix timestamp.
	 *
	 * @throws RuntimeException When the calendar is not configured or cannot be read.
	 */
	public function find_free( string $id, int $now ): ?Event {
		foreach ( $this->free_events( $now ) as $event ) {
			if ( $event->id === $id ) {
				return $event;
			}
		}

		return null;
	}

	/**
	 * Booked events in the configured date range, with the details parsed from the description.
	 *
	 * @param int $now Current unix timestamp.
	 *
	 * @return list<Booking>
	 * @throws RuntimeException When the calendar is not configured or cannot be read.
	 */
	public function bookings( int $now ): array {
		$pattern = $this->settings->booked_regex();
		if ( '' === $pattern ) {
			return array();
		}

		$events = Event_Filter::from_pattern( $pattern, true )->apply( $this->all_events( $now ) );

		return array_map( static fn( Event $event ): Booking => Booking::from_event( $event ), $events );
	}

	/**
	 * All events in the configured date range, cached.
	 *
	 * @param int $now Current unix timestamp.
	 *
	 * @return list<Event>
	 * @throws RuntimeException When the calendar is not configured or cannot be read.
	 */
	private function all_events( int $now ): array {
		$calendar_id = $this->settings->calendar_id();
		$json        = $this->settings->credentials_json();
		$api_key     = $this->settings->api_key();
		if ( '' === $calendar_id || ( '' === $json && '' === $api_key ) ) {
			throw new RuntimeException( 'Calendar is not configured.' );
		}

		$range  = new Date_Range( $this->settings->range_from(), $this->settings->range_to(), wp_timezone() );
		$key    = self::CACHE_KEY . md5( HSC_GCAL_VERSION . get_option( self::CACHE_GENERATION, '' ) . $calendar_id . $range->key() );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return array_values( array_filter( $cached, static fn( mixed $item ): bool => $item instanceof Event ) );
		}

		try {
			$events = $this->client->list_events(
				$calendar_id,
				'' === $json ? null : Credentials::from_json( $json ),
				$api_key,
				$range->time_min( $now ),
				$now,
				$range->time_max()
			);
		} catch ( InvalidArgumentException $e ) {
			throw new RuntimeException( $e->getMessage(), 0, $e );
		}

		set_transient( $key, $events, self::CACHE_SECONDS );

		return $events;
	}
}
