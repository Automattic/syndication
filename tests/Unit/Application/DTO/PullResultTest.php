<?php
/**
 * Unit tests for PullResult.
 *
 * @package Automattic\Syndication\Tests\Unit\Application\DTO
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Tests\Unit\Application\DTO;

use Automattic\Syndication\Application\DTO\PullResult;
use Automattic\Syndication\Tests\Unit\TestCase;

/**
 * Test case for PullResult.
 *
 * @group unit
 * @covers \Automattic\Syndication\Application\DTO\PullResult
 */
class PullResultTest extends TestCase {

	/**
	 * Test success factory method.
	 */
	public function test_success_creates_correct_result(): void {
		$result = PullResult::success( 123, 5, 3 );

		$this->assertEquals( 123, $result->site_id );
		$this->assertEquals( PullResult::STATUS_SUCCESS, $result->status );
		$this->assertEquals( 5, $result->created );
		$this->assertEquals( 3, $result->updated );
		$this->assertEquals( 0, $result->skipped );
		$this->assertEquals( '', $result->error_code );
		$this->assertEquals( '', $result->message );
		$this->assertEquals( array(), $result->errors );
	}

	/**
	 * Test failure factory method.
	 */
	public function test_failure_creates_correct_result(): void {
		$result = PullResult::failure( 123, 'invalid_site', 'Site not found.' );

		$this->assertEquals( 123, $result->site_id );
		$this->assertEquals( PullResult::STATUS_FAILURE, $result->status );
		$this->assertEquals( 0, $result->created );
		$this->assertEquals( 0, $result->updated );
		$this->assertEquals( 0, $result->skipped );
		$this->assertEquals( 'invalid_site', $result->error_code );
		$this->assertEquals( 'Site not found.', $result->message );
		$this->assertEquals( array(), $result->errors );
	}

	/**
	 * Test skipped factory method.
	 */
	public function test_skipped_creates_correct_result(): void {
		$result = PullResult::skipped( 123, 'Site is disabled.' );

		$this->assertEquals( 123, $result->site_id );
		$this->assertEquals( PullResult::STATUS_SKIPPED, $result->status );
		$this->assertEquals( 0, $result->created );
		$this->assertEquals( 0, $result->updated );
		$this->assertEquals( 0, $result->skipped );
		$this->assertEquals( '', $result->error_code );
		$this->assertEquals( 'Site is disabled.', $result->message );
		$this->assertEquals( array(), $result->errors );
	}

	/**
	 * Test partial factory method.
	 */
	public function test_partial_creates_correct_result(): void {
		$errors = array( 'Post 1 missing GUID.', 'Post 2 failed insert.' );
		$result = PullResult::partial( 123, 3, 2, 1, $errors );

		$this->assertEquals( 123, $result->site_id );
		$this->assertEquals( PullResult::STATUS_PARTIAL, $result->status );
		$this->assertEquals( 3, $result->created );
		$this->assertEquals( 2, $result->updated );
		$this->assertEquals( 1, $result->skipped );
		$this->assertEquals( '', $result->error_code );
		$this->assertEquals( '', $result->message );
		$this->assertEquals( $errors, $result->errors );
	}

	/**
	 * Test is_success returns true for success status.
	 */
	public function test_is_success_returns_true_for_success(): void {
		$result = PullResult::success( 123, 1, 1 );

		$this->assertTrue( $result->is_success() );
		$this->assertFalse( $result->is_failure() );
		$this->assertFalse( $result->is_skipped() );
		$this->assertFalse( $result->is_partial() );
	}

	/**
	 * Test is_failure returns true for failure status.
	 */
	public function test_is_failure_returns_true_for_failure(): void {
		$result = PullResult::failure( 123, 'error', 'Error message' );

		$this->assertFalse( $result->is_success() );
		$this->assertTrue( $result->is_failure() );
		$this->assertFalse( $result->is_skipped() );
		$this->assertFalse( $result->is_partial() );
	}

	/**
	 * Test is_skipped returns true for skipped status.
	 */
	public function test_is_skipped_returns_true_for_skipped(): void {
		$result = PullResult::skipped( 123, 'Reason' );

		$this->assertFalse( $result->is_success() );
		$this->assertFalse( $result->is_failure() );
		$this->assertTrue( $result->is_skipped() );
		$this->assertFalse( $result->is_partial() );
	}

	/**
	 * Test is_partial returns true for partial status.
	 */
	public function test_is_partial_returns_true_for_partial(): void {
		$result = PullResult::partial( 123, 1, 1, 1, array( 'error' ) );

		$this->assertFalse( $result->is_success() );
		$this->assertFalse( $result->is_failure() );
		$this->assertFalse( $result->is_skipped() );
		$this->assertTrue( $result->is_partial() );
	}

	/**
	 * Test get_total_processed calculates correctly.
	 */
	public function test_get_total_processed(): void {
		$result = PullResult::partial( 123, 3, 2, 1, array( 'error1', 'error2' ) );

		// 3 created + 2 updated + 1 skipped + 2 errors = 8
		$this->assertEquals( 8, $result->get_total_processed() );
	}

	/**
	 * Test get_total_processed for success result.
	 */
	public function test_get_total_processed_for_success(): void {
		$result = PullResult::success( 123, 5, 3 );

		// 5 created + 3 updated = 8
		$this->assertEquals( 8, $result->get_total_processed() );
	}

	/**
	 * Test to_array returns correct structure.
	 */
	public function test_to_array_returns_correct_structure(): void {
		$errors = array( 'error1', 'error2' );
		$result = PullResult::partial( 123, 3, 2, 1, $errors );
		$array  = $result->to_array();

		$this->assertArrayHasKey( 'site_id', $array );
		$this->assertArrayHasKey( 'status', $array );
		$this->assertArrayHasKey( 'created', $array );
		$this->assertArrayHasKey( 'updated', $array );
		$this->assertArrayHasKey( 'skipped', $array );
		$this->assertArrayHasKey( 'error_code', $array );
		$this->assertArrayHasKey( 'message', $array );
		$this->assertArrayHasKey( 'errors', $array );

		$this->assertEquals( 123, $array['site_id'] );
		$this->assertEquals( 'partial', $array['status'] );
		$this->assertEquals( 3, $array['created'] );
		$this->assertEquals( 2, $array['updated'] );
		$this->assertEquals( 1, $array['skipped'] );
		$this->assertEquals( $errors, $array['errors'] );
	}

	/**
	 * Test status constants have expected values.
	 */
	public function test_status_constants(): void {
		$this->assertEquals( 'success', PullResult::STATUS_SUCCESS );
		$this->assertEquals( 'failure', PullResult::STATUS_FAILURE );
		$this->assertEquals( 'skipped', PullResult::STATUS_SKIPPED );
		$this->assertEquals( 'partial', PullResult::STATUS_PARTIAL );
	}

	/**
	 * Test success with zero counts.
	 */
	public function test_success_with_zero_counts(): void {
		$result = PullResult::success( 123, 0, 0 );

		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 0, $result->created );
		$this->assertEquals( 0, $result->updated );
		$this->assertEquals( 0, $result->get_total_processed() );
	}
}
