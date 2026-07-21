<?php
/**
 * The bundled disposable-domain snapshot (email-guard-spec, sections 4.3
 * and 9.1).
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Guard;

/**
 * Matching is exact by design: the Verify API matches its live list the
 * same way, and the local check must never block an address the API would
 * let pass.
 */
class DisposableSnapshot {

	const FORMAT = 'email-guard-disposable/1';

	/** @var string */
	private $path;

	/** @var array<string, true>|null */
	private $domains = null;

	/** @var string|null */
	private $version = null;

	/**
	 * @param string|null $path Snapshot file (.json or .json.gz); defaults to the bundled one.
	 */
	public function __construct( $path = null ) {
		$this->path = null !== $path ? $path : dirname( __DIR__, 2 ) . '/data/disposable-snapshot.json';
	}

	/**
	 * @param string $domain Lowercased domain part.
	 * @return bool
	 * @throws SnapshotException When the snapshot cannot be loaded.
	 */
	public function is_disposable( $domain ) {
		$this->load();

		return isset( $this->domains[ $domain ] );
	}

	/**
	 * @return string Calendar version of the bundled export.
	 * @throws SnapshotException When the snapshot cannot be loaded.
	 */
	public function version() {
		$this->load();

		return null !== $this->version ? $this->version : '';
	}

	/**
	 * @throws SnapshotException When the snapshot cannot be loaded.
	 */
	private function load() {
		if ( null !== $this->domains ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal SnapshotException, caught by the guard's fail-open path, never rendered to a visitor.
		$raw = @file_get_contents( $this->path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- failure is classified below.
		if ( false === $raw ) {
			throw new SnapshotException( "Cannot read disposable snapshot at {$this->path}" );
		}
		if ( '.gz' === substr( $this->path, -3 ) ) {
			if ( ! function_exists( 'gzdecode' ) ) {
				throw new SnapshotException( 'zlib is not available to gunzip the disposable snapshot' );
			}
			$raw = @gzdecode( $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- failure is classified below.
			if ( false === $raw ) {
				throw new SnapshotException( "Cannot gunzip disposable snapshot at {$this->path}" );
			}
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || self::FORMAT !== ( isset( $data['format'] ) ? $data['format'] : null ) || ! isset( $data['domains'] ) || ! is_array( $data['domains'] ) ) {
			throw new SnapshotException( "Disposable snapshot at {$this->path} is not a valid " . self::FORMAT . ' document' );
		}
		if ( ( isset( $data['count'] ) ? $data['count'] : -1 ) !== count( $data['domains'] ) ) {
			throw new SnapshotException( "Disposable snapshot at {$this->path} fails its count check" );
		}

		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		$this->domains = array_fill_keys( $data['domains'], true );
		$this->version = isset( $data['version'] ) && is_string( $data['version'] ) ? $data['version'] : '';
	}
}
