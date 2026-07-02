<?php
/**
 * What an adapter should do with one submission.
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp;

use EmailSherlock\EmailGuardWp\Guard\Result;

/**
 * The service-level wrapper around a guard Result: deny carries the
 * visitor-facing message, flag means let it through but mark it.
 */
class Decision {

	/** @var Result|null Null when the check was skipped (empty input, disabled form). */
	public $result;

	/** @var bool */
	private $denied;

	/** @var bool */
	private $flagged;

	/** @var string */
	private $message;

	/**
	 * @param Result|null $result  Underlying guard result, if a check ran.
	 * @param bool        $denied  Whether the submission should be rejected.
	 * @param bool        $flagged Whether the submission should be marked for review.
	 * @param string      $message Visitor-facing error message for a denial.
	 */
	public function __construct( $result, $denied, $flagged, $message = '' ) {
		$this->result  = $result;
		$this->denied  = $denied;
		$this->flagged = $flagged;
		$this->message = $message;
	}

	/** @return bool */
	public function is_denied() {
		return $this->denied;
	}

	/** @return bool */
	public function is_flagged() {
		return $this->flagged;
	}

	/** @return bool */
	public function is_allowed() {
		return ! $this->denied;
	}

	/** @return string Visitor-facing message; empty unless denied. */
	public function message() {
		return $this->message;
	}
}
