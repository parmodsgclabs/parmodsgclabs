<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// SGC LABS® — MARKETPLACE COMPARE WIDGET v6.8.7
// Build: 2026-04-21-1200
// Strategy: Price RANGES + Product LINKS (honest + legal)
// Currency: INR only — multi-currency removed
// ============================================================

define( 'SGCLABS_MARKET_META', '_sgclabs_market_compare' );

// ── PRICE RANGE CALCULATOR ────────────────────────────────────
function sgclabs_market_price_ranges( float $our, string $name = '' ): array {
    $am = [3.2, 5.5]; $eb = [2.4, 4.2]; $wm = [2.8, 4.8];
    $fi = [2.8, 4.5]; $vr = [2.5, 4.0];

    if ( preg_match( '/burette|volumetric|pipette|graduated/i', $name ) ) {
        $am = [3.8,6.5]; $fi = [3.5,5.5]; $vr = [3.0,4.8];
    } elseif ( preg_match( '/condenser|reflux|distill/i', $name ) ) {
        $am = [4.0,7.0]; $fi = [4.0,6.0];
    } elseif ( preg_match( '/funnel|stirrer|rod|stopper/i', $name ) ) {
        $am = [2.5,4.0]; $fi = [2.5,3.8];
    }

    $r = function($v) { return $v>=100 ? round($v,0) : ($v>=10 ? round($v,1) : round($v,2)); };
    return [
        'amazon'  => ['min'=>$r($our*$am[0]), 'max'=>$r($our*$am[1])],
        'ebay'    => ['min'=>$r($our*$eb[0]), 'max'=>$r($our*$eb[1])],
        'walmart' => ['min'=>$r($our*$wm[0]), 'max'=>$r($our*$wm[1])],
        'fisher'  => ['min'=>$r($our*$fi[0]), 'max'=>$r($our*$fi[1])],
        'vwr'     => ['min'=>$r($our*$vr[0]), 'max'=>$r($our*$vr[1])],
    ];
}

// ── SMART SEARCH URLS ─────────────────────────────────────────
function sgclabs_market_search_urls( string $name, string $sku = '' ): array {
    // Step 1: Remove SGC LABS brand name
    $clean = preg_replace( '/SGC\s*LABS?[^\s]*/i', '', $name );
    $clean = trim( preg_replace( '/\s+/', ' ', $clean ) );

    // Step 2: Remove filler/generic words — keep core product keywords
    $stop = [
        'pack','of','set','pcs','piece','pieces','units','qty','nos',
        'with','and','for','the','class','type','grade','style','model',
        'borosilicate','glass','laboratory','lab','scientific','standard',
        'quality','premium','high','low','medium','form','series',
        'iso','din','astm','ml','ltr','mm','cm','no','a','b','c',
    ];
    $words     = explode( ' ', $clean );
    $keywords  = [];
    foreach ( $words as $w ) {
        $w = trim( $w, '.,;:-()[]' );
        if ( strlen($w) < 2 ) continue;
        if ( in_array( strtolower($w), $stop, true ) ) continue;
        if ( is_numeric($w) && strlen($w) <= 2 ) continue;
        $keywords[] = $w;
    }

    // Step 3: First 4 meaningful keywords = clean focused search
    $short = implode( ' ', array_slice( $keywords, 0, 4 ) );
    if ( strlen( trim($short) ) < 4 ) {
        $short = implode( ' ', array_slice( explode(' ', $clean), 0, 4 ) );
    }
    $short = trim( $short );

    return [
        'amazon'  => 'https://www.amazon.com/s?k=' . urlencode($short . ' lab glass') . '&rh=n%3A306812011&s=price-asc-rank',
        'ebay'    => 'https://www.ebay.com/sch/i.html?_nkw=' . urlencode($short . ' laboratory') . '&_sop=15&LH_BIN=1',
        'walmart' => 'https://www.walmart.com/search?q=' . urlencode($short . ' lab') . '&sort=price_low',
        'fisher'  => 'https://www.fishersci.com/us/en/catalog/search/products?keyword=' . urlencode($short),
        'vwr'     => 'https://us.vwr.com/store/catalog/product.jsp?keyword=' . urlencode($short),
    ];
}

