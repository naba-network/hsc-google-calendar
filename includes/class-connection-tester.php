<?php
/**
 * Tests the connection to a Google Calendar with a service account.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

// Exception messages are internal and escaped on output by Admin_Page.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

namespace Hsc\GoogleCalendar;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Exchanges a signed service account JWT for an access token and reads the calendar metadata.
 * No Google client library needed: two HTTPS calls are enough to prove that credentials and calendar ID work.
 */
final class Connection_Tester {

	private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	private const CALENDAR_URL = 'https://www.googleapis.com/calendar/v3/calendars/';
	private const SCOPE        = 'https://www.googleapis.com/auth/calendar.readonly';
	private const GRANT_TYPE   = 'urn:ietf:params:oauth:grant-type:jwt-bearer';
	private const TOKEN_TTL    = 3600;

	private const HTTP_OK        = 200;
	private const HTTP_FORBIDDEN = 403;
	private const HTTP_NOT_FOUND = 404;

	/**
	 * Creates the tester.
	 *
	 * @param Closure $send Transport: fn( string $method, string $url, array<string,string> $headers, string $body ): array{status: int, body: string}.
	 *                      Must throw RuntimeException when the request itself fails (no response).
	 */
	public function __construct( private readonly Closure $send ) {}

	/**
	 * Creates a tester that sends requests through the WordPress HTTP API.
	 */
	public static function with_wordpress_http(): self {
		return new self( self::wordpress_sender() );
	}

	/**
	 * Transport that sends requests through the WordPress HTTP API.
	 */
	public static function wordpress_sender(): Closure {
		return static function ( string $method, string $url, array $headers, string $body ): array {
				$response = wp_remote_request(
					$url,
					array(
						'method'  => $method,
						'headers' => $headers,
						'body'    => '' === $body ? null : $body,
						'timeout' => 15,
					)
				);
			if ( is_wp_error( $response ) ) {
				throw new RuntimeException( $response->get_error_message() );
			}

				return array(
					'status' => (int) wp_remote_retrieve_response_code( $response ),
					'body'   => (string) wp_remote_retrieve_body( $response ),
				);
		};
	}

	/**
	 * Runs the full check. Never throws: every problem becomes a failed result with a readable reason.
	 *
	 * @param string $calendar_id      Google Calendar ID.
	 * @param string $credentials_json Service account JSON.
	 * @param int    $now              Current unix timestamp.
	 */
	public function test( string $calendar_id, string $credentials_json, int $now ): Connection_Result {
		if ( '' === $calendar_id ) {
			return Connection_Result::failure( 'No calendar ID configured.', $now );
		}
		if ( '' === $credentials_json ) {
			return Connection_Result::failure( 'No service account JSON configured.', $now );
		}

		try {
			$credentials = Credentials::from_json( $credentials_json );
			$token       = $this->access_token( $credentials, $now );

			return Connection_Result::success( $this->read_calendar( $calendar_id, $token, $credentials->client_email ), $now );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			return Connection_Result::failure( $e->getMessage(), $now );
		}
	}

	/**
	 * Requests an access token.
	 *
	 * @param Credentials $credentials Service account.
	 * @param int         $now         Current unix timestamp.
	 *
	 * @throws RuntimeException When signing or the token request fails.
	 */
	public function access_token( Credentials $credentials, int $now ): string {
		$assertion = $this->build_jwt( $credentials, $now );
		$response  = ( $this->send )(
			'POST',
			self::TOKEN_URL,
			array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			http_build_query(
				array(
					'grant_type' => self::GRANT_TYPE,
					'assertion'  => $assertion,
				)
			)
		);

		$data = self::decode( $response['body'] );
		if ( self::HTTP_OK !== $response['status'] || ! isset( $data['access_token'] ) || ! is_string( $data['access_token'] ) ) {
			throw new RuntimeException( 'Google rejected the credentials: ' . self::error_message( $data, $response['status'] ) );
		}

		return $data['access_token'];
	}

