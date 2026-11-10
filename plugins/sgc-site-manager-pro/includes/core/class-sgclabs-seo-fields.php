<?php
/**
 * SGC LABS® — SEO Fields Canonical Schema
 *
 * Single source of truth for every SEO-related meta key the plugin reads or writes.
 *
 * Before v6.9.3, the same logical SEO field (e.g. "page title") was scattered
 * across 5+ different post-meta keys (`_yoast_wpseo_title`, `_sgclabs_meta_title`,
 * `_sgclabs_seo_title`, `_sgclabs_meta_page_title`, `_sgclabs_ai_seo_title`, …).
 * Modules silently overwrote each other — manual edits got clobbered by AI batches,
 * Bulk Editor saved one key while Quick Editor read another, and translations had
 * their own private namespace. This class ends that.
 *
 * Every module that touches SEO data MUST go through `sgclabs_seo_get()` /
 * `sgclabs_seo_set()` (defined in `seo-api.php`), which in turn consult this
 * registry to find the canonical key. A backward-compatibility filter
 * (`seo-legacy-bridge.php`) keeps legacy reads working until the migrator runs.
 *
 * @package SGCLabs\Core
 * @since   6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SGCLabs_SEO_Fields' ) ) :

final class SGCLabs_SEO_Fields {

	/**
	 * Canonical key map.
	 *
	 * Format:
	 *   field_id => [
	 *       'key'        => '_sgclabs_<short>',
	 *       'label'      => 'Human label',
	 *       'multilang'  => bool — does this field accept language variants?
	 *       'legacy'     => [ 'old_key1', 'old_key2', … ],
	 *       'max_length' => int|null — soft length hint
	 *   ]
	 *
	 * @return array<string,array>
	 */
	public static function map(): array {
		return [
			// ── Core SEO ────────────────────────────────────────────────
			'seo_title' => [
				'key'        => '_sgclabs_seo_title',
				'label'      => 'SEO Page Title',
				'multilang'  => true,
				'legacy'     => [
					'_sgclabs_ai_seo_title',
					'_sgclabs_meta_title',
					'_sgclabs_meta_page_title',
					'_yoast_wpseo_title',
					'rank_math_title',
				],
				'max_length' => 60,
			],
			'meta_desc' => [
				'key'        => '_sgclabs_meta_desc',
				'label'      => 'Meta Description',
				'multilang'  => true,
				'legacy'     => [
					'_sgclabs_ai_meta_desc',
					'_sgclabs_seo_description',
					'_yoast_wpseo_metadesc',
					'rank_math_description',
				],
				'max_length' => 155,
			],
			'focus_kw' => [
				'key'        => '_sgclabs_focus_kw',
				'label'      => 'Focus Keyword',
				'multilang'  => false,
				'legacy'     => [
					'_sgclabs_focus_keyword',
					'_yoast_wpseo_focuskw',
					'rank_math_focus_keyword',
				],
				'max_length' => null,
			],
			'keywords' => [
				'key'        => '_sgclabs_keywords',
				'label'      => 'Keywords (CSV)',
				'multilang'  => true,
				'legacy'     => [
					'_sgclabs_global_kw',
				],
				'max_length' => null,
			],

			// ── Long-form content (multilingual) ────────────────────────
			'short_desc' => [
				'key'        => '_sgclabs_short_desc',
				'label'      => 'Short Description',
				'multilang'  => true,
				'legacy'     => [
					'_sgclabs_ai_short_desc',
				],
				'max_length' => null,
				'mirrors'    => 'post_excerpt', // English value also written to native field
			],
			'main_desc' => [
				'key'        => '_sgclabs_main_desc',
				'label'      => 'Main Description',
				'multilang'  => true,
				'legacy'     => [
					'_sgclabs_ai_main_desc',
				],
				'max_length' => null,
				'mirrors'    => 'post_content', // English value also written to native field
			],

			// ── Open Graph ──────────────────────────────────────────────
			'og_title' => [
				'key'       => '_sgclabs_og_title',
				'label'     => 'OG Title',
				'multilang' => false,
				'legacy'    => [],
			],
			'og_desc' => [
				'key'       => '_sgclabs_og_desc',
				'label'     => 'OG Description',
				'multilang' => false,
				'legacy'    => [ '_sgclabs_og_description' ],
			],

			// ── Twitter ─────────────────────────────────────────────────
			'twitter_title' => [
				'key'       => '_sgclabs_twitter_title',
				'label'     => 'Twitter Title',
				'multilang' => false,
				'legacy'    => [],
			],
			'twitter_desc' => [
				'key'       => '_sgclabs_twitter_desc',
				'label'     => 'Twitter Description',
				'multilang' => false,
				'legacy'    => [ '_sgclabs_twitter_description' ],
			],

			// ── Misc ────────────────────────────────────────────────────
			'breadcrumb_title' => [
				'key'       => '_sgclabs_breadcrumb_title',
				'label'     => 'Breadcrumb Title',
				'multilang' => false,
				'legacy'    => [],
			],
			'schema_desc' => [
				'key'       => '_sgclabs_schema_desc',
				'label'     => 'Schema Description',
				'multilang' => false,
				'legacy'    => [],
			],
		];
	}

	/**
	 * Resolve a logical field id to its canonical post-meta key.
	 * If a language is given and the field supports translations, the
	 * key is suffixed (`_ar`, `_fr`, …). English (`en` or empty) returns
	 * the base key.
	 *
	 * @param string $field Logical field id (e.g. 'seo_title').
	 * @param string $lang  ISO code or '' for default.
	 * @return string|null Meta key, or null if field unknown.
	 */
	public static function key( string $field, string $lang = '' ): ?string {
		$map = self::map();
		if ( ! isset( $map[ $field ] ) ) {
			return null;
		}
		$base = $map[ $field ]['key'];
		$lang = strtolower( trim( $lang ) );
		if ( $lang === '' || $lang === 'en' ) {
			return $base;
		}
		if ( empty( $map[ $field ]['multilang'] ) ) {
			return $base; // ignore lang for non-multilingual fields
		}
		if ( ! self::is_supported_language( $lang ) ) {
			return null;
		}
		return $base . '_' . $lang;
	}

	/**
	 * Companion key that records who last wrote the canonical field.
	 * Used for audit / hybrid migration ("regenerate AI-written fields, leave manual ones").
	 */
	public static function source_key( string $field, string $lang = '' ): ?string {
		$base = self::key( $field, $lang );
		return $base ? $base . '__src' : null;
	}

	/**
	 * All legacy keys (across every field) that the bridge filter should
	 * transparently redirect to canonical reads.
	 *
	 * @return array<string,string> legacy_key => canonical_key
	 */
	public static function legacy_alias_map(): array {
		$out = [];
		foreach ( self::map() as $field => $cfg ) {
			foreach ( $cfg['legacy'] ?? [] as $legacy ) {
				$out[ $legacy ] = $cfg['key'];
			}
		}
		return $out;
	}

	// ────────────────────────────────────────────────────────────
	// Languages
	// ────────────────────────────────────────────────────────────

	/**
	 * Supported languages for translation (FR1).
	 *
	 * Order matters — first key is the default (no suffix) and is used as
	 * the source language for AI translations.
	 *
	 * @return array<string,array{label:string,native:string,rtl:bool,hreflang:string}>
	 */
	public static function languages(): array {
		return [
			'en' => [ 'label' => 'English',     'native' => 'English',  'rtl' => false, 'hreflang' => 'en' ],
			'ar' => [ 'label' => 'Arabic',      'native' => 'العربية',   'rtl' => true,  'hreflang' => 'ar' ],
			'fr' => [ 'label' => 'French',      'native' => 'Français', 'rtl' => false, 'hreflang' => 'fr' ],
			'de' => [ 'label' => 'German',      'native' => 'Deutsch',  'rtl' => false, 'hreflang' => 'de' ],
			'it' => [ 'label' => 'Italian',     'native' => 'Italiano', 'rtl' => false, 'hreflang' => 'it' ],
			'es' => [ 'label' => 'Spanish',     'native' => 'Español',  'rtl' => false, 'hreflang' => 'es' ],
			'pt' => [ 'label' => 'Portuguese',  'native' => 'Português','rtl' => false, 'hreflang' => 'pt' ],
			'zh' => [ 'label' => 'Chinese',     'native' => '中文',      'rtl' => false, 'hreflang' => 'zh-CN' ],
		];
	}

	public static function is_supported_language( string $code ): bool {
		return array_key_exists( strtolower( $code ), self::languages() );
	}

	// ────────────────────────────────────────────────────────────
	// Description tones (FR3)
	// ────────────────────────────────────────────────────────────

	/**
	 * Tone presets for AI-generated descriptions.
	 *
	 * Each entry includes:
	 *   - label    : UI label
	 *   - icon     : Emoji/icon for the dropdown
	 *   - prompt   : System-prompt fragment fed to the AI
	 *   - good_for : Use-case hint
	 */
	public static function tones(): array {
		return [
			'professional' => [
				'label'    => 'Professional',
				'icon'     => '🎓',
				'prompt'   => 'Write in a professional, clean B2B tone — confident, neutral, factual. Active voice. No marketing fluff.',
				'good_for' => 'Standard B2B copy',
			],
			'formal' => [
				'label'    => 'Formal',
				'icon'     => '📋',
				'prompt'   => 'Write in a formal, institutional tone suitable for procurement officers, government tenders, and university buyers. Avoid contractions; use full noun phrases.',
				'good_for' => 'Government / institutional buyers',
			],
			'technical' => [
				'label'    => 'Technical + Detailed',
				'icon'     => '🔬',
				'prompt'   => 'Write in a precise technical tone. Lead with specifications, materials, tolerances, and standards. Assume the reader is a lab manager or quality engineer who wants exact numbers.',
				'good_for' => 'Lab managers, QC engineers',
			],
			'scientific' => [
				'label'    => 'Scientific / Academic',
				'icon'     => '📜',
				'prompt'   => 'Write in an academic register, methodology-oriented. Reference applicable standards (ASTM/ISO/USP) when relevant. Avoid promotional language.',
				'good_for' => 'Researchers, paper-cited use',
			],
			'sales' => [
				'label'    => 'Sales / Persuasive',
				'icon'     => '💼',
				'prompt'   => 'Write in a benefits-first sales tone. Lead with the buyer outcome, then back it with proof points. End with a soft CTA.',
				'good_for' => 'Marketing campaigns, ads',
			],
			'export' => [
				'label'    => 'International / Export',
				'icon'     => '🌐',
				'prompt'   => 'Write in simple, clear English designed for non-native readers. Short sentences. Avoid idioms and slang. Use concrete units (mm, ml, kg) on first reference.',
				'good_for' => 'International export buyers',
			],
			'concise' => [
				'label'    => 'Concise / Minimal',
				'icon'     => '✂️',
				'prompt'   => 'Write tersely. No fluff, no filler. Every sentence carries new information.',
				'good_for' => 'Catalogue entries, density',
			],
		];
	}

	public static function is_supported_tone( string $tone ): bool {
		return array_key_exists( $tone, self::tones() );
	}

	/**
	 * Default tone the site uses when a product has none set.
	 *
	 * Stored in option `sgclabs_default_desc_tone`.
	 */
	public static function default_tone(): string {
		$opt = get_option( 'sgclabs_default_desc_tone', 'technical' );
		return self::is_supported_tone( $opt ) ? $opt : 'technical';
	}

	/**
	 * Tone that should apply for a given post — per-post override wins,
	 * else falls back to the global default.
	 */
	public static function tone_for( int $post_id ): string {
		$tone = get_post_meta( $post_id, '_sgclabs_desc_tone', true );
		if ( is_string( $tone ) && self::is_supported_tone( $tone ) ) {
			return $tone;
		}
		return self::default_tone();
	}
}

endif;
