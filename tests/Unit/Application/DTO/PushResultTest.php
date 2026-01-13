<?php
/**
 * Unit tests for PushResult.
 *
 * @package Automattic\Syndication\Tests\Unit\Application\DTO
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Application\DTO;

use Automattic\Syndication\Application\DTO\PushResult;
use Automattic\Syndication\Tests\Unit\TestCase;

/**
 * Test case for PushResult.
 *
 * @group unit
 * @covers \Automattic\Syndication\Application\DTO\PushResult
 */
class PushResultTest extends TestCase {

	/**
	 * Test success factory method.
	 */
	public function test_success_creates_correct_result(): void {
		$result = PushResult::success( 123, 456, 'created' );

		$this->assertEquals( 123, $result->site_id );
		$this->assertEquals( PushResult::STATUS_SUCCESS, $result->status );
		$this->assertEquals( 456, $result->remote_id );
		$this->assertEquals( 'created', $result->action );
		$this->assertEquals( '', $result->error_code );
		$this->assertEquals( '', $result->message );
	}

	/**
	 * Test failure factory method.
	 */
	public function test_failure_creates_correct_result(): void {
		$result = PushResult::failure( 123, 'connection_error', 'Could not connect.' );

		$this->assertEquals( 123, $result->site_id );
		$this->assertEquals( PushResult::STATUS_FAILURE, $result->status );
		$this->assertEquals( 0, $result->remote_id );
		$this->assertEquals( '', $result->action );
		$this->assertEquals( 'connection_error', $result->error_code );
		$this->assertEquals( 'Could not connect.', $result->message );
	}

	/**
	 * Test skipped factory method.
	 */
	public function test_skipped_creates_correct_result(): void {
		$result = PushResult::skipped( 123, 'Site is disabled.' );

		$this->assertEquals( 123, $result->site_id );
		$this->assertEquals( PushResult::STATUS_SKIPPED, $result->status );
		$this->assertEquals( 0, $result->remote_id );
		$this->assertEquals( '', $result->action );
		$this->assertEquals( '', $result->error_code );
		$this->assertEquals( 'Site is disabled.', $result->message );
	}

	/**
	 * Test is_success returns true for success status.
	 */
	public function test_is_success_returns_true_for_success(): void {
		$result = PushResult::success( 123, 456, 'updated' );

		$this->assertTrue( $result->is_success() );
		$this->assertFalse( $result->is_failure() );
		$this->assertFalse( $result->is_skipped() );
	}

	/**
	 * Test is_failure returns true for failure status.
	 */
	public function test_is_failure_returns_true_for_failure(): void {
		$result = PushResult::failure( 123, 'error', 'Error message' );

		$this->assertFalse( $result->is_success() );
		$this->assertTrue( $result->is_failure() );
		$this->assertFalse( $result->is_skipped() );
	}

	/**
	 * Test is_skipped returns true for skipped status.
	 */
	public function test_is_skipped_returns_true_for_skipped(): void {
		$result = PushResult::skipped( 123, 'Reason' );

		$this->assertFalse( $result->is_success() );
		$this->assertFalse( $result->is_failure() );
		$this->assertTrue( $result->is_skipped() );
	}

	/**
	 * Test to_array returns correct structure.
	 */
	public function test_to_array_returns_correct_structure(): void {
		$result = PushResult::success( 123, 456, 'created' );
		$array  = $result->to_array();

		$this->assertArrayHasKey( 'site_id', $array );
		$this->assertArrayHasKey( 'status', $array );
		$this->assertArrayHasKey( 'remote_id', $array );
		$this->assertArrayHasKey( 'error_code', $array );
		$this->assertArrayHasKey( 'message', $array );
		$this->assertArrayHasKey( 'action', $array );

		$this->assertEquals( 123, $array['site_id'] );
		$this->assertEquals( 'success', $array['status'] );
		$this->assertEquals( 456, $array['remote_id'] );
		$this->assertEquals( 'created', $array['action'] );
	}

	/**
	 * Test status constants have expected values.
	 */
	public function test_status_constants(): void {
		$this->assertEquals( 'success', PushResult::STATUS_SUCCESS );
		$this->assertEquals( 'failure', PushResult::STATUS_FAILURE );
		$this->assertEquals( 'skipped', PushResult::STATUS_SKIPPED );
	}

	/**
	 * Test success with different actions.
	 *
	 * @dataProvider action_provider
	 *
	 * @param string $action The action to test.
	 */
	public function test_success_with_various_actions( string $action ): void {
		$result = PushResult::success( 1, 2, $action );

		$this->assertEquals( $action, $result->action );
	}

	/**
	 * Provide action values for testing.
	 *
	 * @return array<string, array<string>>
	 */
	public static function action_provider(): array {
		return array(
			'created' => array( 'created' ),
			'updated' => array( 'updated' ),
			'deleted' => array( 'deleted' ),
		);
	}
}
