<?php
/**
 * A calendar event.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Immutable subset of a Google Calendar event.
 */
final class Event {

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
