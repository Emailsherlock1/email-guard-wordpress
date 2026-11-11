<?php
/**
 * Raised when the bundled disposable snapshot cannot be loaded.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A packaging defect, not a runtime condition: the guard skips the
 * disposable check and keeps working when it sees this.
 */
class SnapshotException extends \RuntimeException {
}
