<?php
/**
 * SGC LABS® Module Guard — shared module on/off control + automatic safe mode.
 *
 * Shipped identically inside all four SGC plugins (first one loaded wins).
 *
 *  - Every plugin registers its module files; each module can be switched OFF from
 *    SGC LABS → Module Control. A switched-off module file is never included, so a
 *    broken module can be isolated without FTP.
 *  - Automatic safe mode: when PHP dies with a fatal error inside a registered module
 *    file, that module is disabled automatically, the error is logged, and an admin
 *    notice explains what happened. The next request loads without it.
 *  - Emergency safe mode: a secret link (shown on the Module Control page) disables all
 *    optional modules of all SGC plugins even when wp-admin itself is unreachable.
 *
 * @version 1.0.0
 */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SGC_Module_Guard' ) ) :

final class SGC_Module_Guard {

	const VERSION          = '1.0.0';
	const OPT_DISABLED     = 'sgc_mg_disabled';        // [ plugin_id => [ module_key, ... ] ] (manual)
	const OPT_AUTO         = 'sgc_mg_auto_disabled';   // [ plugin_id => [ module_key => [ 'message','file','line','time' ] ] ]
	const OPT_LOG          = 'sgc_mg_fatal_log';       // last 30 fatals
	const OPT_SAFE_BACKUP  = 'sgc_mg_safe_mode_backup';
	const OPT_EMERGENCY    = 'sgc_mg_emergency_key';
	const NONCE            = 'sgc_mg_nonce';
	const PAGE             = 'sgc-module-control';

	/** @var array plugin_id => [ label, path, modules => [ key => label ], protected => [ key ] ] */
	private static $plugins = [];
	private static $booted  = false;

	// ── Registration (called by each plugin before it loads modules) ─────────────
	public static function register( string $plugin_id, string $label, string $base_path, array $modules, array $protected = [] ): void {
		self::$plugins[ $plugin_id ] = [
			'label'     => $label,
			'path'      => wp_normalize_path( trailingslashit( $base_path ) ),
			'modules'   => $modules,
			'protected' => $protected,
		];
		self::boot();
	}

	/** Module key = path relative to the plugin's includes/ dir, without .php */
	public static function key_from_path( string $plugin_id, string $file ): string {
		$base = self::$plugins[ $plugin_id ]['path'] ?? '';
		$rel  = wp_normalize_path( $file );
		if ( $base && strpos( $rel, $base ) === 0 ) $rel = substr( $rel, strlen( $base ) );
		$rel = preg_replace( '#^includes/#', '', $rel );
		return preg_replace( '#\.php$#', '', $rel );
	}

	/** Should this module file be included? */
	public static function should_load( string $plugin_id, string $key ): bool {
		if ( in_array( $key, self::$plugins[ $plugin_id ]['protected'] ?? [], true ) ) return true;
		$disabled = get_option( self::OPT_DISABLED, [] );
		if ( ! empty( $disabled[ $plugin_id ] ) && in_array( $key, (array) $disabled[ $plugin_id ], true ) ) return false;
		return true;
	}

	public static function is_disabled( string $plugin_id, string $key ): bool {
		return ! self::should_load( $plugin_id, $key );
	}

	// ── Boot (once) ─────────────────────────────────────────────────────────────
	private static function boot(): void {
		if ( self::$booted ) return;
		self::$booted = true;

		self::maybe_emergency_safe_mode();

		// Automatic safe mode: WordPress runs this filter while handling a fatal error.
		add_filter( 'wp_php_error_message', [ __CLASS__, 'on_php_error_message' ], 10, 2 );
		// Fallback when WordPress' fatal handler is disabled.
		register_shutdown_function( [ __CLASS__, 'on_shutdown' ] );

		if ( is_admin() ) {
			add_action( 'admin_menu',            [ __CLASS__, 'admin_menu' ], 4 );
			add_action( 'admin_notices',         [ __CLASS__, 'admin_notices' ] );
			add_action( 'wp_ajax_sgc_mg_toggle',   [ __CLASS__, 'ajax_toggle' ] );
			add_action( 'wp_ajax_sgc_mg_bulk',     [ __CLASS__, 'ajax_bulk' ] );
			add_action( 'wp_ajax_sgc_mg_clear_log',[ __CLASS__, 'ajax_clear_log' ] );
		}
	}

	// ── Fatal error → auto-disable the module ────────────────────────────────────
	public static function on_php_error_message( $message, $error ) {
		if ( is_array( $error ) ) self::record_fatal( $error );
		return $message;
	}

