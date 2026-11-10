<?php
/**
 * SGC LABS® — Bulk Multilingual Generator (FR1 + Bug 2 + FR3)
 *
 * One admin page to:
 *   - Enable Arabic SEO (and the other 7 languages) for many products in a
 *     single click — no more per-product checkbox (Bug 2 root cause).
 *   - Generate `seo_title`, `meta_desc`, `short_desc`, `main_desc` translations
 *     in 8 languages via the AI provider router (FR1).
 *   - Apply a chosen tone preset to every regeneration (FR3).
 *   - Filter the target product set by category (FR5 — used here too).
 *
 * Heavy lifting goes through the v6.9.1 Background Processor so 7000-product
 * bulk operations don't time out on Bluehost shared hosting.
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ────────────────────────────────────────────────────────────
// Routing helper (FR1)
// ────────────────────────────────────────────────────────────

if ( ! function_exists( 'sgclabs_multilang_pick_provider' ) ) :
	/**
	 * Decide which AI provider to use for a given language + field.
	 *
	 * Defaults to DeepSeek for cost-efficient bulk translation.
	 * Arabic falls back to Claude Haiku because Arabic technical translation
	 * (RTL + scientific terminology) is consistently better there.
	 *
	 * @param string $lang  Target language code.
	 * @param string $field Logical SEO field id.
	 * @return string Provider id ('claude' | 'deepseek' | 'gemini' | …).
	 */
	function sgclabs_multilang_pick_provider( string $lang, string $field ): string {
		// Arabic technical: prefer Claude Haiku.
		if ( $lang === 'ar' && in_array( $field, [ 'main_desc', 'short_desc', 'meta_desc' ], true ) ) {
			return 'claude';
		}
		// Everything else: DeepSeek (cheap, multilingual).
		return 'deepseek';
	}
endif;

// ────────────────────────────────────────────────────────────
// Background-processor handler — translates one product
// ────────────────────────────────────────────────────────────

if ( ! function_exists( 'sgclabs_multilang_translate_one' ) ) :
	/**
	 * Background job entry-point.
	 *
	 * @param array $payload {
	 *   @type int    $post_id
	 *   @type array  $langs   List of target language codes.
	 *   @type array  $fields  Logical field ids to translate.
	 *   @type string $tone    Tone preset id.
	 * }
	 * @return true|WP_Error
	 */
	function sgclabs_multilang_translate_one( array $payload ) {
		$post_id = (int) ( $payload['post_id'] ?? 0 );
		$langs   = (array) ( $payload['langs'] ?? [] );
		$fields  = (array) ( $payload['fields'] ?? [] );
		$tone    = (string) ( $payload['tone'] ?? 'technical' );

		if ( ! $post_id ) {
			return new WP_Error( 'no_post', 'Missing post_id' );
		}
		if ( ! function_exists( 'sgclabs_ai_call' ) ) {
			return new WP_Error( 'no_ai', 'AI provider not configured' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'no_post', 'Post not found' );
		}

		$tones      = SGCLabs_SEO_Fields::tones();
		$tone_intro = $tones[ $tone ]['prompt'] ?? $tones['technical']['prompt'];

		foreach ( $fields as $field ) {
			$source_text = sgclabs_seo_get( $post_id, $field, 'en' );
			if ( $source_text === '' ) {
				continue;
			}
			foreach ( $langs as $lang ) {
				if ( $lang === 'en' ) {
					continue; // English is the source.
				}
				if ( ! SGCLabs_SEO_Fields::is_supported_language( $lang ) ) {
					continue;
				}
				$existing = sgclabs_seo_get( $post_id, $field, $lang );
				$src      = sgclabs_seo_source( $post_id, $field, $lang );
				// Hybrid policy: skip manual-edited targets.
				if ( $existing !== '' && $src === 'manual' ) {
					continue;
				}

				$lang_cfg  = SGCLabs_SEO_Fields::languages()[ $lang ];
				$lang_name = $lang_cfg['label'];

				$prompt = "$tone_intro\n\n"
					. "Translate this product copy from English to {$lang_name}.\n"
					. "Field: {$field}\n"
					. "Product: " . $post->post_title . "\n"
					. "Source text:\n{$source_text}\n\n"
					. "Rules:\n"
					. "- Preserve product names, model numbers, units (mm, ml, kg) verbatim.\n"
					. "- Match the tone described above.\n"
					. "- Output ONLY the translated text. No commentary, no quotes, no labels.\n";

				$provider  = sgclabs_multilang_pick_provider( $lang, $field );
				$translated = sgclabs_ai_call( $prompt, [
					'provider'   => $provider,
					'max_tokens' => 800,
				] );
				if ( ! is_string( $translated ) || $translated === '' ) {
					continue;
				}
				$translated = trim( $translated );
				sgclabs_seo_set( $post_id, $field, $translated, 'ai', $lang );
			}
		}

		// Bug 2 fix: turn on the "Arabic SEO" flag automatically so the
		// existing front-end emitter starts injecting tags. Same for any
		// other language — done by writing a meta key the emitter reads.
		foreach ( $langs as $lang ) {
			if ( $lang === 'en' ) {
				continue;
			}
			update_post_meta( $post_id, '_sgclabs_lang_' . $lang . '_active', '1' );
			if ( $lang === 'ar' ) {
				// Maintain backward compatibility with amazon-arabic.php.
				update_post_meta( $post_id, '_sgclabs_ar_active', '1' );
			}
		}

		return true;
	}
