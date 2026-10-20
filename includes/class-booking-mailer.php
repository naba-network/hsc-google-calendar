<?php
/**
 * E-mails for a booking request.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

use Closure;

/**
 * Sends the notification to the club and the confirmation to the person who booked.
 * The notification starts with a "-- Eintrag für Kalender --" block of "Key: value" lines, ready to paste into the calendar event description.
 */
final class Booking_Mailer {

	public const LABEL_NOTIFICATION = 'Notification to the club';
	public const LABEL_CONFIRMATION = 'Confirmation to the visitor';

	public const SCOPE_BOTH     = 'both';
	public const SCOPE_CLUB     = 'club';
	public const SCOPE_CUSTOMER = 'customer';

	private const LINE_BREAK = "\n";

	public const DRAFT_CONFIRMATION = 'verleih-bestaetigung.eml';
	public const DRAFT_DECLINE      = 'verleih-absage.eml';

	/**
	 * Creates the mailer.
	 *
	 * @param Closure              $send       Transport: fn( Mail_Message $mail ): ?string, returns null when the mail was accepted, else the error.
	 * @param string               $from_email Sender address.
	 * @param string               $from_name  Sender name.
	 * @param string               $notify_to  Address that receives the notification.
	 * @param array<string,string> $draft_templates Paths of the .emltpl templates by attachment file name, empty for no attachments.
	 * @param string               $cc        Copy recipient of the confirmation draft and the mailto link, empty for none.
	 * @param array<string,string> $phones Phone numbers for the draft, by placeholder name. A "<name>_link" placeholder holds the dialable form.
	 */
	public function __construct(
		private readonly Closure $send,
		private readonly string $from_email,
		private readonly string $from_name,
		private readonly string $notify_to,
		private readonly array $draft_templates = array(),
		private readonly string $cc = '',
		private readonly array $phones = array()
	) {}

