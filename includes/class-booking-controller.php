<?php
/**
 * REST endpoints used by the booking form.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /captcha hands out a signed challenge, POST /booking validates the form and sends the e-mails.
 * Nothing is stored; the club books manually in Google Calendar.
 */
final class Booking_Controller {

	public const REST_NAMESPACE = 'hsc-gcal/v1';
	public const ROUTE_CAPTCHA  = '/captcha';
	public const ROUTE_BOOKING  = '/booking';

	private const STATUS_OK          = 200;
	private const STATUS_INVALID     = 422;
	private const STATUS_CONFLICT    = 409;
	private const STATUS_UNAVAILABLE = 503;

	private const ERROR_EVENT_GONE  = 'event_unavailable';
	private const ERROR_SEND_FAILED = 'send_failed';
	private const ERROR_UNAVAILABLE = 'calendar_unavailable';

	/**
	 * Creates the controller.
	 *
	 * @param Event_Source   $events  Calendar events.
	 * @param Captcha        $captcha Captcha.
	 * @param Booking_Mailer $mailer  Mailer.
	 */
	public function __construct(
		private readonly Event_Source $events,
		private readonly Captcha $captcha,
		private readonly Booking_Mailer $mailer
	) {}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the routes. Both are public on purpose: visitors are anonymous, abuse is limited by captcha and honeypot.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE_CAPTCHA,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_captcha' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE_BOOKING,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_booking' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Translated validation messages by error code, shared with the form template.
	 *
	 * @return array<string, string>
	 */
	public static function messages(): array {
		return array(
			Booking_Request::ERROR_NAME_REQUIRED  => __( 'Bitte gib deinen Namen an.', 'hsc-google-calendar' ),
			Booking_Request::ERROR_EMAIL_REQUIRED => __( 'Bitte gib deine E-Mail-Adresse an.', 'hsc-google-calendar' ),
			Booking_Request::ERROR_EMAIL_INVALID  => __( 'Das ist keine gültige E-Mail-Adresse, z. B. name@example.com.', 'hsc-google-calendar' ),
			Booking_Request::ERROR_PHONE_INVALID  => __( 'Bitte gib eine Telefonnummer an, z. B. +43 660 0000000.', 'hsc-google-calendar' ),
			Booking_Request::ERROR_PARTICIPANTS   => __( 'Bitte 1 bis 30 Teilnehmer angeben.', 'hsc-google-calendar' ),
			Booking_Request::ERROR_RENTAL         => __( 'Bitte 0 oder mehr angeben.', 'hsc-google-calendar' ),
			Booking_Request::ERROR_RENTAL_EXCEEDS => __( 'Nicht mehr als Teilnehmer.', 'hsc-google-calendar' ),
			Booking_Request::ERROR_CAPTCHA        => __( 'Das Ergebnis stimmt nicht. Bitte nochmal rechnen.', 'hsc-google-calendar' ),
			self::ERROR_EVENT_GONE                => __( 'Dieser Termin ist leider nicht mehr frei. Bitte lade die Seite neu.', 'hsc-google-calendar' ),
			self::ERROR_SEND_FAILED               => __( 'Die Anfrage konnte nicht gesendet werden. Bitte versuche es später noch einmal.', 'hsc-google-calendar' ),
			self::ERROR_UNAVAILABLE               => __( 'Der Kalender ist gerade nicht erreichbar. Bitte versuche es später noch einmal.', 'hsc-google-calendar' ),
		);
	}

	/**
	 * Returns a fresh challenge. Never cached.
	 */
	public function handle_captcha(): WP_REST_Response {
		$response = new WP_REST_Response( $this->captcha->challenge( time() ), self::STATUS_OK );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Validates the request and sends the e-mails.
	 *
	 * @param WP_REST_Request $http Request.
	 */
	public function handle_booking( WP_REST_Request $http ): WP_REST_Response {
		$params = $http->get_json_params();
		$params = is_array( $params ) ? $params : $http->get_body_params();
		$input  = Booking_Request::from_array( $params );

		// Bots get a success answer so they learn nothing; no mail is sent.
		if ( $input->is_spam() ) {
			return new WP_REST_Response( array( 'ok' => true ), self::STATUS_OK );
		}

		$errors = $input->errors();
		$token  = $params[ Booking_Request::FIELD_TOKEN ] ?? '';
		$answer = $params[ Booking_Request::FIELD_CAPTCHA ] ?? '';
		if ( ! is_string( $token ) || ! is_string( $answer ) || ! $this->captcha->verify( $token, $answer, time() ) ) {
			$errors[ Booking_Request::FIELD_CAPTCHA ] = Booking_Request::ERROR_CAPTCHA;
		}
		if ( array() !== $errors ) {
			return $this->failure( $errors, self::STATUS_INVALID );
		}

		try {
			$event = $this->events->find_free( $input->event_id, time() );
		} catch ( RuntimeException ) {
			return $this->failure( array( '' => self::ERROR_UNAVAILABLE ), self::STATUS_UNAVAILABLE );
		}
		if ( null === $event ) {
			return $this->failure( array( '' => self::ERROR_EVENT_GONE ), self::STATUS_CONFLICT );
		}

		if ( ! $this->mailer->send( $input, $event ) ) {
			return $this->failure( array( '' => self::ERROR_SEND_FAILED ), self::STATUS_UNAVAILABLE );
		}

		return new WP_REST_Response( array( 'ok' => true ), self::STATUS_OK );
	}

	/**
	 * Error response. The key '' is a general error not tied to a field.
	 *
	 * @param array<string, string> $codes  Field => error code.
	 * @param int                   $status HTTP status.
	 */
	private function failure( array $codes, int $status ): WP_REST_Response {
		$messages = self::messages();
		$errors   = array();
		foreach ( $codes as $field => $code ) {
			$errors[ $field ] = $messages[ $code ] ?? $code;
		}

		return new WP_REST_Response(
			array(
				'ok'     => false,
				'errors' => $errors,
			),
			$status
		);
	}
}
