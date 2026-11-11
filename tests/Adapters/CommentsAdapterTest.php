<?php
/**
 * Comments adapter: deny turns the submission into a WP_Error, flag holds
 * for moderation, trusted paths pass through.
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters;

use HostSherlock\EmailGuardWp\Adapters\CommentsAdapter;
use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
use PHPUnit\Framework\TestCase;

final class CommentsAdapterTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	private function comment_data( array $overrides = array() ): array {
		return array_merge(
			array(
				'comment_type'         => 'comment',
				'comment_author_email' => 'visitor@mailinator.com',
				'user_id'              => 0,
			),
			$overrides
		);
	}

	public function testIdAndAvailability(): void {
		$adapter = new CommentsAdapter();
		$this->assertSame( 'comments', $adapter->id() );
		$this->assertTrue( $adapter->is_available() );
	}

	public function testDeniedDecisionBecomesWpError(): void {
		$service = FakeGuardService::denying( 'Blocked address.' );
		$adapter = new CommentsAdapter();
		$adapter->register( $service );

		$approved = apply_filters( 'pre_comment_approved', 1, $this->comment_data() );

		$this->assertInstanceOf( \WP_Error::class, $approved );
		$this->assertSame( 'Blocked address.', $approved->get_error_message() );
		$this->assertSame( 200, $approved->get_error_data() );
		$this->assertSame( 'visitor@mailinator.com', $service->checks[0]['email'] );
		$this->assertSame( 'comments', $service->checks[0]['form_id'] );
	}

	public function testFlaggedDecisionHoldsApprovedCommentForModeration(): void {
		$adapter = new CommentsAdapter();
		$adapter->register( FakeGuardService::flagging() );

		$this->assertSame( '0', apply_filters( 'pre_comment_approved', 1, $this->comment_data() ) );
	}

	public function testFlaggedDecisionLeavesAlreadyHeldCommentAlone(): void {
		$adapter = new CommentsAdapter();
		$adapter->register( FakeGuardService::flagging() );

		$this->assertSame( 0, apply_filters( 'pre_comment_approved', 0, $this->comment_data() ) );
	}

	public function testAllowedDecisionPassesThrough(): void {
		$adapter = new CommentsAdapter();
		$adapter->register( FakeGuardService::allowing() );

		$this->assertSame( 1, apply_filters( 'pre_comment_approved', 1, $this->comment_data() ) );
	}

	public function testExistingErrorAndSpamPassThroughWithoutCheck(): void {
		$service = FakeGuardService::denying();
		$adapter = new CommentsAdapter();
		$adapter->register( $service );

		$error = new \WP_Error( 'other', 'earlier error' );
		$this->assertSame( $error, apply_filters( 'pre_comment_approved', $error, $this->comment_data() ) );
		$this->assertSame( 'spam', apply_filters( 'pre_comment_approved', 'spam', $this->comment_data() ) );
		$this->assertSame( array(), $service->checks );
	}

	public function testPingbacksAndLoggedInUsersSkipTheCheck(): void {
		$service = FakeGuardService::denying();
		$adapter = new CommentsAdapter();
		$adapter->register( $service );

		$this->assertSame( 1, apply_filters( 'pre_comment_approved', 1, $this->comment_data( array( 'comment_type' => 'pingback' ) ) ) );
		$this->assertSame( 1, apply_filters( 'pre_comment_approved', 1, $this->comment_data( array( 'user_id' => 5 ) ) ) );
		$this->assertSame( array(), $service->checks );
	}
}
