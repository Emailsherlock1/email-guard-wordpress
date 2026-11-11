<?php
/**
 * Action constants (email-guard-spec, section 6.4).
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the form should do with the address.
 */
final class Action {

	const ALLOW  = 'allow';
	const DENY   = 'deny';
	const REVIEW = 'review';

	private function __construct() {
	}
}
