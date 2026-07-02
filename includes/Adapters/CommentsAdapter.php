<?php
/**
 * Guards the WordPress core comment form.
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Adapters;

use EmailSherlock\EmailGuardWp\GuardService;

/**
 * Hooks pre_comment_approved: a denied address turns the submission into a
 * WP_Error (core renders the message on the comment error page), a flagged
 * one is held for moderation instead of approved.
 */
class CommentsAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'comments';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Comments', 'emailsherlock-email-guard' );
	}

	/**
	 * @return bool Core surface, always present.
	 */
	public function is_available() {
		return true;
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_filter( 'pre_comment_approved', array( $this, 'filter_approval' ), 20, 2 );
	}

	/**
	 * @param int|string|\WP_Error $approved     The approval status so far.
	 * @param array                $comment_data Comment data.
	 * @return int|string|\WP_Error
	 */
	public function filter_approval( $approved, $comment_data ) {
		// Someone earlier already rejected or binned it; nothing to add.
		if ( is_wp_error( $approved ) || 'spam' === $approved || 'trash' === $approved ) {
			return $approved;
		}

		// Only visitor comments; pingbacks and trackbacks carry no typed address.
		$type = isset( $comment_data['comment_type'] ) ? $comment_data['comment_type'] : '';
		if ( '' !== $type && 'comment' !== $type ) {
			return $approved;
		}

		// Trusted authors skip the check: they proved their address at signup.
		if ( ! empty( $comment_data['user_id'] ) ) {
			return $approved;
		}

		$email    = isset( $comment_data['comment_author_email'] ) ? (string) $comment_data['comment_author_email'] : '';
		$decision = $this->service->check( $email, $this->id() );

		if ( $decision->is_denied() ) {
			// Data 200 keeps core's comment error page with a back link.
			return new \WP_Error( 'emailsherlock_email_guard_denied', $decision->message(), 200 );
		}

		if ( $decision->is_flagged() && 1 === (int) $approved ) {
			// Flag = let it in, but hold it for moderation.
			return '0';
		}

		return $approved;
	}
}
