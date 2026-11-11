<?php
/**
 * SGC Smart CRM Pro — Products Tab
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$categories = class_exists( 'WooCommerce' ) ? SGC_CRM_Product_Fetch::get_categories() : array();
?>
<div class="sgc-crm-products-page">

	<div id="products-notice" class="sgc-crm-notice" style="display:none"></div>

	<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
		<div class="sgc-crm-notice error" style="display:block">
			<?php esc_html_e( 'WooCommerce is not active. Activate WooCommerce to use the Products tab.', 'sgc-smart-crm' ); ?>
		</div>
	<?php else : ?>

	<div class="sgc-crm-box">
		<h3><?php esc_html_e( '🛍️ Build Product Promo Campaign', 'sgc-smart-crm' ); ?></h3>
		<p style="color:#666;margin-top:0"><?php esc_html_e( 'Select products → AI generates a full promo email campaign.', 'sgc-smart-crm' ); ?></p>

		<!-- Filters -->
		<div class="sgc-crm-toolbar" style="margin-bottom:16px">
			<div class="sgc-crm-toolbar-left">
				<select id="product-cat-filter" class="sgc-crm-select">
					<option value=""><?php esc_html_e( 'All Categories', 'sgc-smart-crm' ); ?></option>
					<?php foreach ( $categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?> (<?php echo (int) $cat->count; ?>)</option>
					<?php endforeach; ?>
				</select>
				<select id="product-limit" class="sgc-crm-select">
					<option value="6">6 <?php esc_html_e( 'Products', 'sgc-smart-crm' ); ?></option>
					<option value="12">12 <?php esc_html_e( 'Products', 'sgc-smart-crm' ); ?></option>
					<option value="24">24 <?php esc_html_e( 'Products', 'sgc-smart-crm' ); ?></option>
				</select>
				<button class="sgc-crm-btn secondary" id="btn-load-products">🔄 <?php esc_html_e( 'Load Products', 'sgc-smart-crm' ); ?></button>
			</div>
			<div class="sgc-crm-toolbar-right">
				<select id="promo-tone" class="sgc-crm-select">
					<option value="professional"><?php esc_html_e( 'Professional', 'sgc-smart-crm' ); ?></option>
					<option value="friendly"><?php esc_html_e( 'Friendly', 'sgc-smart-crm' ); ?></option>
					<option value="urgent"><?php esc_html_e( 'Promotional', 'sgc-smart-crm' ); ?></option>
				</select>
				<select id="promo-segment" class="sgc-crm-select">
					<option value="all"><?php esc_html_e( 'All Contacts', 'sgc-smart-crm' ); ?></option>
					<option value="international"><?php esc_html_e( 'International', 'sgc-smart-crm' ); ?></option>
					<option value="active_buyer"><?php esc_html_e( 'Active Buyers', 'sgc-smart-crm' ); ?></option>
					<option value="repeat_buyer"><?php esc_html_e( 'Repeat Buyers', 'sgc-smart-crm' ); ?></option>
				</select>
				<button class="sgc-crm-btn primary" id="btn-build-promo" disabled>🤖 <?php esc_html_e( 'AI Build Promo', 'sgc-smart-crm' ); ?></button>
			</div>
		</div>

		<!-- Selection counter -->
		<div id="selection-bar" style="display:none;background:#f0f7ff;padding:10px 14px;border-radius:6px;margin-bottom:12px;display:flex;align-items:center;gap:12px">
			<strong id="selected-count">0 <?php esc_html_e( 'selected', 'sgc-smart-crm' ); ?></strong>
			<button class="sgc-crm-btn secondary" id="btn-select-all" style="padding:4px 10px;font-size:12px"><?php esc_html_e( 'Select All', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn secondary" id="btn-clear-sel" style="padding:4px 10px;font-size:12px"><?php esc_html_e( 'Clear', 'sgc-smart-crm' ); ?></button>
		</div>

		<!-- Product grid -->
		<div id="products-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px">
			<div style="grid-column:1/-1;text-align:center;padding:32px;color:#888">
				<?php esc_html_e( 'Click "Load Products" to browse your WooCommerce catalogue.', 'sgc-smart-crm' ); ?>
			</div>
		</div>
	</div>

	<!-- Generated promo preview -->
	<div class="sgc-crm-box" id="promo-preview-box" style="display:none">
		<h3>✨ <?php esc_html_e( 'AI Generated Promo', 'sgc-smart-crm' ); ?></h3>
		<div class="sgc-crm-form-row">
			<label><?php esc_html_e( 'Subject:', 'sgc-smart-crm' ); ?></label>
			<div id="promo-subject-out" class="sgc-crm-preview-subject"></div>
		</div>
		<div class="sgc-crm-form-row">
			<label><?php esc_html_e( 'Preview:', 'sgc-smart-crm' ); ?></label>
			<iframe id="promo-body-out" style="width:100%;height:450px;border:1px solid #ddd;border-radius:4px"></iframe>
		</div>
		<div style="display:flex;gap:10px;margin-top:12px">
			<button class="sgc-crm-btn secondary" id="btn-regen-promo">🔄 <?php esc_html_e( 'Regenerate', 'sgc-smart-crm' ); ?></button>
			<button class="sgc-crm-btn primary" id="btn-use-promo">📨 <?php esc_html_e( 'Create Campaign →', 'sgc-smart-crm' ); ?></button>
		</div>
	</div>

	<?php endif; ?>
</div>

<script>
jQuery(function($){
	var selectedIds=[], promoData=null;
	function showNotice(m,t){ var $n=$('#products-notice'); $n.text(m).removeClass('success error').addClass(t||'success').show(); setTimeout(function(){ $n.fadeOut(); },4000); }

	$('#btn-load-products').on('click',function(){
		var $g=$('#products-grid').html('<div style="grid-column:1/-1;text-align:center;padding:24px">⏳ <?php esc_html_e("Loading…","sgc-smart-crm"); ?></div>');
		$.post(ajaxurl,{action:'sgc_crm_get_products',nonce:SGC_CRM.nonce,limit:$('#product-limit').val(),category:$('#product-cat-filter').val()},function(r){
			if(!r.success||!r.data||!r.data.length){ $g.html('<div style="grid-column:1/-1;text-align:center;padding:24px;color:#888"><?php esc_html_e("No products found.","sgc-smart-crm"); ?></div>'); return; }
			var html='';
			r.data.forEach(function(p){
				var img = p.image ? '<img src="'+p.image+'" style="width:100%;height:120px;object-fit:contain;border-radius:4px;background:#f9f9f9">' : '<div style="height:120px;background:#f0f0f0;border-radius:4px"></div>';
				html+='<div class="sgc-product-card" data-id="'+p.id+'" data-name="'+$('<div>').text(p.name).html()+'">'
					+img
					+'<div class="sgc-product-info"><div class="sgc-product-name">'+$('<div>').text(p.name).html()+'</div>'
					+(p.price_raw>0?'<div class="sgc-product-price">'+p.price+'</div>':'')
					+(p.sku?'<div style="font-size:10px;color:#aaa">'+p.sku+'</div>':'')
					+'</div><div class="sgc-product-check">✓</div></div>';
			});
			$g.html(html);
			selectedIds=[]; updateBuildBtn();
			$('#selection-bar').css('display','flex');
		});
	});

	// Product selection
	$(document).on('click','.sgc-product-card',function(){
		var id=$(this).data('id'), idx=selectedIds.indexOf(id);
		if(idx>-1){ selectedIds.splice(idx,1); $(this).removeClass('selected'); }
		else { selectedIds.push(id); $(this).addClass('selected'); }
		updateBuildBtn();
	});

	$('#btn-select-all').on('click',function(){
		selectedIds=[]; $('.sgc-product-card').each(function(){ selectedIds.push($(this).data('id')); $(this).addClass('selected'); }); updateBuildBtn();
	});
	$('#btn-clear-sel').on('click',function(){
		selectedIds=[]; $('.sgc-product-card').removeClass('selected'); updateBuildBtn();
	});

	function updateBuildBtn(){
		var n=selectedIds.length;
		$('#selected-count').text(n+' <?php esc_html_e("selected","sgc-smart-crm"); ?>');
		$('#btn-build-promo').prop('disabled', n===0);
	}

	function buildPromo(){
		if(!selectedIds.length){ showNotice('<?php esc_html_e("Select at least one product.","sgc-smart-crm"); ?>','error'); return; }
		$('#btn-build-promo').text('⏳ <?php esc_html_e("Generating…","sgc-smart-crm"); ?>').prop('disabled',true);
		$('#promo-preview-box').hide();
		$.post(ajaxurl,{action:'sgc_crm_build_product_promo',nonce:SGC_CRM.nonce,
			product_ids:selectedIds.join(','), tone:$('#promo-tone').val(), segment:$('#promo-segment').val()
		},function(r){
			$('#btn-build-promo').text('🤖 <?php esc_html_e("AI Build Promo","sgc-smart-crm"); ?>').prop('disabled',false);
			if(!r.success){ showNotice(r.data,'error'); return; }
			promoData=r.data;
			$('#promo-subject-out').text(r.data.subject);
			var iframe=$('#promo-body-out')[0];
			iframe.contentDocument.open(); iframe.contentDocument.write(r.data.body_html||''); iframe.contentDocument.close();
			$('#promo-preview-box').show();
		}).fail(function(){ $('#btn-build-promo').prop('disabled',false).text('🤖 AI Build Promo'); });
	}

	$('#btn-build-promo,#btn-regen-promo').on('click', buildPromo);

	$('#btn-use-promo').on('click',function(){
		if(!promoData) return;
		// Pass to campaign tab via URL with sessionStorage
		sessionStorage.setItem('sgc_crm_promo', JSON.stringify(promoData));
		window.location = '<?php echo esc_url( admin_url( 'admin.php?page=sgc-smart-crm&tab=campaigns&from=promo' ) ); ?>';
	});
});
</script>
