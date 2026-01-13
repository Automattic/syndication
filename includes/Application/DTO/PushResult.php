<?php
/**
 * Push result data transfer object.
 *
 * @package Automattic\Syndication\Application\DTO
 */

declare( strict_types=1 );

namespace Automattic\Syndication\Application\DTO;

/**
 * Immutable data transfer object for push operation results.
 */
final class PushResult {

	/**
	 * Status constants.
	 */
	public const STATUS_SUCCESS = 'success';
	public const STATUS_FAILURE = 'failure';
	public const STATUS_SKIPPED = 'skipped';

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
	 * The remote post ID (if successful).
	 *
	 * @var int
	 */
	public readonly int $remote_id;

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
	 * The action taken (created, updated, deleted).
	 *
	 * @var string
	 */
	public readonly string $action;

	/**
	 * Private constructor - use static factory methods.
	 *
	 * @param int    $site_id    Site ID.
	 * @param string $status     Status.
	 * @param int    $remote_id  Remote post ID.
	 * @param string $error_code Error code.
	 * @param string $message    Message.
	 * @param string $action     Action taken.
	 */
	private function __construct(
		int $site_id,
		string $status,
		int $remote_id,
		string $error_code,
		string $message,
		string $action
	) {
		$this->site_id    = $site_id;
		$this->status     = $status;
		$this->remote_id  = $remote_id;
		$this->error_code = $error_code;
		$this->message    = $message;
		$this->action     = $action;
	}

	/**
	 * Create a success result.
	 *
	 * @param int    $site_id   Site ID.
	 * @param int    $remote_id Remote post ID.
	 * @param string $action    Action taken (created, updated, deleted).
	 * @return self
	 */
	public static function success( int $site_id, int $remote_id, string $action ): self {
		return new self(
			$site_id,
			self::STATUS_SUCCESS,
			$remote_id,
			'',
			'',
			$action
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
			$error_code,
			$message,
			''
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
			'',
			$reason,
			''
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
	 * Convert to array for serialization.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'site_id'    => $this->site_id,
			'status'     => $this->status,
			'remote_id'  => $this->remote_id,
			'error_code' => $this->error_code,
			'message'    => $this->message,
			'action'     => $this->action,
		);
	}
}
