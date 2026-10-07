<?php
/**
 * Stateless "add two small numbers" captcha.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

use Closure;

/**
 * The challenge is signed with a secret, so nothing has to be stored on the server
 * and the form keeps working behind page caches.
 */
final class Captcha {

	private const TTL        = 7200;
	private const SEPARATOR  = '.';
	private const ALGO       = 'sha256';
	private const MAX_ADDEND = 9;

	/**
	 * Creates the captcha.
	 *
	 * @param Closure $secret Returns the secret used to sign challenges. Lazy because wp_salt() is not available when plugins load.
	 */
	public function __construct( private readonly Closure $secret ) {}

	/**
	 * Creates a challenge.
	 *
	 * @param int      $now Current unix timestamp.
	 * @param int|null $a   First number, random when null.
	 * @param int|null $b   Second number, random when null.
	 *
	 * @return array{question: string, token: string}
	 */
	public function challenge( int $now, ?int $a = null, ?int $b = null ): array {
		$a     ??= random_int( 1, self::MAX_ADDEND );
		$b     ??= random_int( 1, self::MAX_ADDEND );
		$payload = $a . ':' . $b . ':' . ( $now + self::TTL );

		return array(
			'question' => $a . ' + ' . $b,
			'token'    => $payload . self::SEPARATOR . $this->sign( $payload ),
		);
	}

	/**
	 * Checks a token and the answer.
	 *
	 * @param string $token  Token from challenge().
	 * @param string $answer Answer typed by the visitor.
	 * @param int    $now    Current unix timestamp.
	 */
	public function verify( string $token, string $answer, int $now ): bool {
		$parts = explode( self::SEPARATOR, $token );
		if ( 2 !== count( $parts ) || ! hash_equals( $this->sign( $parts[0] ), $parts[1] ) ) {
			return false;
		}

		$numbers = explode( ':', $parts[0] );
		if ( 3 !== count( $numbers ) || (int) $numbers[2] < $now ) {
			return false;
		}

		return trim( $answer ) === (string) ( (int) $numbers[0] + (int) $numbers[1] );
	}

	/**
	 * HMAC of a payload.
	 *
	 * @param string $payload Signed data.
	 */
	private function sign( string $payload ): string {
		return hash_hmac( self::ALGO, $payload, ( $this->secret )() );
	}
}
