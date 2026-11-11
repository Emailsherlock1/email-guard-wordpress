<?php
/**
 * Reserved names that can never receive public mail
 * (email-guard-spec, section 4.2).
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The lists are part of the spec; changing them here without a spec release
 * breaks conformance.
 */
final class ReservedTld {

	const RESERVED_TLDS = array(
		'test'      => true,
		'example'   => true,
		'invalid'   => true,
		'localhost' => true,
		'local'     => true,
		'onion'     => true,
		'alt'       => true,
		'internal'  => true,
	);

	const RESERVED_DOMAINS = array( 'example.com', 'example.net', 'example.org' );

	/**
	 * @param string $domain Lowercased domain part.
	 * @return bool
	 */
	public static function is_reserved( $domain ) {
		$dot_position = strrpos( $domain, '.' );
		$tld          = false === $dot_position ? $domain : substr( $domain, $dot_position + 1 );
		if ( isset( self::RESERVED_TLDS[ $tld ] ) ) {
			return true;
		}
		foreach ( self::RESERVED_DOMAINS as $reserved ) {
			if ( $domain === $reserved || substr( $domain, -strlen( '.' . $reserved ) ) === '.' . $reserved ) {
				return true;
			}
		}

		return false;
	}

	private function __construct() {
	}
}
