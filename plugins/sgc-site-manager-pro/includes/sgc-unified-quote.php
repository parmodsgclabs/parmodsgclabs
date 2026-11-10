<?php
/**
 * ============================================================
 * SGC LABS®  —  UNIFIED QUOTE & LEAD SYSTEM   (v1.0.0)
 * ============================================================
 * Replaces the fragmented quote/inquiry stack with ONE system:
 *   - One table:  {prefix}sgc_leads   (new, clean, multi-item)
 *   - One form:   [sgclabs_quote_form] (quote + bulk + general contact)
 *   - One cart:   localStorage 'sgclabs_quote_cart' (add from anywhere)
 *   - One submit: wp_ajax sgc_uq_submit  -> sgc_leads
 *   - Hybrid:     per-product "Buy Now" vs "Request Quote"
 *
 * SAFETY BRIDGE: every lead is ALSO mirrored into the existing
 * {prefix}sgclabs_inquiries table so Invoice Pro + Smart CRM keep
 * working unchanged. The new sgc_leads table is the source of truth.
 *
 * Canonical hooks fired on every submission (back-compat):
 *   do_action('sgclabs_inquiry_received', $data)   // follow-up automation
 *   do_action('sgclabs_inquiry_submitted', $data)  // Smart CRM instant reply
 *
 * @package SGCLabs_Manager
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( defined( 'SGC_UQ_VERSION' ) ) { return; }
define( 'SGC_UQ_VERSION', '1.0.0' );

/* ============================================================
 * 1. TABLE
 * ============================================================ */
function sgc_uq_table() {
	global $wpdb;
	return $wpdb->prefix . 'sgc_leads';
}

function sgc_uq_create_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$table   = sgc_uq_table();
	dbDelta(
		"CREATE TABLE IF NOT EXISTS {$table} (
			id            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_number   varchar(40)  DEFAULT NULL,
			source_type   varchar(20)  NOT NULL DEFAULT 'quote',
			trade_type    varchar(10)  NOT NULL DEFAULT 'export',
			name          varchar(120) NOT NULL DEFAULT '',
			email         varchar(120) NOT NULL DEFAULT '',
			phone         varchar(40)  DEFAULT '',
			company       varchar(160) DEFAULT '',
			country       varchar(100) DEFAULT '',
			incoterm      varchar(20)  DEFAULT '',
			port          varchar(120) DEFAULT '',
			products_json longtext     DEFAULT NULL,
			product_summary varchar(255) DEFAULT '',
			qty           varchar(120) DEFAULT '',
			message       text         DEFAULT NULL,
			buyer_type    varchar(60)  DEFAULT '',
			status        varchar(20)  NOT NULL DEFAULT 'new',
			pi_number     varchar(40)  DEFAULT NULL,
			ip            varchar(45)  DEFAULT '',
			user_agent    varchar(255) DEFAULT '',
			legacy_ref    varchar(60)  DEFAULT NULL,
			created_at    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY email_idx (email(80)),
			KEY status_idx (status),
			KEY source_idx (source_type),
			KEY created_idx (created_at),
			KEY legacy_idx (legacy_ref)
		) {$charset};"
	);
}

/** Run table + migration once per version, on admin load. */
add_action( 'admin_init', function () {
	if ( get_option( 'sgc_uq_db_ver' ) !== SGC_UQ_VERSION ) {
		sgc_uq_create_table();
		update_option( 'sgc_uq_db_ver', SGC_UQ_VERSION, false );
	}
	if ( ! get_option( 'sgc_uq_migrated' ) ) {
		sgc_uq_migrate();
		update_option( 'sgc_uq_migrated', 1, false );
	}
	if ( ! get_option( 'sgc_uq_installed' ) ) {
		// Disable the legacy Get-a-Quote front display so there are no duplicate
		// buttons / conflicting catalog filters. Fully reversible — set the option
		// 'sgclabs_quote_active' back to '1' to restore the old behaviour.
		update_option( 'sgclabs_quote_active', '0' );
		update_option( 'sgc_uq_installed', 1, false );
	}
} );

/* ============================================================
 * 2. LEAD NUMBER (re-uses unified doc sequence if available)
 * ============================================================ */
function sgc_uq_lead_number() {
	if ( function_exists( 'sgc_generate_doc_number' ) ) {
		$n = sgc_generate_doc_number( 'LEAD' );
		if ( $n ) { return $n; }
	}
	return 'LEAD-' . gmdate( 'Y' ) . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 6 ) );
}

/* ============================================================
 * 3. INSERT  (+ safety mirror into sgclabs_inquiries)
 * ============================================================ */
