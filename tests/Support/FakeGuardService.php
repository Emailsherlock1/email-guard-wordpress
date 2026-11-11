<?php
/**
 * GuardService test double for adapter tests.
 */

namespace HostSherlock\EmailGuardWp\Tests\Support;

use HostSherlock\EmailGuardWp\Decision;
use HostSherlock\EmailGuardWp\GuardService;
use HostSherlock\EmailGuardWp\Settings;

/**
 * Returns a canned Decision and records every check() call, so adapter
 * tests assert only the adapter's wiring, not the engine.
 */
final class FakeGuardService extends GuardService {

	/** @var Decision */
	private $decision;

	/** @var array<int, array{email: string, form_id: string}> */
	public $checks = array();

	public function __construct( Decision $decision ) {
		parent::__construct( new Settings() );
		$this->decision = $decision;
	}

	/**
	 * @param string $email   Raw form input.
	 * @param string $form_id Adapter id.
	 * @return Decision
	 */
	public function check( $email, $form_id ) {
		$this->checks[] = array(
			'email'   => $email,
			'form_id' => $form_id,
		);

		return $this->decision;
	}

	public static function denying( string $message = 'blocked' ): self {
		return new self( new Decision( null, true, false, $message ) );
	}

	public static function flagging(): self {
		return new self( new Decision( null, false, true ) );
	}

	public static function allowing(): self {
		return new self( new Decision( null, false, false ) );
	}
}
