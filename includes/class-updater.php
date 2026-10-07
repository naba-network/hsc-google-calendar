<?php
/**
 * Self-updater that reads GitHub releases of the (public) plugin repository.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Makes new GitHub releases show up as regular updates in the WordPress admin.
 */
final class Updater {

	public const ASSET_NAME = 'hsc-google-calendar.zip';
	private const CACHE_KEY = 'hsc_gcal_latest_release';
	private const ERROR_KEY = 'hsc_gcal_update_error';
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Creates the updater.
	 *
	 * @param string $basename Plugin basename, e.g. "hsc-google-calendar/hsc-google-calendar.php".
	 * @param string $version  Currently installed version.
	 * @param string $repo     GitHub "owner/name".
	 */
	public function __construct(
		private readonly string $basename,
		private readonly string $version,
		private readonly string $repo
	) {}

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
	}

	/**
	 * Adds the latest release to the update transient when it is newer.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::parse_release( $this->fetch_latest_release() );
		if ( null === $release ) {
			return $transient;
		}

		$slug = dirname( $this->basename );
		$item = (object) array(
			'id'          => $this->repo,
			'slug'        => $slug,
			'plugin'      => $this->basename,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['package'],
		);

		if ( version_compare( $release['version'], $this->version, '>' ) ) {
			$transient->response[ $this->basename ] = $item;
		} else {
			$transient->no_update[ $this->basename ] = $item;
		}

		return $transient;
	}

	/**
	 * Provides data for the "View details" modal.
	 *
	 * @param mixed  $result Existing result.
	 * @param string $action API action.
	 * @param mixed  $args   API arguments.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || dirname( $this->basename ) !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$release = self::parse_release( $this->fetch_latest_release() );
		if ( null === $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'HSC Google Calendar',
			'slug'          => dirname( $this->basename ),
			'version'       => $release['version'],
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'sections'      => array( 'changelog' => nl2br( esc_html( $release['notes'] ) ) ),
		);
	}

	/**
	 * Extracts version and download URL from a GitHub release payload.
	 *
	 * @param array<string, mixed>|null $release Decoded GitHub API response.
	 * @return array{version: string, package: string, url: string, notes: string}|null
	 */
	public static function parse_release( ?array $release ): ?array {
		if ( null === $release || empty( $release['tag_name'] ) || ! is_string( $release['tag_name'] ) ) {
			return null;
		}

		$version = ltrim( $release['tag_name'], 'vV' );
		if ( 1 !== preg_match( '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version ) ) {
			return null;
		}

		foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
			if ( is_array( $asset ) && ( $asset['name'] ?? '' ) === self::ASSET_NAME && ! empty( $asset['browser_download_url'] ) ) {
				return array(
					'version' => $version,
					'package' => (string) $asset['browser_download_url'],
					'url'     => (string) ( $release['html_url'] ?? '' ),
					'notes'   => (string) ( $release['body'] ?? '' ),
				);
			}
		}

		return null;
	}

	/**
	 * Clears all caches and asks WordPress to re-check plugin updates right now.
	 *
	 * @return string|null Error message, or null when the check succeeded.
	 */
	public function force_check(): ?string {
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		return $this->last_error();
	}

	/**
	 * Error of the last GitHub request, if it failed.
	 */
	public function last_error(): ?string {
		$error = get_site_transient( self::ERROR_KEY );
		return is_string( $error ) && '' !== $error ? $error : null;
	}

	/**
	 * Fetches (and caches) the latest release from GitHub.
	 *
	 * @return array<string, mixed>|null
	 */
	private function fetch_latest_release(): ?array {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->repo ),
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->fail( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return $this->fail(
				404 === $code
					? 'GitHub HTTP 404: no release found or the repository is private (it must be public).'
					: sprintf( 'GitHub HTTP %d', $code )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return $this->fail( 'Invalid response from GitHub.' );
		}
		if ( null === self::parse_release( $data ) ) {
			return $this->fail( sprintf( 'Latest release has no valid version tag or no "%s" asset.', self::ASSET_NAME ) );
		}

		delete_site_transient( self::ERROR_KEY );
		set_site_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * Remembers an error for display in the admin page.
	 *
	 * @param string $message Error message.
	 */
	private function fail( string $message ): ?array {
		set_site_transient( self::ERROR_KEY, $message, DAY_IN_SECONDS );
		return null;
	}
}
