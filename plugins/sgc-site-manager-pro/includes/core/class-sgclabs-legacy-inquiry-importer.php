<?php
/**
 * SGC LABS® — Legacy Inquiry Table Bridge (Bug 4)
 *
 * Pulls existing rows out of the legacy `wp_sgclabs_inquiries` DB table into
 * the unified `SGCLabs_Inquiry_Store` so the Reply Center's count reflects
 * everything historically received, not just what the shortcode posted after
 * v6.9.3 was activated.
 *
 * Runs at most once (state stored in `sgclabs_inquiry_table_imported`). The
 * admin can re-trigger by deleting that option and revisiting any admin page.
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SGCLabs_Legacy_Inquiry_Importer' ) ) :

final class SGCLabs_Legacy_Inquiry_Importer {

	const FLAG_OPTION = 'sgclabs_inquiry_table_imported';

	public static function boot(): void {
		add_action( 'admin_init', [ __CLASS__, 'maybe_import' ] );
	}

	public static function maybe_import(): void {
		if ( get_option( self::FLAG_OPTION ) === 'done' ) {
			return;
		}
		if ( ! class_exists( 'SGCLabs_Inquiry_Store' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'sgclabs_inquiries';
		// Detect table; bail silently if it doesn't exist.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			update_option( self::FLAG_OPTION, 'done', false );
			return;
		}

		$rows = $wpdb->get_results( "SELECT * FROM `$table` ORDER BY id ASC LIMIT 5000", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			update_option( self::FLAG_OPTION, 'done', false );
			return;
		}

		$imported = 0;
		foreach ( $rows as $row ) {
			$created_at = isset( $row['created_at'] )
				? strtotime( (string) $row['created_at'] )
				: ( isset( $row['date'] ) ? strtotime( (string) $row['date'] ) : time() );

			SGCLabs_Inquiry_Store::add( [
				'source'        => 'shortcode',
				'status'        => 'new',
				'name'          => (string) ( $row['name'] ?? '' ),
				'email'         => (string) ( $row['email'] ?? '' ),
				'phone'         => (string) ( $row['phone'] ?? '' ),
				'country'       => (string) ( $row['country'] ?? '' ),
				'company'       => (string) ( $row['company'] ?? '' ),
				'subject'       => 'Product Inquiry: ' . (string) ( $row['product'] ?? '' ),
				'message'       => (string) ( $row['message'] ?? '' ),
				'product_title' => (string) ( $row['product'] ?? '' ),
				'created_at'    => $created_at ?: time(),
				'meta'          => [
					'incoterm' => (string) ( $row['incoterm'] ?? '' ),
					'port'     => (string) ( $row['port'] ?? '' ),
					'qty'      => (string) ( $row['qty'] ?? '' ),
					'legacy_id'=> (int) ( $row['id'] ?? 0 ),
				],
			] );
			$imported++;
		}

		update_option( self::FLAG_OPTION, 'done', false );

		if ( $imported > 0 ) {
			add_action( 'admin_notices', function () use ( $imported ) {
				echo '<div class="notice notice-success is-dismissible"><p>'
					. '<strong>SGC LABS®:</strong> '
					. esc_html(
						sprintf(
							/* translators: %d: count */
							_n(
								'Imported %d historical inquiry into the unified Reply Center.',
								'Imported %d historical inquiries into the unified Reply Center.',
								$imported,
								'sgclabs-manager'
							),
							$imported
						)
					)
					. '</p></div>';
			} );
		}
	}
}

SGCLabs_Legacy_Inquiry_Importer::boot();

endif;
