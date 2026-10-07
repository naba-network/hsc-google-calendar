<?php
/**
 * Self-updater based on plugin-update-checker, reading GitHub releases.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\GitHubApi;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\PluginUpdateChecker;

/**
 * Makes new GitHub releases show up as regular updates in the WordPress admin.
 */
final class Updater {

	private const ERROR_KEY = 'hsc_gcal_update_error';

	/**
	 * Underlying update checker, null until registered.
	 *
	 * @var PluginUpdateChecker|null
	 */
	private ?PluginUpdateChecker $checker = null;

	/**
	 * Creates the updater.
	 *
	 * @param string $plugin_file Absolute path of the main plugin file.
	 * @param string $repo        GitHub "owner/name". The repository must be public.
	 * @param string $slug        Plugin slug.
	 */
	public function __construct(
		private readonly string $plugin_file,
		private readonly string $repo,
		private readonly string $slug
	) {}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$checker = PucFactory::buildUpdateChecker(
			sprintf( 'https://github.com/%s/', $this->repo ),
			$this->plugin_file,
			$this->slug
		);
		if ( ! $checker instanceof PluginUpdateChecker ) {
			return;
		}

		// Update from the zip attached to the GitHub release by the CI pipeline.
		// Branch zipballs must not be used: they lack the vendor/ directory.
		$api = $checker->getVcsApi();
		if ( $api instanceof GitHubApi ) {
			$api->enableReleaseAssets();
		}

		$this->checker = $checker;
	}

	/**
	 * Checks GitHub for a new release right now, bypassing the update cache.
	 *
	 * @return string|null Error message, or null when the check succeeded.
	 */
	public function force_check(): ?string {
		if ( null === $this->checker ) {
			return $this->remember_error( 'plugin-update-checker is not installed (run composer install).' );
		}

		$this->checker->checkForUpdates();

		$errors = $this->checker->getLastRequestApiErrors();
		if ( array() === $errors ) {
			delete_site_transient( self::ERROR_KEY );
			return null;
		}

		$last    = end( $errors );
		$error   = $last['error'] ?? null;
		$code    = wp_remote_retrieve_response_code( $last['httpResponse'] ?? array() );
		$message = is_wp_error( $error ) ? $error->get_error_message() : '';
		if ( 404 === $code ) {
			$message = 'GitHub HTTP 404: no release found or the repository is private (it must be public).';
		} elseif ( '' === $message ) {
			$message = sprintf( 'GitHub HTTP %d', (int) $code );
		}

		return $this->remember_error( $message );
	}

	/**
	 * Error of the last manual check, if it failed.
	 */
	public function last_error(): ?string {
		$error = get_site_transient( self::ERROR_KEY );
		return is_string( $error ) && '' !== $error ? $error : null;
	}

	/**
	 * Remembers an error for display in the admin page.
	 *
	 * @param string $message Error message.
	 */
	private function remember_error( string $message ): string {
		set_site_transient( self::ERROR_KEY, $message, DAY_IN_SECONDS );
		return $message;
	}
}
