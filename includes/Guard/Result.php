<?php
/**
 * Outcome of one guard check (email-guard-spec, section 7).
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Guard;

/**
 * The four spec fields plus the extras the spec allows: the raw API
 * response, the relay signal, and the domain (for domain-only telemetry).
 */
class Result {

	/** @var string One of the Verdict constants. */
	public $verdict;

	/** @var string One of the Action constants. */
	public $action;

	/** @var string[] Ordered reason codes, possibly empty. */
	public $reasons;

	/** @var bool True only when a wanted API call failed. */
	public $degraded;

	/** @var bool Whether the transport was hit at all. */
	public $api_called;

	/** @var array|null Raw decoded API response, informational only. */
	public $api_response;

	/**
	 * The relay signal (spec 6.5): the address sits on a relay / forwarding /
	 * alias-masking service. Only ever set by the remote check.
	 *
	 * @var bool
	 */
	public $relay;

	/** @var string|null Service name when $relay is true, informational. */
	public $relay_provider;

	/**
	 * Lowercased domain part of the checked address, or null when the input
	 * had no usable domain. The only address-derived value telemetry and the
	 * decision log may ever see.
	 *
	 * @var string|null
	 */
	public $domain;

	/**
	 * @param string      $verdict        Verdict constant.
	 * @param string      $action         Action constant.
	 * @param string[]    $reasons        Ordered reason codes.
	 * @param bool        $degraded       Degraded flag.
	 * @param bool        $api_called     Whether the transport was hit.
	 * @param array|null  $api_response   Raw API response.
	 * @param bool        $relay          Relay signal.
	 * @param string|null $relay_provider Relay service name.
	 * @param string|null $domain         Lowercased domain part.
	 */
	public function __construct( $verdict, $action, $reasons, $degraded, $api_called, $api_response = null, $relay = false, $relay_provider = null, $domain = null ) {
		$this->verdict        = $verdict;
		$this->action         = $action;
		$this->reasons        = $reasons;
		$this->degraded       = $degraded;
		$this->api_called     = $api_called;
		$this->api_response   = $api_response;
		$this->relay          = $relay;
		$this->relay_provider = $relay_provider;
		$this->domain         = $domain;
	}

	/** @return bool */
	public function is_allowed() {
		return Action::ALLOW === $this->action;
	}

	/** @return bool */
	public function is_denied() {
		return Action::DENY === $this->action;
	}

	/** @return bool */
	public function needs_review() {
		return Action::REVIEW === $this->action;
	}
}
