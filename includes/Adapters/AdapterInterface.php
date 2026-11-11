<?php
/**
 * Contract every form adapter implements.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Adapters;

use HostSherlock\EmailGuardWp\GuardService;

/**
 * An adapter owns one form surface: it knows how to detect its host plugin,
 * where the email field lives, and how to reject or flag a submission in
 * that plugin's own vocabulary. Adding support for another form plugin means
 * adding one class implementing this and listing it in Plugin::adapters().
 */
interface AdapterInterface {

	/**
	 * Stable id, also the key of the per-form toggle in settings.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Human label for the settings screen.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the host plugin (or core surface) is present. Called on every
	 * request; keep it a cheap class_exists / function_exists probe.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Hook into the form. Only called when is_available() and the per-form
	 * toggle are both true.
	 *
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service );
}
