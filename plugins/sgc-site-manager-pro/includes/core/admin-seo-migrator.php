<?php
/**
 * SGC LABS® — SEO Migrator Admin Page
 *
 * Lives at SGC LABS → 🔄 SEO Migrator. Lets the admin:
 *   1. See how many legacy SEO meta rows exist
 *   2. Run a dry-run scan to preview what would change
 *   3. Tick the "I have a database backup" checkbox to unlock Apply
 *   4. Run the apply in batches (Bluehost-safe — 200 rows per call)
 *   5. Read the recent migration log
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sgclabs_seo_migrator_page' ) ) :

function sgclabs_seo_migrator_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'sgclabs-manager' ) );
	}

	$result = null;

	if ( ! empty( $_POST['sgc_seo_mig_action'] ) ) {
		check_admin_referer( 'sgc_seo_migrator' );
		$action   = sanitize_key( wp_unslash( $_POST['sgc_seo_mig_action'] ) );
		$strategy = sanitize_key( wp_unslash( $_POST['strategy'] ?? 'hybrid' ) );
		$offset   = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
		$apply    = ( $action === 'apply' );
		$backup   = ! empty( $_POST['backup_confirmed'] );

		if ( $action === 'reset' ) {
			SGCLabs_SEO_Migrator::reset_log();
			echo '<div class="notice notice-success"><p>'
				. esc_html__( 'Migration log cleared.', 'sgclabs-manager' )
				. '</p></div>';
		} elseif ( $action === 'run_all' ) {
			if ( ! $backup ) {
				echo '<div class="notice notice-error"><p>'
					. esc_html__( 'Please confirm you have a database backup before running all batches.', 'sgclabs-manager' )
					. '</p></div>';
			} else {
				$job_id = SGCLabs_SEO_Migrator::queue_run_all( $strategy );
				if ( $job_id ) {
					echo '<div class="notice notice-success is-dismissible"><p>'
						. '🚀 <strong>' . esc_html__( 'Auto-migration started.', 'sgclabs-manager' ) . '</strong> '
						. esc_html__( 'The Background Processor will work through every batch automatically. Refresh this page to see progress, or visit the Background Processor page.', 'sgclabs-manager' )
						. ' <a href="' . esc_url( admin_url( 'admin.php?page=sgclabs-bg-processor' ) ) . '">'
						. esc_html__( 'Open Background Processor →', 'sgclabs-manager' )
						. '</a></p></div>';
				} else {
					echo '<div class="notice notice-error"><p>'
						. esc_html__( 'Background Processor not loaded — cannot start auto-migration.', 'sgclabs-manager' )
						. '</p></div>';
				}
			}
		} elseif ( $action === 'stop_all' ) {
			$n = SGCLabs_SEO_Migrator::stop_run_all();
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html(
					sprintf(
						/* translators: %d: cancelled batches */
						__( '⛔ Auto-migration stopped. %d queued batches cancelled.', 'sgclabs-manager' ),
						$n
					)
				)
				. '</p></div>';
		} elseif ( $apply && ! $backup ) {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Please confirm you have a database backup before applying.', 'sgclabs-manager' )
				. '</p></div>';
		} elseif ( in_array( $action, [ 'dryrun', 'apply' ], true ) ) {
			$result = SGCLabs_SEO_Migrator::run_batch( [
				'apply'    => $apply,
				'strategy' => $strategy,
				'offset'   => $offset,
			] );
		}
	}

	$total       = SGCLabs_SEO_Migrator::total_legacy_rows();
	$recent_log  = SGCLabs_SEO_Migrator::log( 10 );
	$state       = SGCLabs_SEO_Migrator::state();
	$is_running  = ! empty( $state['running'] );

	// Auto-refresh every 8 seconds while a run-all chain is active so the
	// admin sees live progress without manually F5'ing.
	if ( $is_running ) {
		echo '<meta http-equiv="refresh" content="8">';
	}

	?>
	<div class="wrap" style="max-width:1100px;">
		<h1>🔄 <?php esc_html_e( 'SEO Field Migrator', 'sgclabs-manager' ); ?></h1>

		<p style="color:#555;max-width:780px;line-height:1.6;">
			<?php esc_html_e( 'Migrates SEO meta keys from legacy locations (Yoast / RankMath / older SGC LABS keys) into a single canonical schema. Manual entries are preserved; AI-written ones can be regenerated later from the AI Content Center. Always run a dry-run first.', 'sgclabs-manager' ); ?>
		</p>

		<!-- Status cards -->
		<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:18px 0 28px;">
			<div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid #1a1a2e;padding:16px;border-radius:6px;">
				<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">
					<?php esc_html_e( 'Legacy rows in DB', 'sgclabs-manager' ); ?>
				</div>
				<div style="font-size:28px;font-weight:600;color:#1a1a2e;margin-top:4px;">
					<?php echo esc_html( number_format_i18n( $total ) ); ?>
				</div>
			</div>
			<div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid #16a34a;padding:16px;border-radius:6px;">
				<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">
					<?php esc_html_e( 'Migrations logged', 'sgclabs-manager' ); ?>
				</div>
				<div style="font-size:28px;font-weight:600;color:#16a34a;margin-top:4px;">
					<?php echo esc_html( number_format_i18n( count( $recent_log ) ) ); ?>
				</div>
			</div>
			<div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid #f59e0b;padding:16px;border-radius:6px;">
				<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">
					<?php esc_html_e( 'Batch size', 'sgclabs-manager' ); ?>
				</div>
				<div style="font-size:28px;font-weight:600;color:#f59e0b;margin-top:4px;">
					<?php echo esc_html( (string) SGCLabs_SEO_Migrator::BATCH_SIZE ); ?>
				</div>
			</div>
		</div>

		<!-- Live progress card (visible only when auto-migration is running) -->
		<?php if ( $is_running || ! empty( $state['offset'] ) ) :
			$processed = (int) ( $state['offset'] ?? 0 );
			$pct       = $total > 0 ? min( 100, (int) round( ( $processed / max( $total, 1 ) ) * 100 ) ) : 0;
			$last      = $state['last_batch'] ?? [];
			?>
			<div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid <?php echo $is_running ? '#f59e0b' : '#16a34a'; ?>;border-radius:8px;padding:22px;margin-bottom:22px;">
				<h2 style="margin-top:0;">
					<?php if ( $is_running ) : ?>
						⚡ <?php esc_html_e( 'Auto-migration in progress…', 'sgclabs-manager' ); ?>
					<?php else : ?>
						✅ <?php esc_html_e( 'Auto-migration complete', 'sgclabs-manager' ); ?>
					<?php endif; ?>
				</h2>
				<div style="background:#f3f4f6;border-radius:999px;height:14px;overflow:hidden;margin:8px 0 14px;">
					<div style="background:<?php echo $is_running ? '#f59e0b' : '#16a34a'; ?>;height:100%;width:<?php echo esc_attr( (string) $pct ); ?>%;transition:width .4s ease;"></div>
				</div>
				<p style="margin:0;color:#374151;font-size:13px;">
					<strong><?php echo esc_html( (string) $processed ); ?></strong> /
					<strong><?php echo esc_html( (string) $total ); ?></strong>
					<?php esc_html_e( 'rows processed', 'sgclabs-manager' ); ?>
					(<?php echo esc_html( $pct . '%' ); ?>)
					<?php if ( ! empty( $last['at'] ) ) : ?>
						· <?php esc_html_e( 'Last batch:', 'sgclabs-manager' ); ?>
						<?php echo esc_html( (string) $last['migrated'] ); ?> <?php esc_html_e( 'migrated', 'sgclabs-manager' ); ?>,
						<?php echo esc_html( (string) $last['conflicts'] ); ?> <?php esc_html_e( 'conflicts', 'sgclabs-manager' ); ?>
						<?php echo ' · ' . esc_html( wp_date( 'H:i:s', (int) $last['at'] ) ); ?>
					<?php endif; ?>
				</p>
				<?php if ( $is_running ) : ?>
					<form method="post" style="margin-top:12px;display:inline;">
						<?php wp_nonce_field( 'sgc_seo_migrator' ); ?>
						<button type="submit" name="sgc_seo_mig_action" value="stop_all" class="button"
							onclick="return confirm('<?php echo esc_js( __( 'Stop the auto-migration? Already-migrated rows are kept.', 'sgclabs-manager' ) ); ?>');">
							⛔ <?php esc_html_e( 'Stop auto-migration', 'sgclabs-manager' ); ?>
						</button>
						<span style="color:#6b7280;font-size:11px;margin-left:10px;">
							<?php esc_html_e( 'Page auto-refreshes every 8 seconds.', 'sgclabs-manager' ); ?>
						</span>
					</form>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- Run form -->
		<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:22px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Run migration', 'sgclabs-manager' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'sgc_seo_migrator' ); ?>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Strategy', 'sgclabs-manager' ); ?></th>
						<td>
							<?php
							$strategy = isset( $_POST['strategy'] )
								? sanitize_key( wp_unslash( $_POST['strategy'] ) )
								: 'hybrid';
							?>
							<select name="strategy" style="min-width:240px;">
								<option value="hybrid"       <?php selected( $strategy, 'hybrid' ); ?>>
									<?php esc_html_e( 'Hybrid (recommended)', 'sgclabs-manager' ); ?>
								</option>
								<option value="preserve_all" <?php selected( $strategy, 'preserve_all' ); ?>>
									<?php esc_html_e( 'Preserve all values', 'sgclabs-manager' ); ?>
								</option>
								<option value="rename_only"  <?php selected( $strategy, 'rename_only' ); ?>>
									<?php esc_html_e( 'Rename only (no AI regen)', 'sgclabs-manager' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Hybrid copies legacy values into canonical keys and tags AI-generated rows so the AI Content Center can regenerate them later.', 'sgclabs-manager' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Resume from offset', 'sgclabs-manager' ); ?></th>
						<td>
							<input type="number" name="offset" min="0" step="1"
								value="<?php echo esc_attr( (string) ( $result['next_offset'] ?? 0 ) ); ?>"
								style="width:120px;">
							<p class="description">
								<?php esc_html_e( 'Only used by the manual single-batch buttons. The "Run All Batches" button below ignores this — it auto-chains from offset 0.', 'sgclabs-manager' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Backup confirmation', 'sgclabs-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="backup_confirmed" value="1">
								<?php esc_html_e( 'I have a fresh database backup. (Required before Apply / Run All.)', 'sgclabs-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<!-- ⭐ One-click full migration (recommended) -->
				<div style="background:#1a1a2e;color:#fff;padding:18px 22px;border-radius:8px;margin:18px 0;">
					<h3 style="color:#fff;margin:0 0 8px;">🚀 <?php esc_html_e( 'Recommended: One-click full migration', 'sgclabs-manager' ); ?></h3>
					<p style="margin:0 0 12px;color:#cbd5e1;font-size:13px;line-height:1.6;">
						<?php esc_html_e( 'Queues every batch into the Background Processor. Each finished batch automatically queues the next, so you never have to click again. Safe on Bluehost shared hosting — runs at one batch per minute (200 rows / batch).', 'sgclabs-manager' ); ?>
					</p>
					<button type="submit" name="sgc_seo_mig_action" value="run_all" class="button button-primary button-hero"
						<?php disabled( $is_running ); ?>
						onclick="return confirm('<?php echo esc_js( __( 'Start automatic migration of ALL legacy SEO meta? This processes everything in the background, batch by batch. You can stop it any time.', 'sgclabs-manager' ) ); ?>');">
						🚀 <?php
						echo $is_running
							? esc_html__( 'Auto-migration running…', 'sgclabs-manager' )
							: esc_html__( 'Run All Batches Automatically', 'sgclabs-manager' );
						?>
					</button>
				</div>

				<!-- Manual single-batch operations (advanced users) -->
				<details style="margin-top:14px;">
					<summary style="cursor:pointer;font-weight:600;color:#374151;">
						⚙️ <?php esc_html_e( 'Advanced: manual single-batch operations', 'sgclabs-manager' ); ?>
					</summary>
					<p style="margin-top:12px;">
						<button type="submit" name="sgc_seo_mig_action" value="dryrun" class="button">
							🔍 <?php esc_html_e( 'Run dry-run scan', 'sgclabs-manager' ); ?>
						</button>
						<button type="submit" name="sgc_seo_mig_action" value="apply" class="button"
							onclick="return confirm('<?php echo esc_js( __( 'Apply ONE batch only. To process everything, use Run All Batches above.', 'sgclabs-manager' ) ); ?>');">
							▶ <?php esc_html_e( 'Apply migration (one batch)', 'sgclabs-manager' ); ?>
						</button>
						<button type="submit" name="sgc_seo_mig_action" value="reset" class="button"
							onclick="return confirm('<?php echo esc_js( __( 'Clear migration log?', 'sgclabs-manager' ) ); ?>');">
							🧹 <?php esc_html_e( 'Reset log', 'sgclabs-manager' ); ?>
						</button>
					</p>
				</details>
			</form>
		</div>

		<?php if ( $result ) : ?>
			<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:22px;margin-top:20px;">
				<h2 style="margin-top:0;">
					<?php
					echo $result['apply']
						? '✅ ' . esc_html__( 'Apply complete (batch)', 'sgclabs-manager' )
						: '🔍 ' . esc_html__( 'Dry-run results', 'sgclabs-manager' );
					?>
				</h2>
				<ul style="line-height:1.8;">
					<li><strong><?php esc_html_e( 'Rows scanned:', 'sgclabs-manager' ); ?></strong>
						<?php echo esc_html( (string) $result['scanned'] ); ?></li>
					<li><strong><?php esc_html_e( 'Migrated:', 'sgclabs-manager' ); ?></strong>
						<?php echo esc_html( (string) $result['migrated'] ); ?></li>
					<li><strong><?php esc_html_e( 'Conflicts (canonical already had different value):', 'sgclabs-manager' ); ?></strong>
						<?php echo esc_html( (string) $result['conflicts'] ); ?></li>
					<li><strong><?php esc_html_e( 'Next offset:', 'sgclabs-manager' ); ?></strong>
						<?php echo esc_html( (string) $result['next_offset'] ); ?></li>
					<li><strong><?php esc_html_e( 'Finished:', 'sgclabs-manager' ); ?></strong>
						<?php echo $result['finished'] ? '✅ yes' : '⏳ no — keep clicking Apply'; ?></li>
				</ul>

				<?php if ( ! empty( $result['samples'] ) ) : ?>
					<h3><?php esc_html_e( 'Sample changes (first 10)', 'sgclabs-manager' ); ?></h3>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Post ID', 'sgclabs-manager' ); ?></th>
								<th><?php esc_html_e( 'Legacy key', 'sgclabs-manager' ); ?></th>
								<th><?php esc_html_e( 'Canonical key', 'sgclabs-manager' ); ?></th>
								<th><?php esc_html_e( 'Decision', 'sgclabs-manager' ); ?></th>
								<th><?php esc_html_e( 'Value preview', 'sgclabs-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $result['samples'] as $s ) : ?>
								<tr>
									<td><?php echo esc_html( (string) $s['post_id'] ); ?></td>
									<td><code style="font-size:11px;"><?php echo esc_html( $s['from'] ); ?></code></td>
									<td><code style="font-size:11px;"><?php echo esc_html( $s['to'] ); ?></code></td>
									<td><?php echo esc_html( $s['decision'] ); ?></td>
									<td style="max-width:300px;"><?php echo esc_html( $s['value'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- Recent log -->
		<?php if ( ! empty( $recent_log ) ) : ?>
			<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:22px;margin-top:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Recent migration log', 'sgclabs-manager' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Strategy', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Scanned', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Migrated', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Conflicts', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Offset', 'sgclabs-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_log as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $entry['time'] ) ); ?></td>
								<td><?php echo esc_html( $entry['strategy'] ); ?></td>
								<td><?php echo esc_html( (string) $entry['scanned'] ); ?></td>
								<td><?php echo esc_html( (string) $entry['migrated'] ); ?></td>
								<td><?php echo esc_html( (string) $entry['conflicts'] ); ?></td>
								<td><?php echo esc_html( (string) $entry['offset'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

endif;
