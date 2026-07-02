<?php
/**
 * Plugin settings: one option, sane defaults, policy mapping.
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp;

use EmailSherlock\EmailGuardWp\Guard\Verdict;

/**
 * Policy belongs to you, the site owner, not to the server: every threshold
 * and toggle lives here (email-guard design principle). The per-verdict
 * actions map onto the spec's block_on / review_on lists.
 */
class Settings {

	const OPTION = 'emailsherlock_email_guard_settings';

	/** Per-verdict actions the UI offers. */
	const ACTION_BLOCK = 'block';
	const ACTION_FLAG  = 'flag';
	const ACTION_ALLOW = 'allow';

	/**
	 * Decision tokens you can act on: verdicts (minus `valid`, which is
	 * always allowed) plus the `relay` signal.
	 */
	const POLICY_TOKENS = array( 'invalid', 'disposable', 'role', 'catch_all', 'unknown', 'relay' );

	/** @var array|null Cached merged settings. */
	private $settings = null;

	/**
	 * @return array Default settings.
	 */
	public static function defaults() {
		return array(
			'api_key'         => '',
			'site_key'        => '',
			'fail_open'       => true,
			'verdict_actions' => array(
				'invalid'    => self::ACTION_BLOCK,
				'disposable' => self::ACTION_BLOCK,
				'role'       => self::ACTION_ALLOW,
				'catch_all'  => self::ACTION_ALLOW,
				'unknown'    => self::ACTION_ALLOW,
				'relay'      => self::ACTION_FLAG,
			),
			'forms'           => array(
				'comments'       => true,
				'registration'   => true,
				'woocommerce'    => true,
				'contact_form_7' => true,
				'wpforms'        => true,
				'gravity_forms'  => true,
			),
			'blocked_message' => '',
			'log_enabled'     => true,
			'log_size'        => 20,
		);
	}

	/**
	 * @return array Stored settings merged over the defaults.
	 */
	public function all() {
		if ( null === $this->settings ) {
			$stored   = get_option( self::OPTION, array() );
			$defaults = self::defaults();
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			$merged = array_merge( $defaults, $stored );
			// Nested maps merge key-wise so a partial save keeps defaults.
			foreach ( array( 'verdict_actions', 'forms' ) as $map ) {
				$merged[ $map ] = array_merge(
					$defaults[ $map ],
					isset( $stored[ $map ] ) && is_array( $stored[ $map ] ) ? $stored[ $map ] : array()
				);
			}
			$this->settings = $merged;
		}

		return $this->settings;
	}

	/**
	 * @param string $key Settings key.
	 * @return mixed
	 */
	public function get( $key ) {
		$all = $this->all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * @return string|null Trimmed API key, null when not configured.
	 */
	public function api_key() {
		$key = trim( (string) $this->get( 'api_key' ) );

		return '' !== $key ? $key : null;
	}

	/**
	 * @return string|null Trimmed site key (Guard property), null when not set.
	 */
	public function site_key() {
		$key = trim( (string) $this->get( 'site_key' ) );

		return '' !== $key ? $key : null;
	}

	/**
	 * Tokens whose configured action is "block" (spec block_on).
	 *
	 * @return string[]
	 */
	public function block_on() {
		return $this->tokens_with_action( self::ACTION_BLOCK );
	}

	/**
	 * Tokens whose configured action is "flag" (spec review_on).
	 *
	 * @return string[]
	 */
	public function review_on() {
		return $this->tokens_with_action( self::ACTION_FLAG );
	}

	/**
	 * @param string $form_id Adapter id.
	 * @return bool Whether protection is on for that form.
	 */
	public function form_enabled( $form_id ) {
		$forms = $this->get( 'forms' );

		return ! empty( $forms[ $form_id ] );
	}

	/**
	 * Guard engine config derived from these settings (spec section 6.3).
	 *
	 * @return array
	 */
	public function guard_config() {
		$config = array(
			'api_key'    => $this->api_key(),
			'block_on'   => $this->block_on(),
			'review_on'  => $this->review_on(),
			'fail_open'  => (bool) $this->get( 'fail_open' ),
			'timeout_ms' => (int) apply_filters( 'emailsherlock_email_guard_timeout_ms', 800 ),
			'base_url'   => (string) apply_filters( 'emailsherlock_email_guard_base_url', 'https://api.emailsherlock.com' ),
		);

		return $config;
	}

	/**
	 * The message a visitor sees on a blocked submission.
	 *
	 * @return string
	 */
	public function blocked_message() {
		$custom = trim( (string) $this->get( 'blocked_message' ) );
		if ( '' !== $custom ) {
			return $custom;
		}

		return __( 'This email address can\'t receive mail. Check it for typos.', 'emailsherlock-email-guard' );
	}

	/**
	 * Sanitize callback for register_setting.
	 *
	 * @param mixed $raw Raw submitted value.
	 * @return array Clean settings array.
	 */
	public function sanitize( $raw ) {
		$defaults = self::defaults();
		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$clean             = array();
		$clean['api_key']  = isset( $raw['api_key'] ) ? sanitize_text_field( (string) $raw['api_key'] ) : '';
		$clean['site_key'] = isset( $raw['site_key'] ) ? sanitize_text_field( (string) $raw['site_key'] ) : '';

		// Checkboxes: absent means unchecked.
		$clean['fail_open']   = ! empty( $raw['fail_open'] );
		$clean['log_enabled'] = ! empty( $raw['log_enabled'] );

		$clean['verdict_actions'] = array();
		$allowed_actions          = array( self::ACTION_BLOCK, self::ACTION_FLAG, self::ACTION_ALLOW );
		foreach ( self::POLICY_TOKENS as $token ) {
			$action = isset( $raw['verdict_actions'][ $token ] ) ? (string) $raw['verdict_actions'][ $token ] : $defaults['verdict_actions'][ $token ];
			if ( ! in_array( $action, $allowed_actions, true ) ) {
				$action = $defaults['verdict_actions'][ $token ];
			}
			$clean['verdict_actions'][ $token ] = $action;
		}

		$clean['forms'] = array();
		foreach ( array_keys( $defaults['forms'] ) as $form_id ) {
			$clean['forms'][ $form_id ] = ! empty( $raw['forms'][ $form_id ] );
		}

		$clean['blocked_message'] = isset( $raw['blocked_message'] ) ? sanitize_text_field( (string) $raw['blocked_message'] ) : '';

		$size              = isset( $raw['log_size'] ) ? (int) $raw['log_size'] : $defaults['log_size'];
		$clean['log_size'] = max( 1, min( 200, $size ) );

		// Bust the in-memory cache so a save is visible immediately.
		$this->settings = null;

		return $clean;
	}

	/**
	 * @param string $action One of the ACTION_* constants.
	 * @return string[] Policy tokens configured to that action.
	 */
	private function tokens_with_action( $action ) {
		$actions = $this->get( 'verdict_actions' );
		$tokens  = array();
		foreach ( self::POLICY_TOKENS as $token ) {
			if ( isset( $actions[ $token ] ) && $actions[ $token ] === $action ) {
				$tokens[] = $token;
			}
		}

		return $tokens;
	}
}
