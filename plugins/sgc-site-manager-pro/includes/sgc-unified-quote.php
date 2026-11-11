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
 * 5. FRONT-END  —  Quote Cart API + floating cart + Add-to-Quote buttons
 *    v7.5.3: FAB on every front-end page (was hidden on many), moved above the
 *    WhatsApp / floating-quote buttons (it sat on top of them → count invisible),
 *    items keep the product URL, cross-tab count sync, legacy /request-quote/
 *    page hydration (cart list + prefill + submit through the unified handler).
 * ============================================================ */

/** Global JS cart API + floating mini-cart, loaded on every front-end page. */
add_action( 'wp_footer', function () {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return; }
	$quote_url = esc_url( sgc_uq_form_url() );
	$ajax      = esc_url( admin_url( 'admin-ajax.php' ) );
	?>
	<div id="sgc-uq-fab" style="position:fixed;right:20px;bottom:160px;z-index:9999;display:none;">
		<a href="<?php echo $quote_url; ?>" style="display:flex;align-items:center;gap:8px;background:#1a1a2e;color:#fff;text-decoration:none;padding:12px 18px;border-radius:30px;box-shadow:0 4px 14px rgba(0,0,0,.25);font-weight:700;font-size:14px;">
			🛒 Quote Cart <span id="sgc-uq-fab-count" style="background:#27ae60;border-radius:50%;min-width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;padding:0 6px;">0</span>
		</a>
	</div>
	<script>
	(function(){
		var AJAX=<?php echo wp_json_encode( $ajax ); ?>, QUOTE_URL=<?php echo wp_json_encode( $quote_url ); ?>;
		function get(){try{return JSON.parse(localStorage.getItem('sgclabs_quote_cart')||'[]');}catch(e){return[];}}
		function set(c){localStorage.setItem('sgclabs_quote_cart',JSON.stringify(c));bubble();try{window.dispatchEvent(new CustomEvent('sgc:quotecart',{detail:c}));}catch(e){}}
		function count(c){var n=0;c.forEach(function(i){n+=parseInt(i.qty)||1;});return n;}
		function bubble(){
			var c=get(),n=count(c);
			var fab=document.getElementById('sgc-uq-fab'),ct=document.getElementById('sgc-uq-fab-count');
			if(ct)ct.textContent=n;if(fab)fab.style.display=c.length?'block':'none';
			// any element with class sgc-uq-count (e.g. header menu badge via [sgclabs_quote_cart_link]) shows the count too
			Array.prototype.forEach.call(document.querySelectorAll('.sgc-uq-count'),function(el){el.textContent=n;el.style.display=c.length?'':'none';});
		}
		window.sgcQuoteCart={get:get,set:set,count:function(){return count(get());},url:QUOTE_URL,ajax:AJAX};
		// Public API used by product button, quick-view, compare, wishlist
		window.sgcAddToQuote=function(item){
			if(!item||!item.id){return;}
			var c=get(),found=false;
			c.forEach(function(it){if(String(it.id)===String(item.id)){it.qty=(parseInt(it.qty)||1)+(parseInt(item.qty)||1);if(item.url&&!it.url)it.url=item.url;found=true;}});
			if(!found){c.push({id:item.id,name:item.name||'Product',sku:item.sku||'—',price:item.price||'0.00',img:item.img||'',url:item.url||'',qty:parseInt(item.qty)||1});}
			set(c);
			if(window.sgcUQToast){window.sgcUQToast('✓ Added to Quote Cart ('+count(c)+')');}
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
			var q=1,qi=b.closest('form,.summary,.product')&&b.closest('form,.summary,.product').querySelector('input.qty');
			if(qi&&parseInt(qi.value)>0)q=parseInt(qi.value);
			window.sgcAddToQuote({id:b.dataset.id,name:b.dataset.name,sku:b.dataset.sku,price:b.dataset.price,img:b.dataset.img,url:b.dataset.url,qty:q});
		});
		window.addEventListener('storage',function(e){if(e.key==='sgclabs_quote_cart')bubble();});
		bubble();

		/* ── Legacy page support: a page that still contains the old B2B quote form
		 *    (#sgc-b2b-quote-form, single "product"/"qty" fields, stale nonce) gets the cart
		 *    list injected, the fields prefilled, and its submit routed through the unified
		 *    handler with a fresh nonce — so the old /request-quote/ page keeps working. ── */
		function hydrateLegacy(){
			var form=document.getElementById('sgc-b2b-quote-form');
			if(!form||form.dataset.sgcUqHydrated)return;
			form.dataset.sgcUqHydrated='1';
			// "Who are you?" buyer cards of the old page (its own inline script is usually broken by wpautop)
			var grid=document.querySelector('.sgc-quote-wrap .buyer-grid')||document.querySelector('.buyer-grid');
			if(grid&&!grid.dataset.sgcUq){grid.dataset.sgcUq='1';grid.addEventListener('click',function(e){
				var card=e.target.closest('.bt-card');if(!card)return;
				Array.prototype.forEach.call(grid.querySelectorAll('.bt-card'),function(c){c.classList.remove('active');c.style.borderColor='';c.style.background='';});
				card.classList.add('active');card.style.borderColor='#7c3aed';card.style.background='#f5f0ff';
				var type=card.getAttribute('data-type')||'',notice=card.getAttribute('data-notice')||'',company=card.getAttribute('data-company')||'';
				var h1=document.getElementById('sgc-buyer-type'),h2=document.getElementById('sgc-buyer-type-field');
				if(h1)h1.value=type;if(h2)h2.value=type;
				var nb=document.getElementById('sgc-buyer-notice');if(nb){nb.textContent=notice;nb.classList.add('show');nb.style.display='block';}
				var ci=form.querySelector('[name="company"]');if(ci&&company)ci.placeholder=company;
			});}
			var box=document.createElement('div');box.id='sgc-uq-legacy-cart';box.style.cssText='margin:0 0 16px;';
			form.parentNode.insertBefore(box,form);
			var prod=form.querySelector('[name="product"]'),qty=form.querySelector('[name="qty"]');
			function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
			function render(){
				var c=get();
				if(!c.length){box.innerHTML='';return;}
				var h='<div style="border:1px solid #e0e0e0;border-radius:8px;padding:12px 14px;background:#fff"><div style="font-weight:700;color:#1a1a2e;margin-bottom:8px">📦 Products in your Quote Cart ('+count(c)+')</div>';
				c.forEach(function(it,i){
					h+='<div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-top:1px solid #f0f0f0">'+(it.img?'<img src="'+esc(it.img)+'" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px">':'')+
					   '<div style="flex:1;font-size:14px">'+(it.url?'<a href="'+esc(it.url)+'" target="_blank" rel="noopener" style="font-weight:600">'+esc(it.name)+'</a>':'<strong>'+esc(it.name)+'</strong>')+'<div style="font-size:12px;color:#888">SKU: '+esc(it.sku||'—')+'</div></div>'+
					   '<input type="number" min="1" value="'+(parseInt(it.qty)||1)+'" data-i="'+i+'" class="sgc-uq-lq" style="width:64px;padding:4px;text-align:center"><button type="button" data-i="'+i+'" class="sgc-uq-lr" style="background:none;border:none;color:#e74c3c;font-size:18px;cursor:pointer">✕</button></div>';
				});
				box.innerHTML=h+'</div>';
				if(prod&&(!prod.value||prod.dataset.sgcAuto==='1')){prod.value=c.map(function(it){return it.name+' (SKU '+(it.sku||'—')+') × '+(parseInt(it.qty)||1)+(it.url?' — '+it.url:'');}).join('\n');prod.dataset.sgcAuto='1';}
				if(qty&&(!qty.value||qty.dataset.sgcAuto==='1')){qty.value=count(c);qty.dataset.sgcAuto='1';}
			}
			box.addEventListener('change',function(e){var t=e.target;if(t.classList.contains('sgc-uq-lq')){var c=get();if(c[t.dataset.i]){c[t.dataset.i].qty=parseInt(t.value)||1;set(c);render();}}});
			box.addEventListener('click',function(e){var t=e.target;if(t.classList.contains('sgc-uq-lr')){var c=get();c.splice(parseInt(t.dataset.i),1);set(c);render();}});
			window.addEventListener('sgc:quotecart',render);
			render();
			// Submit through the unified handler (fresh nonce → works on cached pages too)
			function submitViaUnified(e){
				e.preventDefault();e.stopImmediatePropagation();
				var v=function(n){var el=form.querySelector('[name="'+n+'"]:not([type=radio]),[name="'+n+'"]:checked');return el?el.value.trim():'';};
				var name=v('name'),email=v('email'),phone=v('phone'),country=v('country');
				var msgEl=form.querySelector('#sgc-form-msg,.sgc-form-msg')||(function(){var m=document.createElement('div');m.className='sgc-form-msg';m.style.cssText='padding:10px 14px;border-radius:6px;margin:10px 0;font-size:14px;';form.insertBefore(m,form.firstChild);return m;})();
				function msg(t,ok){msgEl.style.display='block';msgEl.style.background=ok?'#efe':'#fee';msgEl.style.color=ok?'#155724':'#721c24';msgEl.textContent=t;}
				if(!name||!email||!phone||!country){return msg('❌ Please fill Name, Email, Phone and Country.',false);}
				var c=get();
				if(!c.length){var pv=v('product');if(pv){c=[{id:'m_'+Date.now(),name:pv,sku:'—',qty:parseInt(v('qty'))||1}];}}
				var btn=form.querySelector('[type=submit],#sgc-submit-btn');if(btn){btn.disabled=true;btn.dataset.t=btn.textContent;btn.textContent='Sending...';}
				var bt=(document.getElementById('sgc-buyer-type-field')||{}).value||(document.getElementById('sgc-buyer-type')||{}).value||v('buyer_type');var qt=v('query_type');
				fetch(AJAX,{method:'POST',credentials:'same-origin',body:new URLSearchParams({action:'sgc_uq_nonce'})}).then(function(r){return r.json();}).then(function(n){
					var body=new URLSearchParams({action:'sgc_uq_submit',nonce:(n&&n.data&&n.data.nonce)||'',rtype:'quote',name:name,email:email,phone:phone,country:country,company:v('company'),incoterm:v('incoterm'),port:v('port'),buyer_type:bt,query_type:qt,message:v('message'),products:JSON.stringify(c)});
					return fetch(AJAX,{method:'POST',credentials:'same-origin',body:body}).then(function(r){return r.json();});
				}).then(function(res){
					if(res&&res.success){localStorage.removeItem('sgclabs_quote_cart');bubble();render();msg('✅ Submitted! Ref: '+res.data.ref+' — we will reply within 24 hours.',true);form.style.opacity='.6';form.style.pointerEvents='none';}
					else{msg('❌ '+((res&&res.data)||'Error. Please email us directly.'),false);if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}}
				}).catch(function(){msg('❌ Network error. Please try again.',false);if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}});
			}
			form.addEventListener('submit',submitViaUnified,true);
			document.addEventListener('click',function(e){var b=e.target.closest('#sgc-b2b-quote-form [type=submit], #sgc-b2b-quote-form #sgc-submit-btn');if(b)submitViaUnified(e);},true);
		}
		if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',hydrateLegacy);}else{hydrateLegacy();}
	})();
	</script>
	<?php
}, 20 );