function sgc_uq_insert( array $d ) {
	global $wpdb;
	$now  = current_time( 'mysql' );
	$row  = [
		'lead_number'     => $d['lead_number'] ?? sgc_uq_lead_number(),
		'source_type'     => $d['source_type'] ?? 'quote',
		'trade_type'      => $d['trade_type'] ?? 'export',
		'name'            => $d['name'] ?? '',
		'email'           => $d['email'] ?? '',
		'phone'           => $d['phone'] ?? '',
		'company'         => $d['company'] ?? '',
		'country'         => $d['country'] ?? '',
		'incoterm'        => $d['incoterm'] ?? '',
		'port'            => $d['port'] ?? '',
		'products_json'   => $d['products_json'] ?? '',
		'product_summary' => $d['product_summary'] ?? '',
		'qty'             => $d['qty'] ?? '',
		'message'         => $d['message'] ?? '',
		'buyer_type'      => $d['buyer_type'] ?? '',
		'status'          => $d['status'] ?? 'new',
		'ip'              => $d['ip'] ?? '',
		'user_agent'      => $d['user_agent'] ?? '',
		'legacy_ref'      => $d['legacy_ref'] ?? null,
		'created_at'      => $d['created_at'] ?? $now,
		'updated_at'      => $now,
	];
	$ok = $wpdb->insert( sgc_uq_table(), $row );
	if ( $ok === false ) { return false; }
	$row['id'] = (int) $wpdb->insert_id;

	// --- SAFETY BRIDGE: mirror into existing sgclabs_inquiries ---
	sgc_uq_mirror_to_inquiries( $row );

	return $row;
}

/** Keep the legacy canonical table populated for Invoice Pro + CRM. */
function sgc_uq_mirror_to_inquiries( array $r ) {
	global $wpdb;
	$inq = $wpdb->prefix . 'sgclabs_inquiries';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $inq ) ) !== $inq ) {
		return; // legacy table absent — nothing to mirror
	}
	$cols    = $wpdb->get_col( "DESCRIBE {$inq}" ); // phpcs:ignore
	$payload = [
		'name'        => $r['name'],
		'email'       => $r['email'],
		'phone'       => $r['phone'],
		'country'     => $r['country'],
		'product'     => '[' . ucfirst( $r['source_type'] ) . '] ' . $r['product_summary'],
		'qty'         => $r['qty'],
		'message'     => $r['message'],
		'status'      => 'new',
	];
	// Only add columns that actually exist on this install.
	$optional = [
		'company'     => $r['company'],
		'incoterm'    => $r['incoterm'],
		'port'        => $r['port'],
		'source_type' => $r['source_type'],
		'trade_type'  => $r['trade_type'],
		'buyer_type'  => $r['buyer_type'],
		'inq_number'  => $r['lead_number'],
	];
	foreach ( $optional as $col => $val ) {
		if ( in_array( $col, $cols, true ) ) { $payload[ $col ] = $val; }
	}
	$wpdb->insert( $inq, $payload );
}

/* ============================================================
 * 4. ONE-TIME MIGRATION (idempotent, dedup by legacy_ref)
 * ============================================================ */
