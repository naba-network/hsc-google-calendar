<?php
/**
 * A booked appointment, read from a calendar event.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * The club books manually by renaming the event and writing the details as "Key: value" lines
 * into its description. Nothing is stored in WordPress; this class only reads those lines.
 */
final class Booking {

	private const FIELD_NAME         = 'name';
	private const FIELD_PARTICIPANTS = 'participants';
	private const FIELD_RENTAL       = 'rental';
	private const FIELD_EMAIL        = 'email';
	private const FIELD_PHONE        = 'phone';
	private const FIELD_NOTE         = 'note';

	/**
	 * Accepted keys (lower case) per field.
	 */
	private const KEYS = array(
		'name'                    => self::FIELD_NAME,
		'teilnehmer'              => self::FIELD_PARTICIPANTS,
		'teilnehmerzahl'          => self::FIELD_PARTICIPANTS,
		'personen'                => self::FIELD_PARTICIPANTS,
		'leihausrüstung'          => self::FIELD_RENTAL,
		'leihausruestung'         => self::FIELD_RENTAL,
		'leihgeräte'              => self::FIELD_RENTAL,
		'leihgeraete'             => self::FIELD_RENTAL,
		'ausrüstungen'            => self::FIELD_RENTAL,
		'ausruestungen'           => self::FIELD_RENTAL,
		'ausrüstung'              => self::FIELD_RENTAL,
		'ausruestung'             => self::FIELD_RENTAL,
		'e-mail'                  => self::FIELD_EMAIL,
		'email'                   => self::FIELD_EMAIL,
		'telefon'                 => self::FIELD_PHONE,
		'tel'                     => self::FIELD_PHONE,
		'anmerkung'               => self::FIELD_NOTE,
		'anmerkungen'             => self::FIELD_NOTE,
		'fragen oder anmerkungen' => self::FIELD_NOTE,
	);

	/**
	 * Creates a booking.
	 *
	 * @param Event  $event        Calendar event.
	 * @param string $name         Name of the person who booked.
	 * @param int    $participants Number of participants.
	 * @param int    $rental       Number of rental gear sets.
	 * @param string $email        Contact e-mail.
	 * @param string $phone        Contact phone.
	 * @param string $note         Free text.
	 */
	public function __construct(
		public readonly Event $event,
		public readonly string $name,
		public readonly int $participants,
		public readonly int $rental,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $note
	) {}

	/**
	 * Reads the booking details from the event description. Missing lines stay empty / zero.
	 *
	 * @param Event $event Booked event.
	 */
	public static function from_event( Event $event ): self {
		$values = array(
			self::FIELD_NAME         => '',
			self::FIELD_PARTICIPANTS => '',
			self::FIELD_RENTAL       => '',
			self::FIELD_EMAIL        => '',
			self::FIELD_PHONE        => '',
			self::FIELD_NOTE         => '',
		);

		foreach ( self::lines( $event->description ) as $line ) {
			if ( 1 !== preg_match( '/^\s*([^:]{1,40}?)\s*:\s*(.*?)\s*$/u', $line, $match ) ) {
				continue;
			}
			$field = self::KEYS[ mb_strtolower( $match[1] ) ] ?? null;
			if ( null !== $field ) {
				$values[ $field ] = $match[2];
			}
		}

		return new self(
			$event,
			'' !== $values[ self::FIELD_NAME ] ? $values[ self::FIELD_NAME ] : $event->summary,
			self::number( $values[ self::FIELD_PARTICIPANTS ] ),
			self::number( $values[ self::FIELD_RENTAL ] ),
			$values[ self::FIELD_EMAIL ],
			$values[ self::FIELD_PHONE ],
			$values[ self::FIELD_NOTE ]
		);
	}

	/**
	 * Splits a description into plain text lines. Google may store simple HTML (<br>, <b>) there.
	 *
	 * @param string $description Raw description.
	 *
	 * @return list<string>
	 */
	private static function lines( string $description ): array {
		$text  = preg_replace( '~<\s*(br|/p|/div|/li)\s*/?>~i', "\n", $description ) ?? $description;
		$text  = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure class, WordPress is not loaded in unit tests.
		$lines = preg_split( '/\R/u', $text );

		return false === $lines ? array() : $lines;
	}

	/**
	 * First whole number in a string, 0 when there is none.
	 *
	 * @param string $value Raw value.
	 */
	private static function number( string $value ): int {
		return 1 === preg_match( '/\d+/', $value, $match ) ? (int) $match[0] : 0;
	}
}
