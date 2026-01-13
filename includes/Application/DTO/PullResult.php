<?php
/**
 * Pull result data transfer object.
 *
 * @package Automattic\Syndication\Application\DTO
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application\DTO;

/**
 * Immutable data transfer object for pull operation results.
 */
final class PullResult {

	/**
	 * Status constants.
	 */
	public const STATUS_SUCCESS = 'success';
	public const STATUS_FAILURE = 'failure';
	public const STATUS_SKIPPED = 'skipped';
	public const STATUS_PARTIAL = 'partial';

	/**
	 * The site ID.
	 *
	 * @var int
	 */
	public readonly int $site_id;

	/**
	 * The status.
	 *
	 * @var string
	 */
	public readonly string $status;

	/**
	 * Number of posts created.
	 *
	 * @var int
	 */
	public readonly int $created;

	/**
	 * Number of posts updated.
	 *
	 * @var int
	 */
	public readonly int $updated;

	/**
	 * Number of posts skipped.
	 *
	 * @var int
	 */
	public readonly int $skipped;

	/**
	 * The error code (if failed).
	 *
	 * @var string
	 */
	public readonly string $error_code;

	/**
	 * The message.
	 *
	 * @var string
	 */
	public readonly string $message;

	/**
	 * Array of error messages for partial failures.
	 *
	 * @var array<string>
	 */
	public readonly array $errors;

	/**
	 * Private constructor - use static factory methods.
	 *
	 * @param int           $site_id    Site ID.
	 * @param string        $status     Status.
	 * @param int           $created    Posts created.
	 * @param int           $updated    Posts updated.
	 * @param int           $skipped    Posts skipped.
	 * @param string        $error_code Error code.
	 * @param string        $message    Message.
	 * @param array<string> $errors     Array of error messages.
	 */
	private function __construct(
		int $site_id,
		string $status,
		int $created,
		int $updated,
		int $skipped,
		string $error_code,
		string $message,
		array $errors
	) {
		$this->site_id    = $site_id;
		$this->status     = $status;
		$this->created    = $created;
		$this->updated    = $updated;
		$this->skipped    = $skipped;
		$this->error_code = $error_code;
		$this->message    = $message;
		$this->errors     = $errors;
	}

	/**
	 * Create a success result.
	 *
	 * @param int $site_id Site ID.
	 * @param int $created Number of posts created.
	 * @param int $updated Number of posts updated.
	 * @return self
	 */
	public static function success( int $site_id, int $created, int $updated ): self {
		return new self(
			$site_id,
			self::STATUS_SUCCESS,
			$created,
			$updated,
			0,
			'',
			'',
			array()
		);
	}

	/**
	 * Create a failure result.
	 *
	 * @param int    $site_id    Site ID.
	 * @param string $error_code Error code.
	 * @param string $message    Error message.
	 * @return self
	 */
	public static function failure( int $site_id, string $error_code, string $message ): self {
		return new self(
			$site_id,
			self::STATUS_FAILURE,
			0,
			0,
			0,
			$error_code,
			$message,
			array()
		);
	}

	/**
	 * Create a skipped result.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $reason  Reason for skipping.
	 * @return self
	 */
	public static function skipped( int $site_id, string $reason ): self {
		return new self(
			$site_id,
			self::STATUS_SKIPPED,
			0,
			0,
			0,
			'',
			$reason,
			array()
		);
	}

	/**
	 * Create a partial success result.
	 *
	 * @param int           $site_id Site ID.
	 * @param int           $created Number of posts created.
	 * @param int           $updated Number of posts updated.
	 * @param int           $skipped Number of posts skipped.
	 * @param array<string> $errors  Array of error messages.
	 * @return self
	 */
	public static function partial( int $site_id, int $created, int $updated, int $skipped, array $errors ): self {
		return new self(
			$site_id,
			self::STATUS_PARTIAL,
			$created,
			$updated,
			$skipped,
			'',
			'',
			$errors
		);
	}

	/**
	 * Check if the result is successful.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return self::STATUS_SUCCESS === $this->status;
	}

	/**
	 * Check if the result is a failure.
	 *
	 * @return bool
	 */
	public function is_failure(): bool {
		return self::STATUS_FAILURE === $this->status;
	}

	/**
	 * Check if the operation was skipped.
	 *
	 * @return bool
	 */
	public function is_skipped(): bool {
		return self::STATUS_SKIPPED === $this->status;
	}

	/**
	 * Check if the result is partial (some errors).
	 *
	 * @return bool
	 */
	public function is_partial(): bool {
		return self::STATUS_PARTIAL === $this->status;
	}

	/**
	 * Get the total number of posts processed.
	 *
	 * @return int
	 */
	public function get_total_processed(): int {
		return $this->created + $this->updated + $this->skipped + count( $this->errors );
	}

	/**
	 * Convert to array for serialization.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'site_id'    => $this->site_id,
			'status'     => $this->status,
			'created'    => $this->created,
			'updated'    => $this->updated,
			'skipped'    => $this->skipped,
			'error_code' => $this->error_code,
			'message'    => $this->message,
			'errors'     => $this->errors,
		);
	}
}
