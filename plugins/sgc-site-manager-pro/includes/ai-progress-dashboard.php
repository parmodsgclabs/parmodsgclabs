<?php
/**
 * SGC LABS® AI Progress Dashboard
 * Floating real-time dashboard for all AI bulk operations.
 * Shows status: completed / interrupted / running / not started
 * Reads from existing DB keys: sgclabs_ace_prog_{mod}
 *
 * ⚠️ ADDITIVE ONLY — no existing files modified (except 1-line include in sgclabs-manager.php)
 *
 * @package    SGC LABS Manager Pro
 * @since      6.6.6
 * @version    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// CONSTANTS
// ============================================================

if ( ! defined( 'SGCLABS_PROG_DASH_VERSION' ) ) {
    define( 'SGCLABS_PROG_DASH_VERSION', '1.0.0' );
}

// All modules tracked — matches existing ace_prog_ keys
if ( ! defined( 'SGCLABS_PROG_MODULES' ) ) {
    define( 'SGCLABS_PROG_MODULES', [
    'master'       => [ 'label' => '⚡ Master (All-in-One)', 'icon' => '⚡' ],
    'titles'       => [ 'label' => '📝 SEO Titles',          'icon' => '📝' ],
    'meta_title'   => [ 'label' => '🏷️ Meta Titles',        'icon' => '🏷️' ],
    'keywords'     => [ 'label' => '🔑 Focus Keywords',      'icon' => '🔑' ],
    'meta'         => [ 'label' => '📄 Meta Descriptions',   'icon' => '📄' ],
    'short_desc'   => [ 'label' => '📋 Short Descriptions',  'icon' => '📋' ],
    'main_desc'    => [ 'label' => '📚 Main Descriptions',   'icon' => '📚' ],
    'images'       => [ 'label' => '🖼️ Image Alt Text',     'icon' => '🖼️' ],
    'multilingual' => [ 'label' => '🌐 Multilingual SEO',   'icon' => '🌐' ],
    ] );
}

// ============================================================
// HELPER: Load all module progress from DB
// ============================================================

function sgclabs_progdash_load_all(): array {
    $modules = array_keys( SGCLABS_PROG_MODULES );
    $out = [];
    foreach ( $modules as $mod ) {
        $p = get_option( "sgclabs_ace_prog_{$mod}", null );
        if ( ! is_array( $p ) ) {
            $out[ $mod ] = [
                'status'     => 'idle',
                'offset'     => 0,
                'done'       => 0,
                'total'      => 0,
                'percent'    => 0,
                'finished'   => false,
                'last_batch' => '',
            ];
        } else {
            $done     = intval( $p['done']   ?? 0 );
            $total    = intval( $p['total']  ?? 0 );
            $offset   = intval( $p['offset'] ?? 0 );
            $finished = ! empty( $p['finished'] );
            $percent  = $total > 0 ? min( 100, round( $done / $total * 100 ) ) : 0;

            // Determine status
            if ( $finished && $done > 0 ) {
                $status = 'completed';
            } elseif ( ! $finished && $done > 0 ) {
                $status = 'interrupted';
            } else {
                $status = 'idle';
            }

            $out[ $mod ] = [
                'status'     => $status,
                'offset'     => $offset,
                'done'       => $done,
                'total'      => $total,
                'percent'    => $percent,
                'finished'   => $finished,
                'last_batch' => esc_html( $p['last_batch'] ?? '' ),
            ];
        }
    }
    return $out;
}

// ============================================================
// AJAX: Refresh progress data (called by dashboard polling)
// ============================================================

add_action( 'wp_ajax_sgclabs_progdash_refresh', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    wp_send_json_success( sgclabs_progdash_load_all() );
} );

// ============================================================
// AJAX: Reset a single module progress
// ============================================================

add_action( 'wp_ajax_sgclabs_progdash_reset', function () {
    check_ajax_referer( 'sgclabs_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $allowed = array_keys( SGCLABS_PROG_MODULES );
    $mod     = sanitize_key( $_POST['mod'] ?? '' );
    if ( ! in_array( $mod, $allowed, true ) ) wp_send_json_error( 'Invalid module' );

    update_option( "sgclabs_ace_prog_{$mod}", [
        'offset'     => 0,
        'done'       => 0,
        'total'      => 0,
        'finished'   => false,
        'last_batch' => '',
    ], false );

    wp_send_json_success();
} );

// ============================================================
// ENQUEUE: Load dashboard JS+CSS only on SGC admin pages
// ============================================================

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    // Only on SGC LABS admin pages
    $sgc_pages = [
        'toplevel_page_sgclabs-ai-center',
        'sgc-labs_page_sgclabs-ai-center',
        'admin_page_sgclabs-ai-center',
    ];
    $is_sgc = strpos( $hook, 'sgclabs' ) !== false || in_array( $hook, $sgc_pages, true );
    if ( ! $is_sgc ) return;

    // Inline CSS
    wp_add_inline_style( 'wp-admin', sgclabs_progdash_css() );

    // Inline JS (depends on jQuery which WP always loads in admin)
    $init_data = wp_json_encode( [
        'nonce'   => wp_create_nonce( 'sgclabs_nonce' ),
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'modules' => SGCLABS_PROG_MODULES,
        'initial' => sgclabs_progdash_load_all(),
    ] );
    wp_add_inline_script( 'jquery', "var sgcProgDashData = {$init_data}; " . sgclabs_progdash_js() );
} );

// ============================================================
// INJECT: Dashboard HTML into admin footer
// ============================================================

add_action( 'admin_footer', function () {
    $hook = $GLOBALS['hook_suffix'] ?? '';
    $is_sgc = strpos( $hook, 'sgclabs' ) !== false;
    if ( ! $is_sgc ) return;
    echo sgclabs_progdash_html();
} );

// ============================================================
// HTML: Floating dashboard widget
// ============================================================

function sgclabs_progdash_html(): string {
    return '
<div id="sgc-progdash" class="sgc-pd-wrap sgc-pd-collapsed">

    <!-- Header bar (always visible) -->
    <div class="sgc-pd-header" id="sgc-pd-header">
        <span class="sgc-pd-title">🤖 AI Operations</span>
        <span class="sgc-pd-summary" id="sgc-pd-summary">Loading...</span>
        <div class="sgc-pd-hdr-btns">
            <button class="sgc-pd-hbtn" id="sgc-pd-refresh-btn" title="Refresh now" onclick="sgcPD.refresh()">🔄</button>
            <button class="sgc-pd-hbtn" id="sgc-pd-toggle-btn" title="Expand/Collapse" onclick="sgcPD.toggle()">▲</button>
        </div>
    </div>

    <!-- Body (collapsible) -->
    <div class="sgc-pd-body" id="sgc-pd-body">

        <!-- Auto-refresh bar -->
        <div class="sgc-pd-autobar">
            <label class="sgc-pd-autolabel">
                <input type="checkbox" id="sgc-pd-auto" checked onchange="sgcPD.toggleAuto(this.checked)">
                Auto-refresh
            </label>
            <span class="sgc-pd-lastup" id="sgc-pd-lastup">—</span>
        </div>

        <!-- Module rows -->
        <div class="sgc-pd-list" id="sgc-pd-list">
            <div class="sgc-pd-loading">⏳ Loading progress data...</div>
        </div>

    </div>
</div>';
}

// ============================================================
// CSS
// ============================================================

function sgclabs_progdash_css(): string {
    return '
/* ── SGC AI Progress Dashboard ── */
#sgc-progdash{
    position:fixed;
    bottom:24px;
    right:24px;
    width:380px;
    background:#fff;
    border-radius:14px;
    box-shadow:0 8px 32px rgba(0,0,0,.18);
    z-index:99999;
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
    border:1px solid #e5e7eb;
    overflow:hidden;
    transition:box-shadow .2s;
}
#sgc-progdash:hover{box-shadow:0 12px 40px rgba(0,0,0,.22);}

