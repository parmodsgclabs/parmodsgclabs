<?php
/**
 * SGC LABS® — Background Processor Admin Page
 *
 * Fixes Bug 3 (v6.9.1): the URL `?page=sgclabs-bg-processor` was linked from
 * AI Content Center but had no registered handler, so WordPress threw the
 * "Sorry, you are not allowed to access this page" guard. This page now exists,
 * is registered through admin-menu.php, and exposes the queue managed by
 * SGCLabs_BG_Processor.
 *
 * @package SGCLabs\Core
 * @since   6.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sgclabs_bg_processor_page' ) ) :
	/**
	 * Render the Background Processor admin page.
	 */
	function sgclabs_bg_processor_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sgclabs-manager' ) );
		}

		// Handle inline actions (purge / cancel / delete).
		if ( ! empty( $_POST['sgc_bg_action'] ) ) {
			check_admin_referer( 'sgclabs_bg_processor_action' );
			$action = sanitize_key( wp_unslash( $_POST['sgc_bg_action'] ) );
			$jobid  = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
			$msg    = '';

			if ( class_exists( 'SGCLabs_BG_Processor' ) ) {
				switch ( $action ) {
					case 'cancel':
						if ( $jobid && SGCLabs_BG_Processor::cancel( $jobid ) ) {
							$msg = __( 'Job cancelled.', 'sgclabs-manager' );
						}
						break;
					case 'delete':
						if ( $jobid && SGCLabs_BG_Processor::delete( $jobid ) ) {
							$msg = __( 'Job removed.', 'sgclabs-manager' );
						}
						break;
					case 'purge':
						$n   = SGCLabs_BG_Processor::purge_finished();
						$msg = sprintf(
							/* translators: %d: number of jobs cleared */
							__( 'Cleared %d finished jobs.', 'sgclabs-manager' ),
							$n
						);
						break;
					case 'tick_now':
						SGCLabs_BG_Processor::tick();
						$msg = __( 'Processor ticked manually.', 'sgclabs-manager' );
						break;
				}
			}

			if ( $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>'
					. esc_html( $msg ) . '</p></div>';
			}
		}

		$counts = class_exists( 'SGCLabs_BG_Processor' )
			? SGCLabs_BG_Processor::counts()
			: [ 'total' => 0, 'queued' => 0, 'running' => 0, 'done' => 0, 'failed' => 0, 'cancelled' => 0 ];

		$jobs = class_exists( 'SGCLabs_BG_Processor' )
			? SGCLabs_BG_Processor::all_jobs()
			: [];

		// Newest first.
		usort( $jobs, function ( $a, $b ) {
			return ( $b['updated_at'] ?? 0 ) <=> ( $a['updated_at'] ?? 0 );
		} );

		?>
		<div class="wrap" style="max-width:1200px;">
			<h1>🖥️ <?php esc_html_e( 'Background Processor', 'sgclabs-manager' ); ?></h1>
			<p style="color:#555;max-width:780px;line-height:1.6;">
				<?php esc_html_e( 'Long-running tasks (bulk AI generation, multilingual translation, related-link discovery, reorder reminder blasts) run here in the background via WP-Cron, so the browser never times out. Each job retries up to 3 times before being marked failed.', 'sgclabs-manager' ); ?>
			</p>

			<!-- Status cards -->
			<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin:18px 0 24px;">
				<?php foreach (
					[
						[ 'queued',    '⏳ Queued',    '#0ea5e9' ],
						[ 'running',   '⚡ Running',   '#f59e0b' ],
						[ 'done',      '✅ Done',      '#16a34a' ],
						[ 'failed',    '❌ Failed',    '#dc2626' ],
						[ 'cancelled', '⛔ Cancelled', '#6b7280' ],
						[ 'total',     '📊 Total',    '#1a1a2e' ],
					] as $card
				) :
					[ $key, $label, $color ] = $card;
					$num                     = $counts[ $key ] ?? 0;
					?>
					<div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid <?php echo esc_attr( $color ); ?>;padding:14px 16px;border-radius:6px;">
						<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">
							<?php echo esc_html( $label ); ?>
						</div>
						<div style="font-size:26px;font-weight:600;color:<?php echo esc_attr( $color ); ?>;margin-top:4px;">
							<?php echo esc_html( number_format_i18n( $num ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Toolbar -->
			<form method="post" style="margin-bottom:18px;">
				<?php wp_nonce_field( 'sgclabs_bg_processor_action' ); ?>
				<button type="submit" name="sgc_bg_action" value="tick_now" class="button">
					⚡ <?php esc_html_e( 'Run one tick now', 'sgclabs-manager' ); ?>
				</button>
				<button type="submit" name="sgc_bg_action" value="purge" class="button"
					onclick="return confirm('<?php echo esc_js( __( 'Clear all finished (done/failed/cancelled) jobs?', 'sgclabs-manager' ) ); ?>');">
					🧹 <?php esc_html_e( 'Clear finished jobs', 'sgclabs-manager' ); ?>
				</button>
			</form>

			<!-- Queue table -->
			<?php if ( empty( $jobs ) ) : ?>
				<div style="background:#f9fafb;border:1px dashed #d1d5db;padding:40px;text-align:center;border-radius:6px;color:#6b7280;">
					<?php esc_html_e( 'Queue is empty. Background jobs will appear here when modules schedule work.', 'sgclabs-manager' ); ?>
				</div>
			<?php else : ?>
				<table class="widefat striped" style="background:#fff;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Job ID', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Type', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Status', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Attempts', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Updated', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Last Error', 'sgclabs-manager' ); ?></th>
							<th><?php esc_html_e( 'Action', 'sgclabs-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $jobs as $job ) :
							$status_color = [
								'queued'    => '#0ea5e9',
								'running'   => '#f59e0b',
								'done'      => '#16a34a',
								'failed'    => '#dc2626',
								'cancelled' => '#6b7280',
							][ $job['status'] ?? 'queued' ] ?? '#6b7280';
							?>
							<tr>
								<td style="font-family:monospace;font-size:11px;color:#6b7280;">
									<?php echo esc_html( substr( $job['id'], 0, 16 ) ); ?>…
								</td>
								<td><?php echo esc_html( $job['type'] ); ?></td>
								<td>
									<span style="background:<?php echo esc_attr( $status_color ); ?>;color:#fff;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:500;">
										<?php echo esc_html( strtoupper( $job['status'] ?? '?' ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( (string) ( $job['attempts'] ?? 0 ) ); ?></td>
								<td style="font-size:12px;color:#6b7280;">
									<?php
									echo esc_html(
										wp_date(
											'Y-m-d H:i:s',
											isset( $job['updated_at'] ) ? (int) $job['updated_at'] : time()
										)
									);
									?>
								</td>
								<td style="font-size:12px;color:#dc2626;max-width:280px;word-wrap:break-word;">
									<?php echo esc_html( $job['last_error'] ?? '' ); ?>
								</td>
								<td>
									<form method="post" style="display:inline;">
										<?php wp_nonce_field( 'sgclabs_bg_processor_action' ); ?>
										<input type="hidden" name="job_id" value="<?php echo esc_attr( $job['id'] ); ?>" />
										<?php if ( $job['status'] === 'queued' ) : ?>
											<button type="submit" name="sgc_bg_action" value="cancel" class="button button-small">
												<?php esc_html_e( 'Cancel', 'sgclabs-manager' ); ?>
											</button>
										<?php else : ?>
											<button type="submit" name="sgc_bg_action" value="delete" class="button button-small">
												<?php esc_html_e( 'Remove', 'sgclabs-manager' ); ?>
											</button>
										<?php endif; ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p style="margin-top:24px;color:#6b7280;font-size:12px;">
				<?php
				$next = wp_next_scheduled( 'sgclabs_bg_tick' );
				if ( $next ) {
					/* translators: %s: human-readable time */
					echo esc_html(
						sprintf(
							__( 'Next scheduled tick: %s', 'sgclabs-manager' ),
							wp_date( 'Y-m-d H:i:s', $next )
						)
					);
				} else {
					esc_html_e( 'Cron tick is not scheduled — try deactivating and reactivating the plugin.', 'sgclabs-manager' );
				}
				?>
			</p>
		</div>
		<?php
	}
endif;
