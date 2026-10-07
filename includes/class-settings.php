<?php
/**
 * Plugin settings stored in the WordPress options table.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Calendar ID, service account key and the last connection status.
 */
final class Settings {

	public const OPTION_CALENDAR_ID = 'hsc_gcal_calendar_id';
	public const OPTION_CREDENTIALS = 'hsc_gcal_service_account';
	public const OPTION_STATUS      = 'hsc_gcal_connection_status';
	public const OPTION_API_KEY     = 'hsc_gcal_api_key';
	public const OPTION_REGEX       = 'hsc_gcal_event_regex';

	/**
	 * Configured calendar ID.
	 */
	public function calendar_id(): string {
		return (string) get_option( self::OPTION_CALENDAR_ID, '' );
	}

	/**
	 * Stored service account JSON.
	 */
	public function credentials_json(): string {
		return (string) get_option( self::OPTION_CREDENTIALS, '' );
	}

	/**
	 * Stored Google Cloud API key (public calendars only).
	 */
	public function api_key(): string {
		return (string) get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Regex (without delimiters) used to filter events by title or description.
	 */
	public function event_regex(): string {
		return (string) get_option( self::OPTION_REGEX, '' );
	}

	/**
	 * Saves the API key.
	 *
	 * @param string $api_key API key.
	 */
	public function save_api_key( string $api_key ): void {
		update_option( self::OPTION_API_KEY, $api_key, false );
	}

	/**
	 * Saves the event filter regex.
	 *
	 * @param string $regex Pattern without delimiters (already validated by the caller).
	 */
	public function save_event_regex( string $regex ): void {
		update_option( self::OPTION_REGEX, $regex, false );
	}

	/**
	 * Saves the calendar ID.
	 *
	 * @param string $calendar_id Calendar ID.
	 */
	public function save_calendar_id( string $calendar_id ): void {
		update_option( self::OPTION_CALENDAR_ID, $calendar_id, false );
	}

	/**
	 * Saves the service account JSON (already validated by the caller).
	 *
	 * @param string $json Service account JSON.
	 */
	public function save_credentials_json( string $json ): void {
		update_option( self::OPTION_CREDENTIALS, $json, false );
	}

	/**
	 * Last stored connection test result.
	 */
	public function status(): ?Connection_Result {
		return Connection_Result::from_stored( get_option( self::OPTION_STATUS, null ) );
	}

	/**
	 * Stores a connection test result.
	 *
	 * @param Connection_Result $result Result to store.
	 */
	public function save_status( Connection_Result $result ): void {
		update_option( self::OPTION_STATUS, $result->to_array(), false );
	}
}