endif;

// ────────────────────────────────────────────────────────────
// Admin page
// ────────────────────────────────────────────────────────────

if ( ! function_exists( 'sgclabs_multilang_bulk_page' ) ) :

function sgclabs_multilang_bulk_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'sgclabs-manager' ) );
	}

	$queued = 0;

	if ( ! empty( $_POST['sgc_ml_queue'] ) ) {
		check_admin_referer( 'sgc_multilang_bulk' );

		$category = isset( $_POST['category'] ) ? (int) $_POST['category'] : 0;
		$langs    = array_filter( (array) ( $_POST['langs'] ?? [] ), function ( $code ) {
			return SGCLabs_SEO_Fields::is_supported_language( $code );
		} );
		$fields   = array_intersect(
			(array) ( $_POST['fields'] ?? [] ),
			array_keys( SGCLabs_SEO_Fields::map() )
		);
		$tone     = isset( $_POST['tone'] ) && SGCLabs_SEO_Fields::is_supported_tone( sanitize_key( $_POST['tone'] ) )
			? sanitize_key( $_POST['tone'] )
			: SGCLabs_SEO_Fields::default_tone();

		// Build product query.
		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 5000,
		];
		if ( $category > 0 ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category,
				],
			];
		}
		$ids = get_posts( $args );

		if ( class_exists( 'SGCLabs_BG_Processor' ) ) {
			foreach ( $ids as $id ) {
				SGCLabs_BG_Processor::queue(
					'multilang_translate',
					[
						'post_id' => (int) $id,
						'langs'   => array_values( $langs ),
						'fields'  => array_values( $fields ),
						'tone'    => $tone,
					],
					'sgclabs_multilang_translate_one'
				);
				$queued++;
			}
			// Kick off immediately so the user doesn't wait for the next cron tick.
			if ( $queued > 0 ) {
				SGCLabs_BG_Processor::tick();
			}
		}

		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html(
				sprintf(
					/* translators: %d: queued count */
					_n(
						'Queued %d product for multilingual translation.',
						'Queued %d products for multilingual translation.',
						$queued,
						'sgclabs-manager'
					),
					$queued
				)
			)
			. ' <a href="' . esc_url( admin_url( 'admin.php?page=sgclabs-bg-processor' ) ) . '">'
			. esc_html__( 'View Background Processor →', 'sgclabs-manager' )
			. '</a></p></div>';
	}

	$all_categories = get_terms( [
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	] );

	?>
	<div class="wrap" style="max-width:1100px;">
		<h1>🌐 <?php esc_html_e( 'Bulk Multilingual Generator', 'sgclabs-manager' ); ?></h1>
		<p style="color:#555;max-width:780px;line-height:1.6;">
			<?php esc_html_e( 'Pick a category, the languages you want, the fields to translate, and a tone — every matching product is queued in the Background Processor. AI does the heavy lifting; manual entries are never overwritten.', 'sgclabs-manager' ); ?>
		</p>

		<form method="post" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;margin-top:18px;">
			<?php wp_nonce_field( 'sgc_multilang_bulk' ); ?>

			<table class="form-table">
				<!-- FR5: Category filter -->
				<tr>
					<th><?php esc_html_e( 'Product category', 'sgclabs-manager' ); ?></th>
					<td>
						<select name="category" style="min-width:280px;">
							<option value="0">— <?php esc_html_e( 'All products', 'sgclabs-manager' ); ?> —</option>
							<?php if ( ! is_wp_error( $all_categories ) && is_array( $all_categories ) ) : ?>
								<?php foreach ( $all_categories as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>">
										<?php echo esc_html( $cat->name . ' (' . $cat->count . ')' ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</td>
				</tr>

				<!-- FR1: Languages -->
				<tr>
					<th><?php esc_html_e( 'Languages', 'sgclabs-manager' ); ?></th>
					<td>
						<?php foreach ( SGCLabs_SEO_Fields::languages() as $code => $cfg ) : ?>
							<?php if ( $code === 'en' ) continue; ?>
							<label style="display:inline-block;margin-right:14px;">
								<input type="checkbox" name="langs[]" value="<?php echo esc_attr( $code ); ?>"
									<?php checked( in_array( $code, [ 'ar', 'fr', 'de', 'es' ], true ) ); ?>>
								<?php echo esc_html( $cfg['label'] . ' (' . strtoupper( $code ) . ')' ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>

				<!-- Fields -->
				<tr>
					<th><?php esc_html_e( 'Fields to translate', 'sgclabs-manager' ); ?></th>
					<td>
						<?php
						$default_fields = [ 'seo_title', 'meta_desc', 'short_desc', 'main_desc' ];
						foreach ( SGCLabs_SEO_Fields::map() as $field => $cfg ) :
							if ( empty( $cfg['multilang'] ) ) continue;
							?>
							<label style="display:inline-block;margin-right:14px;">
								<input type="checkbox" name="fields[]" value="<?php echo esc_attr( $field ); ?>"
									<?php checked( in_array( $field, $default_fields, true ) ); ?>>
								<?php echo esc_html( $cfg['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>

				<!-- FR3: Tone -->
				<tr>
					<th><?php esc_html_e( 'Description tone', 'sgclabs-manager' ); ?></th>
					<td>
						<select name="tone" style="min-width:280px;">
							<?php
							$current_tone = SGCLabs_SEO_Fields::default_tone();
							foreach ( SGCLabs_SEO_Fields::tones() as $tid => $tcfg ) :
								?>
								<option value="<?php echo esc_attr( $tid ); ?>" <?php selected( $current_tone, $tid ); ?>>
									<?php echo esc_html( $tcfg['icon'] . ' ' . $tcfg['label'] . ' — ' . $tcfg['good_for'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" name="sgc_ml_queue" value="1" class="button button-primary">
					🚀 <?php esc_html_e( 'Queue all matching products', 'sgclabs-manager' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sgclabs-bg-processor' ) ); ?>" class="button">
					🖥️ <?php esc_html_e( 'View Background Processor', 'sgclabs-manager' ); ?>
				</a>
			</p>

			<p style="color:#6b7280;font-size:12px;line-height:1.7;margin-top:14px;">
				<strong><?php esc_html_e( 'AI routing:', 'sgclabs-manager' ); ?></strong>
				<?php esc_html_e( 'Arabic translations of long-form text use Claude Haiku (better for RTL technical content). All other translations route through DeepSeek for cost-efficient bulk processing.', 'sgclabs-manager' ); ?>
				<br>
				<strong><?php esc_html_e( 'Hybrid safety:', 'sgclabs-manager' ); ?></strong>
				<?php esc_html_e( 'Manual entries (any field whose source is "manual") are never overwritten. Only AI-written or empty targets are populated.', 'sgclabs-manager' ); ?>
			</p>
		</form>
	</div>
	<?php
}

endif;
