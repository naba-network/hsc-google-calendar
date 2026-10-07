<?php
/**
 * Reads events from the Google Calendar API.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

// Exception messages are internal and escaped on output by Admin_Page.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

namespace Hsc\GoogleCalendar;

use Closure;
use RuntimeException;

/**
 * Fetches all events of a calendar (paginated), expanded to single instances and sorted by start.
 */
final class Calendar_Client {

	private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/%s/events';
	private const PAGE_SIZE  = 250;
	private const MAX_PAGES  = 20;
	private const HTTP_OK    = 200;

	/**
	 * Creates the client.
	 *
	 * @param Closure           $send Transport, see Connection_Tester::__construct().
	 * @param Connection_Tester $auth Provides access tokens for service accounts.
	 */
	public function __construct(
		private readonly Closure $send,
		private readonly Connection_Tester $auth
	) {}

	/**
	 * Fetches events starting at or after $time_min.
	 *
	 * @param string           $calendar_id Calendar ID.
	 * @param Credentials|null $credentials Service account; when null, $api_key is used (public calendars only).
	 * @param string           $api_key     Google Cloud API key, may be empty when credentials are given.
	 * @param int              $time_min    Unix timestamp of the earliest event start to include.
	 * @param int              $now         Current unix timestamp (for the token).
	 * @param int|null         $time_max    Unix timestamp the event start must be before, null for no upper limit.
	 *
	 * @return list<Event>
	 * @throws RuntimeException When authentication or the request fails.
	 */
	public function list_events( string $calendar_id, ?Credentials $credentials, string $api_key, int $time_min, int $now, ?int $time_max = null ): array {
		$headers = array();
		$query   = array(
			'singleEvents' => 'true',
			'orderBy'      => 'startTime',
			'maxResults'   => (string) self::PAGE_SIZE,
			'timeMin'      => gmdate( 'Y-m-d\TH:i:s\Z', $time_min ),
		);
		if ( null !== $time_max ) {
			$query['timeMax'] = gmdate( 'Y-m-d\TH:i:s\Z', $time_max );
		}
		if ( null !== $credentials ) {
			$headers['Authorization'] = 'Bearer ' . $this->auth->access_token( $credentials, $now );
		} elseif ( '' !== $api_key ) {
			$query['key'] = $api_key;
		} else {
			throw new RuntimeException( 'No service account JSON or API key configured.' );
		}

		$events     = array();
		$page_token = null;
		for ( $page = 0; $page < self::MAX_PAGES; ++$page ) {
			$page_query = null === $page_token ? $query : $query + array( 'pageToken' => $page_token );
			$response   = ( $this->send )( 'GET', sprintf( self::EVENTS_URL, rawurlencode( $calendar_id ) ) . '?' . http_build_query( $page_query ), $headers, '' );
			$data       = json_decode( $response['body'], true );
			$data       = is_array( $data ) ? $data : array();

			if ( self::HTTP_OK !== $response['status'] ) {
				throw new RuntimeException( 'Could not load events: ' . Connection_Tester::error_message( $data, $response['status'] ) );
			}

			foreach ( is_array( $data['items'] ?? null ) ? $data['items'] : array() as $item ) {
				$event = is_array( $item ) ? Event::from_api( $item ) : null;
				if ( null !== $event ) {
					$events[] = $event;
				}
			}

			$page_token = is_string( $data['nextPageToken'] ?? null ) && '' !== $data['nextPageToken'] ? $data['nextPageToken'] : null;
			if ( null === $page_token ) {
				break;
			}
		}

		return $events;
	}
}