	public static function on_shutdown(): void {
		$error = error_get_last();
		if ( ! $error || ! in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR ], true ) ) return;
		self::record_fatal( $error );
	}

	private static $recorded = false;
	private static function record_fatal( array $error ): void {
		if ( self::$recorded ) return;
		self::$recorded = true;
		if ( ! in_array( $error['type'] ?? 0, [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR ], true ) ) return;

		$file = wp_normalize_path( (string) ( $error['file'] ?? '' ) );
		$hit  = self::locate_module( $file );
		$entry = [
			'time'    => current_time( 'mysql' ),
			'message' => substr( (string) ( $error['message'] ?? '' ), 0, 600 ),
			'file'    => $file,
			'line'    => (int) ( $error['line'] ?? 0 ),
			'plugin'  => $hit['plugin'] ?? '',
			'module'  => $hit['module'] ?? '',
			'action'  => $hit ? 'module auto-disabled' : 'not inside an SGC module — nothing disabled',
		];
		$log = get_option( self::OPT_LOG, [] );
		array_unshift( $log, $entry );
		update_option( self::OPT_LOG, array_slice( $log, 0, 30 ), false );

		if ( $hit ) {
			$disabled = get_option( self::OPT_DISABLED, [] );
			$disabled[ $hit['plugin'] ]   = array_values( array_unique( array_merge( (array) ( $disabled[ $hit['plugin'] ] ?? [] ), [ $hit['module'] ] ) ) );
			update_option( self::OPT_DISABLED, $disabled, false );
			$auto = get_option( self::OPT_AUTO, [] );
			$auto[ $hit['plugin'] ][ $hit['module'] ] = $entry;
			update_option( self::OPT_AUTO, $auto, false );
			error_log( sprintf( '[SGC Module Guard] fatal in %s/%s (%s:%d) — module auto-disabled: %s', $hit['plugin'], $hit['module'], $file, $entry['line'], $entry['message'] ) );
		}
	}

	/** Map a file path to [plugin, module] if it is a registered, non-protected module. */
	private static function locate_module( string $file ): ?array {
		foreach ( self::$plugins as $pid => $p ) {
			if ( strpos( $file, $p['path'] ) !== 0 ) continue;
			$key = self::key_from_path( $pid, $file );
			if ( isset( $p['modules'][ $key ] ) && ! in_array( $key, $p['protected'], true ) ) {
				return [ 'plugin' => $pid, 'module' => $key ];
			}
			// A fatal inside a sub-file (templates/, admin/views/) of a module directory maps to its parent module.
			foreach ( $p['modules'] as $mkey => $label ) {
				if ( strpos( $key, $mkey . '/' ) === 0 && ! in_array( $mkey, $p['protected'], true ) ) return [ 'plugin' => $pid, 'module' => $mkey ];
			}
		}
		return null;
	}

	// ── Emergency safe mode via secret link (works even when wp-admin is broken) ──
	private static function maybe_emergency_safe_mode(): void {
		if ( empty( $_GET['sgc_mg_safe'] ) ) return;
		$key = get_option( self::OPT_EMERGENCY, '' );
		if ( ! $key || ! hash_equals( $key, sanitize_text_field( wp_unslash( $_GET['sgc_mg_safe'] ) ) ) ) return;
		self::enter_safe_mode();
		update_option( self::OPT_EMERGENCY, wp_generate_password( 32, false ), false ); // one-time use
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&safe=1' ) );
		exit;
	}

	public static function emergency_key(): string {
		$key = get_option( self::OPT_EMERGENCY, '' );
		if ( ! $key ) { $key = wp_generate_password( 32, false ); update_option( self::OPT_EMERGENCY, $key, false ); }
		return $key;
	}

	/** Disable every optional module of every registered plugin (remembering the previous state). */
	public static function enter_safe_mode(): void {
		update_option( self::OPT_SAFE_BACKUP, get_option( self::OPT_DISABLED, [] ), false );
		$all = [];
		foreach ( self::$plugins as $pid => $p ) {
			$all[ $pid ] = array_values( array_diff( array_keys( $p['modules'] ), $p['protected'] ) );
		}
		update_option( self::OPT_DISABLED, $all, false );
	}

	public static function exit_safe_mode(): void {
		update_option( self::OPT_DISABLED, get_option( self::OPT_SAFE_BACKUP, [] ), false );
		delete_option( self::OPT_SAFE_BACKUP );
	}

	public static function in_safe_mode(): bool {
		return get_option( self::OPT_SAFE_BACKUP, null ) !== null;
	}

	// ── Admin ───────────────────────────────────────────────────────────────────
	public static function admin_menu(): void {
		global $admin_page_hooks;
		$parent = isset( $admin_page_hooks['sgclabs-manager'] ) ? 'sgclabs-manager' : '';
		if ( $parent ) {
			add_submenu_page( $parent, 'Module Control', '🧩 Module Control', 'manage_options', self::PAGE, [ __CLASS__, 'render' ] );
		} else {
			add_menu_page( 'SGC Module Control', 'SGC Modules', 'manage_options', self::PAGE, [ __CLASS__, 'render' ], 'dashicons-admin-generic', 58 );
		}
	}

	public static function admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$auto = array_filter( (array) get_option( self::OPT_AUTO, [] ) );
		if ( ! $auto && ! self::in_safe_mode() ) return;
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		if ( self::in_safe_mode() ) {
			echo '<div class="notice notice-warning"><p><strong>SGC LABS® Safe Mode is ON</strong> — all optional modules are switched off. <a href="' . esc_url( $url ) . '">Open Module Control</a> to switch modules back on.</p></div>';
		}
		foreach ( $auto as $pid => $mods ) {
			foreach ( $mods as $key => $e ) {
				printf( '<div class="notice notice-error"><p><strong>SGC LABS® auto-repair:</strong> module <code>%s</code> of %s was switched off after a fatal error (%s). <a href="%s">Review in Module Control</a>.</p></div>',
					esc_html( $key ), esc_html( self::$plugins[ $pid ]['label'] ?? $pid ), esc_html( substr( $e['message'] ?? '', 0, 140 ) ), esc_url( $url ) );
			}
		}
	}

	private static function verify(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );
	}

	public static function ajax_toggle(): void {
		self::verify();
		$pid = sanitize_text_field( wp_unslash( $_POST['plugin'] ?? '' ) );
		$key = sanitize_text_field( wp_unslash( $_POST['module'] ?? '' ) );
		$on  = ( '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ?? '0' ) ) );
		if ( ! isset( self::$plugins[ $pid ]['modules'][ $key ] ) ) wp_send_json_error( 'Unknown module' );
		if ( in_array( $key, self::$plugins[ $pid ]['protected'], true ) ) wp_send_json_error( 'This core module cannot be switched off' );
		$disabled = get_option( self::OPT_DISABLED, [] );
		$list = (array) ( $disabled[ $pid ] ?? [] );
		$list = $on ? array_values( array_diff( $list, [ $key ] ) ) : array_values( array_unique( array_merge( $list, [ $key ] ) ) );
		$disabled[ $pid ] = $list;
		update_option( self::OPT_DISABLED, $disabled, false );
		if ( $on ) { $auto = get_option( self::OPT_AUTO, [] ); unset( $auto[ $pid ][ $key ] ); update_option( self::OPT_AUTO, array_filter( $auto ), false ); }
		wp_send_json_success( [ 'plugin' => $pid, 'module' => $key, 'enabled' => $on ] );
	}

	public static function ajax_bulk(): void {
		self::verify();
		$what = sanitize_key( $_POST['what'] ?? '' );
		if ( 'safe_mode_on' === $what )       { self::enter_safe_mode(); }
		elseif ( 'safe_mode_off' === $what )  { self::exit_safe_mode(); }
		elseif ( 'enable_all' === $what )     { delete_option( self::OPT_SAFE_BACKUP ); update_option( self::OPT_DISABLED, [], false ); update_option( self::OPT_AUTO, [], false ); }
		else wp_send_json_error( 'Unknown action' );
		wp_send_json_success();
	}

	public static function ajax_clear_log(): void {
		self::verify();
		update_option( self::OPT_LOG, [], false );
		wp_send_json_success();
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$disabled = get_option( self::OPT_DISABLED, [] );
		$auto     = get_option( self::OPT_AUTO, [] );
		$log      = get_option( self::OPT_LOG, [] );
		$nonce    = wp_create_nonce( self::NONCE );
		$safe     = self::in_safe_mode();
		$emerg    = add_query_arg( 'sgc_mg_safe', self::emergency_key(), home_url( '/' ) );
		?>
		<div class="wrap" style="max-width:1100px">
			<h1>🧩 SGC LABS® Module Control <small style="font-size:12px;color:#666">guard v<?php echo esc_html( self::VERSION ); ?></small></h1>
			<p>Switch any module of any SGC plugin on or off. A module that is OFF is never loaded by PHP, so a faulty module can be isolated without FTP. When a module causes a PHP fatal error it is switched off <strong>automatically</strong> and listed below.</p>

			<div style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0">
				<?php if ( $safe ) : ?>
					<button class="button button-primary sgc-mg-bulk" data-what="safe_mode_off">✅ Exit Safe Mode (restore previous switches)</button>
				<?php else : ?>
					<button class="button sgc-mg-bulk" data-what="safe_mode_on" data-confirm="Switch OFF all optional modules of all SGC plugins?">🛟 Safe Mode (switch everything optional OFF)</button>
				<?php endif; ?>
				<button class="button sgc-mg-bulk" data-what="enable_all" data-confirm="Switch ON every module?">🔛 Enable all modules</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sgclabs-auto-repair' ) ); ?>">🛠 Auto-Repair</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sgclabs-health-monitor' ) ); ?>">🩺 Health Monitor</a>
			</div>

			<div style="background:#fff8e1;border-left:4px solid #f9a825;padding:10px 14px;margin-bottom:18px;font-size:13px">
				<strong>Emergency safe-mode link</strong> (works even if wp-admin shows a fatal error; one-time use, a new link is generated after use):<br>
				<code style="user-select:all"><?php echo esc_html( $emerg ); ?></code><br>
				<span style="color:#666">Save this link somewhere safe. Opening it switches all optional modules OFF and brings you back here.</span>
			</div>

			<?php foreach ( self::$plugins as $pid => $p ) : $pdis = (array) ( $disabled[ $pid ] ?? [] ); ?>
			<h2 style="margin-top:24px"><?php echo esc_html( $p['label'] ); ?> <small style="color:#666;font-weight:normal">(<?php echo count( $p['modules'] ); ?> modules, <?php echo count( $pdis ); ?> off)</small></h2>
			<table class="widefat striped" style="margin-bottom:10px">
				<thead><tr><th style="width:90px">Status</th><th>Module</th><th>File</th><th>Notes</th></tr></thead>
				<tbody>
				<?php foreach ( $p['modules'] as $key => $label ) :
					$prot = in_array( $key, $p['protected'], true );
					$off  = in_array( $key, $pdis, true );
					$a    = $auto[ $pid ][ $key ] ?? null; ?>
					<tr>
						<td>
							<?php if ( $prot ) : ?><span title="Core — always loaded">🔒 core</span>
							<?php else : ?>
								<label><input type="checkbox" class="sgc-mg-toggle" data-plugin="<?php echo esc_attr( $pid ); ?>" data-module="<?php echo esc_attr( $key ); ?>" <?php checked( ! $off ); ?>> <?php echo $off ? 'OFF' : 'ON'; ?></label>
							<?php endif; ?>
						</td>
						<td><strong><?php echo esc_html( $label ); ?></strong></td>
						<td><code><?php echo esc_html( 'includes/' . $key . '.php' ); ?></code></td>
						<td><?php if ( $a ) : ?><span style="color:#b91c1c">⚠ auto-disabled <?php echo esc_html( $a['time'] ); ?>: <?php echo esc_html( substr( $a['message'], 0, 160 ) ); ?> (line <?php echo (int) $a['line']; ?>)</span><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endforeach; ?>

			<h2 style="margin-top:24px">Fatal error log <button class="button button-small" id="sgc-mg-clear-log">Clear</button></h2>
			<?php if ( ! $log ) : ?><p style="color:#666">No fatal errors recorded. 🎉</p><?php else : ?>
			<table class="widefat striped"><thead><tr><th>Time</th><th>Plugin / module</th><th>Error</th><th>Action taken</th></tr></thead><tbody>
			<?php foreach ( $log as $e ) : ?>
				<tr><td><?php echo esc_html( $e['time'] ); ?></td><td><?php echo esc_html( trim( ( $e['plugin'] ?? '' ) . ' / ' . ( $e['module'] ?? '' ), ' /' ) ?: '—' ); ?></td>
				<td><code style="font-size:11px"><?php echo esc_html( $e['message'] ); ?></code><br><small><?php echo esc_html( basename( $e['file'] ) . ':' . $e['line'] ); ?></small></td><td><?php echo esc_html( $e['action'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php endif; ?>
		</div>
		<script>
		(function(){
			var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce = <?php echo wp_json_encode( $nonce ); ?>;
			function post(data, cb){ data.nonce = nonce; var f = new FormData(); for (var k in data) f.append(k, data[k]);
				fetch(ajax, {method:'POST', credentials:'same-origin', body:f}).then(function(r){return r.json();}).then(cb).catch(function(){ alert('Request failed'); }); }
			document.querySelectorAll('.sgc-mg-toggle').forEach(function(cb){
				cb.addEventListener('change', function(){
					var el = this; post({action:'sgc_mg_toggle', plugin:el.dataset.plugin, module:el.dataset.module, enabled: el.checked ? '1':'0'}, function(r){
						if(!r.success){ alert(r.data || 'Error'); el.checked = !el.checked; return; }
						el.parentNode.lastChild.textContent = ' ' + (el.checked ? 'ON' : 'OFF');
					});
				});
			});
			document.querySelectorAll('.sgc-mg-bulk').forEach(function(b){
				b.addEventListener('click', function(){
					if (this.dataset.confirm && !confirm(this.dataset.confirm)) return;
					post({action:'sgc_mg_bulk', what:this.dataset.what}, function(r){ if(r.success) location.reload(); else alert(r.data || 'Error'); });
				});
			});
			var cl = document.getElementById('sgc-mg-clear-log'); if (cl) cl.addEventListener('click', function(){ post({action:'sgc_mg_clear_log'}, function(){ location.reload(); }); });
		})();
		</script>
		<?php
	}
}

endif;
