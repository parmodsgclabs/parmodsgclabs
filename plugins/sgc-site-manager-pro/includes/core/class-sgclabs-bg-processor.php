<?php
/**
 * SGC LABS® — Background Processor
 *
 * Lightweight background-job queue built on top of WP-Cron + WordPress options.
 * Designed for shared hosting (Bluehost MySQL 5.7) — no Action Scheduler dependency,
 * no custom DB tables, no long-running PHP processes.
 *
 * Use cases (current + planned):
 * - Bulk multilingual translation (Phase 2 — FR1, 8 languages × 7000 products)
 * - Generate Related Links (Phase 3 — A7, currently times out at HTTP 520)
 * - Reorder Reminder email blasts (Phase 3 — A6)
 * - Bulk SEO regeneration via AI (existing AI Content Center batches)
 *
 * Job lifecycle:
 *   queued → running → done | failed (max retries)
 *
 * @package SGCLabs\Core
 * @since   6.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SGCLabs_BG_Processor' ) ) :

final class SGCLabs_BG_Processor {

	/** Option key for the job queue (single-row JSON) */
	const QUEUE_OPT = 'sgclabs_bg_queue';

	/** WP-Cron hook name */
	const CRON_HOOK = 'sgclabs_bg_tick';

	/** WP-Cron recurrence */
	const CRON_INTERVAL = 'sgclabs_every_minute';

	/** Maximum retries per job */
	const MAX_RETRIES = 3;

	/** Maximum jobs processed per tick (Bluehost shared safety) */
	const JOBS_PER_TICK = 1;

	// ------------------------------------------------------------------
	// Bootstrapping
	// ------------------------------------------------------------------

	/**
	 * Wire up cron schedules + the tick handler.
	 * Called automatically on file load.
	 */
	public static function boot(): void {
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] );
		add_action( self::CRON_HOOK, [ __CLASS__, 'tick' ] );

		// Make sure our recurring event is scheduled.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * Register a 1-minute recurrence (smaller than WP's default 5-min).
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public static function add_cron_interval( array $schedules ): array {
		$schedules[ self::CRON_INTERVAL ] = [
			'interval' => 60,
			'display'  => __( 'Every Minute (SGC LABS BG)', 'sgclabs-manager' ),
		];
		return $schedules;
	}

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Queue a job.
	 *
	 * @param string $job_type     Logical job type (e.g. 'translate_product').
	 * @param array  $payload      Arbitrary serializable data passed to the handler.
	 * @param string $handler      Callable name (function or 'Class::method').
	 * @return string              Job ID.
	 */
	public static function queue( string $job_type, array $payload, string $handler ): string {
		$queue   = self::read_queue();
		$id      = uniqid( 'job_', true );
		$queue[] = [
			'id'        => $id,
			'type'      => $job_type,
			'handler'   => $handler,
			'payload'   => $payload,
			'status'    => 'queued',
			'attempts'  => 0,
			'queued_at' => time(),
			'updated_at'=> time(),
			'last_error'=> '',
		];
		self::write_queue( $queue );
		return $id;
	}

	/**
	 * Read all jobs in any state. Useful for the admin monitor UI.
	 *
	 * @return array
	 */
	public static function all_jobs(): array {
		return self::read_queue();
	}

	/**
	 * Get a single job by ID.
	 *
	 * @param string $id Job ID.
	 * @return array|null
	 */
	public static function get_job( string $id ): ?array {
		foreach ( self::read_queue() as $job ) {
			if ( $job['id'] === $id ) {
				return $job;
			}
		}
		return null;
	}

	/**
	 * Cancel a queued job (cannot cancel one that's already running).
	 *
	 * @param string $id Job ID.
	 * @return bool
	 */
	public static function cancel( string $id ): bool {
		$queue = self::read_queue();
		foreach ( $queue as $i => $job ) {
			if ( $job['id'] === $id && $job['status'] === 'queued' ) {
				$queue[ $i ]['status']    = 'cancelled';
				$queue[ $i ]['updated_at']= time();
				self::write_queue( $queue );
				return true;
			}
		}
		return false;
	}

	/**
	 * Permanently remove a job from the queue. Used by the "Clear" button
	 * for done/failed/cancelled rows.
	 *
	 * @param string $id Job ID.
	 * @return bool
	 */
	public static function delete( string $id ): bool {
		$queue = self::read_queue();
		$kept  = array_values( array_filter( $queue, function ( $j ) use ( $id ) {
			return $j['id'] !== $id;
		} ) );
		if ( count( $kept ) === count( $queue ) ) {
			return false;
		}
		self::write_queue( $kept );
		return true;
	}

	/**
	 * Clear all done/failed/cancelled jobs in one go.
	 *
	 * @return int Number of jobs removed.
	 */
	public static function purge_finished(): int {
		$queue   = self::read_queue();
		$initial = count( $queue );
		$kept    = array_values( array_filter( $queue, function ( $j ) {
			return in_array( $j['status'], [ 'queued', 'running' ], true );
		} ) );
		self::write_queue( $kept );
		return $initial - count( $kept );
	}

	/**
	 * Counts by status — for the admin dashboard cards.
	 *
	 * @return array<string,int>
	 */
	public static function counts(): array {
		$out = [
			'queued'    => 0,
			'running'   => 0,
			'done'      => 0,
			'failed'    => 0,
			'cancelled' => 0,
			'total'     => 0,
		];
		foreach ( self::read_queue() as $job ) {
			$status = $job['status'] ?? 'queued';
			if ( isset( $out[ $status ] ) ) {
				$out[ $status ]++;
			}
			$out['total']++;
		}
		return $out;
	}

	// ------------------------------------------------------------------
	// Cron tick — processes one job per run
	// ------------------------------------------------------------------

	public static function tick(): void {
		$queue = self::read_queue();
		if ( empty( $queue ) ) {
			return;
		}

		$processed = 0;
		foreach ( $queue as $i => $job ) {
			if ( $processed >= self::JOBS_PER_TICK ) {
				break;
			}
			if ( $job['status'] !== 'queued' ) {
				continue;
			}

			// Mark running.
			$queue[ $i ]['status']     = 'running';
			$queue[ $i ]['attempts']  += 1;
			$queue[ $i ]['updated_at'] = time();
			self::write_queue( $queue );

			// Execute.
			$result = self::run_handler( $job['handler'], $job['payload'] );

			// Re-read queue (handler may have queued more jobs).
			$queue = self::read_queue();
			foreach ( $queue as $j => $row ) {
				if ( $row['id'] !== $job['id'] ) {
					continue;
				}
				if ( $result['ok'] ) {
					$queue[ $j ]['status']     = 'done';
					$queue[ $j ]['last_error'] = '';
				} elseif ( $row['attempts'] >= self::MAX_RETRIES ) {
					$queue[ $j ]['status']     = 'failed';
					$queue[ $j ]['last_error'] = $result['error'];
				} else {
					$queue[ $j ]['status']     = 'queued';
					$queue[ $j ]['last_error'] = $result['error'];
				}
				$queue[ $j ]['updated_at'] = time();
			}
			self::write_queue( $queue );
			$processed++;
		}
	}

	/**
	 * Safely invoke the job handler.
	 *
	 * @param string $handler Callable name.
	 * @param array  $payload Arguments.
	 * @return array{ok:bool,error:string}
	 */
	private static function run_handler( string $handler, array $payload ): array {
		try {
			if ( ! is_callable( $handler ) ) {
				return [ 'ok' => false, 'error' => "Handler not callable: $handler" ];
			}
			$ret = call_user_func( $handler, $payload );
			if ( is_wp_error( $ret ) ) {
				return [ 'ok' => false, 'error' => $ret->get_error_message() ];
			}
			return [ 'ok' => true, 'error' => '' ];
		} catch ( \Throwable $e ) {
			return [ 'ok' => false, 'error' => $e->getMessage() ];
		}
	}

	// ------------------------------------------------------------------
	// Queue read/write (single option, JSON-encoded)
	// ------------------------------------------------------------------

	private static function read_queue(): array {
		$raw = get_option( self::QUEUE_OPT, '[]' );
		if ( ! is_string( $raw ) ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	private static function write_queue( array $queue ): void {
		// Cap queue at 1000 entries to protect autoloaded options on Bluehost.
		if ( count( $queue ) > 1000 ) {
			$queue = array_slice( $queue, -1000 );
		}
		update_option( self::QUEUE_OPT, wp_json_encode( $queue ), false );
	}

	// ------------------------------------------------------------------
	// Activation / deactivation hooks for cron lifecycle
	// ------------------------------------------------------------------

	public static function on_activate(): void {
		self::boot();
	}

	public static function on_deactivate(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}
}

// Boot on every load so the cron event self-heals if accidentally unscheduled.
SGCLabs_BG_Processor::boot();

// Hook activation/deactivation.
register_activation_hook(
	defined( 'SGCLABS_FILE' ) ? SGCLABS_FILE : __FILE__,
	[ 'SGCLabs_BG_Processor', 'on_activate' ]
);
register_deactivation_hook(
	defined( 'SGCLABS_FILE' ) ? SGCLABS_FILE : __FILE__,
	[ 'SGCLabs_BG_Processor', 'on_deactivate' ]
);

endif;
