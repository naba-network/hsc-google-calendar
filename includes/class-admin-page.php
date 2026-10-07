<?php
/**
 * Admin menu page with an overview of the plugin.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

use InvalidArgumentException;
use RuntimeException;

/**
 * Top-level "HSC Calendar" admin page.
 */
final class Admin_Page {

	public const SLUG   = 'hsc-google-calendar';
	public const ACTION = 'hsc_gcal_check_updates';

	public const ACTION_SAVE = 'hsc_gcal_save_settings';
	public const ACTION_TEST = 'hsc_gcal_test_connection';
	public const ACTION_MAIL = 'hsc_gcal_test_mail';

	private const FIELD_TEST_TO   = 'hsc_test_to';
	private const TRANSIENT_MAIL  = 'hsc_gcal_mail_test_';
	private const MAIL_RESULT_TTL = 600;

	private const FIELD_CALENDAR_ID = 'hsc_calendar_id';
	private const FIELD_CREDENTIALS = 'hsc_service_account';
	private const FIELD_API_KEY     = 'hsc_api_key';
	private const FIELD_REGEX       = 'hsc_event_regex';
	private const FIELD_BOOKED      = 'hsc_booked_regex';
	private const FIELD_RANGE_FROM  = 'hsc_range_from';
	private const FIELD_RANGE_TO    = 'hsc_range_to';
	private const FIELD_MAIL_FROM   = 'hsc_mail_from';
	private const FIELD_MAIL_NAME   = 'hsc_mail_from_name';
	private const FIELD_MAIL_TO     = 'hsc_mail_to';
	private const QUERY_NOTICE      = 'hsc_notice';

