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
	public const OPTION_BOOKED      = 'hsc_gcal_booked_regex';
	public const OPTION_RANGE_FROM  = 'hsc_gcal_range_from';
	public const OPTION_RANGE_TO    = 'hsc_gcal_range_to';
	public const OPTION_MAIL_FROM   = 'hsc_gcal_mail_from';
	public const OPTION_MAIL_NAME   = 'hsc_gcal_mail_from_name';
	public const OPTION_MAIL_TO     = 'hsc_gcal_mail_to';

	public const DEFAULT_MAIL_FROM = 'verleih@sc-hohenems.at';
	public const DEFAULT_MAIL_NAME = 'SC Samina Hohenems - Verleih';

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
	 * Regex (without delimiters) that marks an event as booked, matched against title and description.
	 * Empty means no event counts as booked.
	 */
	public function booked_regex(): string {
		return (string) get_option( self::OPTION_BOOKED, '' );
	}

	/**
	 * First day the shortcodes look at ("Y-m-d"), empty for the start of the current month.
	 */
	public function range_from(): string {
		return (string) get_option( self::OPTION_RANGE_FROM, '' );
	}

	/**
	 * Last day the shortcodes look at ("Y-m-d", inclusive), empty for no end.
	 */
	public function range_to(): string {
		return (string) get_option( self::OPTION_RANGE_TO, '' );
	}

	/**
	 * Saves the date range (already validated by the caller).
	 *
	 * @param string $from First day or ''.
	 * @param string $to   Last day or ''.
	 */
	public function save_range( string $from, string $to ): void {
		update_option( self::OPTION_RANGE_FROM, $from, false );
		update_option( self::OPTION_RANGE_TO, $to, false );
	}

	/**
	 * Sender address of booking e-mails.
	 */
	public function mail_from(): string {
		$value = (string) get_option( self::OPTION_MAIL_FROM, '' );

		return '' !== $value ? $value : self::DEFAULT_MAIL_FROM;
	}

	/**
	 * Sender name of booking e-mails.
	 */
	public function mail_from_name(): string {
		$value = (string) get_option( self::OPTION_MAIL_NAME, '' );

		return '' !== $value ? $value : self::DEFAULT_MAIL_NAME;
	}

	/**
	 * Address that receives booking notifications, defaults to the sender address.
	 */
	public function mail_to(): string {
		$value = (string) get_option( self::OPTION_MAIL_TO, '' );

		return '' !== $value ? $value : $this->mail_from();
	}

	/**
	 * Saves the booked-event regex.
	 *
	 * @param string $regex Pattern without delimiters (already validated by the caller).
	 */
	public function save_booked_regex( string $regex ): void {
		update_option( self::OPTION_BOOKED, $regex, false );
	}

	/**
	 * Saves the e-mail settings. Empty values fall back to the defaults.
	 *
	 * @param string $from Sender address.
	 * @param string $name Sender name.
	 * @param string $to   Notification recipient.
	 */
	public function save_mail( string $from, string $name, string $to ): void {
		update_option( self::OPTION_MAIL_FROM, $from, false );
		update_option( self::OPTION_MAIL_NAME, $name, false );
		update_option( self::OPTION_MAIL_TO, $to, false );
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
