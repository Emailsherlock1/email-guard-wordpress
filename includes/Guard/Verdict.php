<?php
/**
 * Verdict constants (email-guard-spec, section 6.1).
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Guard;

/**
 * Classification of an address. Mirrors the `result` enum of the Verify API.
 * Plain constants instead of a PHP 8.1 enum: the plugin targets PHP 7.4.
 */
final class Verdict {

	const VALID      = 'valid';
	const INVALID    = 'invalid';
	const DISPOSABLE = 'disposable';
	const ROLE       = 'role';
	const CATCH_ALL  = 'catch_all';
	const UNKNOWN    = 'unknown';

	const ALL = array(
		self::VALID,
		self::INVALID,
		self::DISPOSABLE,
		self::ROLE,
		self::CATCH_ALL,
		self::UNKNOWN,
	);

	/**
	 * Coerce an arbitrary API `result` value to a known verdict.
	 *
	 * @param mixed $value Raw value from the API response.
	 * @return string One of the verdict constants; UNKNOWN for anything else.
	 */
	public static function from_api( $value ) {
		if ( is_string( $value ) && in_array( $value, self::ALL, true ) ) {
			return $value;
		}

		return self::UNKNOWN;
	}

	private function __construct() {
	}
}