function sgc_uq_migrate() {
	global $wpdb;
	$dest = sgc_uq_table();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $dest ) ) !== $dest ) { return; }

	$exists = function ( $ref ) use ( $wpdb, $dest ) {
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$dest} WHERE legacy_ref = %s", $ref ) ) > 0;
	};

	// 4a. From sgclabs_inquiries
	$inq = $wpdb->prefix . 'sgclabs_inquiries';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $inq ) ) === $inq ) {
		$rows = $wpdb->get_results( "SELECT * FROM {$inq} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $row ) {
			$ref = 'inq_' . $row['id'];
			if ( $exists( $ref ) ) { continue; }
			$wpdb->insert( $dest, [
				'lead_number'     => $row['inq_number'] ?? null,
				'source_type'     => $row['source_type'] ?? 'quote',
				'trade_type'      => $row['trade_type'] ?? 'export',
				'name'            => $row['name'] ?? '',
				'email'           => $row['email'] ?? '',
				'phone'           => $row['phone'] ?? '',
				'company'         => $row['company'] ?? '',
				'country'         => $row['country'] ?? '',
				'incoterm'        => $row['incoterm'] ?? '',
				'port'            => $row['port'] ?? '',
				'product_summary' => mb_substr( (string) ( $row['product'] ?? '' ), 0, 255 ),
				'qty'             => $row['qty'] ?? '',
				'message'         => $row['message'] ?? '',
				'buyer_type'      => $row['buyer_type'] ?? '',
				'status'          => $row['status'] ?? 'new',
				'pi_number'       => $row['pi_number'] ?? null,
				'legacy_ref'      => $ref,
				'created_at'      => $row['created_at'] ?? current_time( 'mysql' ),
			] );
		}
	}

	// 4b. From sgclabs_b2b_quotes
	$b2b = $wpdb->prefix . 'sgclabs_b2b_quotes';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $b2b ) ) === $b2b ) {
		$cols = $wpdb->get_col( "DESCRIBE {$b2b}" ); // phpcs:ignore
		$rows = $wpdb->get_results( "SELECT * FROM {$b2b} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $row ) {
			$ref = 'b2b_' . $row['id'];
			if ( $exists( $ref ) ) { continue; }
			$wpdb->insert( $dest, [
				'source_type'     => 'b2b',
				'name'            => $row['name'] ?? $row['customer_name'] ?? '',
				'email'           => $row['email'] ?? '',
				'phone'           => $row['phone'] ?? '',
				'company'         => $row['company'] ?? '',
				'country'         => $row['country'] ?? '',
				'products_json'   => $row['products'] ?? ( $row['items'] ?? '' ),
				'product_summary' => mb_substr( (string) ( $row['product_summary'] ?? ( $row['products'] ?? '' ) ), 0, 255 ),
				'message'         => $row['message'] ?? ( $row['notes'] ?? '' ),
				'status'          => $row['status'] ?? 'new',
				'legacy_ref'      => $ref,
				'created_at'      => $row['created_at'] ?? current_time( 'mysql' ),
			] );
		}
	}

	// 4c. From the rogue wp_option log (sgc_quote_submissions_log)
	$log = get_option( 'sgc_quote_submissions_log', [] );
	if ( is_array( $log ) ) {
		foreach ( $log as $i => $entry ) {
			$ref = 'optlog_' . md5( ( $entry['email'] ?? '' ) . ( $entry['timestamp'] ?? $i ) );
			if ( $exists( $ref ) ) { continue; }
			$wpdb->insert( $dest, [
				'source_type'     => 'quote',
				'name'            => $entry['name'] ?? '',
				'email'           => $entry['email'] ?? '',
				'phone'           => $entry['phone'] ?? '',
				'company'         => $entry['company'] ?? '',
				'country'         => $entry['country'] ?? '',
				'product_summary' => mb_substr( (string) ( $entry['product'] ?? '' ), 0, 255 ),
				'qty'             => $entry['qty'] ?? '',
				'message'         => $entry['message'] ?? '',
				'buyer_type'      => $entry['buyer_type'] ?? '',
				'ip'              => $entry['ip'] ?? '',
				'legacy_ref'      => $ref,
				'created_at'      => $entry['timestamp'] ?? current_time( 'mysql' ),
			] );
		}
	}
}

/* ============================================================
 * 5. FRONT-END  —  Quote Cart API + Add-to-Quote buttons
 * ============================================================ */

/** Global JS cart API + floating mini-cart, loaded on shop/product pages. */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_woocommerce' ) ) { return; }
	if ( ! ( is_shop() || is_product_category() || is_product() || is_cart() || is_page() ) ) { return; }
	$quote_url = esc_url( sgc_uq_form_url() );
	?>
	<div id="sgc-uq-fab" style="position:fixed;right:18px;bottom:18px;z-index:9998;display:none;">
		<a href="<?php echo $quote_url; ?>" style="display:flex;align-items:center;gap:8px;background:#1a1a2e;color:#fff;text-decoration:none;padding:12px 18px;border-radius:30px;box-shadow:0 4px 14px rgba(0,0,0,.25);font-weight:700;font-size:14px;">
			🛒 Quote Cart <span id="sgc-uq-fab-count" style="background:#27ae60;border-radius:50%;min-width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;">0</span>
		</a>
	</div>
	<script>
	(function(){
		function get(){try{return JSON.parse(localStorage.getItem('sgclabs_quote_cart')||'[]');}catch(e){return[];}}
		function set(c){localStorage.setItem('sgclabs_quote_cart',JSON.stringify(c));bubble();}
		function bubble(){
			var c=get(),n=0;c.forEach(function(i){n+=parseInt(i.qty)||1;});
			var fab=document.getElementById('sgc-uq-fab'),ct=document.getElementById('sgc-uq-fab-count');
			if(ct)ct.textContent=n;if(fab)fab.style.display=c.length?'block':'none';
		}
		// Public API used by product button, quick-view, compare, wishlist
		window.sgcAddToQuote=function(item){
			if(!item||!item.id){return;}
			var c=get(),found=false;
			c.forEach(function(it){if(String(it.id)===String(item.id)){it.qty=(parseInt(it.qty)||1)+(parseInt(item.qty)||1);found=true;}});
			if(!found){c.push({id:item.id,name:item.name||'Product',sku:item.sku||'—',price:item.price||'0.00',img:item.img||'',qty:parseInt(item.qty)||1});}
			set(c);
			if(window.sgcUQToast){window.sgcUQToast('✓ Added to Quote Cart');}
		};
		window.sgcUQToast=function(t){
			var d=document.createElement('div');
			d.textContent=t;
			d.style.cssText='position:fixed;left:50%;bottom:80px;transform:translateX(-50%);background:#27ae60;color:#fff;padding:10px 20px;border-radius:24px;z-index:99999;font-weight:700;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.2);opacity:0;transition:opacity .2s;';
			document.body.appendChild(d);
			requestAnimationFrame(function(){d.style.opacity='1';});
			setTimeout(function(){d.style.opacity='0';setTimeout(function(){d.remove();},250);},1800);
		};
		document.addEventListener('click',function(e){
			var b=e.target.closest('.sgc-add-to-quote');
			if(!b)return;
			e.preventDefault();
			window.sgcAddToQuote({id:b.dataset.id,name:b.dataset.name,sku:b.dataset.sku,price:b.dataset.price,img:b.dataset.img,qty:1});
		});
		bubble();
	})();
	</script>
	<?php
}, 20 );

