<?php
/**
 * Google service account credentials.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

// Exception messages are internal and escaped on output by Admin_Page.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

namespace Hsc\GoogleCalendar;

use InvalidArgumentException;
use JsonException;

/**
 * Validated subset of a service account key file (the JSON downloaded from Google Cloud).
 */
final class Credentials {

	private const TYPE_SERVICE_ACCOUNT = 'service_account';

	/**
	 * Creates credentials.
	 *
	 * @param string $client_email Service account e-mail address.
	 * @param string $private_key  PEM encoded private key.
	 */
	private function __construct(
		public readonly string $client_email,
		public readonly string $private_key
	) {}

	/**
	 * Parses and validates the service account JSON.
	 *
	 * @param string $json Contents of the key file.
	 *
	 * @throws InvalidArgumentException When the JSON is unusable.
	 */
	public static function from_json( string $json ): self {
		try {
			$data = json_decode( $json, true, 8, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			throw new InvalidArgumentException( 'The service account JSON is not valid JSON: ' . $e->getMessage(), 0, $e );
		}

		if ( ! is_array( $data ) ) {
			throw new InvalidArgumentException( 'The service account JSON must be an object.' );
		}
		if ( isset( $data['type'] ) && self::TYPE_SERVICE_ACCOUNT !== $data['type'] ) {
			throw new InvalidArgumentException( 'The JSON is not a service account key (type must be "service_account").' );
		}

		$email = $data['client_email'] ?? null;
		$key   = $data['private_key'] ?? null;
		if ( ! is_string( $email ) || '' === $email ) {
			throw new InvalidArgumentException( 'The service account JSON has no "client_email".' );
		}
		if ( ! is_string( $key ) || '' === $key ) {
			throw new InvalidArgumentException( 'The service account JSON has no "private_key".' );
		}

		return new self( $email, $key );
	}
}