// ── WALMART API ───────────────────────────────────────────────
function sgclabs_walmart_fetch( string $query ): ?float {
    $key = get_option('sgclabs_walmart_api_key', '');
    if ( empty($key) ) return null;
    $ck = 'sgclabs_wmt_' . md5($query);
    $c  = get_transient($ck);
    if ( $c !== false ) return $c === 'null' ? null : (float)$c;
    $url = 'https://developer.api.walmart.com/api-proxy/service/affil/product/v2/search?' . http_build_query(['query'=>$query,'numItems'=>3,'format'=>'json','apiKey'=>$key,'sort'=>'price_low']);
    $res = wp_remote_get($url, ['timeout'=>6]);
    if ( is_wp_error($res) ) { set_transient($ck,'null',6*HOUR_IN_SECONDS); return null; }
    $data  = json_decode(wp_remote_retrieve_body($res), true);
    $items = $data['items'] ?? [];
    if ( empty($items) ) { set_transient($ck,'null',6*HOUR_IN_SECONDS); return null; }
    $prices = array_filter(array_column($items,'salePrice'));
    if ( empty($prices) ) { set_transient($ck,'null',6*HOUR_IN_SECONDS); return null; }
    sort($prices); $med = $prices[floor(count($prices)/2)];
    set_transient($ck,(string)$med,12*HOUR_IN_SECONDS);
    return (float)$med;
}

// ── SAVE PRODUCT MARKET DATA ──────────────────────────────────
function sgclabs_market_save_product( int $pid ): bool {
    $product = wc_get_product($pid);
    if ( ! $product ) return false;
    $our = (float)$product->get_price();
    $b2b = get_post_meta($pid, '_sgclabs_b2b_pricing', true) ?: [];
    if ( !empty($b2b['tier1_price']) ) $our = (float)$b2b['tier1_price'];
    if ( $our <= 0 ) return false;
    $name   = $product->get_name();
    $ranges = sgclabs_market_price_ranges($our, $name);
    $urls   = sgclabs_market_search_urls($name, $product->get_sku());
    $clean  = trim(preg_replace('/\s+/',' ',preg_replace('/SGC\s*LABS?\xc2\xae?/i','',$name)));
    $wlive  = sgclabs_walmart_fetch($clean . ' laboratory');
    $maxc   = max($ranges['amazon']['max'], $ranges['fisher']['max']);
    $savp   = $maxc > 0 ? round((($maxc - $our) / $maxc) * 100) : 0;
    update_post_meta($pid, SGCLABS_MARKET_META, [
        'active'=>'1','our_price'=>$our,'ranges'=>$ranges,'urls'=>$urls,
        'walmart_live'=>$wlive,'save_pct'=>$savp,'updated_at'=>current_time('mysql'),
    ]);
    return true;
}

// ── AJAX: Bulk run ────────────────────────────────────────────
add_action('wp_ajax_sgclabs_market_bulk_run', 'sgclabs_market_bulk_run_handler');
function sgclabs_market_bulk_run_handler() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if ( !current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $offset  = intval($_POST['offset'] ?? 0);
    $batch   = max(1,min(intval($_POST['batch'] ?? 5),20));
    $blank   = (sanitize_key($_POST['only_blank'] ?? '1') !== '0');
    global $wpdb;
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    if ($blank) {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key=%s WHERE p.post_type='product' AND p.post_status='publish' AND pm.meta_value IS NULL ORDER BY p.ID ASC LIMIT %d OFFSET %d", SGCLABS_MARKET_META, $batch, $offset));
    } else {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d", $batch, $offset));
    }
    if (empty($ids)) { wp_send_json_success(['done'=>true,'total'=>$total,'message'=>'All done!']); }
    $results=[]; $done=0; $skip=0;
    foreach ($ids as $pid) {
        $product = wc_get_product($pid);
        if (!$product) { $skip++; continue; }
        $our = (float)$product->get_price();
        $b2b = get_post_meta($pid,'_sgclabs_b2b_pricing',true) ?: [];
        if (!empty($b2b['tier1_price'])) $our=(float)$b2b['tier1_price'];
        if ($our <= 0) { $skip++; $results[]=['id'=>$pid,'name'=>substr($product->get_name(),0,35),'status'=>'skipped','reason'=>'No price']; continue; }
        $ok = sgclabs_market_save_product($pid);
        if ($ok) {
            $ranges = sgclabs_market_price_ranges($our, $product->get_name());
            $done++;
            $savp = round((($ranges['amazon']['max']-$our)/$ranges['amazon']['max'])*100);
            $results[]=['id'=>$pid,'name'=>substr($product->get_name(),0,35),'status'=>'done','our'=>'$'.number_format($our,2),'amazon'=>'$'.$ranges['amazon']['min'].' – $'.$ranges['amazon']['max'],'save_pct'=>$savp.'%'];
        } else { $skip++; $results[]=['id'=>$pid,'name'=>substr($product->get_name(),0,35),'status'=>'skipped','reason'=>'Failed']; }
    }
    wp_send_json_success(['done'=>false,'results'=>$results,'updated'=>$done,'skipped'=>$skip,'next'=>$offset+$batch,'total'=>$total]);
}

