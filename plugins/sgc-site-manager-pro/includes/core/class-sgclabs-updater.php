<?php
/**
 * SGC LABS® Auto-Updater & Version Stamper
 *
 * GitHub-style release pipeline:
 *   - Reads VERSION file at plugin root
 *   - Generates a build signature (SHA-256 hash of the manifest)
 *   - Stamps every loaded module via filter
 *   - Optional: hooks WP plugin updater to a remote release feed
 *
 * @package   SGCLabs\Core
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SGCLabs_Updater' ) ) :

final class SGCLabs_Updater {

    /** Hard-coded fallback if VERSION file missing. */
    const FALLBACK_VERSION = '6.9.0';

    /** Public release feed (replace with real URL when GitHub Releases active). */
    const RELEASE_FEED = 'https://labsglass.com/sgclabs-releases.json';

    /** Manifest file written on every build. */
    const MANIFEST_FILE = 'release.manifest.json';

    /** @var string|null cached signature */
    private static $signature = null;

    /** Get the canonical version (from VERSION file, falls back to constant). */
    public static function version(): string {
        if ( ! defined( 'SGCLABS_PATH' ) ) return self::FALLBACK_VERSION;
        $vfile = SGCLABS_PATH . 'VERSION';
        if ( is_readable( $vfile ) ) {
            $v = trim( (string) file_get_contents( $vfile ) );
            if ( $v !== '' ) return $v;
        }
        return defined( 'SGCLABS_BASE_VERSION' ) ? SGCLABS_BASE_VERSION : self::FALLBACK_VERSION;
    }

    /** Build signature — short hash of manifest, used as "sign stamp". */
    public static function signature(): string {
        if ( self::$signature !== null ) return self::$signature;

        $manifest = self::manifest();
        $hash     = hash( 'sha256', wp_json_encode( $manifest ) );
        self::$signature = substr( $hash, 0, 12 );
        return self::$signature;
    }

    /** Full label for display: e.g. "v6.9.0 · build 202604251200 · sig a1b2c3d4e5f6" */
    public static function label(): string {
        $v = self::version();
        $b = defined( 'SGCLABS_UNIQUE_BUILD' ) ? SGCLABS_UNIQUE_BUILD : gmdate( 'YmdHi' );
        return sprintf( 'v%s · build %s · sig %s', $v, $b, self::signature() );
    }

    /** Load (or compute) the build manifest. */
    public static function manifest(): array {
        if ( ! defined( 'SGCLABS_PATH' ) ) return [];
        $file = SGCLABS_PATH . self::MANIFEST_FILE;

        if ( is_readable( $file ) ) {
            $data = json_decode( (string) file_get_contents( $file ), true );
            if ( is_array( $data ) ) return $data;
        }

        // Compute on the fly if manifest absent.
        return self::compute_manifest();
    }

    /** Compute fresh manifest by walking included PHP files. */
    public static function compute_manifest(): array {
        $base = defined( 'SGCLABS_PATH' ) ? SGCLABS_PATH : __DIR__;
        $files = self::list_files( $base );

        $entries = [];
        foreach ( $files as $rel => $abs ) {
            $entries[ $rel ] = hash_file( 'sha256', $abs );
        }
        ksort( $entries );

        return [
            'name'      => 'SGC LABS® Site Manager Pro',
            'version'   => self::version(),
            'build'     => defined( 'SGCLABS_UNIQUE_BUILD' ) ? SGCLABS_UNIQUE_BUILD : gmdate( 'YmdHi' ),
            'generated' => gmdate( 'c' ),
            'files'     => $entries,
        ];
    }

    /** Write manifest to disk (called on activation / build). */
    public static function write_manifest(): bool {
        if ( ! defined( 'SGCLABS_PATH' ) ) return false;
        $file = SGCLABS_PATH . self::MANIFEST_FILE;
        $data = self::compute_manifest();
        return (bool) @file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT ) );
    }

    /** Verify currently installed files match manifest hashes. */
    public static function verify_integrity(): array {
        $manifest = self::manifest();
        if ( empty( $manifest['files'] ) ) return [ 'ok' => false, 'reason' => 'no-manifest' ];

        $base = defined( 'SGCLABS_PATH' ) ? SGCLABS_PATH : __DIR__;
        $mismatched = [];
        foreach ( $manifest['files'] as $rel => $expected ) {
            $abs = $base . $rel;
            if ( ! is_readable( $abs ) ) {
                $mismatched[] = [ 'file' => $rel, 'reason' => 'missing' ];
                continue;
            }
            $actual = hash_file( 'sha256', $abs );
            if ( $actual !== $expected ) {
                $mismatched[] = [ 'file' => $rel, 'reason' => 'modified' ];
            }
        }
        return [
            'ok'         => empty( $mismatched ),
            'mismatched' => $mismatched,
            'total'      => count( $manifest['files'] ),
        ];
    }

    private static function list_files( string $base ): array {
        $out = [];
        $rdi = new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS );
        $rii = new RecursiveIteratorIterator( $rdi );
        foreach ( $rii as $f ) {
            if ( ! $f->isFile() ) continue;
            $ext = strtolower( $f->getExtension() );
            if ( ! in_array( $ext, [ 'php', 'js', 'css' ], true ) ) continue;
            $rel = str_replace( '\\', '/', str_replace( $base, '', $f->getPathname() ) );
            $out[ $rel ] = $f->getPathname();
        }
        return $out;
    }
}

endif;

// Stamp manifest on every activation.
add_action( 'sgclabs_on_activate', [ 'SGCLabs_Updater', 'write_manifest' ], 99 );
