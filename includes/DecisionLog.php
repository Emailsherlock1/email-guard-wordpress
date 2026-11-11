<?php
/**
 * Local decision log: the last N guard decisions, domain-only.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp;

use HostSherlock\EmailGuardWp\Guard\Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A ring buffer in one non-autoloaded option. Stores the domain, never the
 * address: the local part is personal data and has no diagnostic value here.
 */
class DecisionLog {

	const OPTION = 'hostsherlock_email_guard_log';

	/** @var Settings */
	private $settings;

	/**
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Record one decision. No-op when the log is disabled.
	 *
	 * @param string $form_id Adapter id the decision came from.
	 * @param Result $result  The guard decision.
	 */
	public function add( $form_id, Result $result ) {
		if ( ! $this->settings->get( 'log_enabled' ) ) {
			return;
		}

		$entries = $this->entries();
		array_unshift(
			$entries,
			array(
				'time'     => time(),
				'form'     => $form_id,
				'domain'   => $result->domain,
				'verdict'  => $result->verdict,
				'action'   => $result->action,
				'reasons'  => $result->reasons,
				'degraded' => $result->degraded,
			)
		);

		$size = max( 1, (int) $this->settings->get( 'log_size' ) );
		update_option( self::OPTION, array_slice( $entries, 0, $size ), false );
	}

	/**
	 * @return array[] Newest first.
	 */
	public function entries() {
		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Drop all entries.
	 */
	public function clear() {
		delete_option( self::OPTION );
	}
}
