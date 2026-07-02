<?php
/**
 * Decision log: ring buffer, domain-only, honors the enable toggle.
 */

namespace EmailSherlock\EmailGuardWp\Tests;

use EmailSherlock\EmailGuardWp\DecisionLog;
use EmailSherlock\EmailGuardWp\Guard\Action;
use EmailSherlock\EmailGuardWp\Guard\Result;
use EmailSherlock\EmailGuardWp\Guard\Verdict;
use EmailSherlock\EmailGuardWp\Settings;
use PHPUnit\Framework\TestCase;

final class DecisionLogTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	private function result( string $domain ): Result {
		return new Result( Verdict::DISPOSABLE, Action::DENY, array( 'disposable_provider' ), false, false, null, false, null, $domain );
	}

	public function testAddStoresDomainOnlyNewestFirst(): void {
		$log = new DecisionLog( new Settings() );
		$log->add( 'comments', $this->result( 'a.test' ) );
		$log->add( 'wpforms', $this->result( 'b.test' ) );

		$entries = $log->entries();
		$this->assertCount( 2, $entries );
		$this->assertSame( 'b.test', $entries[0]['domain'] );
		$this->assertSame( 'wpforms', $entries[0]['form'] );
		$this->assertSame( 'deny', $entries[0]['action'] );
		$this->assertSame( array( 'disposable_provider' ), $entries[0]['reasons'] );
		// Only these keys: no address, no local part, ever.
		$this->assertSame(
			array( 'time', 'form', 'domain', 'verdict', 'action', 'reasons', 'degraded' ),
			array_keys( $entries[0] )
		);
	}

	public function testCapsAtConfiguredSize(): void {
		update_option( Settings::OPTION, array( 'log_size' => 2 ) );
		$log = new DecisionLog( new Settings() );
		$log->add( 'comments', $this->result( 'a.test' ) );
		$log->add( 'comments', $this->result( 'b.test' ) );
		$log->add( 'comments', $this->result( 'c.test' ) );

		$entries = $log->entries();
		$this->assertCount( 2, $entries );
		$this->assertSame( 'c.test', $entries[0]['domain'] );
		$this->assertSame( 'b.test', $entries[1]['domain'] );
	}

	public function testDisabledLogWritesNothing(): void {
		update_option( Settings::OPTION, array( 'log_enabled' => false ) );
		$log = new DecisionLog( new Settings() );
		$log->add( 'comments', $this->result( 'a.test' ) );

		$this->assertSame( array(), $log->entries() );
	}

	public function testClear(): void {
		$log = new DecisionLog( new Settings() );
		$log->add( 'comments', $this->result( 'a.test' ) );
		$log->clear();

		$this->assertSame( array(), $log->entries() );
	}
}
