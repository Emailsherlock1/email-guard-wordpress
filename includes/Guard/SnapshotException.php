<?php
/**
 * Raised when the bundled disposable snapshot cannot be loaded.
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Guard;

/**
 * A packaging defect, not a runtime condition: the guard skips the
 * disposable check and keeps working when it sees this.
 */
class SnapshotException extends \RuntimeException {
}
