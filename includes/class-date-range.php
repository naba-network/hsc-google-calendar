<?php
/**
 * Date range the shortcodes look at.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Optional first and last day (inclusive, "Y-m-d") in the site time zone.
 * Without a first day the range starts at the beginning of the current month, without a last day it is open-ended.
 */
final class Date_Range {

	private const FORMAT = 'Y-m-d';

	/**
	 * Creates the range. Use from_dates() for stored settings.
	 *
	 * @param string       $from First day, '' for the start of the current month.
	 * @param string       $to   Last day, '' for no end.
	 * @param DateTimeZone $zone Time zone the days are meant in.
	 */
	public function __construct(
		private readonly string $from,
		private readonly string $to,
		private readonly DateTimeZone $zone
	) {}

	/**
	 * Whether a string is an existing day in "Y-m-d" form.
	 *
	 * @param string $date Date string.
	 */
	public static function is_valid_day( string $date ): bool {
		$parsed = DateTimeImmutable::createFromFormat( '!' . self::FORMAT, $date );

		return false !== $parsed && $parsed->format( self::FORMAT ) === $date;
	}

	/**
	 * Unix timestamp events must not start before.
	 *
	 * @param int $now Current unix timestamp.
	 */
	public function time_min( int $now ): int {
		if ( self::is_valid_day( $this->from ) ) {
			return $this->midnight( $this->from )->getTimestamp();
		}

		return $this->midnight( ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $this->zone )->format( 'Y-m-01' ) )->getTimestamp();
	}

	/**
	 * Unix timestamp events must start before: midnight after the last day. Null when there is no end.
	 */
	public function time_max(): ?int {
		if ( ! self::is_valid_day( $this->to ) ) {
			return null;
		}

		return $this->midnight( $this->to )->modify( '+1 day' )->getTimestamp();
	}

	/**
	 * Stable string for cache keys.
	 */
	public function key(): string {
		return $this->from . '|' . $this->to;
	}

	/**
	 * Midnight of a day in the range's time zone.
	 *
	 * @param string $day Day in "Y-m-d" form.
	 */
	private function midnight( string $day ): DateTimeImmutable {
		return new DateTimeImmutable( $day . ' 00:00:00', $this->zone );
	}
}
