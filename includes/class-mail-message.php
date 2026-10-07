<?php
/**
 * An e-mail ready to be sent.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Immutable e-mail: recipient, subject, body (HTML or plain text), plain text alternative and raw headers.
 */
final class Mail_Message {

	/**
	 * Creates a message.
	 *
	 * @param string   $to      Recipient address.
	 * @param string   $subject Subject.
	 * @param string   $body    Body, HTML when the Content-Type header says so.
	 * @param string[] $headers Raw header lines ("Name: value").
	 * @param string   $alt_body Plain text alternative for HTML bodies, empty for none.
	 */
	public function __construct(
		public readonly string $to,
		public readonly string $subject,
		public readonly string $body,
		public readonly array $headers,
		public readonly string $alt_body = ''
	) {}

	/**
	 * Copy with another recipient and a prefix in front of the subject, e.g. for test sends.
	 *
	 * @param string $to             New recipient.
	 * @param string $subject_prefix Text put in front of the subject.
	 */
	public function redirected( string $to, string $subject_prefix ): self {
		return new self( $to, $subject_prefix . $this->subject, $this->body, $this->headers, $this->alt_body );
	}
}
