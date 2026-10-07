<?php
/**
 * Slots and bookings grouped by month with totals.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Groups bookings by the month of their start and sums slots, participants and rental gear.
 * A slot is a free appointment or a booking, so slots = still free + booked.
 */
final class Booking_Summary {

	/**
	 * Creates the summary.
	 *
	 * @param Booking[] $bookings   Bookings, any order.
	 * @param Event[]   $free_slots Appointments that are still free (past ones included), any order.
	 */
	public function __construct(
		private readonly array $bookings,
		private readonly array $free_slots = array()
	) {}

	/**
	 * Bookings per month ("Y-m"), months and bookings sorted by start. Bookings without a valid start are skipped.
	 *
	 * @return array<string, list<Booking>>
	 */
	public function by_month(): array {
		$months = array();
		foreach ( $this->sorted() as $booking ) {
			$key = $booking->event->month_key();
			if ( null !== $key ) {
				$months[ $key ][] = $booking;
			}
		}
		ksort( $months );

		return $months;
	}

	/**
	 * The booking to highlight: the first one that starts today or later. Null when all are in the past.
	 *
	 * @param int $now Current Unix timestamp.
	 */
	public function next( int $now ): ?Booking {
		foreach ( $this->sorted() as $booking ) {
			$days = $booking->event->days_from( $now );
			if ( null !== $days && $days >= 0 ) {
				return $booking;
			}
		}

		return null;
	}

	/**
	 * Bookings sorted by start.
	 *
	 * @return list<Booking>
	 */
	private function sorted(): array {
		$bookings = array_values( $this->bookings );
		usort(
			$bookings,
			static fn( Booking $a, Booking $b ): int => ( $a->event->start_time()?->getTimestamp() ?? 0 ) <=> ( $b->event->start_time()?->getTimestamp() ?? 0 )
		);

		return $bookings;
	}

	/**
	 * One row per month that has a slot or a booking, sorted by month.
	 *
	 * @return array<string, array{slots: int, bookings: int, participants: int, rental: int}>
	 */
	public function rows(): array {
		$free = array();
		foreach ( $this->free_slots as $event ) {
			$key = $event->month_key();
			if ( null !== $key ) {
				$free[ $key ] = ( $free[ $key ] ?? 0 ) + 1;
			}
		}

		$booked = $this->by_month();
		$rows   = array();
		foreach ( array_unique( array_merge( array_keys( $free ), array_keys( $booked ) ) ) as $key ) {
			$sums         = self::totals_of( $booked[ $key ] ?? array() );
			$rows[ $key ] = array(
				'slots'        => ( $free[ $key ] ?? 0 ) + $sums['bookings'],
				'bookings'     => $sums['bookings'],
				'participants' => $sums['participants'],
				'rental'       => $sums['rental'],
			);
		}
		ksort( $rows );

		return $rows;
	}

	/**
	 * Sums of a list of bookings.
	 *
	 * @param Booking[] $bookings Bookings to sum up.
	 *
	 * @return array{bookings: int, participants: int, rental: int}
	 */
	public static function totals_of( array $bookings ): array {
		return array(
			'bookings'     => count( $bookings ),
			'participants' => array_sum( array_map( static fn( Booking $b ): int => $b->participants, $bookings ) ),
			'rental'       => array_sum( array_map( static fn( Booking $b ): int => $b->rental, $bookings ) ),
		);
	}

	/**
	 * Sums over all months.
	 *
	 * @return array{slots: int, bookings: int, participants: int, rental: int}
	 */
	public function totals(): array {
		$totals = array(
			'slots'        => 0,
			'bookings'     => 0,
			'participants' => 0,
			'rental'       => 0,
		);
		foreach ( $this->rows() as $row ) {
			foreach ( $row as $field => $value ) {
				$totals[ $field ] += $value;
			}
		}

		return $totals;
	}

	/**
	 * Whether there is nothing to show.
	 */
	public function is_empty(): bool {
		return array() === $this->rows();
	}
}