	/**
	 * Mailer on top of wp_mail(). The envelope sender is set to the From address so SPF can align with it.
	 *
	 * @param string               $from_email Sender address.
	 * @param string               $from_name  Sender name.
	 * @param string               $notify_to  Address that receives the notification.
	 * @param string               $cc        Copy recipient of the confirmation draft and the mailto link, empty for none.
	 * @param array<string,string> $phones Phone numbers for the draft, by placeholder name (e.g. telefon_eismeister).
	 */
	public static function with_wordpress_mail( string $from_email, string $from_name, string $notify_to, string $cc = '', array $phones = array() ): self {
		$send = static function ( Mail_Message $mail ) use ( $from_email ): ?string {
			$error = '';
			// Adds the plain text alternative and the attachments. The envelope sender is only set for PHP mail()/sendmail: SMTP plugins such as
			// WP Mail SMTP choose it themselves, and an address the SMTP account does not own would be rejected.
			$set_sender = static function ( $phpmailer ) use ( $from_email, $mail ): void {
				if ( '' !== $mail->alt_body ) {
					$phpmailer->AltBody = $mail->alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				}
				foreach ( $mail->attachments as $file_name => $content ) {
					// Not message/rfc822: RFC 2046 forbids base64 for it, and Exchange/Outlook/OWA then parse it as an embedded message and show 0 KB.
					$phpmailer->addStringAttachment( $content, (string) $file_name, 'base64', 'application/octet-stream' );
				}
				if ( in_array( $phpmailer->Mailer, array( 'mail', 'sendmail' ), true ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
					$phpmailer->Sender = $from_email; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				}
			};
			$capture    = static function ( \WP_Error $failure ) use ( &$error ): void {
				$error = $failure->get_error_message();
			};
			add_action( 'phpmailer_init', $set_sender, 99 );
			add_action( 'wp_mail_failed', $capture );
			$sent = wp_mail( $mail->to, $mail->subject, $mail->body, $mail->headers );
			remove_action( 'phpmailer_init', $set_sender, 99 );
			remove_action( 'wp_mail_failed', $capture );

			if ( $sent ) {
				return null;
			}

			return '' !== $error ? $error : 'wp_mail() returned false.';
		};

		return new self(
			$send,
			$from_email,
			$from_name,
			$notify_to,
			array(
				self::DRAFT_CONFIRMATION => dirname( __DIR__ ) . '/templates/verleih-buchung.emltpl',
				self::DRAFT_DECLINE      => dirname( __DIR__ ) . '/templates/verleih-absage.emltpl',
			),
			$cc,
			$phones
		);
	}

	/**
	 * Sends both mails. A failing confirmation does not fail the request, the club mail is what matters.
	 *
	 * @param Booking_Request $request Validated request.
	 * @param Event           $event   Event the visitor wants to book.
	 *
	 * @return bool Whether the club notification was accepted for delivery.
	 */
	public function send( Booking_Request $request, Event $event ): bool {
		$messages = $this->build( $request, $event );
		if ( null !== $this->deliver( $messages['notification'] ) ) {
			return false;
		}

		$this->deliver( $messages['confirmation'] );

		return true;
	}

	/**
	 * Builds both mails without sending them.
	 *
	 * @param Booking_Request $request Validated request.
	 * @param Event           $event   Event the visitor wants to book.
	 *
	 * @return array{notification: Mail_Message, confirmation: Mail_Message}
	 */
	public function build( Booking_Request $request, Event $event ): array {
		$label = $event->date_label() . ', ' . $event->time_label();
		$slot  = $event->slot_label();

		return array(
			'notification' => new Mail_Message(
				$this->notify_to,
				'Verleih Anfrage - ' . self::header_value( $request->name ) . ' - ' . $slot,
				$this->notification_html( $request, $event ),
				$this->headers( self::header_value( $request->name ) . ' <' . $request->email . '>' ),
				$this->notification_body( $request, $event ),
				$this->drafts( $request, $event )
			),
			'confirmation' => new Mail_Message(
				$request->email,
				'Deine Buchungsanfrage: ' . $label,
				$this->confirmation_html( $request, $event ),
				$this->headers( null ),
				$this->confirmation_body( $request, $event )
			),
		);
	}

	/**
	 * Sends the selected mails to one test address only, whatever the visitor address is. The visitor address only appears
	 * inside the content (Reply-To, draft, mailto link). When the sample has none, the test address stands in for it, so
	 * Reply-To and the drafts point to a real mailbox instead of a sample domain that mail servers may reject.
	 *
	 * @param Booking_Request $sample  Sample request, its e-mail address may be empty.
	 * @param Event           $event   Sample event.
	 * @param string          $to      Test recipient.
	 * @param string          $scope   Which mails to send: SCOPE_BOTH, SCOPE_CLUB or SCOPE_CUSTOMER. Anything else sends both.
	 *
	 * @return list<array{label: string, mail: Mail_Message, error: string|null}> One entry per mail, in sending order.
	 */
	public function send_test( Booking_Request $sample, Event $event, string $to, string $scope = self::SCOPE_BOTH ): array {
		$request  = new Booking_Request( $sample->event_id, $sample->name, '' !== $sample->email ? $sample->email : $to, $sample->phone, $sample->participants, $sample->rental, $sample->message, '' );
		$messages = $this->build( $request, $event );
		$results  = array();

		$selected = array(
			self::LABEL_NOTIFICATION => $messages['notification'],
			self::LABEL_CONFIRMATION => $messages['confirmation'],
		);
		if ( self::SCOPE_CLUB === $scope ) {
			unset( $selected[ self::LABEL_CONFIRMATION ] );
		} elseif ( self::SCOPE_CUSTOMER === $scope ) {
			unset( $selected[ self::LABEL_NOTIFICATION ] );
		}

		foreach ( $selected as $label => $message ) {
			$mail      = $message->redirected( $to, '[TEST] ' );
			$results[] = array(
				'label' => $label,
				'mail'  => $mail,
				'error' => $this->deliver( $mail ),
			);
		}

		return $results;
	}

	/**
	 * Hands one mail to the transport.
	 *
	 * @param Mail_Message $mail Message.
	 *
	 * @return string|null Null when the mail was accepted for delivery, else the error.
	 */
	public function deliver( Mail_Message $mail ): ?string {
		return ( $this->send )( $mail );
	}

	/**
	 * The .eml drafts for the club (confirmation and decline) by file name. Templates that are missing or unreadable are left out.
	 *
	 * @param Booking_Request $request Request.
	 * @param Event           $event   Event.
	 *
	 * @return array<string,string>
	 */
	private function drafts( Booking_Request $request, Event $event ): array {
		$drafts = array();
		foreach ( $this->draft_templates as $file_name => $path ) {
			$draft = $this->draft( $path, $request, $event );
			if ( '' !== $draft ) {
				$drafts[ $file_name ] = $draft;
			}
		}

		return $drafts;
	}

	/**
	 * An unsent .eml draft for the visitor, filled from a template. The club opens it in its own
	 * mail client and sends it from there. Empty when the template cannot be read.
	 *
	 * Placeholders like {{name}} are replaced in the headers (no line breaks, no angle brackets) and escaped in the HTML body.
	 *
	 * @param string          $path    Path of the .emltpl template.
	 * @param Booking_Request $request Request.
	 * @param Event           $event   Event.
	 */
	private function draft( string $path, Booking_Request $request, Event $event ): string {
		$template = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local template file.
		if ( false === $template ) {
			return '';
		}

		$values = array();
		foreach ( $this->phones as $key => $phone ) {
			$values[ $key ]           = $phone;
			$values[ $key . '_link' ] = (string) preg_replace( '/[^\d+]/', '', $phone );
		}
		$values += array(
			'name'            => $request->name,
			'email'           => $request->email,
			'telefon'         => $request->phone,
			'datum'           => $event->date_label(),
			'termin'          => self::subject_slot( $event ),
			'uhrzeit'         => $event->time_label(),
			'teilnehmer'      => (string) $request->participants,
			'leihausruestung' => (string) $request->rental,
			'club_email'      => $this->notify_to,
			'absender_email'  => $this->from_email,
			'cc'              => $this->cc,
		);

		$parts   = preg_split( '/\R\R/', $template, 2 );
		$parts   = false === $parts ? array( $template ) : $parts;
		$head    = self::fill( $parts[0], array_map( array( self::class, 'single_line' ), $values ) );
		$body    = self::fill( $parts[1] ?? '', array_map( array( Mail_Layout::class, 'escape' ), $values ) );
		$headers = array();
		foreach ( (array) preg_split( '/\R/', $head ) as $line ) {
			if ( 'Cc: ' === $line ) {
				continue;
			}
			$headers[] = str_starts_with( $line, 'Subject: ' ) ? 'Subject: ' . self::encode_header( substr( $line, 9 ) ) : $line;
		}

		return str_replace( "\n", "\r\n", str_replace( "\r\n", "\n", implode( "\n", $headers ) . "\n\n" . $body ) );
	}

	/**
	 * Phone number as tel: link (dialable digits only), plain escaped text when it has no digits.
	 *
	 * @param string $phone Phone number as typed by the visitor.
	 */
	private static function phone_link( string $phone ): string {
		$dial = (string) preg_replace( '/[^\d+]/', '', $phone );
		if ( '' === $dial ) {
			return Mail_Layout::escape( $phone );
		}

		return '<a href="tel:' . Mail_Layout::escape( $dial ) . '" style="color:#000000;text-decoration:underline;">' . Mail_Layout::escape( $phone ) . '</a>';
	}

	/**
	 * Date and time for subjects, e.g. "SA, 17.10.2026 von 10:00 – 11:30 Uhr".
	 *
	 * @param Event $event Event.
	 */
	private static function subject_slot( Event $event ): string {
		$slot = (string) preg_replace_callback(
			'/^[A-Z][a-z],/',
			static fn ( array $found ): string => strtoupper( $found[0] ),
			$event->slot_label()
		);

		return str_replace( ' - ', ' – ', $slot );
	}

	/**
	 * Link with the mailto scheme that opens a new mail to the visitor with subject and a short plain text confirmation.
	 * Works in every mail client, unlike the .eml draft, but cannot carry the HTML layout.
	 *
	 * @param Booking_Request $request Request.
	 * @param Event           $event   Event.
	 */
	private function confirmation_mailto( Booking_Request $request, Event $event ): string {
		$subject = 'Verleih Bestätigung - ' . self::single_line( $request->name ) . ' - ' . self::subject_slot( $event );
		$body    = str_replace( self::LINE_BREAK, "\r\n", $this->confirmation_body( $request, $event ) );

		$cc = '' !== self::single_line( $this->cc ) ? '&cc=' . rawurlencode( self::single_line( $this->cc ) ) : '';

		return 'mailto:' . rawurlencode( $request->email ) . '?subject=' . rawurlencode( $subject ) . $cc . '&body=' . rawurlencode( $body );
	}

	/**
	 * Replaces {{key}} placeholders.
	 *
	 * @param string               $text   Template text.
	 * @param array<string,string> $values Replacement per key.
	 */
	private static function fill( string $text, array $values ): string {
		$map = array();
		foreach ( $values as $key => $value ) {
			$map[ '{{' . $key . '}}' ] = $value;
		}

		return strtr( $text, $map );
	}

	/**
	 * Encodes a header value as RFC 2047 when it has non-ASCII characters (umlauts in the subject).
	 *
	 * @param string $value Header value without name.
	 */
	private static function encode_header( string $value ): string {
		if ( 1 !== preg_match( '/[^\x20-\x7E]/', $value ) ) {
			return $value;
		}

		// RFC 2047 header encoding, not obfuscation.
		return '=?UTF-8?B?' . base64_encode( $value ) . '?='; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Plain text body for the club.
	 *
	 * @param Booking_Request $request Request.
	 * @param Event           $event   Event.
	 */
	private function notification_body( Booking_Request $request, Event $event ): string {
		$slot    = $event->slot_label();
		$contact = $request->name . ' - ' . $request->phone;

		return implode(
			self::LINE_BREAK,
			array(
				'Verleih Anfrage',
				'',
				'-- Eintrag für Kalender --',
				...$this->calendar_lines( $request, $slot ),
				'',
				'-- E-Mail: --',
				'Datum: ',
				$slot,
				'Anzahl: ',
				$request->participants . ' Personen / ' . $request->rental . ' Ausrüstungen',
				'Ansprechperson: ',
				$contact,
				'',
				'Nachricht:',
				$request->message,
			)
		);
	}

	/**
	 * HTML body for the club. The calendar block is one div per line, so pasting it keeps the line breaks.
	 *
	 * @param Booking_Request $request Request.
	 * @param Event           $event   Event.
	 */
	private function notification_html( Booking_Request $request, Event $event ): string {
		$slot  = $event->slot_label();
		$lines = '';
		foreach ( $this->calendar_lines( $request, $slot ) as $line ) {
			$lines .= '<div>' . ( 'Telefon: ' . $request->phone === $line ? 'Telefon: ' . self::phone_link( $request->phone ) : Mail_Layout::escape( $line ) ) . '</div>';
		}

		$details = array(
			array( 'Datum', Mail_Layout::escape( $slot ) ),
			array( 'Anzahl', Mail_Layout::escape( $request->participants . ' Personen / ' . $request->rental . ' Ausrüstungen' ) ),
			array( 'Ansprechperson', Mail_Layout::escape( $request->name ) . ' - ' . self::phone_link( $request->phone ) ),
		);
		$rows    = '';
		foreach ( $details as list( $label, $value ) ) {
			$rows .= '<tr><td style="padding:8px 12px 8px 0;vertical-align:top;white-space:nowrap;font-weight:600;color:#6B7280;">' . Mail_Layout::escape( $label ) . '</td>'
				. '<td style="padding:8px 0;vertical-align:top;">' . $value . '</td></tr>';
		}

		$content = '<h1 style="margin:0 0 16px;font-size:24px;line-height:28px;font-weight:700;color:#181818;">Verleih Anfrage</h1>'
			. Mail_Layout::paragraph( '<a href="' . Mail_Layout::escape( $this->confirmation_mailto( $request, $event ) ) . '" style="color:#000000;text-decoration:underline;">Bestätigung in neuer E-Mail öffnen</a>' )
			. '<div style="margin:0 0 24px;padding:16px;background-color:#F4F4F4;border-radius:8px;">'
			. '<div style="font-weight:600;">-- Eintrag für Kalender --</div>' . $lines . '</div>'
			. '<div style="margin:0 0 8px;font-weight:600;">-- E-Mail: --</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 24px;">' . $rows . '</table>'
			. '<div style="margin:0 0 8px;font-weight:600;">Nachricht:</div>'
			. Mail_Layout::paragraph( nl2br( Mail_Layout::escape( $request->message ), false ) );

		return Mail_Layout::wrap( 'Verleih Anfrage', $content );
	}

	/**
	 * HTML body for the visitor.
	 *
	 * @param Booking_Request $request Request.
	 * @param Event           $event   Event.
	 */
	private function confirmation_html( Booking_Request $request, Event $event ): string {
		$content = Mail_Layout::paragraph( 'Hallo ' . Mail_Layout::escape( $request->name ) . ',' )
			. Mail_Layout::paragraph( 'danke für deine Buchungsanfrage. Wir haben sie erhalten und melden uns bei dir.' )
			. Mail_Layout::paragraph(
				'Termin: ' . Mail_Layout::escape( $event->date_label() . ', ' . $event->time_label() ) . '<br>'
				. 'Teilnehmer: ' . $request->participants . '<br>'
				. 'Leihausrüstung: ' . $request->rental
			)
			. Mail_Layout::paragraph( Mail_Layout::escape( $this->from_name ) );

		return Mail_Layout::wrap( 'Deine Buchungsanfrage', $content );
	}

	/**
	 * The "Key: value" lines of the calendar block.
	 *
	 * @param Booking_Request $request Request.
	 * @param string          $slot    Date and time label.
	 *
	 * @return list<string>
	 */
	private function calendar_lines( Booking_Request $request, string $slot ): array {
		return array(
			'Name: ' . $request->name,
			'E-Mail: ' . $request->email,
			'Telefon: ' . $request->phone,
			'Datum: ' . $slot,
			'Personen: ' . $request->participants,
			'Ausrüstungen: ' . $request->rental,
		);
	}

	/**
	 * Plain text body for the visitor.
	 *
	 * @param Booking_Request $request Request.
	 * @param Event           $event   Event.
	 */
	private function confirmation_body( Booking_Request $request, Event $event ): string {
		return implode(
			self::LINE_BREAK,
			array(
				'Hallo ' . $request->name . ',',
				'',
				'danke für deine Buchungsanfrage. Wir haben sie erhalten und melden uns bei dir.',
				'',
				'Termin: ' . $event->date_label() . ', ' . $event->time_label(),
				'Teilnehmer: ' . $request->participants,
				'Leihausrüstung: ' . $request->rental,
				'',
				$this->from_name,
			)
		);
	}

	/**
	 * Mail headers.
	 *
	 * @param string|null $reply_to Reply-To header value, null for none.
	 *
	 * @return list<string>
	 */
	private function headers( ?string $reply_to ): array {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . self::header_value( $this->from_name ) . ' <' . $this->from_email . '>',
		);
		if ( null !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		return $headers;
	}

	/**
	 * Makes a user supplied string safe inside one header line: no line breaks (header injection), no angle brackets or quotes.
	 *
	 * @param string $value Raw value.
	 */
	private static function single_line( string $value ): string {
		return trim( (string) preg_replace( '/[\r\n<>"]+/', ' ', $value ) );
	}

	/**
	 * Makes a user supplied string safe inside a header: no line breaks (header injection), no angle brackets or quotes.
	 *
	 * @param string $value Raw value.
	 */
	private static function header_value( string $value ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( '/[\r\n<>",;]+/', ' ', $value ) ) );
	}
}
