<?php
/**
 * SGC LABS® — Inquiry Import / Export Admin Page (FR4)
 *
 * Lives at SGC LABS → Quotes & Inquiries → 📥 Import / Export. Lets the
 * admin export the unified inquiry store (JSON or CSV with date / status /
 * source filters) and import a previously exported JSON file (append or
 * merge mode). The export schema is versioned so a future plugin or a
 * separate SGC LABS® product can ingest it without ambiguity.
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sgclabs_inquiry_import_export_page' ) ) :

function sgclabs_inquiry_import_export_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'sgclabs-manager' ) );
	}

	// ---- Handle EXPORT (sends file directly, exits) ----------------------
	if ( ! empty( $_POST['sgc_inq_export'] ) ) {
		check_admin_referer( 'sgc_inquiry_io' );
		$format  = sanitize_key( $_POST['format'] ?? 'json' );
		$filters = [
			'status' => sanitize_key( $_POST['status'] ?? '' ),
			'source' => sanitize_key( $_POST['source'] ?? '' ),
			'from'   => isset( $_POST['from_date'] ) && $_POST['from_date'] !== ''
				? strtotime( sanitize_text_field( wp_unslash( $_POST['from_date'] ) ) ) : 0,
			'to'     => isset( $_POST['to_date'] ) && $_POST['to_date'] !== ''
				? strtotime( sanitize_text_field( wp_unslash( $_POST['to_date'] ) ) ) : 0,
		];
		$stamp = gmdate( 'Ymd-His' );

		if ( $format === 'csv' ) {
			$body = SGCLabs_Inquiry_Store::export_csv( $filters );
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="sgclabs-inquiries-' . $stamp . '.csv"' );
			echo $body; // already CSV-escaped
			exit;
		}

		$body = SGCLabs_Inquiry_Store::export_json( $filters );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sgclabs-inquiries-' . $stamp . '.json"' );
		echo $body;
		exit;
	}

	$import_result = null;

	// ---- Handle IMPORT --------------------------------------------------
	if ( ! empty( $_POST['sgc_inq_import'] ) ) {
		check_admin_referer( 'sgc_inquiry_io' );
		$mode = sanitize_key( $_POST['import_mode'] ?? 'append' );

		if ( ! empty( $_FILES['import_file']['tmp_name'] ) && is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			$contents      = file_get_contents( $_FILES['import_file']['tmp_name'] );
			$import_result = SGCLabs_Inquiry_Store::import_json( $contents, $mode );
		} else {
			$import_result = [ 'imported' => 0, 'skipped' => 0, 'errors' => [ 'No file uploaded.' ] ];
		}
	}

	$counts = SGCLabs_Inquiry_Store::counts();

	?>
	<div class="wrap" style="max-width:1100px;">
		<h1>📥 <?php esc_html_e( 'Inquiry Import / Export', 'sgclabs-manager' ); ?></h1>
		<p style="color:#555;max-width:780px;line-height:1.6;">
			<?php esc_html_e( 'The unified inquiry store collects entries from the contact shortcode, WooCommerce, and any module that fires `sgclabs_inquiry_received`. Export here for backups or to seed a separate plugin / install. Import is schema-versioned, so a future SGC LABS® plugin can ingest the same JSON without changes.', 'sgclabs-manager' ); ?>
		</p>

		<!-- Stats -->
		<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:18px 0 28px;">
			<?php foreach (
				[
					[ 'total',       '📊 Total',      '#1a1a2e' ],
					[ 'new',         '🆕 New',         '#0ea5e9' ],
					[ 'in_progress', '⚡ In progress', '#f59e0b' ],
					[ 'replied',     '✉️ Replied',     '#16a34a' ],
					[ 'closed',      '🔒 Closed',      '#6b7280' ],
				] as $card
			) :
				[ $key, $label, $color ] = $card;
				$num                     = $counts[ $key ] ?? 0;
				?>
				<div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid <?php echo esc_attr( $color ); ?>;padding:14px 16px;border-radius:6px;">
					<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">
						<?php echo esc_html( $label ); ?>
					</div>
					<div style="font-size:24px;font-weight:600;color:<?php echo esc_attr( $color ); ?>;margin-top:4px;">
						<?php echo esc_html( number_format_i18n( $num ) ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- EXPORT -->
		<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:22px;margin-bottom:22px;">
			<h2 style="margin-top:0;">⬇️ <?php esc_html_e( 'Export', 'sgclabs-manager' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'sgc_inquiry_io' ); ?>
				<table class="form-table" style="margin-top:0;">
					<tr>
						<th><?php esc_html_e( 'Format', 'sgclabs-manager' ); ?></th>
						<td>
							<label><input type="radio" name="format" value="json" checked> JSON (full, with replies + meta)</label><br>
							<label><input type="radio" name="format" value="csv"> CSV (flat, for Excel review)</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status filter', 'sgclabs-manager' ); ?></th>
						<td>
							<select name="status">
								<option value="">— <?php esc_html_e( 'all statuses', 'sgclabs-manager' ); ?> —</option>
								<option value="new">new</option>
								<option value="in_progress">in_progress</option>
								<option value="replied">replied</option>
								<option value="closed">closed</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Source filter', 'sgclabs-manager' ); ?></th>
						<td>
							<select name="source">
								<option value="">— <?php esc_html_e( 'all sources', 'sgclabs-manager' ); ?> —</option>
								<option value="shortcode">shortcode</option>
								<option value="woocommerce">woocommerce</option>
								<option value="manual">manual</option>
								<option value="imported">imported</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Date range', 'sgclabs-manager' ); ?></th>
						<td>
							<?php esc_html_e( 'From', 'sgclabs-manager' ); ?>
							<input type="date" name="from_date" style="margin-right:12px;">
							<?php esc_html_e( 'To', 'sgclabs-manager' ); ?>
							<input type="date" name="to_date">
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" name="sgc_inq_export" value="1" class="button button-primary">
						⬇️ <?php esc_html_e( 'Download export file', 'sgclabs-manager' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- IMPORT -->
		<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:22px;">
			<h2 style="margin-top:0;">⬆️ <?php esc_html_e( 'Import', 'sgclabs-manager' ); ?></h2>
			<p style="color:#555;">
				<?php esc_html_e( 'Upload a JSON file previously exported from this plugin (or another SGC LABS® plugin using the same schema).', 'sgclabs-manager' ); ?>
			</p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'sgc_inquiry_io' ); ?>
				<table class="form-table" style="margin-top:0;">
					<tr>
						<th><?php esc_html_e( 'JSON file', 'sgclabs-manager' ); ?></th>
						<td><input type="file" name="import_file" accept=".json,application/json" required></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mode', 'sgclabs-manager' ); ?></th>
						<td>
							<label><input type="radio" name="import_mode" value="append" checked>
								<?php esc_html_e( 'Append (add everything, even duplicates)', 'sgclabs-manager' ); ?>
							</label><br>
							<label><input type="radio" name="import_mode" value="merge">
								<?php esc_html_e( 'Merge (skip duplicates by id / email + timestamp)', 'sgclabs-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" name="sgc_inq_import" value="1" class="button">
						⬆️ <?php esc_html_e( 'Import', 'sgclabs-manager' ); ?>
					</button>
				</p>
			</form>
		</div>

		<?php if ( $import_result ) : ?>
			<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:22px;margin-top:18px;">
				<h2 style="margin-top:0;">📋 <?php esc_html_e( 'Import result', 'sgclabs-manager' ); ?></h2>
				<ul style="line-height:1.8;">
					<li><strong><?php esc_html_e( 'Imported:', 'sgclabs-manager' ); ?></strong>
						<?php echo esc_html( (string) ( $import_result['imported'] ?? 0 ) ); ?></li>
					<li><strong><?php esc_html_e( 'Skipped:', 'sgclabs-manager' ); ?></strong>
						<?php echo esc_html( (string) ( $import_result['skipped'] ?? 0 ) ); ?></li>
				</ul>
				<?php if ( ! empty( $import_result['errors'] ) ) : ?>
					<p style="color:#dc2626;"><strong><?php esc_html_e( 'Errors:', 'sgclabs-manager' ); ?></strong></p>
					<ul style="color:#dc2626;">
						<?php foreach ( $import_result['errors'] as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

endif;
