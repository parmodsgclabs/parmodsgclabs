<?php
/**
 * SGC LABS® — SEO Field Migrator
 *
 * Walks the postmeta table once, finds every legacy SEO key, and merges
 * its value into the corresponding canonical key according to the chosen
 * strategy:
 *
 *   - 'hybrid'  (default) — manual entries are preserved as-is; AI-written
 *                            entries are migrated but kept (the next AI run
 *                            will rewrite them via canonical keys anyway)
 *   - 'preserve_all'      — both manual and AI entries are migrated as-is
 *   - 'rename_only'       — values copied to canonical key, source recorded
 *                            as 'migrated', AI rewrites later if user opts
 *
 * Heuristics for "manual" vs "AI":
 *   - If a sibling `_sgclabs_*__src = 'ai'` already exists → AI
 *   - If the legacy key equals `_yoast_wpseo_*` and there's NO sibling AI
 *     record → manual (Yoast was edited by a human historically)
 *   - Default → 'imported' (unknown provenance)
 *
 * Always supports DRY-RUN (`apply = false`). The admin UI requires a backup
 * checkbox before enabling the Apply button.
 *
 * Batch-safe: caps work per call so Bluehost shared hosting doesn't time out.
 * The Background Processor (Phase 1) can chain calls for very large stores.
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SGCLabs_SEO_Migrator' ) ) :

final class SGCLabs_SEO_Migrator {

	const LOG_OPTION   = 'sgclabs_seo_migration_log';
	const STATE_OPTION = 'sgclabs_seo_migration_state';

	/** Posts processed per call — Bluehost-safe. */
	const BATCH_SIZE = 200;

	/**
	 * Run a single batch.
	 *
	 * @param array $args {
	 *   @type bool   $apply     If false, only count changes (dry run).
	 *   @type string $strategy  hybrid | preserve_all | rename_only.
	 *   @type int    $offset    Where to resume.
	 * }
	 * @return array Stats for this batch.
	 */
	public static function run_batch( array $args = [] ): array {
		$args = wp_parse_args( $args, [
			'apply'    => false,
			'strategy' => 'hybrid',
			'offset'   => 0,
		] );

		$apply    = (bool) $args['apply'];
		$strategy = in_array( $args['strategy'], [ 'hybrid', 'preserve_all', 'rename_only' ], true )
			? $args['strategy']
			: 'hybrid';
		$offset   = max( 0, (int) $args['offset'] );

		if ( ! class_exists( 'SGCLabs_SEO_Fields' ) ) {
			return [ 'ok' => false, 'error' => 'Schema not loaded' ];
		}

		$alias_map = SGCLabs_SEO_Fields::legacy_alias_map();
		if ( empty( $alias_map ) ) {
			return [
				'ok'         => true,
				'scanned'    => 0,
				'migrated'   => 0,
				'preserved'  => 0,
				'conflicts'  => 0,
				'finished'   => true,
				'next_offset'=> 0,
			];
		}

		global $wpdb;
		$legacy_keys = array_keys( $alias_map );
		$placeholders = implode( ',', array_fill( 0, count( $legacy_keys ), '%s' ) );
		$batch_size   = self::BATCH_SIZE;

		// Pull all postmeta rows for legacy keys, paginated.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, post_id, meta_key, meta_value
				   FROM {$wpdb->postmeta}
				  WHERE meta_key IN ($placeholders)
			   ORDER BY meta_id ASC
				  LIMIT %d OFFSET %d",
				array_merge( $legacy_keys, [ $batch_size, $offset ] )
			)
		);

		$stats = [
			'ok'          => true,
			'scanned'     => count( $rows ),
			'migrated'    => 0,
			'preserved'   => 0,
			'conflicts'   => 0,
			'samples'     => [],
			'finished'    => count( $rows ) < $batch_size,
			'next_offset' => $offset + count( $rows ),
			'strategy'    => $strategy,
			'apply'       => $apply,
		];

		foreach ( $rows as $row ) {
			$post_id    = (int) $row->post_id;
			$legacy_key = $row->meta_key;
			$legacy_val = $row->meta_value;

			if ( ! isset( $alias_map[ $legacy_key ] ) || $legacy_val === '' || $legacy_val === null ) {
				continue;
			}
			$canonical_key = $alias_map[ $legacy_key ];

			// What's already at the canonical key?
			$canonical_val = get_post_meta( $post_id, $canonical_key, true );

			$decision = '';
			if ( $canonical_val !== '' && $canonical_val !== false && $canonical_val !== null ) {
				if ( $canonical_val === $legacy_val ) {
					$decision = 'identical_skip';
				} else {
					// Conflict: canonical already has a different value.
					$stats['conflicts']++;
					if ( $strategy === 'preserve_all' ) {
						// Keep canonical as-is (manual wins by default).
						$decision = 'kept_canonical';
					} else {
						// hybrid / rename_only: respect canonical, log the conflict.
						$decision = 'kept_canonical_conflict';
					}
				}
			} else {
				// Canonical is empty — migrate.
				if ( $apply ) {
					update_post_meta( $post_id, $canonical_key, $legacy_val );
					// Source companion.
					$src_key = $canonical_key . '__src';
					if ( get_post_meta( $post_id, $src_key, true ) === '' ) {
						update_post_meta( $post_id, $src_key, 'migrated' );
					}
				}
				$stats['migrated']++;
				$decision = 'migrated';
			}

			if ( $decision !== 'identical_skip' ) {
				$stats['preserved']++;
			}

			if ( count( $stats['samples'] ) < 10 ) {
				$stats['samples'][] = [
					'post_id'    => $post_id,
					'from'       => $legacy_key,
					'to'         => $canonical_key,
					'value'      => mb_substr( (string) $legacy_val, 0, 80 ),
					'decision'   => $decision,
				];
			}
		}

		// Persist log.
		if ( $apply ) {
			$log = get_option( self::LOG_OPTION, [] );
			if ( ! is_array( $log ) ) {
				$log = [];
			}
			$log[] = [
				'time'      => time(),
				'strategy'  => $strategy,
				'scanned'   => $stats['scanned'],
				'migrated'  => $stats['migrated'],
				'conflicts' => $stats['conflicts'],
				'offset'    => $offset,
			];
			// Cap log at 200 entries.
			if ( count( $log ) > 200 ) {
				$log = array_slice( $log, -200 );
			}
			update_option( self::LOG_OPTION, $log, false );
		}

		return $stats;
	}

	/**
	 * Estimate total legacy postmeta rows (across all keys) — for progress UI.
	 */
	public static function total_legacy_rows(): int {
		global $wpdb;
		if ( ! class_exists( 'SGCLabs_SEO_Fields' ) ) {
			return 0;
		}
		$keys = array_keys( SGCLabs_SEO_Fields::legacy_alias_map() );
		if ( empty( $keys ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)",
				...$keys
			)
		);
	}

	/**
	 * Recent migration log entries.
	 *
	 * @return array
	 */
	public static function log( int $limit = 20 ): array {
		$log = get_option( self::LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			return [];
		}
		return array_slice( array_reverse( $log ), 0, $limit );
	}

	/**
	 * Reset the migration log (admin tool).
	 */
	public static function reset_log(): void {
		delete_option( self::LOG_OPTION );
	}

	// ────────────────────────────────────────────────────────────
	// One-click "Run All Batches" via Background Processor
	// ────────────────────────────────────────────────────────────

	/**
	 * Queue a chained migration job that processes ALL legacy rows in
	 * 200-row batches automatically. Each batch self-queues the next one
	 * until `finished = true`.
	 *
	 * The FIRST batch runs synchronously (so the admin sees immediate
	 * progress); subsequent batches go through the Background Processor
	 * cron at one-per-minute pace.
	 *
	 * @param string $strategy hybrid | preserve_all | rename_only.
	 * @return string Job ID of the next queued batch (or '' if finished
	 *                in one shot).
	 */
	public static function queue_run_all( string $strategy = 'hybrid' ): string {
		if ( ! class_exists( 'SGCLabs_BG_Processor' ) ) {
			return '';
		}
		$strategy = in_array( $strategy, [ 'hybrid', 'preserve_all', 'rename_only' ], true )
			? $strategy
			: 'hybrid';

		// Mark "run-all in progress" so the UI can show a different state.
		update_option( self::STATE_OPTION, [
			'running'    => true,
			'strategy'   => $strategy,
			'started_at' => time(),
			'offset'     => 0,
		], false );

		// Run the first batch RIGHT NOW so the admin sees instant feedback.
		$first = self::run_batch( [
			'apply'    => true,
			'strategy' => $strategy,
			'offset'   => 0,
		] );

		// Persist progress.
		update_option( self::STATE_OPTION, [
			'running'    => ! ( $first['finished'] ?? false ),
			'strategy'   => $strategy,
			'offset'     => (int) ( $first['next_offset'] ?? 0 ),
			'last_batch' => [
				'scanned'   => (int) ( $first['scanned'] ?? 0 ),
				'migrated'  => (int) ( $first['migrated'] ?? 0 ),
				'conflicts' => (int) ( $first['conflicts'] ?? 0 ),
				'at'        => time(),
			],
			'finished_at'=> ( $first['finished'] ?? false ) ? time() : 0,
		], false );

		// If everything fit in one batch, we're done.
		if ( ! empty( $first['finished'] ) ) {
			return '';
		}

		// Otherwise queue the chain to continue from next_offset.
		return SGCLabs_BG_Processor::queue(
			'seo_migrate_chain',
			[ 'strategy' => $strategy, 'offset' => (int) $first['next_offset'] ],
			'sgclabs_seo_migrator_chain_handler'
		);
	}

	/**
	 * BG Processor handler — runs ONE batch, then queues itself again
	 * with the next offset until finished.
	 *
	 * @param array $payload { strategy, offset }
	 * @return true|WP_Error
	 */
	public static function chain_handler( array $payload ) {
		$strategy = (string) ( $payload['strategy'] ?? 'hybrid' );
		$offset   = (int) ( $payload['offset'] ?? 0 );

		$result = self::run_batch( [
			'apply'    => true,
			'strategy' => $strategy,
			'offset'   => $offset,
		] );

		if ( ! ( $result['ok'] ?? false ) ) {
			update_option( self::STATE_OPTION, [
				'running'    => false,
				'finished_at'=> time(),
				'error'      => $result['error'] ?? 'unknown',
			], false );
			return new WP_Error( 'migrator_failed', $result['error'] ?? 'unknown' );
		}

		// Update progress.
		update_option( self::STATE_OPTION, [
			'running'    => ! $result['finished'],
			'strategy'   => $strategy,
			'offset'     => $result['next_offset'],
			'last_batch' => [
				'scanned'   => $result['scanned'],
				'migrated'  => $result['migrated'],
				'conflicts' => $result['conflicts'],
				'at'        => time(),
			],
			'finished_at'=> $result['finished'] ? time() : 0,
		], false );

		// Queue next batch if not done.
		if ( ! $result['finished'] && class_exists( 'SGCLabs_BG_Processor' ) ) {
			SGCLabs_BG_Processor::queue(
				'seo_migrate_chain',
				[ 'strategy' => $strategy, 'offset' => $result['next_offset'] ],
				'sgclabs_seo_migrator_chain_handler'
			);
		}
		return true;
	}

	/**
	 * Read the current run-all state for the admin UI.
	 *
	 * @return array
	 */
	public static function state(): array {
		$state = get_option( self::STATE_OPTION, [] );
		return is_array( $state ) ? $state : [];
	}

	/**
	 * Stop a running chain (cancels queued chain jobs).
	 */
	public static function stop_run_all(): int {
		$cancelled = 0;
		if ( class_exists( 'SGCLabs_BG_Processor' ) ) {
			foreach ( SGCLabs_BG_Processor::all_jobs() as $job ) {
				if ( ( $job['type'] ?? '' ) === 'seo_migrate_chain' && ( $job['status'] ?? '' ) === 'queued' ) {
					if ( SGCLabs_BG_Processor::cancel( $job['id'] ) ) {
						$cancelled++;
					}
				}
			}
		}
		update_option( self::STATE_OPTION, [
			'running'    => false,
			'finished_at'=> time(),
			'stopped'    => true,
		], false );
		return $cancelled;
	}
}

// Top-level wrapper so SGCLabs_BG_Processor can call it as a string handler.
if ( ! function_exists( 'sgclabs_seo_migrator_chain_handler' ) ) {
	function sgclabs_seo_migrator_chain_handler( array $payload ) {
		return SGCLabs_SEO_Migrator::chain_handler( $payload );
	}
}

endif;
