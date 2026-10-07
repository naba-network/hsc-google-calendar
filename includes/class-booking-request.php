<?php
/**
 * A booking request submitted through the website form.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Immutable, validated copy of the submitted form fields. Validation returns error codes,
 * the caller turns them into translated messages.
 */
final class Booking_Request {

	public const FIELD_NAME         = 'name';
	public const FIELD_EMAIL        = 'email';
	public const FIELD_PHONE        = 'phone';
	public const FIELD_PARTICIPANTS = 'participants';
	public const FIELD_RENTAL       = 'rental';
	public const FIELD_MESSAGE      = 'message';
	public const FIELD_CAPTCHA      = 'captcha';
	public const FIELD_HONEYPOT     = 'website';
	public const FIELD_EVENT        = 'event';
	public const FIELD_TOKEN        = 'token';

	public const ERROR_NAME_REQUIRED  = 'name_required';
	public const ERROR_EMAIL_REQUIRED = 'email_required';
	public const ERROR_EMAIL_INVALID  = 'email_invalid';
	public const ERROR_PHONE_INVALID  = 'phone_invalid';
	public const ERROR_PARTICIPANTS   = 'participants_range';
	public const ERROR_RENTAL         = 'rental_range';
	public const ERROR_RENTAL_EXCEEDS = 'rental_exceeds';
	public const ERROR_CAPTCHA        = 'captcha_wrong';

	public const MAX_PARTICIPANTS = 30;
	public const MAX_NAME         = 100;
	public const MAX_MESSAGE      = 2000;
	private const MAX_EMAIL       = 254;
	private const PHONE_PATTERN   = '/^\+?[0-9][0-9 ()\/-]{5,29}$/';

	/**
	 * Creates the request. Use from_array() for untrusted input.
	 *
	 * @param string $event_id     Calendar event ID.
	 * @param string $name         Name.
	 * @param string $email        E-mail address.
	 * @param string $phone        Phone number.
	 * @param int    $participants Number of participants.
	 * @param int    $rental       Number of rental gear sets.
	 * @param string $message      Free text, may be empty.
	 * @param string $honeypot     Hidden field, must stay empty for humans.
	 */
	public function __construct(
		public readonly string $event_id,
		public readonly string $name,
		public readonly string $email,
		public readonly string $phone,
		public readonly int $participants,
		public readonly int $rental,
		public readonly string $message,
		public readonly string $honeypot
	) {}

	/**
	 * Builds a request from decoded request data. Unknown keys are ignored, wrong types become empty.
	 *
	 * @param array<array-key, scalar|array<array-key, mixed>|null> $data Untrusted input.
	 */
	public static function from_array( array $data ): self {
		return new self(
			self::text( $data, self::FIELD_EVENT ),
			self::text( $data, self::FIELD_NAME ),
			self::text( $data, self::FIELD_EMAIL ),
			self::text( $data, self::FIELD_PHONE ),
			self::whole_number( $data, self::FIELD_PARTICIPANTS ),
			self::whole_number( $data, self::FIELD_RENTAL ),
			self::text( $data, self::FIELD_MESSAGE ),
			self::text( $data, self::FIELD_HONEYPOT )
		);
	}

	/**
	 * Bots fill the hidden field.
	 */
	public function is_spam(): bool {
		return '' !== $this->honeypot;
	}

	/**
	 * Validation errors by field, empty when the request is valid.
	 *
	 * @return array<string, string> Field name => error code.
	 */
	public function errors(): array {
		$errors = array();

		if ( '' === $this->name || mb_strlen( $this->name ) > self::MAX_NAME ) {
			$errors[ self::FIELD_NAME ] = self::ERROR_NAME_REQUIRED;
		}

		if ( '' === $this->email ) {
			$errors[ self::FIELD_EMAIL ] = self::ERROR_EMAIL_REQUIRED;
		} elseif ( strlen( $this->email ) > self::MAX_EMAIL || false === filter_var( $this->email, FILTER_VALIDATE_EMAIL ) ) {
			$errors[ self::FIELD_EMAIL ] = self::ERROR_EMAIL_INVALID;
		}

		if ( 1 !== preg_match( self::PHONE_PATTERN, $this->phone ) ) {
			$errors[ self::FIELD_PHONE ] = self::ERROR_PHONE_INVALID;
		}

		if ( $this->participants < 1 || $this->participants > self::MAX_PARTICIPANTS ) {
			$errors[ self::FIELD_PARTICIPANTS ] = self::ERROR_PARTICIPANTS;
		}

		if ( $this->rental < 0 ) {
			$errors[ self::FIELD_RENTAL ] = self::ERROR_RENTAL;
		} elseif ( ! isset( $errors[ self::FIELD_PARTICIPANTS ] ) && $this->rental > $this->participants ) {
			// Only compared when the participant count itself is valid, otherwise one mistake shows two errors.
			$errors[ self::FIELD_RENTAL ] = self::ERROR_RENTAL_EXCEEDS;
		}

		return $errors;
	}

	/**
	 * Trimmed string value, '' for missing or non-scalar input. Line breaks are only kept in the message.
	 *
	 * @param array<array-key, mixed> $data Input.
	 * @param string                  $key  Field.
	 */
	private static function text( array $data, string $key ): string {
		$value = $data[ $key ] ?? '';
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( str_replace( "\0", '', $value ) );
		if ( self::FIELD_MESSAGE === $key ) {
			return mb_substr( $value, 0, self::MAX_MESSAGE );
		}

		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Whole number from a number or numeric string, -1 when missing or not a whole number.
	 *
	 * @param array<array-key, mixed> $data Input.
	 * @param string                  $key  Field.
	 */
	private static function whole_number( array $data, string $key ): int {
		$value = $data[ $key ] ?? null;
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}

		$number = filter_var( $value, FILTER_VALIDATE_INT );

		return false === $number ? -1 : $number;
	}
}