	/**
	 * Reads the calendar metadata to prove access.
	 *
	 * @param string $calendar_id  Calendar ID.
	 * @param string $token        Access token.
	 * @param string $client_email Service account e-mail, used in hints.
	 *
	 * @return string Success message.
	 * @throws RuntimeException When the calendar is not reachable.
	 */
	private function read_calendar( string $calendar_id, string $token, string $client_email ): string {
		$response = ( $this->send )(
			'GET',
			self::CALENDAR_URL . rawurlencode( $calendar_id ),
			array( 'Authorization' => 'Bearer ' . $token ),
			''
		);

		if ( self::HTTP_OK === $response['status'] ) {
			$data = self::decode( $response['body'] );
			$name = isset( $data['summary'] ) && is_string( $data['summary'] ) ? $data['summary'] : $calendar_id;

			return sprintf( 'Connected to calendar "%s".', $name );
		}

		$reason = self::error_message( self::decode( $response['body'] ), $response['status'] );
		$hint   = match ( $response['status'] ) {
			self::HTTP_NOT_FOUND => sprintf( 'Calendar not found. Check the calendar ID and share the calendar with %s.', $client_email ),
			self::HTTP_FORBIDDEN => sprintf( 'Access denied. Share the calendar with %s and enable the Google Calendar API in the Google Cloud project.', $client_email ),
			default              => 'Calendar request failed.',
		};

		throw new RuntimeException( $hint . ' Google says: ' . $reason );
	}

	/**
	 * Builds and signs the RS256 JWT assertion.
	 *
	 * @param Credentials $credentials Service account.
	 * @param int         $now         Current unix timestamp.
	 *
	 * @throws RuntimeException When the private key cannot be used.
	 */
	private function build_jwt( Credentials $credentials, int $now ): string {
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);
		$claims = array(
			'iss'   => $credentials->client_email,
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => $now,
			'exp'   => $now + self::TOKEN_TTL,
		);
		// Plain json_encode on purpose: this class must work without WordPress loaded (unit tests).
		// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$payload = self::base64url( json_encode( $header, JSON_THROW_ON_ERROR ) ) . '.' . self::base64url( json_encode( $claims, JSON_THROW_ON_ERROR ) );
		// phpcs:enable WordPress.WP.AlternativeFunctions.json_encode_json_encode

		$key = openssl_pkey_get_private( $credentials->private_key );
		if ( false === $key ) {
			throw new RuntimeException( 'The private key in the service account JSON is not valid.' );
		}
		$signature = '';
		if ( ! openssl_sign( $payload, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			throw new RuntimeException( 'Signing the request with the private key failed.' );
		}

		return $payload . '.' . self::base64url( $signature );
	}

	/**
	 * Decodes a JSON response body; garbage becomes an empty array.
	 *
	 * @param string $body Response body.
	 *
	 * @return array<string, mixed> Decoded JSON object (values are unvalidated Google data).
	 */
	private static function decode( string $body ): array {
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Extracts the error text from a Google error response.
	 *
	 * @param array<string, mixed> $data   Decoded body.
	 * @param int                  $status HTTP status.
	 */
	public static function error_message( array $data, int $status ): string {
		$error = $data['error'] ?? null;
		if ( is_array( $error ) && isset( $error['message'] ) && is_string( $error['message'] ) ) {
			return sprintf( '%s (HTTP %d)', $error['message'], $status );
		}
		if ( is_string( $error ) ) {
			$description = isset( $data['error_description'] ) && is_string( $data['error_description'] ) ? ': ' . $data['error_description'] : '';

			return sprintf( '%s%s (HTTP %d)', $error, $description, $status );
		}

		return sprintf( 'unexpected response (HTTP %d)', $status );
	}

	/**
	 * Base64 encoding with the URL safe alphabet, no padding (JWT format).
	 *
	 * @param string $data Raw data.
	 */
	private static function base64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