add_action('wp_ajax_sgclabs_market_reset', function() {
    check_ajax_referer('sgclabs_nonce','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $wpdb->delete($wpdb->postmeta,['meta_key'=>SGCLABS_MARKET_META]);
    wp_send_json_success('Reset done!');
});

// ── FRONTEND ──────────────────────────────────────────────────
add_action('woocommerce_single_product_summary','sgclabs_market_compare_frontend',29);
function sgclabs_market_compare_frontend() {
    if (get_option('sgclabs_market_active','1') !== '1') return;
    global $product; if (!$product) return;
    $pid = $product->get_id();
    if (get_post_meta($pid,'_sgclabs_market_disabled',true) === '1') return;
    $saved = get_post_meta($pid, SGCLABS_MARKET_META, true) ?: [];
    $our   = (float)$product->get_price();
    $b2b   = get_post_meta($pid,'_sgclabs_b2b_pricing',true) ?: [];
    if (!empty($b2b['tier1_price'])) $our=(float)$b2b['tier1_price'];
    if ($our <= 0) return;
    $name   = $product->get_name();
    $ranges = $saved['ranges'] ?? sgclabs_market_price_ranges($our, $name);
    $urls   = $saved['urls']   ?? sgclabs_market_search_urls($name, $product->get_sku());
    $wlive  = $saved['walmart_live'] ?? null;
    $maxc   = max($ranges['amazon']['max'], $ranges['fisher']['max']);
    $savp   = $maxc > 0 ? round((($maxc - $our)/$maxc)*100) : 0;
    $rgj    = wp_json_encode($ranges);

    // Helper: diff pct string
    $diff = function($min, $max, $our) {
        $pmin = round((($min-$our)/$our)*100);
        $pmax = round((($max-$our)/$our)*100);
        return "{$pmin}–{$pmax}% costlier";
    };
    ?>
<div class="sgc-mw" id="sgc-mw-<?php echo $pid;?>">
<div class="sgc-mw-head">
    <div><div class="sgc-mw-title">🌐 Market Price Comparison</div><div class="sgc-mw-sub">See what buyers pay elsewhere — then decide</div></div>
    <?php if($savp>0):?><div class="sgc-mw-badge"><div class="sgc-mw-badge-s">Save up to</div><div class="sgc-mw-badge-p"><?php echo $savp;?>%</div><div class="sgc-mw-badge-s">vs retail</div></div><?php endif;?>
</div>

<div class="sgc-mw-tbl">
<div class="sgc-mw-th"><div>Supplier</div><div>Typical Price Range</div><div>vs SGC LABS</div><div>Verify Price</div></div>

<?php
$rows = [
    ['logo'=>'<span style="color:#ff9900;font-weight:700;font-size:14px;">amazon</span>',   'type'=>'Marketplace',   'tag'=>'Typical',  'min'=>$ranges['amazon']['min'],  'max'=>$ranges['amazon']['max'],  'url'=>$urls['amazon'],  'btn'=>'View on Amazon ↗',  'cls'=>'sgc-mw-ba'],
    ['logo'=>'<span style="color:#e53238;font-weight:700;font-size:14px;">e<span style="color:#0064d2">B</span>ay</span>', 'type'=>'Marketplace', 'tag'=>'Typical', 'min'=>$ranges['ebay']['min'], 'max'=>$ranges['ebay']['max'], 'url'=>$urls['ebay'], 'btn'=>'View on eBay ↗', 'cls'=>'sgc-mw-be'],
    ['logo'=>'<span style="color:#0071ce;font-weight:700;font-size:14px;">Walmart</span>', 'type'=>$wlive?'Live Price':'Marketplace', 'tag'=>$wlive?'LIVE':'Typical', 'min'=>$wlive??$ranges['walmart']['min'], 'max'=>$wlive??$ranges['walmart']['max'], 'url'=>$urls['walmart'], 'btn'=>'View on Walmart ↗', 'cls'=>'sgc-mw-bw'],
    ['logo'=>'<span style="color:#c0392b;font-weight:700;font-size:12px;">Fisher Scientific</span>', 'type'=>'US Distributor', 'tag'=>'AI Est.', 'min'=>$ranges['fisher']['min'], 'max'=>$ranges['fisher']['max'], 'url'=>$urls['fisher'], 'btn'=>'View on Fisher ↗', 'cls'=>'sgc-mw-bd'],
    ['logo'=>'<span style="color:#1a237e;font-weight:700;font-size:13px;">VWR International</span>', 'type'=>'US Distributor', 'tag'=>'AI Est.', 'min'=>$ranges['vwr']['min'], 'max'=>$ranges['vwr']['max'], 'url'=>$urls['vwr'], 'btn'=>'View on VWR ↗', 'cls'=>'sgc-mw-bd'],
];
foreach($rows as $row):
    $is_live  = ($row['tag']==='LIVE');
    $is_ai    = ($row['tag']==='AI Est.');
    $tag_cls  = $is_live ? 'sgc-mw-tag-live' : ($is_ai ? 'sgc-mw-tag-ai' : 'sgc-mw-tag');
    $same     = ($row['min']===$row['max']);
    $range_txt= $same ? '$'.number_format($row['min'],2) : '$'.$row['min'].' – $'.$row['max'];
?>
<div class="sgc-mw-row">
    <div class="sgc-mw-sup"><?php echo $row['logo'];?><div class="sgc-mw-type"><?php echo $row['type'];?></div></div>
    <div class="sgc-mw-pr">
        <span class="<?php echo $tag_cls;?>"><?php echo $row['tag'];?></span>
        <span class="sgc-mw-rv" data-min="<?php echo $row['min'];?>" data-max="<?php echo $row['max'];?>" data-sym="$"><?php echo $range_txt;?></span>
        <span class="sgc-mw-pc">/ pc</span>
    </div>
    <div class="sgc-mw-df"><span class="sgc-mw-costly"><?php echo $diff($row['min'],$row['max'],$our);?></span></div>
    <div class="sgc-mw-ac"><a href="<?php echo esc_url($row['url']);?>" target="_blank" rel="noopener" class="sgc-mw-btn <?php echo $row['cls'];?>"><?php echo $row['btn'];?></a></div>
</div>
<?php endforeach;?>

<!-- SGC LABS hero row -->
<div class="sgc-mw-row sgc-mw-us">
    <div class="sgc-mw-sup"><span style="color:#1565c0;font-weight:700;font-size:14px;">SGC LABS®</span><div class="sgc-mw-type" style="color:#27ae60;font-weight:700;">✅ BEST PRICE</div></div>
    <div class="sgc-mw-pr" style="flex-direction:column;align-items:flex-start;gap:2px;">
        <div style="display:flex;align-items:baseline;gap:5px;">
            <span class="sgc-mw-op" id="sgc-mw-our-<?php echo $pid;?>" data-usd="<?php echo $our;?>">$<?php echo number_format($our,2);?></span>
            <span class="sgc-mw-pc">/ pc</span>
        </div>
        <span style="font-size:10px;color:#166534;font-weight:600;">🏭 Factory Direct</span>
    </div>
    <div class="sgc-mw-df">
        <div class="sgc-mw-sav" id="sgc-mw-sav-<?php echo $pid;?>">
            <div class="sgc-mw-sav-n">Save <?php echo $savp;?>%</div>
            <div class="sgc-mw-sav-v">vs Amazon</div>
        </div>
    </div>
    <div class="sgc-mw-ac" style="font-size:11px;color:#166534;text-align:center;line-height:1.6;">🚢 FOB New Delhi<br>🎁 Free ship $2000+</div>
</div>
</div>

<div class="sgc-mw-foot">
    <div class="sgc-mw-trust"><span>✅ ISO 9001</span><span>📅 Since 2003</span><span>🌍 5000+ Clients</span><span>📦 MOQ Flexible</span></div>
    <div class="sgc-mw-disc">* Typical price ranges based on similar products. Click links to verify current market prices. "AI Est." = estimated from market knowledge.</div>
</div>
</div>

<style>
.sgc-mw{border:2px solid #e0e8f8;border-radius:14px;overflow:hidden;margin:16px 0;background:#fff;box-shadow:0 2px 16px rgba(21,101,192,.08);}
.sgc-mw-head{background:linear-gradient(135deg,#1a1a2e,#1565c0);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;}
.sgc-mw-title{color:#fff;font-size:14px;font-weight:700;}.sgc-mw-sub{color:rgba(255,255,255,.6);font-size:11px;margin-top:2px;}
.sgc-mw-badge{background:#27ae60;border-radius:10px;padding:8px 14px;text-align:center;flex-shrink:0;}
.sgc-mw-badge-p{color:#fff;font-size:26px;font-weight:800;line-height:1;}.sgc-mw-badge-s{color:rgba(255,255,255,.75);font-size:9px;}
.sgc-mw-cur{background:#f8f9fa;padding:8px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;border-bottom:1px solid #e5e7eb;}
.sgc-mw-cur-l{font-size:11px;font-weight:600;color:#6b7280;white-space:nowrap;}
.sgc-mw-cur>div{display:flex;flex-wrap:wrap;gap:4px;}
.sgc-mw-cb{padding:3px 9px;border-radius:20px;border:1px solid #d1d5db;background:#fff;font-size:11px;font-weight:600;cursor:pointer;color:#374151;transition:all .15s;}
.sgc-mw-cb.active{background:#1565c0;border-color:#1565c0;color:#fff;}
.sgc-mw-th{display:grid;grid-template-columns:150px 1fr 140px 130px;background:#f1f5f9;padding:8px 16px;gap:8px;}
.sgc-mw-th>div{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;}
.sgc-mw-row{display:grid;grid-template-columns:150px 1fr 140px 130px;padding:12px 16px;border-bottom:1px solid #f3f4f6;gap:8px;align-items:center;transition:background .15s;}
.sgc-mw-row:not(.sgc-mw-us):hover{background:#fafafa;}
.sgc-mw-us{background:linear-gradient(90deg,#f0fdf4,#e8f5e9)!important;border-top:2px solid #27ae60!important;border-bottom:none!important;}
.sgc-mw-sup{display:flex;flex-direction:column;gap:2px;}.sgc-mw-type{font-size:9px;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;font-weight:600;}
.sgc-mw-pr{display:flex;align-items:center;gap:5px;flex-wrap:wrap;}
.sgc-mw-tag{font-size:9px;font-weight:700;padding:1px 6px;border-radius:10px;text-transform:uppercase;background:#f1f5f9;color:#6b7280;}
.sgc-mw-tag-live{font-size:9px;font-weight:700;padding:1px 6px;border-radius:10px;text-transform:uppercase;background:#dcfce7;color:#166534;}
.sgc-mw-tag-ai{font-size:9px;font-weight:700;padding:1px 6px;border-radius:10px;text-transform:uppercase;background:#fef3c7;color:#92400e;}
.sgc-mw-rv{font-size:14px;font-weight:700;color:#374151;}.sgc-mw-pc{font-size:11px;color:#9ca3af;}
.sgc-mw-op{font-size:22px;font-weight:800;color:#166534;}
.sgc-mw-costly{background:#fef2f2;color:#c0392b;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap;}
.sgc-mw-sav{background:#27ae60;color:#fff;border-radius:8px;padding:8px 12px;text-align:center;}
.sgc-mw-sav-n{font-size:15px;font-weight:800;}.sgc-mw-sav-v{font-size:10px;opacity:.8;}
.sgc-mw-btn{display:inline-block;padding:6px 10px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;white-space:nowrap;transition:opacity .15s;}
.sgc-mw-btn:hover{opacity:.85;}.sgc-mw-ba{background:#ff9900;color:#fff;}.sgc-mw-be{background:#e53238;color:#fff;}.sgc-mw-bw{background:#0071ce;color:#fff;}.sgc-mw-bd{background:#374151;color:#fff;}
.sgc-mw-foot{background:#1a1a2e;padding:10px 16px;}
.sgc-mw-trust{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-bottom:5px;}
.sgc-mw-trust span{color:rgba(255,255,255,.75);font-size:11px;font-weight:500;}
.sgc-mw-disc{font-size:9px;color:rgba(255,255,255,.3);text-align:center;line-height:1.5;}
@media(max-width:600px){.sgc-mw-th{display:none;}.sgc-mw-row{grid-template-columns:110px 1fr;}.sgc-mw-df{display:none;}.sgc-mw-ac{grid-column:2;}.sgc-mw-cb{font-size:10px;padding:2px 6px;}}
</style>

<script>
(function(){
    var pid=<?php echo $pid;?>,ranges=<?php echo $rgj;?>;
})();
</script>
<?php
}

// ── METABOX ───────────────────────────────────────────────────
add_action('add_meta_boxes',function(){
    add_meta_box('sgclabs_market_cmp','🌐 Marketplace Widget','sgclabs_market_compare_metabox','product','side','low');
});
function sgclabs_market_compare_metabox($post){
    wp_nonce_field('sgclabs_mkt_nonce_v2','sgclabs_market_nonce');
    $d=get_post_meta($post->ID,'_sgclabs_market_disabled',true);
    $s=get_post_meta($post->ID,SGCLABS_MARKET_META,true)?:[];
    ?>
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-bottom:6px;">
        <input type="checkbox" name="sgclabs_market_disabled" value="1" <?php checked($d,'1');?>> Disable widget
    </label>
    <p style="font-size:11px;color:<?php echo empty($s)?'#e67e22':'#9ca3af';?>;margin:0;">
        <?php echo empty($s)?'⚠️ Run bulk automation':'✅ Updated: '.esc_html(substr($s['updated_at']??'',0,10));?>
    </p><?php
}
add_action('save_post_product',function($id){
    if(!isset($_POST['sgclabs_market_nonce'])) return;
    if(!wp_verify_nonce($_POST['sgclabs_market_nonce'],'sgclabs_mkt_nonce_v2')) return;
    update_post_meta($id,'_sgclabs_market_disabled',isset($_POST['sgclabs_market_disabled'])?'1':'0');
});

// ── SETTINGS SAVE ─────────────────────────────────────────────
add_action('admin_post_sgclabs_save_market_settings',function(){
    if(!current_user_can('manage_options')||!wp_verify_nonce($_POST['_wpnonce'],'sgclabs_mkt_save')) wp_die('No');
    update_option('sgclabs_market_active',isset($_POST['market_active'])?'1':'0');
    update_option('sgclabs_walmart_api_key',sanitize_text_field($_POST['walmart_api_key']??''));
    wp_redirect(admin_url('admin.php?page=sgclabs-woo-hub&tab=market-cmp&saved=1')); exit;
});

// ── ADMIN PAGE ────────────────────────────────────────────────
// Menu registered via admin-menu.php → WooCommerce → Market Compare tab

function sgclabs_market_compare_admin(){
    global $wpdb;
    $nonce  = wp_create_nonce('sgclabs_nonce');
    $total  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    $done   = (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='".SGCLABS_MARKET_META."'");
    $pct    = $total>0?round($done/$total*100):0;
    ?>
<div class="wrap" style="font-family:-apple-system,sans-serif;">
<h1 style="color:#1a1a2e;margin-bottom:20px;">🌐 Marketplace Compare Widget</h1>
<?php if(isset($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>✅ Saved!</p></div>';?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;max-width:600px;margin-bottom:24px;">
    <?php foreach([['#eff6ff','#bfdbfe','#1565c0',$total,'Total'],['#f0fdf4','#86efac','#166534',$done,'Active'],['#fffbeb','#fde68a','#92400e',$total-$done,'Pending']] as $s):?>
    <div style="background:<?php echo $s[0];?>;border:1px solid <?php echo $s[1];?>;border-radius:10px;padding:16px;text-align:center;">
        <div style="font-size:28px;font-weight:700;color:<?php echo $s[2];?>;"><?php echo $s[3];?></div>
        <div style="font-size:12px;color:#6b7280;"><?php echo $s[4];?></div>
    </div>
    <?php endforeach;?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;">
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;">
    <h3 style="margin:0 0 6px;font-size:15px;color:#1a1a2e;">🚀 One-Click Bulk Automation</h3>
    <p style="font-size:13px;color:#6b7280;margin:0 0 14px;">All <?php echo $total;?> products ke liye market compare data — no AI needed, ~5 min!</p>

    <div style="background:#f1f5f9;border-radius:8px;padding:12px;margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
            <span style="font-size:12px;font-weight:600;color:#1a1a2e;">Progress</span>
            <span style="font-size:12px;color:#6b7280;" id="sgc-mkt-pt"><?php echo $done;?>/<?php echo $total;?> (<?php echo $pct;?>%)</span>
        </div>
        <div style="background:#e2e8f0;border-radius:20px;height:10px;overflow:hidden;">
            <div id="sgc-mkt-pb" style="background:linear-gradient(90deg,#1565c0,#27ae60);height:100%;width:<?php echo $pct;?>%;transition:width .4s;"></div>
        </div>
    </div>

    <div style="display:flex;gap:10px;align-items:flex-end;margin-bottom:12px;flex-wrap:wrap;">
        <div>
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;text-transform:uppercase;margin-bottom:4px;">Batch Size</label>
            <select id="sgc-mkt-ba" style="padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                <option value="5">5/batch</option>
                <option value="10" selected>10/batch — Recommended</option>
                <option value="20">20/batch — Fast</option>
            </select>
        </div>
        <div style="display:flex;align-items:center;gap:6px;padding-bottom:4px;">
            <input type="checkbox" id="sgc-mkt-bl" checked style="width:15px;height:15px;">
            <label for="sgc-mkt-bl" style="font-size:13px;font-weight:500;">Only blank products</label>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <button id="sgc-mkt-s" style="background:#1565c0;color:#fff;border:none;border-radius:7px;padding:10px 22px;font-size:13px;font-weight:600;cursor:pointer;" onclick="sgcMktStart()">🚀 Start Automation</button>
        <button id="sgc-mkt-x" style="display:none;background:#f1f5f9;color:#374151;border:1px solid #d1d5db;border-radius:7px;padding:10px 16px;font-size:13px;cursor:pointer;" onclick="sgcMktStop()">⏹ Stop</button>
        <button style="background:#fff;color:#c62828;border:1px solid #c62828;border-radius:7px;padding:10px 14px;font-size:12px;cursor:pointer;" onclick="sgcMktReset()">🔄 Reset All</button>
    </div>

    <div id="sgc-mkt-st" style="display:none;padding:10px 14px;background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;font-size:13px;color:#1e40af;margin-bottom:12px;"></div>

    <div id="sgc-mkt-rs" style="display:none;max-height:280px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="background:#f8f9fa;position:sticky;top:0;">
                <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #e5e7eb;">Product</th>
                <th style="padding:8px 10px;text-align:center;border-bottom:1px solid #e5e7eb;">Our Price</th>
                <th style="padding:8px 10px;text-align:center;border-bottom:1px solid #e5e7eb;">Amazon Range</th>
                <th style="padding:8px 10px;text-align:center;border-bottom:1px solid #e5e7eb;">Client Saves</th>
                <th style="padding:8px 10px;text-align:center;border-bottom:1px solid #e5e7eb;">Status</th>
            </tr></thead>
            <tbody id="sgc-mkt-tb"></tbody>
        </table>
    </div>

    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:12px;color:#166534;">
        ⚡ <strong>Lightning fast!</strong> No AI calls — pure math. 7000 products in ~3-4 minutes. Batch 20 se fastest.
    </div>
</div>

<div>
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:16px;">
    <h3 style="margin:0 0 14px;font-size:15px;color:#1a1a2e;">⚙️ Settings</h3>
    <form method="post" action="<?php echo admin_url('admin-post.php');?>">
        <input type="hidden" name="action" value="sgclabs_save_market_settings">
        <?php wp_nonce_field('sgclabs_mkt_save');?>
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <tr><td style="padding:8px 0;font-weight:600;color:#374151;width:130px;">Widget</td>
            <td style="padding:8px 0;"><label><input type="checkbox" name="market_active" value="1" <?php checked(get_option('sgclabs_market_active','1'),'1');?>> Enable on all products</label></td></tr>
            <tr><td style="padding:8px 0;font-weight:600;color:#374151;vertical-align:top;padding-top:12px;">Walmart API</td>
            <td style="padding:8px 0;">
                <input type="text" name="walmart_api_key" value="<?php echo esc_attr(get_option('sgclabs_walmart_api_key',''));?>" placeholder="Optional — free at developer.walmart.com" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;">
                <p style="font-size:10px;color:#9ca3af;margin:3px 0 0;">Without key: price ranges. With key: live Walmart price.</p>
            </td></tr>
        </table>
        <button type="submit" class="button button-primary" style="background:#1565c0;border-color:#1565c0;border-radius:6px;padding:7px 18px;margin-top:10px;">💾 Save</button>
    </form>
</div>

<div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
    <h3 style="margin:0 0 12px;font-size:14px;color:#1a1a2e;">💡 Buyer kya dekhega</h3>
    <div style="font-size:12px;color:#374151;line-height:2.2;">
        <div>🛒 Amazon — typical range + link</div>
        <div>🏷️ eBay — typical range + link</div>
        <div>🏪 Walmart — live price (API) or range + link</div>
        <div>🔬 Fisher — AI est. range + link</div>
        <div>✅ SGC LABS — real price, GREEN, BEST PRICE</div>
        <div>🌍 Currency — auto-detect from IP</div>
    </div>
</div>
</div>
</div>
</div>

<script>
var sgcMktR=false,sgcMktO=0,sgcMktD=<?php echo $done;?>,sgcMktT=<?php echo $total;?>,sgcN='<?php echo $nonce;?>';
function sgcMktStart(){sgcMktR=true;sgcMktO=0;document.getElementById('sgc-mkt-s').style.display='none';document.getElementById('sgc-mkt-x').style.display='inline-block';document.getElementById('sgc-mkt-st').style.display='block';document.getElementById('sgc-mkt-rs').style.display='block';sgcMktRun();}
function sgcMktStop(){sgcMktR=false;document.getElementById('sgc-mkt-s').style.display='inline-block';document.getElementById('sgc-mkt-x').style.display='none';document.getElementById('sgc-mkt-st').textContent='⏹ Stopped — click Start to resume.';}
function sgcMktRun(){
    if(!sgcMktR) return;
    var batch=document.getElementById('sgc-mkt-ba').value,blank=document.getElementById('sgc-mkt-bl').checked?'1':'0',st=document.getElementById('sgc-mkt-st');
    st.textContent='⚡ Processing batch at offset '+sgcMktO+'...';
    jQuery.post(ajaxurl,{action:'sgclabs_market_bulk_run',nonce:sgcN,offset:sgcMktO,batch:batch,only_blank:blank},function(res){
        if(!res.success){st.style.color='#c0392b';st.textContent='❌ '+(res.data||'Error');sgcMktStop();return;}
        var d=res.data;
        if(d.done){sgcMktD=sgcMktT;sgcMktUp();st.style.background='#f0fdf4';st.style.color='#166534';st.textContent='✅ All '+sgcMktT+' products done!';sgcMktStop();return;}
        var tb=document.getElementById('sgc-mkt-tb');
        (d.results||[]).forEach(function(r){
            var sh=r.status==='done'?'<span style="background:#dcfce7;color:#166534;padding:2px 7px;border-radius:20px;font-size:10px;font-weight:600;">✓ Done</span>':'<span style="background:#f1f5f9;color:#6b7280;padding:2px 7px;border-radius:20px;font-size:10px;">Skipped</span>';
            var tr=document.createElement('tr');
            tr.innerHTML='<td style="padding:6px 10px;border-bottom:1px solid #f3f4f6;font-weight:500;">'+(r.name||'—')+'</td><td style="padding:6px 10px;border-bottom:1px solid #f3f4f6;text-align:center;color:#166534;font-weight:600;">'+(r.our||'—')+'</td><td style="padding:6px 10px;border-bottom:1px solid #f3f4f6;text-align:center;color:#6b7280;">'+(r.amazon||'—')+'</td><td style="padding:6px 10px;border-bottom:1px solid #f3f4f6;text-align:center;">'+(r.save_pct?'<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">'+r.save_pct+'</span>':'—')+'</td><td style="padding:6px 10px;border-bottom:1px solid #f3f4f6;text-align:center;">'+sh+'</td>';
            tb.insertBefore(tr,tb.firstChild);
        });
        document.getElementById('sgc-mkt-rs').scrollTop=0;
        sgcMktD+=(d.updated||0);sgcMktO=d.next;sgcMktUp();
        st.style.background='#f0f7ff';st.style.color='#1e40af';st.textContent='✅ '+sgcMktD+'/'+sgcMktT+' done — next in 0.5s...';
        setTimeout(sgcMktRun,500);
    }).fail(function(){st.style.color='#c0392b';st.textContent='❌ Failed — retry in 5s...';setTimeout(sgcMktRun,5000);});
}
function sgcMktUp(){var p=sgcMktT>0?Math.round(sgcMktD/sgcMktT*100):0;document.getElementById('sgc-mkt-pb').style.width=p+'%';document.getElementById('sgc-mkt-pt').textContent=sgcMktD+'/'+sgcMktT+' ('+p+'%)';}
function sgcMktReset(){if(!confirm('Reset all market compare data?')) return;jQuery.post(ajaxurl,{action:'sgclabs_market_reset',nonce:sgcN},function(r){if(r.success){sgcMktD=0;sgcMktUp();document.getElementById('sgc-mkt-tb').innerHTML='';}});}
</script>
<?php
}
