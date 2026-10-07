<?php
/**
 * A calendar event.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

use DateTimeImmutable;
use Exception;

/**
 * Immutable subset of a Google Calendar event.
 */
final class Event {

	private const WEEKDAYS_SHORT = array( 'SO', 'MO', 'DI', 'MI', 'DO', 'FR', 'SA' );
	private const WEEKDAYS_LONG  = array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' );
	private const ALL_DAY_LABEL  = 'ganztägig';

	/**
	 * Creates an event.
	 *
	 * @param string $id          Event ID.
	 * @param string $summary     Title.
	 * @param string $description Description (may be empty).
	 * @param string $start       Start as RFC 3339 date-time or Y-m-d for all-day events.
	 * @param string $end         End in the same format as start.
	 * @param bool   $all_day     Whether this is an all-day event.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $summary,
		public readonly string $description,
		public readonly string $start,
		public readonly string $end,
		public readonly bool $all_day
	) {}

	/**
	 * Start as date object in the event's own time zone, null when unparsable.
	 */
	public function start_time(): ?DateTimeImmutable {
		return self::parse( $this->start );
	}

	/**
	 * End as date object in the event's own time zone, null when unparsable.
	 */
	public function end_time(): ?DateTimeImmutable {
		return self::parse( $this->end );
	}

	/**
	 * Month of the start as "Y-m", used to group events. Null when the start is unparsable.
	 */
	public function month_key(): ?string {
		return $this->start_time()?->format( 'Y-m' );
	}

	/**
	 * Short day label for lists, e.g. "SA, 21.11.".
	 */
	public function day_label(): string {
		$start = $this->start_time();
		if ( null === $start ) {
			return $this->start;
		}

		return self::WEEKDAYS_SHORT[ (int) $start->format( 'w' ) ] . ', ' . $start->format( 'd.m.' );
	}

	/**
	 * Calendar days between today and the start day in the event's own time zone: 0 = today, negative = past.
	 * Null when the start cannot be parsed.
	 *
	 * @param int $now Current Unix timestamp.
	 */
	public function days_from( int $now ): ?int {
		$start = $this->start_time();
		if ( null === $start ) {
			return null;
		}

		$today = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $start->getTimezone() )->setTime( 0, 0 );

		return (int) $today->diff( $start->setTime( 0, 0 ) )->format( '%r%a' );
	}

	/**
	 * Long date label for headlines, e.g. "Samstag, 21.11.2026".
	 */
	public function date_label(): string {
		$start = $this->start_time();
		if ( null === $start ) {
			return $this->start;
		}

		return self::WEEKDAYS_LONG[ (int) $start->format( 'w' ) ] . ', ' . $start->format( 'd.m.Y' );
	}

	/**
	 * Time label, e.g. "09:30 – 11:00 Uhr".
	 *
	 * @param string $separator Placed between start and end time.
	 */
	public function time_label( string $separator = ' – ' ): string {
		$start = $this->start_time();
		if ( $this->all_day || null === $start ) {
			return self::ALL_DAY_LABEL;
		}

		$end   = $this->end_time();
		$label = $start->format( 'H:i' );
		if ( null !== $end && $end > $start ) {
			$label .= $separator . $end->format( 'H:i' );
		}

		return $label . ' Uhr';
	}

	/**
	 * Day and time in one line, e.g. "SA, 06.12. um 21:30 - 22:45 Uhr". All-day events: "SA, 06.12., ganztägig".
	 */
	public function when_label(): string {
		if ( $this->all_day || null === $this->start_time() ) {
			return $this->day_label() . ', ' . $this->time_label();
		}

		return $this->day_label() . ' um ' . $this->time_label( ' - ' );
	}

	/**
	 * Date and time for booking mails, e.g. "Sa, 17.01.2026 von 17:00 - 19:45 Uhr". All-day events: "Sa, 17.01.2026, ganztägig".
	 */
	public function slot_label(): string {
		$start = $this->start_time();
		if ( null === $start ) {
			return $this->start;
		}

		$day = ucfirst( strtolower( self::WEEKDAYS_SHORT[ (int) $start->format( 'w' ) ] ) ) . ', ' . $start->format( 'd.m.Y' );
		if ( $this->all_day ) {
			return $day . ', ' . self::ALL_DAY_LABEL;
		}

		$end = $this->end_time();
		if ( null === $end || $end <= $start ) {
			return $day . ' um ' . $start->format( 'H:i' ) . ' Uhr';
		}

		return $day . ' von ' . $start->format( 'H:i' ) . ' - ' . $end->format( 'H:i' ) . ' Uhr';
	}

	/**
	 * Builds an event from a Google API item. Returns null for unusable items (cancelled, no start).
	 *
	 * @param array<mixed> $item Decoded "events" item (untrusted Google data).
	 */
	public static function from_api( array $item ): ?self {
		if ( 'cancelled' === ( $item['status'] ?? null ) ) {
			return null;
		}

		$start = self::time( $item['start'] ?? null );
		if ( null === $start ) {
			return null;
		}
		$end = self::time( $item['end'] ?? null );

		return new self(
			is_string( $item['id'] ?? null ) ? $item['id'] : '',
			is_string( $item['summary'] ?? null ) ? $item['summary'] : '',
			is_string( $item['description'] ?? null ) ? $item['description'] : '',
			$start[0],
			$end[0] ?? $start[0],
			$start[1]
		);
	}

	/**
	 * Parses an RFC 3339 date-time or Y-m-d string.
	 *
	 * @param string $value Raw time string.
	 */
	private static function parse( string $value ): ?DateTimeImmutable {
		try {
			return new DateTimeImmutable( $value );
		} catch ( Exception ) {
			return null;
		}
	}

	/**
	 * Reads a Google "start"/"end" object.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return array{0: string, 1: bool}|null Time string and all-day flag.
	 */
	private static function time( mixed $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		if ( isset( $value['dateTime'] ) && is_string( $value['dateTime'] ) ) {
			return array( $value['dateTime'], false );
		}
		if ( isset( $value['date'] ) && is_string( $value['date'] ) ) {
			return array( $value['date'], true );
		}

		return null;
	}
}
