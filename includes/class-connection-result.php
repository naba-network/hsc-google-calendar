<?php
/**
 * Outcome of a Google Calendar connection test.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Immutable result: connected or not, plus a human readable message.
 */
final class Connection_Result {

	/**
	 * Creates a result.
	 *
	 * @param bool   $ok      Whether the calendar could be reached.
	 * @param string $message Success description or error reason.
	 * @param int    $checked Unix timestamp of the check.
	 */
	public function __construct(
		public readonly bool $ok,
		public readonly string $message,
		public readonly int $checked
	) {}

	/**
	 * Builds a successful result.
	 *
	 * @param string $message Description.
	 * @param int    $checked Unix timestamp.
	 */
	public static function success( string $message, int $checked ): self {
		return new self( true, $message, $checked );
	}

	/**
	 * Builds a failed result.
	 *
	 * @param string $message Error reason.
	 * @param int    $checked Unix timestamp.
	 */
	public static function failure( string $message, int $checked ): self {
		return new self( false, $message, $checked );
	}

	/**
	 * Restores a result from its stored option value.
	 *
	 * @param mixed $stored Raw option value. Untrusted shape (comes from the database).
	 */
	public static function from_stored( mixed $stored ): ?self {
		if ( ! is_array( $stored ) ) {
			return null;
		}
		if ( ! isset( $stored['ok'], $stored['message'], $stored['checked'] ) ) {
			return null;
		}

		return new self( (bool) $stored['ok'], (string) $stored['message'], (int) $stored['checked'] );
	}

	/**
	 * Converts the result to its stored option value.
	 *
	 * @return array{ok: bool, message: string, checked: int}
	 */
	public function to_array(): array {
		return array(
			'ok'      => $this->ok,
			'message' => $this->message,
			'checked' => $this->checked,
		);
	}
}