/** URL of the quote form page (option, falls back to a [sgclabs_quote_form] page or ?sgc-quote). */
function sgc_uq_form_url() {
	$pid = (int) get_option( 'sgc_uq_form_page_id', 0 );
	if ( $pid && get_post_status( $pid ) === 'publish' ) { return get_permalink( $pid ); }
	return home_url( '/request-quote/' );
}

/** Inject an "Add to Quote" button on product pages when the product is quote-enabled.
 *  Hooked on the product summary (not the cart form) so it renders even for
 *  quote-only products whose Add-to-Cart form is hidden. */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product ) { return; }
	$mode = sgc_uq_product_mode( $product );
	if ( $mode === 'buy_now' ) { return; } // pure buy-now: no quote button
	$img = '';
	$iid = $product->get_image_id();
	if ( $iid ) { $src = wp_get_attachment_image_src( $iid, 'thumbnail' ); $img = $src ? $src[0] : ''; }
	printf(
		'<button type="button" class="sgc-add-to-quote button alt" data-id="%s" data-name="%s" data-sku="%s" data-price="%s" data-img="%s" style="margin-top:8px;background:#1a1a2e;">📋 Add to Quote</button>',
		esc_attr( $product->get_id() ),
		esc_attr( $product->get_name() ),
		esc_attr( $product->get_sku() ),
		esc_attr( $product->get_price() ),
		esc_attr( $img )
	);
}, 15 );

/* ============================================================
 * 6. UNIFIED FORM  [sgclabs_quote_form]
 * ============================================================ */
