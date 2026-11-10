<?php
defined( 'ABSPATH' ) || exit;

class SGC_PM_B2B_Export {

	public static function register_ajax() {
		add_action( 'wp_ajax_sgc_pm_b2b_export',    [ __CLASS__, 'ajax_b2b_export' ] );
		add_action( 'wp_ajax_sgc_pm_save_usd_rate', [ __CLASS__, 'ajax_save_usd_rate' ] );
	}

	private static function verify() {
		check_ajax_referer( 'sgc_pm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) )
			wp_send_json_error( 'Unauthorized', 403 );
	}

	public static function ajax_b2b_export() {
		self::verify();

		$ids         = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) : [];
		$usd_rate    = floatval( get_option( 'sgc_pm_usd_rate', '83.5' ) );
		$moq         = (int) get_option( 'sgc_pm_default_moq', '10' );
		$dealer_disc = floatval( get_option( 'sgc_pm_dealer_discount', '20' ) );
		$company     = sanitize_text_field( get_option( 'sgc_pm_company_name', 'SGC LABS® Scientific Glassware' ) );
		$format      = sanitize_key( $_POST['format'] ?? 'csv' );

		if ( empty( $ids ) ) {
			// Export all if none selected
			$ids = get_posts( [ 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'publish' ] );
		}

		$rows = [];
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== get_post_status( $id ) ) continue;

			$price_inr   = floatval( $product->get_regular_price() ?: 0 );
			$price_usd   = $usd_rate > 0 ? round( $price_inr / $usd_rate, 2 ) : 0;
			$dealer_inr  = round( $price_inr * ( 1 - $dealer_disc / 100 ), 2 );
			$dealer_usd  = $usd_rate > 0 ? round( $dealer_inr / $usd_rate, 2 ) : 0;
			$cats        = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] );
			$prod_moq    = get_post_meta( $id, '_export_moq', true ) ?: $moq;

			$rows[] = [
				'id'          => $id,
				'name'        => html_entity_decode( $product->get_name() ),
				'sku'         => $product->get_sku(),
				'category'    => implode( ' > ', is_array( $cats ) ? $cats : [] ),
				'price_inr'   => $price_inr,
				'price_usd'   => $price_usd,
				'dealer_inr'  => $dealer_inr,
				'dealer_usd'  => $dealer_usd,
				'moq'         => $prod_moq,
				'stock'       => $product->get_stock_status() === 'instock' ? 'Available' : 'On Request',
			];
		}

		if ( 'csv' === $format ) {
			$header = [ 'ID', 'Product Name', 'SKU', 'Category', 'Retail Price (INR)', 'Retail Price (USD)', 'Dealer Price (INR)', 'Dealer Price (USD)', 'MOQ', 'Availability' ];
			$csv    = $company . " — B2B Price List — " . gmdate( 'd M Y' ) . "\n";
			$csv   .= implode( ',', $header ) . "\n";
			foreach ( $rows as $r ) {
				$csv .= implode( ',', array_map( fn($v) => '"' . str_replace( '"', '""', $v ) . '"', array_values( $r ) ) ) . "\n";
			}
			wp_send_json_success([
				'csv'      => base64_encode( $csv ),
				'filename' => 'sgc-b2b-pricelist-' . gmdate( 'Y-m-d' ) . '.csv',
				'count'    => count( $rows ),
				'format'   => 'csv',
			]);
		}

		// HTML preview for in-browser print/PDF
		if ( 'html' === $format ) {
			ob_start();
			self::render_b2b_html( $rows, $company );
			$html = ob_get_clean();
			wp_send_json_success([
				'html'   => $html,
				'count'  => count( $rows ),
				'format' => 'html',
			]);
		}

		wp_send_json_error( 'Unknown format.' );
	}

	private static function render_b2b_html( $rows, $company ) {
		?>
		<style>
		body{font-family:Arial,sans-serif;font-size:11px}
		table{width:100%;border-collapse:collapse}
		th{background:#1a1a2e;color:#fff;padding:6px 8px;text-align:left;font-size:10px;text-transform:uppercase}
		td{padding:5px 8px;border-bottom:1px solid #eee}
		tr:nth-child(even) td{background:#f8f8f8}
		.hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #1a1a2e}
		</style>
		<div class="hdr">
			<div>
				<strong style="font-size:16px"><?php echo esc_html( $company ); ?></strong><br>
				<span style="color:#888;font-size:10px">B2B Price List — <?php echo gmdate( 'd M Y' ); ?></span>
			</div>
			<div style="text-align:right;color:#888;font-size:10px">
				All prices exclusive of GST &amp; shipping<br>
				USD prices are approximate (FOB New Delhi)
			</div>
		</div>
		<table>
			<tr>
				<th>SKU</th><th>Product Name</th><th>Category</th>
				<th>Retail INR</th><th>Retail USD</th>
				<th>Dealer INR</th><th>Dealer USD</th>
				<th>MOQ</th><th>Availability</th>
			</tr>
			<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td><?php echo esc_html( $r['sku'] ); ?></td>
				<td><?php echo esc_html( $r['name'] ); ?></td>
				<td style="color:#888"><?php echo esc_html( $r['category'] ); ?></td>
				<td>₹<?php echo number_format( $r['price_inr'], 2 ); ?></td>
				<td>$<?php echo number_format( $r['price_usd'], 2 ); ?></td>
				<td style="color:#0066cc">₹<?php echo number_format( $r['dealer_inr'], 2 ); ?></td>
				<td style="color:#0066cc">$<?php echo number_format( $r['dealer_usd'], 2 ); ?></td>
				<td><?php echo esc_html( $r['moq'] ); ?></td>
				<td style="color:<?php echo $r['stock'] === 'Available' ? 'green' : '#e67e22'; ?>"><?php echo esc_html( $r['stock'] ); ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	public static function ajax_save_usd_rate() {
		self::verify();
		$rate = floatval( $_POST['rate'] ?? 0 );
		if ( $rate <= 0 ) wp_send_json_error( 'Invalid rate.' );
		update_option( 'sgc_pm_usd_rate', $rate );
		update_option( 'sgc_pm_dealer_discount', floatval( $_POST['dealer_disc'] ?? 20 ) );
		update_option( 'sgc_pm_default_moq', max( 1, (int) ( $_POST['moq'] ?? 10 ) ) );
		update_option( 'sgc_pm_company_name', sanitize_text_field( $_POST['company'] ?? 'SGC LABS® Scientific Glassware' ) );
		wp_send_json_success( 'Settings saved.' );
	}
}

SGC_PM_B2B_Export::register_ajax();