	/**
	 * Creates the admin page.
	 *
	 * @param string            $basename Plugin basename.
	 * @param string            $version  Currently installed version.
	 * @param Updater           $updater  Updater used for the manual check.
	 * @param Settings          $settings Stored calendar settings.
	 * @param Connection_Tester $tester   Calendar connection check.
	 * @param Calendar_Client   $client   Event loader.
	 * @param Booking_Mailer    $mailer   Booking e-mails, used for the test send.
	 */
	public function __construct(
		private readonly string $basename,
		private readonly string $version,
		private readonly Updater $updater,
		private readonly Settings $settings,
		private readonly Connection_Tester $tester,
		private readonly Calendar_Client $client,
		private readonly Booking_Mailer $mailer
	) {}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_check_updates' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_' . self::ACTION_TEST, array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_' . self::ACTION_MAIL, array( $this, 'handle_test_mail' ) );
	}

	/**
	 * Adds the menu entry with a calendar icon.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'HSC Calendar', 'hsc-google-calendar' ),
			__( 'HSC Calendar', 'hsc-google-calendar' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-calendar-alt',
			30
		);
	}

	/**
	 * Runs a manual update check and redirects back to the page.
	 */
	public function handle_check_updates(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'hsc-google-calendar' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$this->updater->force_check();

		wp_safe_redirect( add_query_arg( 'hsc_checked', '1', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Saves calendar ID and service account JSON, then tests the connection.
	 */
	public function handle_save_settings(): void {
		$this->authorize( self::ACTION_SAVE );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce checked in authorize().
		$calendar_id = isset( $_POST[ self::FIELD_CALENDAR_ID ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD_CALENDAR_ID ] ) ) : '';
		// JSON is validated by Credentials::from_json(); text sanitizing would corrupt the key.
		$json       = isset( $_POST[ self::FIELD_CREDENTIALS ] ) ? trim( wp_unslash( (string) $_POST[ self::FIELD_CREDENTIALS ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$api_key    = isset( $_POST[ self::FIELD_API_KEY ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD_API_KEY ] ) ) : '';
		$regex      = isset( $_POST[ self::FIELD_REGEX ] ) ? trim( wp_unslash( (string) $_POST[ self::FIELD_REGEX ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by Event_Filter.
		$booked     = isset( $_POST[ self::FIELD_BOOKED ] ) ? trim( wp_unslash( (string) $_POST[ self::FIELD_BOOKED ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by Event_Filter.
		$range_from = isset( $_POST[ self::FIELD_RANGE_FROM ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD_RANGE_FROM ] ) ) : '';
		$range_to   = isset( $_POST[ self::FIELD_RANGE_TO ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD_RANGE_TO ] ) ) : '';
		$from       = isset( $_POST[ self::FIELD_MAIL_FROM ] ) ? sanitize_email( wp_unslash( (string) $_POST[ self::FIELD_MAIL_FROM ] ) ) : '';
		$name       = isset( $_POST[ self::FIELD_MAIL_NAME ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD_MAIL_NAME ] ) ) : '';
		$to         = isset( $_POST[ self::FIELD_MAIL_TO ] ) ? sanitize_email( wp_unslash( (string) $_POST[ self::FIELD_MAIL_TO ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->settings->save_calendar_id( $calendar_id );
		$range_error = $this->range_error( $range_from, $range_to );
		if ( null !== $range_error ) {
			$this->settings->save_status( Connection_Result::failure( $range_error . ' Settings were not saved.', time() ) );
			$this->redirect_back( 'saved' );
		}
		$this->settings->save_range( $range_from, $range_to );
		$this->settings->save_mail( $from, $name, $to );
		// Empty field keeps the stored key, like the service account JSON.
		if ( '' !== $api_key ) {
			$this->settings->save_api_key( $api_key );
		}
		try {
			Event_Filter::from_pattern( $regex );
			Event_Filter::from_pattern( $booked );
			$this->settings->save_event_regex( $regex );
			$this->settings->save_booked_regex( $booked );
		} catch ( InvalidArgumentException $e ) {
			$this->settings->save_status( Connection_Result::failure( $e->getMessage() . ' Filter was not saved.', time() ) );
			$this->redirect_back( 'saved' );
		}

		// Empty textarea keeps the stored key, so the secret never has to be echoed into the form.
		if ( '' !== $json ) {
			try {
				Credentials::from_json( $json );
			} catch ( InvalidArgumentException $e ) {
				$this->settings->save_status( Connection_Result::failure( $e->getMessage() . ' Credentials were not saved.', time() ) );
				$this->redirect_back( 'saved' );
			}
			$this->settings->save_credentials_json( $json );
		}

		$this->run_test();
		$this->redirect_back( 'saved' );
	}

	/**
	 * Sends both booking e-mails with sample data to the given address and stores what was sent, so it can be shown.
	 */
	public function handle_test_mail(): void {
		$this->authorize( self::ACTION_MAIL );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked in authorize().
		$to      = isset( $_POST[ self::FIELD_TEST_TO ] ) ? sanitize_email( wp_unslash( (string) $_POST[ self::FIELD_TEST_TO ] ) ) : '';
		$key     = self::TRANSIENT_MAIL . get_current_user_id();
		$results = array(
			'to'    => $to,
			'sent'  => time(),
			'mails' => array(),
		);

		if ( ! is_email( $to ) ) {
			$results['error'] = 'Please enter a valid e-mail address.';
			set_transient( $key, $results, self::MAIL_RESULT_TTL );
			$this->redirect_back( 'mailtested' );
		}

		$start  = new \DateTimeImmutable( 'next saturday 10:00', wp_timezone() );
		$event  = new Event( 'test-event', 'Freie Eiszeit', '', $start->format( DATE_ATOM ), $start->modify( '+90 minutes' )->format( DATE_ATOM ), false );
		$sample = new Booking_Request( $event->id, 'Max Mustermann', $to, '+43 660 0000000', 4, 2, 'Das ist eine Test-Anfrage.', '' );

		// Both mails go to the test address, never to the real club inbox or a sample visitor.
		foreach ( $this->mailer->send_test( $sample, $event, $to ) as $result ) {
			$results['mails'][] = array(
				'label'   => $result['label'],
				'error'   => $result['error'],
				'to'      => $result['mail']->to,
				'subject' => $result['mail']->subject,
				'headers' => $result['mail']->headers,
				'body'    => $result['mail']->body,
			);
		}

		set_transient( $key, $results, self::MAIL_RESULT_TTL );
		$this->redirect_back( 'mailtested' );
	}

	/**
	 * Why a date range cannot be saved, null when it is fine.
	 *
	 * @param string $from First day ("Y-m-d") or ''.
	 * @param string $to   Last day ("Y-m-d") or ''.
	 */
	private function range_error( string $from, string $to ): ?string {
		foreach ( array( $from, $to ) as $day ) {
			if ( '' !== $day && ! Date_Range::is_valid_day( $day ) ) {
				return 'Invalid date "' . $day . '", expected YYYY-MM-DD.';
			}
		}
		if ( '' !== $from && '' !== $to && $from > $to ) {
			return 'The first day must not be after the last day.';
		}

		return null;
	}

	/**
	 * Tests the stored connection.
	 */
	public function handle_test_connection(): void {
		$this->authorize( self::ACTION_TEST );
		$this->run_test();
		$this->redirect_back( 'tested' );
	}

	/**
	 * Checks capability and nonce for an admin-post action.
	 *
	 * @param string $action Nonce action.
	 */
	private function authorize( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'hsc-google-calendar' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Tests the stored settings and stores the result.
	 */
	private function run_test(): void {
		$this->settings->save_status(
			$this->tester->test( $this->settings->calendar_id(), $this->settings->credentials_json(), time() )
		);
	}

	/**
	 * Redirects to the admin page and stops.
	 *
	 * @param string $notice Notice key shown after the redirect.
	 */
	private function redirect_back( string $notice ): never {
		wp_safe_redirect( add_query_arg( self::QUERY_NOTICE, $notice, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Renders the calendar connection card: status, error and settings form.
	 */
	private function render_connection_card(): void {
		$status = $this->settings->status();
		$email  = $this->stored_client_email();
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Google Calendar connection', 'hsc-google-calendar' ); ?></h2>
			<?php if ( null === $status ) : ?>
				<p><span class="dashicons dashicons-minus" aria-hidden="true"></span> <?php esc_html_e( 'Not connected yet. Enter the details below and save.', 'hsc-google-calendar' ); ?></p>
			<?php elseif ( $status->ok ) : ?>
				<div class="notice notice-success inline"><p><strong><?php esc_html_e( 'Connected.', 'hsc-google-calendar' ); ?></strong> <?php echo esc_html( $status->message ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Connection failed.', 'hsc-google-calendar' ); ?></strong> <?php echo esc_html( $status->message ); ?></p></div>
			<?php endif; ?>
			<?php if ( null !== $status ) : ?>
				<p class="description">
					<?php
					/* translators: %s: date and time of the last connection test */
					printf( esc_html__( 'Last tested: %s', 'hsc-google-calendar' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status->checked ) ) );
					?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>">
				<?php wp_nonce_field( self::ACTION_SAVE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_CALENDAR_ID ); ?>"><?php esc_html_e( 'Calendar ID', 'hsc-google-calendar' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="<?php echo esc_attr( self::FIELD_CALENDAR_ID ); ?>" name="<?php echo esc_attr( self::FIELD_CALENDAR_ID ); ?>" value="<?php echo esc_attr( $this->settings->calendar_id() ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Google Calendar settings, section "Integrate calendar".', 'hsc-google-calendar' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_CREDENTIALS ); ?>"><?php esc_html_e( 'Service account JSON', 'hsc-google-calendar' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="8" id="<?php echo esc_attr( self::FIELD_CREDENTIALS ); ?>" name="<?php echo esc_attr( self::FIELD_CREDENTIALS ); ?>" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( null !== $email ? __( 'A key is stored. Leave empty to keep it, paste a new key to replace it.', 'hsc-google-calendar' ) : '{ "type": "service_account", ... }' ); ?>"></textarea>
							<p class="description"><?php esc_html_e( 'Key file downloaded from Google Cloud (IAM, Service accounts, Keys). Share the calendar with the service account e-mail address.', 'hsc-google-calendar' ); ?></p>
							<?php if ( null !== $email ) : ?>
								<p class="description">
									<?php
									/* translators: %s: service account e-mail address */
									printf( esc_html__( 'Stored service account: %s', 'hsc-google-calendar' ), '<code>' . esc_html( $email ) . '</code>' );
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_API_KEY ); ?>"><?php esc_html_e( 'API key (optional)', 'hsc-google-calendar' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="<?php echo esc_attr( self::FIELD_API_KEY ); ?>" name="<?php echo esc_attr( self::FIELD_API_KEY ); ?>" value="" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $this->settings->api_key() ? __( 'A key is stored. Leave empty to keep it.', 'hsc-google-calendar' ) : '' ); ?>">
							<p class="description"><?php esc_html_e( 'Only for public calendars; used when no service account JSON is stored.', 'hsc-google-calendar' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_REGEX ); ?>"><?php esc_html_e( 'Free events (regex)', 'hsc-google-calendar' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="<?php echo esc_attr( self::FIELD_REGEX ); ?>" name="<?php echo esc_attr( self::FIELD_REGEX ); ?>" value="<?php echo esc_attr( $this->settings->event_regex() ); ?>" autocomplete="off" spellcheck="false">
							<p class="description"><?php esc_html_e( 'Event names that are free to book, listed by [HSC-Event-Booking-List]. Pattern without delimiters, case-insensitive, matched against the title. Empty lists all events.', 'hsc-google-calendar' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_BOOKED ); ?>"><?php esc_html_e( 'Booked events (regex)', 'hsc-google-calendar' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="<?php echo esc_attr( self::FIELD_BOOKED ); ?>" name="<?php echo esc_attr( self::FIELD_BOOKED ); ?>" value="<?php echo esc_attr( $this->settings->booked_regex() ); ?>" autocomplete="off" spellcheck="false">
							<p class="description"><?php esc_html_e( 'Event names that count as valid bookings, shown by [HSC-Event-Booking-Summary]. Matched against the title. Name, Teilnehmer, Leihausrüstung, E-Mail, Telefon and Anmerkung are read from "Key: value" lines in the event description. Empty shows nothing.', 'hsc-google-calendar' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_RANGE_FROM ); ?>"><?php esc_html_e( 'Date range', 'hsc-google-calendar' ); ?></label></th>
						<td>
							<input type="date" id="<?php echo esc_attr( self::FIELD_RANGE_FROM ); ?>" name="<?php echo esc_attr( self::FIELD_RANGE_FROM ); ?>" value="<?php echo esc_attr( $this->settings->range_from() ); ?>">
							<?php esc_html_e( 'to', 'hsc-google-calendar' ); ?>
							<input type="date" id="<?php echo esc_attr( self::FIELD_RANGE_TO ); ?>" name="<?php echo esc_attr( self::FIELD_RANGE_TO ); ?>" value="<?php echo esc_attr( $this->settings->range_to() ); ?>" aria-label="<?php esc_attr_e( 'Last day', 'hsc-google-calendar' ); ?>">
							<p class="description"><?php esc_html_e( 'Both shortcodes only look at events in this range, last day included. Empty start means the beginning of the current month, empty end means no limit. Events that already started are never offered for booking.', 'hsc-google-calendar' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_MAIL_FROM ); ?>"><?php esc_html_e( 'E-mail sender address', 'hsc-google-calendar' ); ?></label></th>
						<td><input type="email" class="regular-text" id="<?php echo esc_attr( self::FIELD_MAIL_FROM ); ?>" name="<?php echo esc_attr( self::FIELD_MAIL_FROM ); ?>" value="<?php echo esc_attr( $this->settings->mail_from() ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_MAIL_NAME ); ?>"><?php esc_html_e( 'E-mail sender name', 'hsc-google-calendar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="<?php echo esc_attr( self::FIELD_MAIL_NAME ); ?>" name="<?php echo esc_attr( self::FIELD_MAIL_NAME ); ?>" value="<?php echo esc_attr( $this->settings->mail_from_name() ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( self::FIELD_MAIL_TO ); ?>"><?php esc_html_e( 'Notification recipient', 'hsc-google-calendar' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="<?php echo esc_attr( self::FIELD_MAIL_TO ); ?>" name="<?php echo esc_attr( self::FIELD_MAIL_TO ); ?>" value="<?php echo esc_attr( $this->settings->mail_to() ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Receives every booking request. Defaults to the sender address.', 'hsc-google-calendar' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save and test connection', 'hsc-google-calendar' ), 'primary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_TEST ); ?>">
				<?php wp_nonce_field( self::ACTION_TEST ); ?>
				<?php submit_button( __( 'Test connection again', 'hsc-google-calendar' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the e-mail test: send form and the content of the last test send.
	 */
	private function render_mail_test_card(): void {
		$last = get_transient( self::TRANSIENT_MAIL . get_current_user_id() );
		$last = is_array( $last ) ? $last : null;
		$user = wp_get_current_user();
		$to   = null !== $last && is_string( $last['to'] ?? null ) && '' !== $last['to'] ? $last['to'] : $user->user_email;
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Test e-mails', 'hsc-google-calendar' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: sender name, 2: sender address, 3: notification recipient */
					esc_html__( 'Sender: %1$s <%2$s>. Booking requests are announced to: %3$s.', 'hsc-google-calendar' ),
					esc_html( $this->settings->mail_from_name() ),
					esc_html( $this->settings->mail_from() ),
					esc_html( $this->settings->mail_to() )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MAIL ); ?>">
				<?php wp_nonce_field( self::ACTION_MAIL ); ?>
				<p>
					<label for="<?php echo esc_attr( self::FIELD_TEST_TO ); ?>"><?php esc_html_e( 'Send both booking e-mails with sample data to', 'hsc-google-calendar' ); ?></label><br>
					<input type="email" class="regular-text" id="<?php echo esc_attr( self::FIELD_TEST_TO ); ?>" name="<?php echo esc_attr( self::FIELD_TEST_TO ); ?>" value="<?php echo esc_attr( $to ); ?>" required>
				</p>
				<p class="description"><?php esc_html_e( 'Nothing goes to the club inbox or to the sample visitor, both mails are sent to this address only and marked [TEST].', 'hsc-google-calendar' ); ?></p>
				<?php submit_button( __( 'Send test e-mails', 'hsc-google-calendar' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( null !== $last ) : ?>
				<h3>
					<?php
					/* translators: %s: date and time of the last test send */
					printf( esc_html__( 'Last test send: %s', 'hsc-google-calendar' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $last['sent'] ?? 0 ) ) ) );
					?>
				</h3>
				<?php if ( is_string( $last['error'] ?? null ) ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( $last['error'] ); ?></p></div>
				<?php endif; ?>
				<?php foreach ( is_array( $last['mails'] ?? null ) ? $last['mails'] : array() as $mail ) : ?>
					<?php $error = is_string( $mail['error'] ?? null ) ? $mail['error'] : null; ?>
					<h4><?php echo esc_html( (string) ( $mail['label'] ?? '' ) ); ?></h4>
					<?php if ( null === $error ) : ?>
						<div class="notice notice-success inline"><p><?php esc_html_e( 'Handed over to the mail system. That does not guarantee delivery: check the inbox and the spam folder.', 'hsc-google-calendar' ); ?></p></div>
					<?php else : ?>
						<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Sending failed.', 'hsc-google-calendar' ); ?></strong> <?php echo esc_html( $error ); ?></p></div>
					<?php endif; ?>
					<p>
						<strong><?php esc_html_e( 'To:', 'hsc-google-calendar' ); ?></strong> <?php echo esc_html( (string) ( $mail['to'] ?? '' ) ); ?><br>
						<strong><?php esc_html_e( 'Subject:', 'hsc-google-calendar' ); ?></strong> <?php echo esc_html( (string) ( $mail['subject'] ?? '' ) ); ?>
					</p>
					<pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;border:1px solid #dcdcde"><?php echo esc_html( implode( "\n", is_array( $mail['headers'] ?? null ) ? $mail['headers'] : array() ) . "\n\n" . (string) ( $mail['body'] ?? '' ) ); ?></pre>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the filtered upcoming events as a list.
	 */
	private function render_events_card(): void {
		$result = $this->load_events();
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Events', 'hsc-google-calendar' ); ?></h2>
			<?php if ( is_string( $result ) ) : ?>
				<p class="description"><?php echo esc_html( $result ); ?></p>
			<?php elseif ( array() === $result ) : ?>
				<p><?php esc_html_e( 'No upcoming events match the filter.', 'hsc-google-calendar' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $result as $event ) : ?>
						<li>
							<strong><?php echo esc_html( '' !== $event->summary ? $event->summary : __( '(no title)', 'hsc-google-calendar' ) ); ?></strong>
							<br><span class="description"><?php echo esc_html( $this->format_range( $event ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Loads and filters events.
	 *
	 * @return list<Event>|string Events, or a message why none could be shown.
	 */
	private function load_events(): string|array {
		$calendar_id = $this->settings->calendar_id();
		$json        = $this->settings->credentials_json();
		$api_key     = $this->settings->api_key();
		if ( '' === $calendar_id || ( '' === $json && '' === $api_key ) ) {
			return __( 'Configure the calendar ID and a service account or API key first.', 'hsc-google-calendar' );
		}

		try {
			$filter = Event_Filter::from_pattern( $this->settings->event_regex() );
			$now    = time();

			return $filter->apply(
				$this->client->list_events( $calendar_id, '' === $json ? null : Credentials::from_json( $json ), $api_key, $now, $now )
			);
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Human readable start/end of an event.
	 *
	 * @param Event $event Event.
	 */
	private function format_range( Event $event ): string {
		$start = strtotime( $event->start );
		$end   = strtotime( $event->end );
		if ( false === $start ) {
			return $event->start;
		}
		if ( $event->all_day ) {
			return wp_date( get_option( 'date_format' ), $start );
		}
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return wp_date( $format, $start ) . ( false !== $end ? ' – ' . wp_date( get_option( 'time_format' ), $end ) : '' );
	}

	/**
	 * Service account e-mail of the stored key, null when none or unreadable.
	 */
	private function stored_client_email(): ?string {
		$json = $this->settings->credentials_json();
		if ( '' === $json ) {
			return null;
		}
		try {
			return Credentials::from_json( $json )->client_email;
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$updates = get_site_transient( 'update_plugins' );
		$latest  = is_object( $updates ) && isset( $updates->response[ $this->basename ] )
			? (string) $updates->response[ $this->basename ]->new_version
			: null;
		$error   = $this->updater->last_error();
		$checked = is_object( $updates ) && ! empty( $updates->last_checked ) ? (int) $updates->last_checked : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'HSC Calendar', 'hsc-google-calendar' ); ?></h1>
			<?php if ( isset( $_GET['hsc_checked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php if ( null === $error ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Update check completed.', 'hsc-google-calendar' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( null !== $error ) : ?>
				<div class="notice notice-error"><p>
					<?php
					/* translators: %s: error message */
					printf( esc_html__( 'Update check failed: %s', 'hsc-google-calendar' ), esc_html( $error ) );
					?>
				</p></div>
			<?php endif; ?>
			<?php $this->render_connection_card(); ?>
			<?php $this->render_mail_test_card(); ?>
			<?php $this->render_events_card(); ?>
			<div class="card">
				<h2><?php esc_html_e( 'Plugin', 'hsc-google-calendar' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: plugin version */
						esc_html__( 'Installed version: %s', 'hsc-google-calendar' ),
						'<strong>' . esc_html( $this->version ) . '</strong>'
					);
					?>
				</p>
				<?php if ( null !== $latest ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: new plugin version */
							esc_html__( 'Update available: %s', 'hsc-google-calendar' ),
							'<strong>' . esc_html( $latest ) . '</strong>'
						);
						?>
						<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to plugins', 'hsc-google-calendar' ); ?></a>
					</p>
				<?php elseif ( null === $error ) : ?>
					<p><?php esc_html_e( 'The plugin is up to date.', 'hsc-google-calendar' ); ?></p>
				<?php endif; ?>
				<?php if ( $checked > 0 ) : ?>
					<p class="description">
						<?php
						/* translators: %s: date and time of the last update check */
						printf( esc_html__( 'Last checked: %s', 'hsc-google-calendar' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $checked ) ) );
						?>
					</p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<?php submit_button( __( 'Check for updates', 'hsc-google-calendar' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
