<?php
/**
 * Reads the "-- Eintrag für Kalender --" block of a booking notification.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Turns the pasted "Key: value" lines back into booking data, so test mails can be prefilled from a real booking.
 */
final class Calendar_Block {

	public const KEY_NAME         = 'name';
	public const KEY_EMAIL        = 'email';
	public const KEY_PHONE        = 'phone';
	public const KEY_PARTICIPANTS = 'participants';
	public const KEY_RENTAL       = 'rental';
	public const KEY_START        = 'start';
	public const KEY_END          = 'end';

	public const DATETIME_FORMAT = '%04d-%02d-%02dT%02d:%02d';

	private const LABELS = array(
		'name'         => self::KEY_NAME,
		'e-mail'       => self::KEY_EMAIL,
		'telefon'      => self::KEY_PHONE,
		'personen'     => self::KEY_PARTICIPANTS,
		'ausrüstungen' => self::KEY_RENTAL,
	);

	/**
	 * Parses the block. Unknown or missing lines are left out of the result.
	 *
	 * @param string $text Pasted text.
	 *
	 * @return array<string,string> Values by KEY_* constant. Start and end are "Y-m-d\TH:i" as in datetime-local inputs.
	 */
	public static function parse( string $text ): array {
		$result = array();

		foreach ( (array) preg_split( '/\R/', $text ) as $line ) {
			$line = (string) $line;
			$pos  = strpos( $line, ':' );
			if ( false === $pos ) {
				continue;
			}

			$label = mb_strtolower( trim( substr( $line, 0, $pos ) ) );
			$value = trim( substr( $line, $pos + 1 ) );
			if ( '' === $value ) {
				continue;
			}

			if ( 'datum' === $label ) {
				$result = array_merge( $result, self::parse_slot( $value ) );
			} elseif ( isset( self::LABELS[ $label ] ) ) {
				$result[ self::LABELS[ $label ] ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Date and times from e.g. "Sa, 21.11.2026 von 09:30 - 11:00 Uhr". Without times the day starts at 00:00.
	 *
	 * @param string $slot Date value of the block.
	 *
	 * @return array<string,string>
	 */
	private static function parse_slot( string $slot ): array {
		if ( 1 !== preg_match( '/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $slot, $date ) ) {
			return array();
		}

		$times = array();
		preg_match_all( '/\b(\d{1,2}):(\d{2})\b/', $slot, $times, PREG_SET_ORDER );

		$result = array(
			self::KEY_START => sprintf( self::DATETIME_FORMAT, (int) $date[3], (int) $date[2], (int) $date[1], (int) ( $times[0][1] ?? 0 ), (int) ( $times[0][2] ?? 0 ) ),
		);
		if ( isset( $times[1] ) ) {
			$result[ self::KEY_END ] = sprintf( self::DATETIME_FORMAT, (int) $date[3], (int) $date[2], (int) $date[1], (int) $times[1][1], (int) $times[1][2] );
		}

		return $result;
	}
}
