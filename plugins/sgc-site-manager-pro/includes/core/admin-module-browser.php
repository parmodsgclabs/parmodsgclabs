<?php
/**
 * SGC LABS® Module Browser
 *
 * One unified admin page that shows EVERY module in the plugin:
 *   - Grouped by category (collapsible accordions)
 *   - Sortable alphabetically (A→Z, Z→A)
 *   - Searchable (live JS filter)
 *   - Per-module ON/OFF toggle
 *   - Health & integrity status
 *
 * Hooked into the existing admin menu via includes/core/loader.php.
 *
 * @package   SGCLabs\Core
 * @since     6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// AJAX: toggle module on/off
add_action( 'wp_ajax_sgclabs_toggle_module', function() {
    check_ajax_referer( 'sgclabs_module_browser', '_wpnonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'forbidden', 403 );
    }
    $slug = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
    $on   = ! empty( $_POST['on'] );
    $ok   = class_exists( 'SGCLabs_Registry' ) && SGCLabs_Registry::set_active( $slug, $on );
    wp_send_json( [ 'ok' => $ok, 'slug' => $slug, 'state' => $on ? 1 : 0 ] );
} );

// Render: Module Browser page
function sgclabs_module_browser_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! class_exists( 'SGCLabs_Registry' ) ) {
        echo '<div class="wrap"><p>Module Registry not loaded.</p></div>';
        return;
    }

    $by_cat   = SGCLabs_Registry::by_category();
    $counts   = SGCLabs_Registry::counts();
    $cats     = SGCLabs_Registry::$categories;
    $health   = SGCLabs_Registry::health_check();
    $sig      = class_exists( 'SGCLabs_Updater' ) ? SGCLabs_Updater::label() : '';
    $integ    = class_exists( 'SGCLabs_Updater' ) ? SGCLabs_Updater::verify_integrity() : [ 'ok' => true ];
    $sec      = class_exists( 'SGCLabs_Security' ) ? SGCLabs_Security::scan() : null;

    $nonce    = wp_create_nonce( 'sgclabs_module_browser' );
    ?>
    <div class="wrap sgc-mb">
        <h1 style="display:flex;align-items:center;gap:10px;">
            📚 Module Browser
            <span style="font-size:11px;background:#1e40af;color:#fff;padding:3px 10px;border-radius:20px;font-weight:600;"><?php echo esc_html( $sig ); ?></span>
        </h1>

        <p style="font-size:13px;color:#475569;max-width:800px;">
            Sab modules ek jagah — categorized, alphabetical, searchable. Toggle se kisi bhi feature ko ON/OFF kar sakte ho without code editing.
        </p>

        <!-- ── DASHBOARD STRIP ───────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:18px 0;">
            <div class="sgc-card sgc-card--ok">
                <div class="sgc-card__num"><?php echo (int) array_sum( $counts ); ?></div>
                <div class="sgc-card__lbl">Total Modules</div>
            </div>
            <?php
            $on_count = 0;
            foreach ( SGCLabs_Registry::all() as $slug => $_ ) {
                if ( SGCLabs_Registry::is_active( $slug ) ) $on_count++;
            }
            ?>
            <div class="sgc-card sgc-card--good">
                <div class="sgc-card__num"><?php echo (int) $on_count; ?></div>
                <div class="sgc-card__lbl">Active</div>
            </div>
            <div class="sgc-card sgc-card--<?php echo empty( $health ) ? 'good' : 'warn'; ?>">
                <div class="sgc-card__num"><?php echo count( $health ); ?></div>
                <div class="sgc-card__lbl">Health Issues</div>
            </div>
            <?php if ( $sec ): ?>
            <div class="sgc-card sgc-card--<?php echo $sec['summary']['critical'] > 0 ? 'crit' : ( $sec['summary']['high'] > 0 ? 'warn' : 'good' ); ?>">
                <div class="sgc-card__num"><?php echo (int) $sec['summary']['total']; ?></div>
                <div class="sgc-card__lbl">Security Findings</div>
            </div>
            <?php endif; ?>
            <div class="sgc-card sgc-card--<?php echo $integ['ok'] ? 'good' : 'warn'; ?>">
                <div class="sgc-card__num"><?php echo $integ['ok'] ? '✓' : ( count( $integ['mismatched'] ?? [] ) ); ?></div>
                <div class="sgc-card__lbl">File Integrity</div>
            </div>
        </div>

        <!-- ── TOOLBAR ───────────────────────────────────────────── -->
        <div style="display:flex;gap:10px;align-items:center;margin:14px 0;flex-wrap:wrap;">
            <input id="sgc-mb-search" type="search" placeholder="🔎 Search modules..." style="flex:1;min-width:240px;padding:8px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;">
            <select id="sgc-mb-sort" style="padding:8px;border-radius:8px;border:1.5px solid #cbd5e1;">
                <option value="cat-az">By Category (A→Z)</option>
                <option value="az">All (A→Z)</option>
                <option value="za">All (Z→A)</option>
                <option value="active">Active first</option>
                <option value="new">New / Beta first</option>
            </select>
            <button type="button" class="button button-secondary" onclick="sgcMbBulk(true)">Enable visible</button>
            <button type="button" class="button button-secondary" onclick="sgcMbBulk(false)">Disable visible</button>
        </div>

        <!-- ── MODULE LIST ───────────────────────────────────────── -->
        <div id="sgc-mb-list">
        <?php foreach ( $by_cat as $cat => $mods ) :
            if ( empty( $mods ) ) continue; ?>
            <details class="sgc-mb-cat" open data-cat="<?php echo esc_attr( $cat ); ?>">
                <summary style="cursor:pointer;padding:12px 16px;background:#1e40af;color:#fff;border-radius:8px 8px 0 0;font-weight:700;font-size:14px;">
                    <?php echo esc_html( $cats[ $cat ] ?? $cat ); ?>
                    <span style="background:rgba(255,255,255,.2);padding:2px 10px;border-radius:20px;margin-left:6px;font-size:11px;"><?php echo count( $mods ); ?></span>
                </summary>
                <div style="background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;padding:0;margin-bottom:18px;">
                    <table class="widefat striped" style="border:none;margin:0;">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Module</th>
                                <th style="width:140px;">Version</th>
                                <th style="width:80px;">Tag</th>
                                <th style="width:100px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $mods as $slug => $mod ):
                            $is_on = SGCLabs_Registry::is_active( $slug ); ?>
                            <tr class="sgc-mb-row" data-slug="<?php echo esc_attr( $slug ); ?>" data-active="<?php echo $is_on ? 1 : 0; ?>" data-tag="<?php echo esc_attr( $mod['tag'] ); ?>">
                                <td>
                                    <label class="sgc-switch">
                                        <input type="checkbox" data-slug="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_on ); ?> onchange="sgcMbToggle(this)">
                                        <span class="sgc-switch__slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $mod['label'] ); ?></strong>
                                    <div style="font-size:12px;color:#64748b;margin-top:2px;"><?php echo esc_html( $mod['desc'] ); ?></div>
                                    <code style="font-size:10px;color:#94a3b8;"><?php echo esc_html( $slug ); ?></code>
                                </td>
                                <td><code><?php echo esc_html( $mod['version'] ); ?></code></td>
                                <td>
                                    <?php if ( $mod['tag'] === 'new' ): ?>
                                        <span style="background:#10b981;color:#fff;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">NEW</span>
                                    <?php elseif ( $mod['tag'] === 'beta' ): ?>
                                        <span style="background:#f59e0b;color:#fff;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">BETA</span>
                                    <?php elseif ( $mod['tag'] === 'pro' ): ?>
                                        <span style="background:#1e40af;color:#fff;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">PRO</span>
                                    <?php elseif ( $mod['tag'] === 'old' ): ?>
                                        <span style="background:#94a3b8;color:#fff;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">LEGACY</span>
                                    <?php endif; ?>
                                </td>
                                <td class="sgc-mb-status">
                                    <?php if ( $is_on ): ?>
                                        <span style="color:#16a34a;font-weight:600;">✓ Active</span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">○ Disabled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endforeach; ?>
        </div>

        <!-- ── ALPHABETICAL FUNCTION INDEX (live built) ─────────── -->
        <details style="margin-top:24px;">
            <summary style="cursor:pointer;padding:12px 16px;background:#0f172a;color:#fff;border-radius:8px;font-weight:700;font-size:14px;">
                🔡 Alphabetical Function Index — every sgclabs_* function
            </summary>
            <div style="background:#fff;border:1px solid #e5e7eb;padding:14px;border-radius:8px;margin-top:6px;">
                <p style="font-size:12px;color:#64748b;">All <code>sgclabs_*</code> global functions, sorted A→Z.</p>
                <pre style="background:#f8fafc;padding:12px;border-radius:6px;max-height:380px;overflow:auto;font-size:11px;line-height:1.6;"><?php
                    $funcs = get_defined_functions()['user'] ?? [];
                    $sgc   = array_filter( $funcs, function( $f ) { return strpos( $f, 'sgclabs_' ) === 0 || strpos( $f, 'sgc_' ) === 0; } );
                    sort( $sgc );
                    echo esc_html( implode( "\n", $sgc ) );
                ?></pre>
                <p style="font-size:11px;color:#94a3b8;margin-top:6px;">Total: <strong><?php echo count( $sgc ?? [] ); ?></strong> functions</p>
            </div>
        </details>

    </div>

    <style>
        .sgc-mb .sgc-card{background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;padding:14px;text-align:center;}
        .sgc-mb .sgc-card--good{border-color:#10b981;background:#f0fdf4;}
        .sgc-mb .sgc-card--warn{border-color:#f59e0b;background:#fffbeb;}
        .sgc-mb .sgc-card--crit{border-color:#dc2626;background:#fef2f2;}
        .sgc-mb .sgc-card--ok  {border-color:#3b82f6;background:#eff6ff;}
        .sgc-mb .sgc-card__num{font-size:28px;font-weight:800;color:#1e293b;}
        .sgc-mb .sgc-card__lbl{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}

        .sgc-switch{position:relative;display:inline-block;width:42px;height:22px;}
        .sgc-switch input{opacity:0;width:0;height:0;}
        .sgc-switch__slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:22px;transition:.2s;}
        .sgc-switch__slider:before{content:"";position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
        .sgc-switch input:checked + .sgc-switch__slider{background:#10b981;}
        .sgc-switch input:checked + .sgc-switch__slider:before{transform:translateX(20px);}
    </style>

    <script>
        const SGC_MB_NONCE = <?php echo wp_json_encode( $nonce ); ?>;
        const SGC_MB_AJAX  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

        function sgcMbToggle(el){
            const slug = el.dataset.slug;
            const on   = el.checked;
            const row  = el.closest('tr');
            const stat = row.querySelector('.sgc-mb-status');
            stat.innerHTML = '<em style="color:#94a3b8;">…saving</em>';

            const fd = new FormData();
            fd.append('action','sgclabs_toggle_module');
            fd.append('_wpnonce',SGC_MB_NONCE);
            fd.append('slug',slug);
            if(on) fd.append('on','1');

            fetch(SGC_MB_AJAX,{method:'POST',body:fd,credentials:'same-origin'})
                .then(r=>r.json())
                .then(d=>{
                    if(d.ok){
                        row.dataset.active = on ? 1 : 0;
                        stat.innerHTML = on
                            ? '<span style="color:#16a34a;font-weight:600;">✓ Active</span>'
                            : '<span style="color:#94a3b8;">○ Disabled</span>';
                    } else {
                        el.checked = !on;
                        stat.innerHTML = '<span style="color:#dc2626;">Error</span>';
                    }
                })
                .catch(()=>{ el.checked = !on; stat.innerHTML='<span style="color:#dc2626;">Failed</span>'; });
        }

        function sgcMbBulk(on){
            document.querySelectorAll('.sgc-mb-row').forEach(r=>{
                if(r.style.display==='none') return;
                const cb = r.querySelector('input[type=checkbox]');
                if(cb && cb.checked!==on){ cb.checked=on; sgcMbToggle(cb); }
            });
        }

        // Search + sort
        const search = document.getElementById('sgc-mb-search');
        search.addEventListener('input',()=>{
            const q = search.value.toLowerCase().trim();
            document.querySelectorAll('.sgc-mb-row').forEach(r=>{
                const txt = r.innerText.toLowerCase();
                r.style.display = (q==='' || txt.includes(q)) ? '' : 'none';
            });
        });

        document.getElementById('sgc-mb-sort').addEventListener('change', e => {
            const v = e.target.value;
            document.querySelectorAll('.sgc-mb-cat').forEach(cat => {
                if (v === 'az' || v === 'za' || v === 'active' || v === 'new') cat.open = true;
                const tbody = cat.querySelector('tbody');
                if (!tbody) return;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a,b)=>{
                    const la = a.innerText.toLowerCase(), lb = b.innerText.toLowerCase();
                    if (v === 'az' || v === 'cat-az') return la.localeCompare(lb);
                    if (v === 'za') return lb.localeCompare(la);
                    if (v === 'active') return (b.dataset.active|0) - (a.dataset.active|0);
                    if (v === 'new')    return (b.dataset.tag === 'new' ? 1 : 0) - (a.dataset.tag === 'new' ? 1 : 0);
                    return 0;
                });
                rows.forEach(r => tbody.appendChild(r));
            });
        });
    </script>
    <?php
}
