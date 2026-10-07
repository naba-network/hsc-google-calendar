<?php
/**
 * Regex filter for events.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

// Exception messages are internal and escaped on output by Admin_Page.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

namespace Hsc\GoogleCalendar;

use InvalidArgumentException;

/**
 * Keeps events whose title or description matches a regular expression (case-insensitive, UTF-8).
 * An empty pattern keeps everything.
 */
final class Event_Filter {

	/**
	 * Creates the filter.
	 *
	 * @param string $regex Full PCRE pattern including delimiters, or '' to match all.
	 */
	private function __construct( private readonly string $regex ) {}

	/**
	 * Builds a filter from a user supplied pattern WITHOUT delimiters, e.g. "^Eiszeit|Training".
	 *
	 * @param string $pattern Pattern body.
	 *
	 * @throws InvalidArgumentException When the pattern is not a valid regular expression.
	 */
	public static function from_pattern( string $pattern ): self {
		$pattern = trim( $pattern );
		if ( '' === $pattern ) {
			return new self( '' );
		}

		$regex = '~' . str_replace( '~', '\~', $pattern ) . '~iu';
		// Invalid patterns raise a warning; we turn it into an exception ourselves.
		if ( false === @preg_match( $regex, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			throw new InvalidArgumentException( 'Invalid regular expression: ' . ( preg_last_error_msg() ) );
		}

		return new self( $regex );
	}

	/**
	 * Whether the event passes the filter.
	 *
	 * @param Event $event Event to test.
	 */
	public function matches( Event $event ): bool {
		if ( '' === $this->regex ) {
			return true;
		}

		return 1 === preg_match( $this->regex, $event->summary ) || 1 === preg_match( $this->regex, $event->description );
	}

	/**
	 * Filters a list of events, keeping order.
	 *
	 * @param Event[] $events Events.
	 *
	 * @return Event[]
	 */
	public function apply( array $events ): array {
		return array_values( array_filter( $events, fn( Event $event ): bool => $this->matches( $event ) ) );
	}
}