/** URL of the quote form page (option → page with [sgclabs_quote_form] → auto-created "Quote Cart" page). */
function sgc_uq_form_url() {
	$pid = (int) get_option( 'sgc_uq_form_page_id', 0 );
	if ( $pid && get_post_status( $pid ) === 'publish' ) { return get_permalink( $pid ); }
	$found = sgc_uq_find_form_page();
	if ( $found ) { return get_permalink( $found ); }
	return home_url( '/quote-cart/' );
}

/** Find a published page that contains the unified form shortcode (any of its aliases). */
function sgc_uq_find_form_page(): int {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish' AND (post_content LIKE '%[sgclabs_quote_form%' OR post_content LIKE '%[sgclabs_b2b_inquiry_form%' OR post_content LIKE '%[sgc_contact_form%' OR post_content LIKE '%[sgclabs_inquiry_form%') ORDER BY ID ASC LIMIT 5" ); // phpcs:ignore
	return $ids ? (int) $ids[0] : 0;
}

/** v7.5.3: make sure a real Quote Cart page exists (so the floating cart never points to a page without the cart). */
function sgc_uq_ensure_form_page(): int {
	$pid = (int) get_option( 'sgc_uq_form_page_id', 0 );
	if ( $pid && get_post_status( $pid ) === 'publish' && has_shortcode( (string) get_post_field( 'post_content', $pid ), 'sgclabs_quote_form' ) ) { return $pid; }
	$found = sgc_uq_find_form_page();
	if ( ! $found ) {
		$found = (int) wp_insert_post( [
			'post_title'   => 'Quote Cart',
			'post_name'    => 'quote-cart',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => "<!-- wp:shortcode -->\n[sgclabs_quote_form]\n<!-- /wp:shortcode -->",
			'comment_status' => 'closed',
		] );
	}
	if ( $found > 0 ) { update_option( 'sgc_uq_form_page_id', $found ); }
	return max( 0, $found );
}
add_action( 'admin_init', function () {
	if ( get_option( 'sgc_uq_page_checked_v' ) === '753' ) { return; }
	sgc_uq_ensure_form_page();
	update_option( 'sgc_uq_page_checked_v', '753', false );
}, 30 );

/** Fresh nonce for the quote form (pages are often served from LiteSpeed cache; an embedded nonce goes stale). */
add_action( 'wp_ajax_sgc_uq_nonce', 'sgc_uq_ajax_nonce' );
add_action( 'wp_ajax_nopriv_sgc_uq_nonce', 'sgc_uq_ajax_nonce' );
function sgc_uq_ajax_nonce() {
	nocache_headers();
	wp_send_json_success( [ 'nonce' => wp_create_nonce( 'sgc_uq_nonce' ) ] );
}

/** [sgclabs_quote_cart_link] — header/menu link with live count badge. */
add_shortcode( 'sgclabs_quote_cart_link', function ( $atts ) {
	$atts = shortcode_atts( [ 'text' => '🛒 Quote Cart' ], (array) $atts );
	return '<a class="sgc-uq-cart-link" href="' . esc_url( sgc_uq_form_url() ) . '">' . esc_html( $atts['text'] ) . ' <span class="sgc-uq-count" style="display:none;background:#27ae60;color:#fff;border-radius:50%;min-width:20px;height:20px;line-height:20px;text-align:center;font-size:12px;padding:0 5px;">0</span></a>';
} );

/** Button markup shared by single product + shop loop. */
function sgc_uq_add_button_html( $product, string $extra_class = '', string $style = '' ): string {
	$img = '';
	$iid = $product->get_image_id();
	if ( $iid ) { $src = wp_get_attachment_image_src( $iid, 'thumbnail' ); $img = $src ? $src[0] : ''; }
	return sprintf(
		'<button type="button" class="sgc-add-to-quote button alt %s" data-id="%s" data-name="%s" data-sku="%s" data-price="%s" data-img="%s" data-url="%s" style="%s">📋 Add to Quote</button>',
		esc_attr( $extra_class ),
		esc_attr( $product->get_id() ),
		esc_attr( $product->get_name() ),
		esc_attr( $product->get_sku() ),
		esc_attr( $product->get_price() ),
		esc_attr( $img ),
		esc_url( get_permalink( $product->get_id() ) ),
		esc_attr( $style )
	);
}

/** Inject an "Add to Quote" button on product pages when the product is quote-enabled. */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product ) { return; }
	if ( sgc_uq_product_mode( $product ) === 'buy_now' ) { return; } // pure buy-now: no quote button
	echo sgc_uq_add_button_html( $product, '', 'margin-top:8px;background:#1a1a2e;' ); // phpcs:ignore WordPress.Security.EscapeOutput
}, 15 );

/** v7.5.3: same button in shop / category loops (quote several products quickly). */
add_action( 'woocommerce_after_shop_loop_item', function () {
	global $product;
	if ( ! $product || get_option( 'sgc_uq_loop_button', '1' ) !== '1' ) { return; }
	if ( sgc_uq_product_mode( $product ) === 'buy_now' ) { return; }
	echo sgc_uq_add_button_html( $product, 'sgc-add-to-quote-loop', 'margin-top:6px;background:#1a1a2e;font-size:12px;padding:6px 10px;' ); // phpcs:ignore WordPress.Security.EscapeOutput
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

		<div class="sgc-uq-rtype" style="margin-top:12px;">
			<label style="font-weight:600;font-size:13px;color:#333;">Who are you?</label>
			<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
				<?php foreach ( [ 'Distributor / Dealer' => '🏬', 'Research Lab' => '🔬', 'University / School' => '🎓', 'Hospital / Pharma' => '🏥', 'Industry / QC Lab' => '🏭', 'Glass Blower / OEM' => '💨', 'Individual' => '👤' ] as $bt => $ic ) : ?>
				<label class="sgc-uq-pill"><input type="radio" name="sgc_uq_btype" value="<?php echo esc_attr( $bt ); ?>"> <?php echo $ic . ' ' . esc_html( $bt ); ?></label>
				<?php endforeach; ?>
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
		function set(c){localStorage.setItem('sgclabs_quote_cart',JSON.stringify(c));render();if(window.sgcQuoteCart){window.sgcQuoteCart.set(c);}}
		function render(){
			var c=get(),w=document.getElementById('sgc-uq-cart-items'),em=document.getElementById('sgc-uq-cart-empty');
			if(!w)return;
			if(!c.length){w.innerHTML='';if(em)em.style.display='block';return;}
			if(em)em.style.display='none';
			w.innerHTML=c.map(function(it,i){
				var im=it.img?'<img src="'+it.img+'" alt="">':'<div style="width:46px;height:46px;border-radius:4px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#ccc;">📦</div>';
				var nm=it.url?'<a href="'+it.url+'" target="_blank" rel="noopener" style="color:#1a1a2e;text-decoration:underline">'+(it.name||'Product')+'</a>':(it.name||'Product');
				return '<div class="sgc-uq-row">'+im+'<div class="nm">'+nm+'<div style="font-size:12px;color:#888;font-weight:400;">SKU: '+(it.sku||'—')+'</div></div>'+
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
		function btype(){var r=document.querySelector('input[name="sgc_uq_btype"]:checked');return r?r.value:'';}
		Array.prototype.forEach.call(document.querySelectorAll('.sgc-uq-pill input'),function(r){r.addEventListener('change',function(){Array.prototype.forEach.call(document.querySelectorAll('input[name="'+r.name+'"]'),function(x){x.parentNode.style.background=x.checked?'#1a1a2e':'';x.parentNode.style.color=x.checked?'#fff':'';});});});
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
			// v7.5.3: fetch a FRESH nonce first — the one embedded in the page goes stale under page caching (LiteSpeed)
			var post=function(d,cb){var x=new XMLHttpRequest();x.open('POST',AJAX);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.onload=function(){try{cb(JSON.parse(x.responseText));}catch(e){cb({success:false,data:'Unexpected server response'});}};x.onerror=function(){cb({success:false,data:'Network error'});};x.send(Object.keys(d).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(d[k]);}).join('&'));};
			post({action:'sgc_uq_nonce'},function(nr){ var fresh=(nr&&nr.success&&nr.data&&nr.data.nonce)?nr.data.nonce:NONCE;
			post({
				action:'sgc_uq_submit',nonce:fresh,rtype:t,name:name,email:email,phone:phone,country:country,buyer_type:btype(),query_type:(t==='b2b'?'Bulk / B2B Order':(t==='contact'?'General Inquiry':'Product Quote')),
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
			}); });
		};
		window.addEventListener('sgc:quotecart',render);
		window.addEventListener('storage',function(e){if(e.key==='sgclabs_quote_cart')render();});
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
	$buyer_type = sanitize_text_field( wp_unslash( $_POST['buyer_type'] ?? '' ) );   // v7.5.3: "Who are you?"
	$query_type = sanitize_text_field( wp_unslash( $_POST['query_type'] ?? '' ) );
	if ( ! $buyer_type || $buyer_type === 'Not Specified' ) { $buyer_type = $company ? 'Business' : 'Not Specified'; }
	if ( ! $query_type ) { $query_type = [ 'quote' => 'Product Quote', 'b2b' => 'Bulk / B2B Order', 'contact' => 'General Inquiry' ][ $rtype ] ?? ucfirst( $rtype ); }

	if ( ! $name || ! is_email( $email ) || ! $phone || ! $country ) {
		wp_send_json_error( 'Please fill Name, Email, Phone and Country.' );
	}

	// Products
	$raw      = isset( $_POST['products'] ) ? wp_unslash( $_POST['products'] ) : '[]';
	$products = json_decode( $raw, true );
	if ( ! is_array( $products ) ) { $products = []; }
	$clean = [];
	$names = [];
	$lines = [];
	$qty_total = 0;
	foreach ( $products as $p ) {
		$pn = sanitize_text_field( $p['name'] ?? '' );
		if ( ! $pn ) { continue; }
		$pq = max( 1, (int) ( $p['qty'] ?? 1 ) );
		$url  = esc_url_raw( (string) ( $p['url'] ?? '' ) );
		$sku  = sanitize_text_field( $p['sku'] ?? '' );
		$clean[] = [
			'id'   => sanitize_text_field( $p['id'] ?? '' ),
			'name' => $pn,
			'sku'  => $sku,
			'qty'  => $pq,
			'url'  => $url,
		];
		$names[]    = $pn . ( $sku && $sku !== '—' ? ' [' . $sku . ']' : '' ) . ' ×' . $pq;
		$lines[]    = sprintf( '- %s | SKU: %s | Qty: %d%s', $pn, ( $sku ?: '—' ), $pq, $url ? ' | ' . $url : '' );
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
		'buyer_type'      => $buyer_type,
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
		'products'   => $clean,
		'qty'        => (string) $qty_total,
		'message'    => "Buyer type: {$buyer_type} | Query type: {$query_type}\n\n" . $message . ( $lines ? "\n\nProducts:\n" . implode( "\n", $lines ) : '' ),
		'query_type' => $query_type,
		'buyer_type' => $buyer_type,
		'incoterm'   => $incoterm,
		'port'       => $port,
		'meta'       => [ 'incoterm' => $incoterm, 'port' => $port, 'qty' => (string) $qty_total, 'buyer_type' => $buyer_type, 'qtype' => $query_type ],
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
		"Ref: {$lead['lead_number']}\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\nCompany: {$company}\nCountry: {$country}\nBuyer type: {$buyer_type}\nQuery type: {$query_type}\nIncoterm: {$incoterm}  Port: {$port}\n\nProducts (" . count( $clean ) . " lines, total qty {$qty_total}):\n" . ( $lines ? implode( "\n", $lines ) : $summary ) . "\n\nMessage:\n{$message}\n\nAdmin: " . admin_url( 'admin.php?page=sgc-uq-leads' )
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
	if ( isset( $_POST['sgc_uq_page_save'] ) && check_admin_referer( 'sgc_uq_page_save' ) ) {
		$sel = (int) ( $_POST['sgc_uq_form_page_id'] ?? 0 );
		if ( $sel === -1 ) { delete_option( 'sgc_uq_form_page_id' ); $sel = sgc_uq_ensure_form_page(); }
		elseif ( $sel > 0 ) { update_option( 'sgc_uq_form_page_id', $sel ); }
		update_option( 'sgc_uq_loop_button', isset( $_POST['sgc_uq_loop_button'] ) ? '1' : '0' );
		echo '<div class="notice notice-success"><p>Quote Cart settings saved.</p></div>';
	}
	echo '<div class="wrap"><h1>Quote Leads <span style="font-size:13px;color:#666;">(' . esc_html( $total ) . ' total — unified)</span></h1>';
	$cur = (int) get_option( 'sgc_uq_form_page_id', 0 );
	echo '<form method="post" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px 16px;margin:12px 0;max-width:860px">';
	wp_nonce_field( 'sgc_uq_page_save' );
	echo '<strong>🛒 Quote Cart page</strong> — the floating "Quote Cart" button and <code>[sgclabs_quote_cart_link]</code> open this page. It must contain <code>[sgclabs_quote_form]</code> (the cart with all added products, qty, SKU and links). Current: <a href="' . esc_url( sgc_uq_form_url() ) . '" target="_blank">' . esc_html( sgc_uq_form_url() ) . '</a><br><br>';
	echo '<select name="sgc_uq_form_page_id"><option value="-1">— Auto (create/find a page with the shortcode) —</option>';
	foreach ( get_pages( [ 'post_status' => 'publish', 'number' => 300 ] ) as $pg ) {
		$has = has_shortcode( (string) $pg->post_content, 'sgclabs_quote_form' ) || has_shortcode( (string) $pg->post_content, 'sgclabs_b2b_inquiry_form' );
		echo '<option value="' . (int) $pg->ID . '" ' . selected( $cur, $pg->ID, false ) . '>' . esc_html( $pg->post_title ) . ( $has ? ' ✓ has shortcode' : '' ) . '</option>';
	}
	echo '</select> &nbsp; <label><input type="checkbox" name="sgc_uq_loop_button" value="1" ' . checked( get_option( 'sgc_uq_loop_button', '1' ), '1', false ) . '> Show "Add to Quote" on shop/category lists too</label> &nbsp; <button class="button button-primary" name="sgc_uq_page_save" value="1">Save</button>';
	echo '<p style="color:#666;font-size:12px;margin:8px 0 0">Tip: an old page that still holds the previous B2B form (<code>#sgc-b2b-quote-form</code>) keeps working — the cart list is injected above it and its submit is routed through the unified handler.</p></form>';
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