/* Header */
.sgc-pd-header{
    background:linear-gradient(135deg,#1a1a2e,#2d2d5e);
    color:#fff;
    padding:10px 14px;
    display:flex;
    align-items:center;
    gap:8px;
    cursor:move;
    user-select:none;
}
.sgc-pd-title{font-weight:700;font-size:13px;flex-shrink:0;}
.sgc-pd-summary{
    flex:1;
    font-size:11px;
    color:rgba(255,255,255,.75);
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    margin:0 6px;
}
.sgc-pd-hdr-btns{display:flex;gap:4px;flex-shrink:0;}
.sgc-pd-hbtn{
    background:rgba(255,255,255,.15);
    border:none;
    color:#fff;
    width:26px;
    height:26px;
    border-radius:6px;
    cursor:pointer;
    font-size:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:.15s;
    padding:0;
}
.sgc-pd-hbtn:hover{background:rgba(255,255,255,.28);}

/* Body */
.sgc-pd-body{
    max-height:480px;
    overflow-y:auto;
    transition:max-height .25s ease;
}
.sgc-pd-collapsed .sgc-pd-body{max-height:0;overflow:hidden;}
.sgc-pd-collapsed #sgc-pd-toggle-btn{transform:rotate(180deg);}

/* Auto-refresh bar */
.sgc-pd-autobar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:7px 14px;
    background:#f8f9fa;
    border-bottom:1px solid #f0f0f0;
    font-size:11px;
    color:#666;
}
.sgc-pd-autolabel{display:flex;align-items:center;gap:5px;cursor:pointer;font-weight:600;}
.sgc-pd-lastup{color:#aaa;font-size:10px;}

/* Module list */
.sgc-pd-list{padding:8px 0;}
.sgc-pd-loading{padding:20px;text-align:center;color:#aaa;font-size:12px;}

/* Module row */
.sgc-pd-row{
    padding:10px 14px;
    border-bottom:1px solid #f5f5f5;
    transition:background .15s;
}
.sgc-pd-row:last-child{border-bottom:none;}
.sgc-pd-row:hover{background:#fafafa;}

/* Row top */
.sgc-pd-row-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:5px;
}
.sgc-pd-mod-label{font-size:12px;font-weight:700;color:#1a1a2e;}
.sgc-pd-status-badge{
    font-size:10px;
    font-weight:700;
    padding:2px 8px;
    border-radius:20px;
    white-space:nowrap;
}
.sgc-pd-badge-completed{background:#f0fdf4;color:#15803d;}
.sgc-pd-badge-interrupted{background:#fef9e7;color:#92400e;}
.sgc-pd-badge-running{background:#eff6ff;color:#1d4ed8;}
.sgc-pd-badge-idle{background:#f3f4f6;color:#9ca3af;}

/* Progress bar */
.sgc-pd-bar-wrap{height:6px;background:#f0f0f0;border-radius:3px;overflow:hidden;margin-bottom:5px;}
.sgc-pd-bar-fill{
    height:100%;
    border-radius:3px;
    transition:width .5s ease;
}
.sgc-pd-fill-completed{background:linear-gradient(90deg,#15803d,#22c55e);}
.sgc-pd-fill-interrupted{background:linear-gradient(90deg,#d97706,#f59e0b);}
.sgc-pd-fill-running{background:linear-gradient(90deg,#1a1a2e,#3b82f6);animation:sgcPDpulse 1.5s ease-in-out infinite;}
.sgc-pd-fill-idle{background:#e5e7eb;}

/* Row meta */
.sgc-pd-row-meta{
    display:flex;
    align-items:center;
    justify-content:space-between;
    font-size:10px;
    color:#9ca3af;
}
.sgc-pd-count{font-weight:600;color:#6b7280;}
.sgc-pd-lastrun{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;}

/* Row actions */
.sgc-pd-row-actions{display:flex;gap:5px;margin-top:7px;flex-wrap:wrap;}
.sgc-pd-btn{
    padding:4px 10px;
    border-radius:6px;
    border:1px solid #ddd;
    background:#fff;
    font-size:11px;
    font-weight:600;
    cursor:pointer;
    transition:.15s;
    display:inline-flex;
    align-items:center;
    gap:4px;
    text-decoration:none;
}
.sgc-pd-btn:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,.08);}
.sgc-pd-btn-view{color:#1d4ed8;border-color:#bfdbfe;}
.sgc-pd-btn-reset{color:#dc2626;border-color:#fecaca;}
.sgc-pd-btn-reset:hover{background:#fef2f2;}

@keyframes sgcPDpulse{
    0%,100%{opacity:1;}
    50%{opacity:.6;}
}

/* Scrollbar */
.sgc-pd-body::-webkit-scrollbar{width:4px;}
.sgc-pd-body::-webkit-scrollbar-track{background:#f8f9fa;}
.sgc-pd-body::-webkit-scrollbar-thumb{background:#ddd;border-radius:2px;}
';
}

// ============================================================
// JS
// ============================================================

function sgclabs_progdash_js(): string {
    return '
(function(){
    // Wait for DOM ready
    jQuery(function($){

        // ── State ────────────────────────────────────────────
        var pd = {
            data:     sgcProgDashData.initial || {},
            modules:  sgcProgDashData.modules  || {},
            nonce:    sgcProgDashData.nonce,
            ajaxurl:  sgcProgDashData.ajaxurl,
            autoOn:   true,
            timer:    null,
            interval: 8000,   // 8 sec auto-refresh
            dragging: false,
            dragOX:   0,
            dragOY:   0,
        };

        // ── Init ─────────────────────────────────────────────
        sgcPD.init = function() {
            sgcPD.render();
            sgcPD.startAuto();
            sgcPD.initDrag();
        };

        // ── Toggle collapse ──────────────────────────────────
        sgcPD.toggle = function() {
            document.getElementById("sgc-progdash").classList.toggle("sgc-pd-collapsed");
        };

        // ── Auto refresh ─────────────────────────────────────
        sgcPD.toggleAuto = function(on) {
            pd.autoOn = on;
            if(on) sgcPD.startAuto(); else clearTimeout(pd.timer);
        };
        sgcPD.startAuto = function() {
            clearTimeout(pd.timer);
            if(!pd.autoOn) return;
            pd.timer = setTimeout(function(){ sgcPD.refresh(); }, pd.interval);
        };

        // ── Refresh from server ──────────────────────────────
        sgcPD.refresh = function() {
            $.post(pd.ajaxurl, {
                action: "sgclabs_progdash_refresh",
                nonce:  pd.nonce
            }, function(res) {
                if(res.success) {
                    pd.data = res.data;
                    sgcPD.render();
                }
            }).always(function(){
                sgcPD.startAuto();
            });
        };

        // ── Render all rows ──────────────────────────────────
        sgcPD.render = function() {
            var list = document.getElementById("sgc-pd-list");
            if(!list) return;

            var html = "";
            var completed = 0, interrupted = 0, running = 0;

            Object.keys(pd.modules).forEach(function(mod) {
                var info  = pd.modules[mod];
                var state = pd.data[mod] || {status:"idle",done:0,total:0,percent:0,last_batch:""};
                var s     = state.status || "idle";

                if(s === "completed")   completed++;
                if(s === "interrupted") interrupted++;
                if(s === "running")     running++;

                var badgeClass = "sgc-pd-badge-" + s;
                var fillClass  = "sgc-pd-fill-"  + s;

                var badgeLabel = {
                    completed:   "✅ Completed",
                    interrupted: "⚠️ Interrupted",
                    running:     "🔄 Running",
                    idle:        "○ Not started"
                }[s] || "○ Not started";

                var pct   = state.percent || 0;
                var done  = state.done    || 0;
                var total = state.total   || 0;
                var last  = state.last_batch ? "Last: " + state.last_batch : "";

                var countStr = total > 0
                    ? done.toLocaleString() + " / " + total.toLocaleString() + " (" + pct + "%)"
                    : "—";

                // Action buttons
                var actions = "";

                if(s === "completed") {
                    actions += "<a class=\"sgc-pd-btn sgc-pd-btn-view\" href=\"" + sgcPD.acePageUrl(mod) + "\">▶ Run Again</a>";
                    actions += "<button class=\"sgc-pd-btn sgc-pd-btn-reset\" onclick=\"sgcPD.resetMod(\'" + mod + "\')\">🗑 Clear</button>";
                }
                if(s === "interrupted") {
                    actions += "<a class=\"sgc-pd-btn\" style=\"color:#15803d;border-color:#86efac;\" href=\"" + sgcPD.acePageUrl(mod) + "\">▶ Resume</a>";
                    actions += "<button class=\"sgc-pd-btn sgc-pd-btn-reset\" onclick=\"sgcPD.resetMod(\'" + mod + "\')\">↺ Reset</button>";
                }
                if(s === "idle") {
                    actions += "<a class=\"sgc-pd-btn\" style=\"color:#1d4ed8;border-color:#bfdbfe;\" href=\"" + sgcPD.acePageUrl(mod) + "\">▶ Start</a>";
                }
                if(s === "running") {
                    actions += "<a class=\"sgc-pd-btn\" style=\"color:#d97706;border-color:#fcd34d;\" href=\"" + sgcPD.acePageUrl(mod) + "\">⏸ Manage</a>";
                }

                html += "<div class=\"sgc-pd-row\" id=\"sgc-pd-row-" + mod + "\">";
                html += "  <div class=\"sgc-pd-row-top\">";
                html += "    <span class=\"sgc-pd-mod-label\">" + info.label + "</span>";
                html += "    <span class=\"sgc-pd-status-badge " + badgeClass + "\">" + badgeLabel + "</span>";
                html += "  </div>";
                html += "  <div class=\"sgc-pd-bar-wrap\">";
                html += "    <div class=\"sgc-pd-bar-fill " + fillClass + "\" style=\"width:" + pct + "%\"></div>";
                html += "  </div>";
                html += "  <div class=\"sgc-pd-row-meta\">";
                html += "    <span class=\"sgc-pd-count\">" + countStr + "</span>";
                html += "    <span class=\"sgc-pd-lastrun\">" + last + "</span>";
                html += "  </div>";
                if(actions) {
                    html += "  <div class=\"sgc-pd-row-actions\">" + actions + "</div>";
                }
                html += "</div>";
            });

            list.innerHTML = html;

            // Update summary
            var parts = [];
            if(completed)   parts.push("✅ " + completed + " done");
            if(interrupted) parts.push("⚠️ " + interrupted + " interrupted");
            if(running)     parts.push("🔄 " + running + " running");
            if(!parts.length) parts.push("No operations run yet");
            document.getElementById("sgc-pd-summary").textContent = parts.join(" · ");

            // Update last-updated time
            var now = new Date();
            document.getElementById("sgc-pd-lastup").textContent =
                "Updated: " + now.toLocaleTimeString("en-IN", {hour:"2-digit", minute:"2-digit", second:"2-digit"});
        };

        // ── Reset a module ───────────────────────────────────
        sgcPD.resetMod = function(mod) {
            if(!confirm("\"" + (pd.modules[mod] && pd.modules[mod].label) + "\" ka progress reset karna chahte ho?")) return;
            $.post(pd.ajaxurl, {
                action: "sgclabs_progdash_reset",
                nonce:  pd.nonce,
                mod:    mod
            }, function(res) {
                if(res.success) {
                    pd.data[mod] = {status:"idle",done:0,total:0,percent:0,last_batch:""};
                    sgcPD.render();
                }
            });
        };

        // ── Get ACE page URL for a module ────────────────────
        sgcPD.acePageUrl = function(mod) {
            var tabMap = {
                master:       "master",
                titles:       "titles",
                meta_title:   "meta_title",
                keywords:     "keywords",
                meta:         "meta",
                short_desc:   "short_desc",
                main_desc:    "main_desc",
                images:       "images",
                multilingual: "multilingual",
            };
            var tab = tabMap[mod] || mod;
            return "admin.php?page=sgclabs-ai-center&tab=" + tab;
        };

        // ── Drag to move ─────────────────────────────────────
        sgcPD.initDrag = function() {
            var el  = document.getElementById("sgc-progdash");
            var hdr = document.getElementById("sgc-pd-header");
            if(!el || !hdr) return;

            hdr.addEventListener("mousedown", function(e) {
                if(e.target.tagName === "BUTTON") return;
                pd.dragging = true;
                var rect = el.getBoundingClientRect();
                pd.dragOX = e.clientX - rect.left;
                pd.dragOY = e.clientY - rect.top;
                el.style.transition = "none";
                e.preventDefault();
            });

            document.addEventListener("mousemove", function(e) {
                if(!pd.dragging) return;
                var x = e.clientX - pd.dragOX;
                var y = e.clientY - pd.dragOY;
                // Clamp to viewport
                x = Math.max(0, Math.min(window.innerWidth  - el.offsetWidth,  x));
                y = Math.max(0, Math.min(window.innerHeight - el.offsetHeight, y));
                el.style.right  = "auto";
                el.style.bottom = "auto";
                el.style.left   = x + "px";
                el.style.top    = y + "px";
            });

            document.addEventListener("mouseup", function() {
                pd.dragging = false;
                el.style.transition = "";
            });
        };

        // ── Boot ─────────────────────────────────────────────
        sgcPD.init();

    }); // end jQuery ready
})();
';
}

// ============================================================
// GLOBAL sgcPD object (defined before inline script runs)
// ============================================================
add_action( 'admin_head', function () {
    $hook = $GLOBALS['hook_suffix'] ?? '';
    if ( strpos( $hook, 'sgclabs' ) === false ) return;
    echo '<script>var sgcPD = {};</script>' . "\n";
} );
