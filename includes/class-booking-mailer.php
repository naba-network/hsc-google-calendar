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

	private const LINE_BREAK = "\n";

	/**
	 * Creates the mailer.
	 *
	 * @param Closure $send       Transport: fn( Mail_Message $mail ): ?string, returns null when the mail was accepted, else the error.
	 * @param string  $from_email Sender address.
	 * @param string  $from_name  Sender name.
	 * @param string  $notify_to  Address that receives the notification.
	 */
	public function __construct(
		private readonly Closure $send,
		private readonly string $from_email,
		private readonly string $from_name,
		private readonly string $notify_to
	) {}

	/**
	 * Mailer on top of wp_mail(). The envelope sender is set to the From address so SPF can align with it.
	 *
	 * @param string $from_email Sender address.
	 * @param string $from_name  Sender name.
	 * @param string $notify_to  Address that receives the notification.
	 */
	public static function with_wordpress_mail( string $from_email, string $from_name, string $notify_to ): self {
		$send = static function ( Mail_Message $mail ) use ( $from_email ): ?string {
			$error = '';
			// Adds the plain text alternative. The envelope sender is only set for PHP mail()/sendmail: SMTP plugins such as
			// WP Mail SMTP choose it themselves, and an address the SMTP account does not own would be rejected.
			$set_sender = static function ( $phpmailer ) use ( $from_email, $mail ): void {
				if ( '' !== $mail->alt_body ) {
					$phpmailer->AltBody = $mail->alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
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

		return new self( $send, $from_email, $from_name, $notify_to );
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
				$this->notification_body( $request, $event )
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
	 * Sends both mails to one test address only. The address also replaces the visitor address, so
	 * Reply-To and the pasted details point to a real mailbox instead of a sample domain that mail servers may reject.
	 *
	 * @param Booking_Request $sample  Sample request, its e-mail address is replaced by $to.
	 * @param Event           $event   Sample event.
	 * @param string          $to      Test recipient.
	 *
	 * @return list<array{label: string, mail: Mail_Message, error: string|null}> One entry per mail, in sending order.
	 */
	public function send_test( Booking_Request $sample, Event $event, string $to ): array {
		$request  = new Booking_Request( $sample->event_id, $sample->name, $to, $sample->phone, $sample->participants, $sample->rental, $sample->message, '' );
		$messages = $this->build( $request, $event );
		$results  = array();

		foreach ( array(
			self::LABEL_NOTIFICATION => $messages['notification'],
			self::LABEL_CONFIRMATION => $messages['confirmation'],
		) as $label => $message ) {
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
				$request->name . ' - ' . $slot,
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
			$lines .= '<div>' . Mail_Layout::escape( $line ) . '</div>';
		}

		$details = array(
			array( 'Datum', $request->name . ' - ' . $slot ),
			array( 'Datum', $slot ),
			array( 'Anzahl', $request->participants . ' Personen / ' . $request->rental . ' Ausrüstungen' ),
			array( 'Ansprechperson', $request->name . ' - ' . $request->phone ),
		);
		$rows    = '';
		foreach ( $details as list( $label, $value ) ) {
			$rows .= '<tr><td style="padding:8px 12px 8px 0;vertical-align:top;white-space:nowrap;font-weight:600;color:#6B7280;">' . Mail_Layout::escape( $label ) . '</td>'
				. '<td style="padding:8px 0;vertical-align:top;">' . Mail_Layout::escape( $value ) . '</td></tr>';
		}

		$content = '<h1 style="margin:0 0 16px;font-size:24px;line-height:28px;font-weight:700;color:#181818;">Verleih Anfrage</h1>'
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
	 * Makes a user supplied string safe inside a header: no line breaks (header injection), no angle brackets or quotes.
	 *
	 * @param string $value Raw value.
	 */
	private static function header_value( string $value ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( '/[\r\n<>",;]+/', ' ', $value ) ) );
	}
}
