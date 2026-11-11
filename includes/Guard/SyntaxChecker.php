<?php
/**
 * The pragmatic syntax profile from email-guard-spec, section 4.1.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Guard;

/**
 * Structural rules (exactly one @, both parts non-empty) always apply.
 * Profile rules are skipped for a part that contains non-ASCII characters:
 * the guard only blocks what it can prove is junk, and internationalized
 * addresses (RFC 6531) are the API's job, not a regex's.
 */
final class SyntaxChecker {

	const LOCAL_PATTERN = '/^[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~.-]+$/';
	const LABEL_PATTERN = '/^[A-Za-z0-9-]+$/';

	/**
	 * Whether the trimmed address passes the spec's syntax profile.
	 *
	 * @param string $address Trimmed address under test.
	 * @return bool
	 */
	public static function is_valid( $address ) {
		if ( 1 !== substr_count( $address, '@' ) ) {
			return false;
		}
		list( $local, $domain ) = explode( '@', $address, 2 );
		if ( '' === $local || '' === $domain ) {
			return false;
		}
		if ( self::is_ascii( $local ) && ! self::local_part_ok( $local ) ) {
			return false;
		}
		if ( self::is_ascii( $domain ) && ! self::domain_part_ok( $domain ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $part Address part.
	 * @return bool
	 */
	private static function is_ascii( $part ) {
		return 1 !== preg_match( '/[^\x00-\x7F]/', $part );
	}

	/**
	 * @param string $local Local part.
	 * @return bool
	 */
	private static function local_part_ok( $local ) {
		if ( strlen( $local ) > 64 ) {
			return false;
		}
		if ( 1 !== preg_match( self::LOCAL_PATTERN, $local ) ) {
			return false;
		}
		if ( '.' === $local[0] || '.' === substr( $local, -1 ) || false !== strpos( $local, '..' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $domain Domain part.
	 * @return bool
	 */
	private static function domain_part_ok( $domain ) {
		$length = strlen( $domain );
		if ( $length < 4 || $length > 253 ) {
			return false;
		}
		$labels = explode( '.', $domain );
		if ( count( $labels ) < 2 ) {
			return false;
		}
		foreach ( $labels as $label ) {
			$label_length = strlen( $label );
			if ( $label_length < 1 || $label_length > 63 ) {
				return false;
			}
			if ( 1 !== preg_match( self::LABEL_PATTERN, $label ) ) {
				return false;
			}
			if ( '-' === $label[0] || '-' === substr( $label, -1 ) ) {
				return false;
			}
		}
		$tld = $labels[ count( $labels ) - 1 ];
		if ( strlen( $tld ) < 2 || 1 === preg_match( '/^[0-9]+$/', $tld ) ) {
			return false;
		}

		return true;
	}
}