function sgc_uq_form_shortcode( $atts = [] ) {
	$atts      = shortcode_atts( [ 'type' => 'quote' ], (array) $atts );
	$countries = [ 'India','USA','United Kingdom','Germany','France','Italy','Spain','Netherlands','Belgium','Australia','Canada','China','Japan','South Korea','UAE','Saudi Arabia','Kuwait','Qatar','Bahrain','Oman','Egypt','Turkey','Brazil','Mexico','South Africa','Nigeria','Pakistan','Bangladesh','Sri Lanka','Russia','Poland','Sweden','Switzerland','Singapore','Malaysia','Thailand','Indonesia','Vietnam','New Zealand','Other' ];
	$incoterms = [ 'EXW'=>'EXW – Ex Works','FOB'=>'FOB – Free on Board','CIF'=>'CIF – Cost Insurance Freight','CFR'=>'CFR – Cost & Freight','DAP'=>'DAP – Delivered at Place','DDP'=>'DDP – Delivered Duty Paid','FCA'=>'FCA – Free Carrier' ];
	ob_start();
	?>
	<div class="sgc-uq-wrap" id="sgc-uq-form">
		<div id="sgc-uq-msg" style="display:none;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;"></div>

		<div class="sgc-uq-rtype">
			<label style="font-weight:600;font-size:13px;color:#333;">What do you need?</label>
			<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
				<label class="sgc-uq-pill"><input type="radio" name="sgc_uq_rtype" value="quote" checked> Product Quote</label>
				<label class="sgc-uq-pill"><input type="radio" name="sgc_uq_rtype" value="b2b"> Bulk / B2B Order</label>
				<label class="sgc-uq-pill"><input type="radio" name="sgc_uq_rtype" value="contact"> General Inquiry</label>
			</div>
		</div>

		<div id="sgc-uq-cart-block">
			<h3 class="sgc-uq-h3">📦 Products in Your Request</h3>
			<div id="sgc-uq-cart-items"></div>
			<div id="sgc-uq-cart-empty" class="sgc-uq-empty" style="display:none;">
				Cart empty. Browse products and click <strong>“Add to Quote”</strong>, or add manually below.
			</div>
			<div style="margin:10px 0 18px;">
				<button type="button" onclick="sgcUQToggleManual()" class="sgc-uq-addbtn">+ Add product manually</button>
				<div id="sgc-uq-manual" style="display:none;margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
					<input type="text" id="sgc-uq-mname" placeholder="Product name / SKU" style="flex:2;min-width:170px;">
					<input type="number" id="sgc-uq-mqty" placeholder="Qty" min="1" value="1" style="width:80px;">
					<button type="button" onclick="sgcUQAddManual()" class="sgc-uq-addbtn solid">Add</button>
				</div>
			</div>
		</div>

		<h3 class="sgc-uq-h3">👤 Your Details</h3>
		<div class="sgc-uq-grid">
			<div class="sgc-uq-f"><label>Full Name *</label><input type="text" id="uq_name" placeholder="Your name"></div>
			<div class="sgc-uq-f"><label>Company</label><input type="text" id="uq_company" placeholder="Company (optional)"></div>
			<div class="sgc-uq-f"><label>Email *</label><input type="email" id="uq_email" placeholder="your@email.com"></div>
			<div class="sgc-uq-f"><label>Phone / WhatsApp *</label><input type="tel" id="uq_phone" placeholder="+1 555 000 0000"></div>
			<div class="sgc-uq-f"><label>Country *</label><select id="uq_country"><option value="">-- Select --</option><?php foreach ( $countries as $c ) { echo "<option value='" . esc_attr( $c ) . "'>" . esc_html( $c ) . '</option>'; } ?></select></div>
			<div class="sgc-uq-f sgc-uq-exportonly"><label>Incoterm</label><select id="uq_incoterm"><?php foreach ( $incoterms as $k => $v ) { echo "<option value='" . esc_attr( $k ) . "'>" . esc_html( $v ) . '</option>'; } ?></select></div>
			<div class="sgc-uq-f sgc-uq-exportonly" style="grid-column:1/-1;"><label>Nearest Port / Airport</label><input type="text" id="uq_port" placeholder="e.g. Nhava Sheva, JFK"></div>
			<div class="sgc-uq-f" style="grid-column:1/-1;"><label>Message / Requirements</label><textarea id="uq_message" rows="3" placeholder="Sizes, branding, certifications, timeline..."></textarea></div>
		</div>
		<button type="button" class="sgc-uq-submit" onclick="sgcUQSubmit()">Submit Request to SGC LABS®</button>
	</div>

	<style>
	.sgc-uq-wrap{max-width:760px;font-family:sans-serif;}
	.sgc-uq-h3{margin:18px 0 12px;font-size:16px;color:#1a1a2e;}
	.sgc-uq-pill{border:1px solid #ccc;border-radius:30px;padding:6px 14px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
	.sgc-uq-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:20px;}
	@media(max-width:600px){.sgc-uq-grid{grid-template-columns:1fr;}}
	.sgc-uq-f{display:flex;flex-direction:column;gap:5px;}
	.sgc-uq-f label{font-weight:600;font-size:13px;color:#333;}
	.sgc-uq-f input,.sgc-uq-f select,.sgc-uq-f textarea{border:1px solid #ccc;border-radius:5px;padding:9px 12px;font-size:14px;box-sizing:border-box;width:100%;}
	.sgc-uq-submit{margin-top:18px;background:#1a1a2e;color:#fff;border:none;padding:14px 28px;border-radius:6px;font-size:15px;font-weight:700;cursor:pointer;width:100%;}
	.sgc-uq-submit:hover{background:#27ae60;}
	.sgc-uq-addbtn{background:none;border:1px dashed #bbb;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;color:#555;}
	.sgc-uq-addbtn.solid{background:#1a1a2e;color:#fff;border:none;}
	.sgc-uq-empty{background:#fff9e6;border:1px solid #f0c040;border-radius:6px;padding:14px;font-size:14px;color:#856404;}
	.sgc-uq-row{display:flex;align-items:center;gap:10px;border:1px solid #e8e8e8;border-radius:6px;padding:10px 14px;margin-bottom:8px;background:#fff;}
	.sgc-uq-row img{width:46px;height:46px;object-fit:cover;border-radius:4px;background:#f0f0f0;}
	.sgc-uq-row .nm{flex:1;font-size:14px;font-weight:600;color:#1a1a2e;}
	.sgc-uq-row input.q{width:66px;padding:5px;border:1px solid #ccc;border-radius:4px;text-align:center;}
	.sgc-uq-row .rm{background:none;border:none;color:#e74c3c;font-size:18px;cursor:pointer;}
	</style>

	<script>
	(function(){
		var AJAX='<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		var NONCE='<?php echo esc_js( wp_create_nonce( 'sgc_uq_nonce' ) ); ?>';
		function get(){try{return JSON.parse(localStorage.getItem('sgclabs_quote_cart')||'[]');}catch(e){return[];}}
		function set(c){localStorage.setItem('sgclabs_quote_cart',JSON.stringify(c));render();}
		function render(){
			var c=get(),w=document.getElementById('sgc-uq-cart-items'),em=document.getElementById('sgc-uq-cart-empty');
			if(!w)return;
			if(!c.length){w.innerHTML='';if(em)em.style.display='block';return;}
			if(em)em.style.display='none';
			w.innerHTML=c.map(function(it,i){
				var im=it.img?'<img src="'+it.img+'" alt="">':'<div style="width:46px;height:46px;border-radius:4px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#ccc;">📦</div>';
				return '<div class="sgc-uq-row">'+im+'<div class="nm">'+(it.name||'Product')+'<div style="font-size:12px;color:#888;font-weight:400;">SKU: '+(it.sku||'—')+'</div></div>'+
					'<input class="q" type="number" min="1" value="'+(it.qty||1)+'" onchange="sgcUQQty('+i+',this.value)"><button class="rm" onclick="sgcUQRemove('+i+')" title="Remove">✕</button></div>';
			}).join('');
		}
		window.sgcUQQty=function(i,v){var c=get();if(c[i])c[i].qty=parseInt(v)||1;set(c);};
		window.sgcUQRemove=function(i){var c=get();c.splice(i,1);set(c);};
		window.sgcUQToggleManual=function(){var m=document.getElementById('sgc-uq-manual');m.style.display=m.style.display==='none'?'flex':'none';};
		window.sgcUQAddManual=function(){
			var n=document.getElementById('sgc-uq-mname').value.trim();if(!n)return;
			var q=parseInt(document.getElementById('sgc-uq-mqty').value)||1;
			var c=get();c.push({id:'m_'+Date.now(),name:n,sku:'—',price:'0.00',img:'',qty:q});set(c);
			document.getElementById('sgc-uq-mname').value='';document.getElementById('sgc-uq-mqty').value='1';
		};
		function rtype(){var r=document.querySelector('input[name="sgc_uq_rtype"]:checked');return r?r.value:'quote';}
		function syncMode(){
			var t=rtype(),cart=document.getElementById('sgc-uq-cart-block');
			if(cart)cart.style.display=(t==='contact')?'none':'block';
		}
		Array.prototype.forEach.call(document.querySelectorAll('input[name="sgc_uq_rtype"]'),function(r){r.addEventListener('change',syncMode);});
		function msg(t,ok){var m=document.getElementById('sgc-uq-msg');m.style.display='block';m.style.background=ok?'#efe':'#fee';m.style.color=ok?'#155724':'#721c24';m.textContent=t;}
		window.sgcUQSubmit=function(){
			var t=rtype();
			var name=document.getElementById('uq_name').value.trim();
			var email=document.getElementById('uq_email').value.trim();
			var phone=document.getElementById('uq_phone').value.trim();
			var country=document.getElementById('uq_country').value;
			if(!name||!email||!phone||!country){return msg('❌ Please fill Name, Email, Phone and Country.',false);}
			var cart=get();
			if(t!=='contact'&&!cart.length){return msg('❌ Add at least one product, or switch to General Inquiry.',false);}
			var btn=document.querySelector('.sgc-uq-submit');btn.disabled=true;btn.textContent='Sending...';
			(window.jQuery?jQuery.post:function(u,d,cb){var x=new XMLHttpRequest();x.open('POST',u);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.onload=function(){cb(JSON.parse(x.responseText));};x.send(Object.keys(d).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(d[k]);}).join('&'));})(AJAX,{
				action:'sgc_uq_submit',nonce:NONCE,rtype:t,name:name,email:email,phone:phone,country:country,
				company:document.getElementById('uq_company').value,
				incoterm:document.getElementById('uq_incoterm').value,
				port:document.getElementById('uq_port').value,
				message:document.getElementById('uq_message').value,
				products:JSON.stringify(cart)
			},function(res){
				if(res&&res.success){
					localStorage.removeItem('sgclabs_quote_cart');
					msg('✅ Submitted! Ref: '+res.data.ref+' — we will reply within 24 hours.',true);
					document.getElementById('sgc-uq-form').style.opacity='.6';
					document.getElementById('sgc-uq-form').style.pointerEvents='none';
				}else{
					msg('❌ '+((res&&res.data)||'Error. Please email sales@labsglass.com'),false);
					btn.disabled=false;btn.textContent='Submit Request to SGC LABS®';
				}
			});
		};
		syncMode();render();
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ============================================================
 * 7. SUBMIT HANDLER
 * ============================================================ */
add_action( 'wp_ajax_sgc_uq_submit', 'sgc_uq_handle_submit' );
add_action( 'wp_ajax_nopriv_sgc_uq_submit', 'sgc_uq_handle_submit' );
function sgc_uq_handle_submit() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sgc_uq_nonce' ) ) {
		wp_send_json_error( 'Security check failed. Please refresh and try again.' );
	}
	// Rate limit: 6 / IP / hour
	$ip   = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
	$rk   = 'sgc_uq_rl_' . md5( $ip );
	$cnt  = (int) get_transient( $rk );
	if ( $cnt >= 6 ) { wp_send_json_error( 'Too many requests. Please try later or WhatsApp us.' ); }
	set_transient( $rk, $cnt + 1, HOUR_IN_SECONDS );

	$rtype = sanitize_key( $_POST['rtype'] ?? 'quote' );
	if ( ! in_array( $rtype, [ 'quote', 'b2b', 'contact' ], true ) ) { $rtype = 'quote'; }

	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$country = sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) );
	$company = sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) );
	$incoterm= sanitize_text_field( wp_unslash( $_POST['incoterm'] ?? '' ) );
	$port    = sanitize_text_field( wp_unslash( $_POST['port'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

	if ( ! $name || ! is_email( $email ) || ! $phone || ! $country ) {
		wp_send_json_error( 'Please fill Name, Email, Phone and Country.' );
	}

	// Products
	$raw      = isset( $_POST['products'] ) ? wp_unslash( $_POST['products'] ) : '[]';
	$products = json_decode( $raw, true );
	if ( ! is_array( $products ) ) { $products = []; }
	$clean = [];
	$names = [];
	$qty_total = 0;
	foreach ( $products as $p ) {
		$pn = sanitize_text_field( $p['name'] ?? '' );
		if ( ! $pn ) { continue; }
		$pq = max( 1, (int) ( $p['qty'] ?? 1 ) );
		$clean[] = [
			'id'   => sanitize_text_field( $p['id'] ?? '' ),
			'name' => $pn,
			'sku'  => sanitize_text_field( $p['sku'] ?? '' ),
			'qty'  => $pq,
		];
		$names[]    = $pn . ' ×' . $pq;
		$qty_total += $pq;
	}
	if ( $rtype !== 'contact' && empty( $clean ) ) {
		wp_send_json_error( 'Please add at least one product.' );
	}

	$summary    = $names ? implode( ', ', $names ) : ( $rtype === 'contact' ? 'General Inquiry' : 'Quote Request' );
	$trade_type = in_array( strtolower( $country ), [ 'india', 'in' ], true ) ? 'domestic' : 'export';

	$lead = sgc_uq_insert( [
		'source_type'     => $rtype,
		'trade_type'      => $trade_type,
		'name'            => $name,
		'email'           => $email,
		'phone'           => $phone,
		'company'         => $company,
		'country'         => $country,
		'incoterm'        => $incoterm,
		'port'            => $port,
		'products_json'   => wp_json_encode( $clean ),
		'product_summary' => mb_substr( $summary, 0, 255 ),
		'qty'             => (string) $qty_total,
		'message'         => $message,
		'ip'              => $ip,
		'user_agent'      => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
	] );

	if ( ! $lead ) { wp_send_json_error( 'Could not save your request. Please email sales@labsglass.com' ); }

	// Canonical hooks (back-compat: feed BOTH automation + CRM)
	$payload = [
		'lead_id'    => $lead['id'],
		'ref'        => $lead['lead_number'],
		'name'       => $name,
		'email'      => $email,
		'phone'      => $phone,
		'company'    => $company,
		'country'    => $country,
		'product'    => $summary,
		'qty'        => (string) $qty_total,
		'message'    => $message,
		'query_type' => ucfirst( $rtype ),
		'buyer_type' => $company ? 'Business' : 'Not Specified',
		'source'     => 'unified_quote',
		'timestamp'  => current_time( 'mysql' ),
	];
	do_action( 'sgclabs_inquiry_received', $payload );
	do_action( 'sgclabs_inquiry_submitted', $payload );

	// Admin notification
	$admin = get_option( 'admin_email' );
	wp_mail(
		$admin,
		sprintf( '[SGC LABS] New %s — %s (%s)', ucfirst( $rtype ), $name, $country ),
		"Ref: {$lead['lead_number']}\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\nCompany: {$company}\nCountry: {$country}\nIncoterm: {$incoterm}  Port: {$port}\nProducts: {$summary}\nMessage:\n{$message}"
	);

	wp_send_json_success( [ 'ref' => $lead['lead_number'], 'id' => $lead['id'] ] );
}

/* ============================================================
 * 8. HYBRID  —  Buy Now vs Request Quote (per product)
 * ============================================================ */
/** Resolve a product's sales mode: buy_now | quote | both (default from global option). */
function sgc_uq_product_mode( $product ) {
	$pid = is_object( $product ) ? $product->get_id() : (int) $product;
	$m   = get_post_meta( $pid, '_sgc_sales_mode', true );
	if ( in_array( $m, [ 'buy_now', 'quote', 'both' ], true ) ) { return $m; }
	// Default 'quote' preserves the current quote-only catalog exactly on rollout.
	// Switch a product (or this global option) to 'buy_now' / 'both' to enable real checkout.
	return get_option( 'sgc_uq_default_mode', 'quote' );
}

/** Disable WooCommerce purchase only for pure "quote" products. */
add_filter( 'woocommerce_is_purchasable', function ( $purchasable, $product ) {
	return ( sgc_uq_product_mode( $product ) === 'quote' ) ? false : $purchasable;
}, 20, 2 );

/** Show "Price on Request" only for pure "quote" products. */
add_filter( 'woocommerce_get_price_html', function ( $price, $product ) {
	if ( sgc_uq_product_mode( $product ) === 'quote' ) {
		return '<span class="sgc-uq-por">Price on Request</span>';
	}
	return $price;
}, 20, 2 );

/** Simple product-edit control to set the mode. */
add_action( 'woocommerce_product_options_general_product_data', function () {
	echo '<div class="options_group">';
	woocommerce_wp_select( [
		'id'      => '_sgc_sales_mode',
		'label'   => 'SGC Sales Mode',
		'options' => [
			''        => 'Default (site setting)',
			'buy_now' => 'Buy Now only (real checkout)',
			'quote'   => 'Request Quote only (hide price/cart)',
			'both'    => 'Both — Buy Now + Add to Quote',
		],
		'desc_tip' => true,
		'description' => 'How customers purchase this product.',
	] );
	echo '</div>';
} );
add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
	$v = isset( $_POST['_sgc_sales_mode'] ) ? sanitize_key( $_POST['_sgc_sales_mode'] ) : '';
	update_post_meta( $post_id, '_sgc_sales_mode', $v );
} );

/* ============================================================
 * 9. ADMIN  —  All Leads dashboard
 * ============================================================ */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'sgclabs-manager',                 // parent (existing SGC menu)
		'Quote Leads',
		'📥 Quote Leads',
		'manage_woocommerce',
		'sgc-uq-leads',
		'sgc_uq_admin_page'
	);
}, 20 );

function sgc_uq_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	global $wpdb;
	$table  = sgc_uq_table();
	$status = isset( $_GET['st'] ) ? sanitize_key( $_GET['st'] ) : '';
	$where  = $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
	$rows   = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore
	$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	echo '<div class="wrap"><h1>Quote Leads <span style="font-size:13px;color:#666;">(' . esc_html( $total ) . ' total — unified)</span></h1>';
	echo '<p><a href="?page=sgc-uq-leads" class="button">All</a> ';
	foreach ( [ 'new'=>'New','quoted'=>'Quoted','won'=>'Won','lost'=>'Lost' ] as $k=>$lab ) {
		echo '<a href="?page=sgc-uq-leads&st=' . esc_attr( $k ) . '" class="button">' . esc_html( $lab ) . '</a> ';
	}
	echo '</p><table class="widefat striped"><thead><tr><th>Ref</th><th>Date</th><th>Type</th><th>Name</th><th>Country</th><th>Products</th><th>Email</th><th>Status</th></tr></thead><tbody>';
	if ( $rows ) {
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( $r['lead_number'] ) . '</td>';
			echo '<td>' . esc_html( mysql2date( 'd M Y', $r['created_at'] ) ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $r['source_type'] ) ) . '</td>';
			echo '<td>' . esc_html( $r['name'] ) . '<br><small>' . esc_html( $r['company'] ) . '</small></td>';
			echo '<td>' . esc_html( $r['country'] ) . '</td>';
			echo '<td>' . esc_html( $r['product_summary'] ) . '</td>';
			echo '<td><a href="mailto:' . esc_attr( $r['email'] ) . '">' . esc_html( $r['email'] ) . '</a><br><small>' . esc_html( $r['phone'] ) . '</small></td>';
			echo '<td>' . esc_html( ucfirst( $r['status'] ) ) . '</td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="8">No leads yet.</td></tr>';
	}
	echo '</tbody></table></div>';
}

/* ============================================================
 * 10. TAKE OVER the legacy shortcodes (kill collisions)
 * ============================================================ */
add_action( 'init', function () {
	foreach ( array( 'sgclabs_quote_form', 'sgclabs_b2b_inquiry_form', 'sgc_contact_form', 'sgclabs_inquiry_form' ) as $sc ) {
		remove_shortcode( $sc );
		add_shortcode( $sc, 'sgc_uq_form_shortcode' );
	}
}, 999 );
