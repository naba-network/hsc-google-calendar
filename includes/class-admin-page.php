<?php
/**
 * Admin menu page with an overview of the plugin.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Top-level "HSC Calendar" admin page.
 */
final class Admin_Page {

	public const SLUG   = 'hsc-google-calendar';
	public const ACTION = 'hsc_gcal_check_updates';

	/**
	 * Creates the admin page.
	 *
	 * @param string  $basename Plugin basename.
	 * @param string  $version  Currently installed version.
	 * @param Updater $updater  Updater used for the manual check.
	 */
	public function __construct(
		private readonly string $basename,
		private readonly string $version,
		private readonly Updater $updater
	) {}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_check_updates' ) );
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
