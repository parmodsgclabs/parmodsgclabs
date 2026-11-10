/* SGC Product Manager Pro v2.0.0 — Main JS */
/* globals: SGCPM, jQuery */

(function($){
'use strict';

// ── Globals ──────────────────────────────────────────────────────────────
window.sgcPMSelectedIds = new Set();

// ── Modal helpers ─────────────────────────────────────────────────────────
window.openModal  = function(id){ $('#'+id).addClass('show').css('display','flex'); };
window.closeModal = function(id){ $('#'+id).removeClass('show').css('display','none'); };

$(document).on('click','.pm-modal-close',function(){ closeModal($(this).data('modal')); });
$(document).on('keydown',function(e){ if(e.key==='Escape') $('.sgc-pm-modal.show').each(function(){ closeModal($(this).attr('id')); }); });

// ── Global notice flash ───────────────────────────────────────────────────
window.sgcNotice = function(msg, type){
	var $n=$('#sgc-pm-global-notice');
	$n.attr('class','sgc-pm-notice sgc-pm-notice-'+(type||'success')).text(msg).show();
	setTimeout(function(){ $n.fadeOut(); }, 3500);
};

// ── Master toggle (header) ────────────────────────────────────────────────
$(document).on('change','.sgc-pm-toggle input[data-option]',function(){
	var opt=$(this).data('option'), val=$(this).is(':checked')?'1':'0';
	$.post(SGCPM.ajaxUrl,{action:'sgc_pm_master_toggle',nonce:SGCPM.nonce,option:opt,value:val},function(r){
		sgcNotice(r.success?(opt.replace('sgc_pm_','').replace(/_/g,' ')+' '+(val==='1'?'enabled':'disabled')):'Error',r.success?'success':'error');
	});
});

// ── Dashboard stats ───────────────────────────────────────────────────────
$(document).on('sgcpm:load_dashboard',function(){
	$.post(SGCPM.ajaxUrl,{action:'sgc_pm_dashboard_stats',nonce:SGCPM.nonce},function(r){
		if(!r.success) return;
		var d=r.data;
		$('#kpi-total').text((d.total||0).toLocaleString());
		$('#kpi-pub').text((d.published||0).toLocaleString());
		$('#kpi-draft').text((d.draft||0).toLocaleString());
		$('#kpi-nostock').text((d.out_of_stock||0).toLocaleString());
		$('#kpi-nosku').text((d.no_sku||0).toLocaleString());
		$('#kpi-noimg').text((d.no_image||0).toLocaleString());
	});
});

// ── Products Tab ──────────────────────────────────────────────────────────
var PM = {
	page       : 1,
	perPage    : 100,
	totalPages : 1,
	filters    : {},
	loading    : false,

	init: function(){
		if(!$('#pm-products-wrap').length) return;
		PM.load();
		PM.bindEvents();
	},

	load: function(){
		if(PM.loading) return;
		PM.loading = true;
		$('#pm-products-tbody').html('<tr><td colspan="9" style="text-align:center;padding:32px;color:#aaa">Loading…</td></tr>');

		$.post(SGCPM.ajaxUrl, $.extend({
			action      : 'sgc_pm_get_products',
			nonce       : SGCPM.nonce,
			page        : PM.page,
			per_page    : PM.perPage,
		}, PM.filters), function(r){
			PM.loading = false;
			if(!r.success){ $('#pm-products-tbody').html('<tr><td colspan="9" style="padding:20px;color:#a00">'+r.data+'</td></tr>'); return; }
			PM.totalPages = r.data.pages || 1;
			PM.renderRows(r.data.products || []);
			PM.updatePager(r.data.total || 0);
			PM.updateBulkCount();
		}).fail(function(){ PM.loading=false; $('#pm-products-tbody').html('<tr><td colspan="9" style="padding:20px;color:#a00">Server error.</td></tr>'); });
	},

	renderRows: function(products){
		if(!products.length){
			$('#pm-products-tbody').html('<tr><td colspan="9" style="text-align:center;padding:40px;color:#aaa">No products found. Adjust filters.</td></tr>');
			return;
		}
		var html='';
		products.forEach(function(p){
			var isSel = window.sgcPMSelectedIds.has(p.id);
			var stockBadge = p.stock_status==='outofstock'
				? '<span class="sgc-pm-badge sgc-pm-badge-red">Out</span>'
				: (p.stock_qty!==null && p.stock_qty<5
					? '<span class="sgc-pm-badge sgc-pm-badge-amber">Low:'+p.stock_qty+'</span>'
					: '<span class="sgc-pm-badge sgc-pm-badge-green">'+(p.stock_qty!==null?p.stock_qty:'✓')+'</span>');
			var stBadge = p.status==='publish'
				? '<span class="sgc-pm-badge sgc-pm-badge-green">Live</span>'
				: '<span class="sgc-pm-badge sgc-pm-badge-gray">'+p.status+'</span>';
			var skuWarn = !p.sku ? '<span title="No SKU" style="color:#EF9F27;margin-right:3px">⚠</span>' : '';
			var health = PM.calcHealth(p);
			var hColor = health>=80?'#27500A':health>=50?'#633806':'#791F1F';
			html+='<tr class="pm-row'+(isSel?' pm-selected-row':'')+(p.stock_status==='outofstock'?' pm-stock-out':'')+(p.stock_qty!==null&&p.stock_qty>0&&p.stock_qty<5?' pm-stock-low':'')+'" data-id="'+p.id+'">'
				+'<td onclick="event.stopPropagation()"><input type="checkbox" class="pm-row-chk" data-id="'+p.id+'"'+(isSel?' checked':'')+' style="width:14px;height:14px"></td>'
				+'<td style="min-width:220px">'+skuWarn+'<a href="'+p.edit_url+'" onclick="event.stopPropagation()" style="color:#185FA5;font-weight:500;font-size:12px">'+p.name+'</a></td>'
				+'<td><span class="pm-editable" data-id="'+p.id+'" data-field="sku">'+(p.sku||'<em style="color:#ccc">—</em>')+'</span></td>'
				+'<td><span class="pm-editable" data-id="'+p.id+'" data-field="regular_price">'+SGCPM.currency+(p.regular_price||'0')+'</span></td>'
				+'<td><span class="pm-editable" data-id="'+p.id+'" data-field="sale_price">'+( p.sale_price ? SGCPM.currency+p.sale_price : '<em style="color:#ccc">—</em>' )+'</span></td>'
				+'<td>'+stockBadge+'</td>'
				+'<td>'+stBadge+'</td>'
				+'<td><span style="font-size:11px;font-weight:600;color:'+hColor+'">'+health+'%</span></td>'
				+'<td style="white-space:nowrap">'
				+'<button class="sgc-pm-btn-sm pm-qe-btn" data-id="'+p.id+'" onclick="event.stopPropagation()" title="Quick Edit" style="background:#0e7c3a;color:#fff;border-color:#27ae60">✏️</button>'
				+'<button class="sgc-pm-btn-sm pm-dup-btn" data-id="'+p.id+'" onclick="event.stopPropagation()" title="Duplicate" style="margin-left:4px">⧉</button>'
				+'</td>'
				+'</tr>';
		});
		$('#pm-products-tbody').html(html);
	},

	calcHealth: function(p){
		var score=0;
		if(p.sku)          score+=25;
		if(p.regular_price)score+=25;
		if(p.has_image)    score+=25;
		if(p.has_desc)     score+=25;
		return score;
	},

	updatePager: function(total){
		$('#pm-total-count').text(total.toLocaleString()+' Products');
		$('#pm-page-info').text('Page '+PM.page+' / '+PM.totalPages);
		$('#pm-prev').prop('disabled', PM.page<=1);
		$('#pm-next').prop('disabled', PM.page>=PM.totalPages);
	},

	updateBulkCount: function(){
		var n = window.sgcPMSelectedIds.size;
		$('#pm-sel-count').text(n+' selected');
		$('#pm-bulk-bar').css('display', n>0 ? 'flex' : 'none');
		$('#ai-bulk-sel-count').text(n+' selected from Products tab');
	},

	bindEvents: function(){
		// Row toggle
		$(document).on('click','#pm-products-tbody .pm-row',function(e){
			if($(e.target).is('input,a,button,.pm-editable')) return;
			var id=parseInt($(this).data('id'));
			if(window.sgcPMSelectedIds.has(id)) window.sgcPMSelectedIds.delete(id);
			else window.sgcPMSelectedIds.add(id);
			$(this).toggleClass('pm-selected-row', window.sgcPMSelectedIds.has(id));
			PM.updateBulkCount();
			// Sync checkbox
			$(this).find('.pm-row-chk').prop('checked', window.sgcPMSelectedIds.has(id));
		});

		// Checkbox
		$(document).on('change','.pm-row-chk',function(e){
			e.stopPropagation();
			var id=parseInt($(this).data('id'));
			$(this).is(':checked') ? window.sgcPMSelectedIds.add(id) : window.sgcPMSelectedIds.delete(id);
			$(this).closest('tr').toggleClass('pm-selected-row', window.sgcPMSelectedIds.has(id));
			PM.updateBulkCount();
		});

		// Select all
		$('#pm-chk-all').on('change',function(){
			var chk=$(this).is(':checked');
			$('.pm-row-chk').prop('checked',chk).each(function(){ var id=parseInt($(this).data('id')); chk?window.sgcPMSelectedIds.add(id):window.sgcPMSelectedIds.delete(id); });
			$('#pm-products-tbody .pm-row').toggleClass('pm-selected-row',chk);
			PM.updateBulkCount();
		});

		// Pagination
		$('#pm-prev').on('click',function(){ if(PM.page>1){PM.page--;PM.load();} });
		$('#pm-next').on('click',function(){ if(PM.page<PM.totalPages){PM.page++;PM.load();} });

		// Filters
		$('#pm-filter-form').on('submit',function(e){
			e.preventDefault();
			PM.page=1;
			PM.filters={
				search      : $('#pm-search').val(),
				category    : $('#pm-cat').val(),
				status      : $('#pm-status').val(),
				stock_status: $('#pm-stock').val(),
				preset      : $('#pm-preset').val(),
			};
			PM.load();
		});

		// Preset filter buttons
		$(document).on('click','.pm-preset-btn',function(){
			$('.pm-preset-btn').removeClass('active');
			$(this).addClass('active');
			$('#pm-preset').val($(this).data('preset'));
			$('#pm-filter-form').trigger('submit');
		});

		// Inline cell edit
		$(document).on('dblclick','.pm-editable',function(e){
			e.stopPropagation();
			var $cell=$(this), id=$cell.data('id'), field=$cell.data('field');
			var cur=$cell.text().replace(/[₹$—]/g,'').trim();
			if($cell.find('input').length) return;
			var $inp=$('<input class="pm-editing">').val(cur);
			$cell.html($inp); $inp.focus().select();

			function save(){
				var v=$inp.val().trim();
				$.post(SGCPM.ajaxUrl,{action:'sgc_pm_inline_save',nonce:SGCPM.nonce,product_id:id,field:field,value:v},function(r){
					if(r.success){
						$cell.text(field.indexOf('price')>=0&&v?SGCPM.currency+v:v||'—');
						sgcNotice('Saved!');
					} else { $cell.text(cur); sgcNotice(r.data,'error'); }
				});
			}
			$inp.on('blur',save).on('keydown',function(e){ if(e.key==='Enter'){e.preventDefault();save();} if(e.key==='Escape'){$cell.text(cur);} });
		});

		// Bulk actions
		$(document).on('click','#pm-bulk-price',function(){ PM.bulkAction('price'); });
		$(document).on('click','#pm-bulk-stock',function(){ PM.bulkAction('stock'); });
		$(document).on('click','#pm-bulk-status',function(){ PM.bulkAction('status'); });
		$(document).on('click','#pm-bulk-sku',function(){ PM.bulkAction('sku'); });
		$(document).on('click','#pm-bulk-export',function(){ PM.bulkAction('export'); });
		$(document).on('click','#pm-bulk-delete',function(){ PM.bulkAction('delete'); });
		$('#pm-clear-sel').on('click',function(){ window.sgcPMSelectedIds.clear(); $('pm-chk-all').prop('checked',false); PM.renderRows(PM.lastProducts||[]); PM.updateBulkCount(); PM.load(); });

		// Duplicate
		$(document).on('click','.pm-dup-btn',function(){
			var id=$(this).data('id');
			$.post(SGCPM.ajaxUrl,{action:'sgc_pm_duplicate_product',nonce:SGCPM.nonce,product_id:id},function(r){
				sgcNotice(r.success?'Duplicated! New product created as Draft.':r.data, r.success?'success':'error');
				if(r.success) PM.load();
			});
		});
	},

	bulkAction: function(type){
		var ids=Array.from(window.sgcPMSelectedIds);
		if(!ids.length){ sgcNotice('Select products first.','error'); return; }
		var data={action:'sgc_pm_bulk_action',nonce:SGCPM.nonce,ids:ids,type:type};

		if(type==='price'){
			var adj=prompt('Price adjustment:\n+10 = increase 10%\n-5 = decrease 5%\n=500 = set to ₹500');
			if(adj===null) return; data.price_value=adj;
		}
		if(type==='stock'){
			var st=prompt('Set stock status:\n1 = In Stock\n0 = Out of Stock\nor enter a number for qty:');
			if(st===null) return; data.stock_value=st;
		}
		if(type==='status'){
			var ns=prompt('New status: publish / draft / private'); if(ns===null) return; data.new_status=ns;
		}
		if(type==='delete'){
			var conf=prompt('Type DELETE to confirm deletion of '+ids.length+' products:');
			if(conf!=='DELETE'){ alert('Cancelled.'); return; }
		}
		if(type==='export'){
			$.post(SGCPM.ajaxUrl,data,function(r){
				if(r.success&&r.data.csv_url) window.open(r.data.csv_url,'_blank');
				else sgcNotice(r.data||'Export ready','success');
			});
			return;
		}

		$.post(SGCPM.ajaxUrl,data,function(r){
			sgcNotice(r.success?r.data.message||'Done!':r.data, r.success?'success':'error');
			if(r.success){ window.sgcPMSelectedIds.clear(); PM.load(); }
		});
	},
};

// ── Init on DOM ready ─────────────────────────────────────────────────────
$(function(){
	PM.init();
	$(document).trigger('sgcpm:load_dashboard');

	// SQL Tab — AI assist button
	$('#pm-sql-ai-btn').on('click',function(){
		var q=$('#pm-sql-question').val();
		if(!q){alert('Enter a question.');return;}
		$(this).prop('disabled',true).text('Thinking…');
		var $b=$(this);
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_ai_sql_assist',nonce:SGCPM.nonce,question:q},function(r){
			$b.prop('disabled',false).text('AI → SQL');
			if(r.success&&r.data.sql){ $('#pm-sql-editor').val(r.data.sql); sgcNotice('SQL generated!'); }
			else sgcNotice(r.data||'Error','error');
		});
	});
});

})(jQuery);

/* ── Category Reassign (from Products Tab bulk bar) ──────────────────── */
$(document).on('click','#pm-bulk-category', function(){
	var n = window.sgcPMSelectedIds.size;
	if(!n){ sgcNotice('Select products first.','error'); return; }
	$('#cat-modal-count').text(n);
	openModal('modal-cat-reassign');
});

$(document).on('click','#btn-do-cat-reassign', function(){
	var ids    = Array.from(window.sgcPMSelectedIds);
	var cat_id = $('#cat-reassign-target').val();
	var append = $('#cat-reassign-append').is(':checked') ? '1' : '0';
	if(!cat_id){ alert('Category select karo.'); return; }

	var $b = $(this).prop('disabled',true).text('Moving…');
	$.post(SGCPM.ajaxUrl,{
		action : 'sgc_pm_bulk_reassign_category',
		nonce  : SGCPM.nonce,
		ids    : ids,
		cat_id : cat_id,
		append : append,
	}, function(r){
		$b.prop('disabled',false).text('✅ Reassign Now');
		closeModal('modal-cat-reassign');
		sgcNotice(r.success ? r.data.message : r.data, r.success ? 'success' : 'error');
		if(r.success){ window.sgcPMSelectedIds.clear(); PM.load(); }
	});
});

/* ── Category Reassign — Products Tab bulk bar ───────────────────────── */
$(document).on('click','#pm-bulk-category', function(){
	var n = window.sgcPMSelectedIds.size;
	if(!n){ sgcNotice('Select products first.','error'); return; }
	$('#cat-modal-count').text(n);
	openModal('modal-cat-reassign');
});

$(document).on('click','#btn-do-cat-reassign', function(){
	var ids    = Array.from(window.sgcPMSelectedIds);
	var cat_id = $('#cat-reassign-target').val();
	var append = $('#cat-reassign-append').is(':checked') ? '1' : '0';
	if(!cat_id){ alert('Category select karo.'); return; }
	var $b = $(this).prop('disabled',true).text('Moving…');
	$.post(SGCPM.ajaxUrl,{
		action:'sgc_pm_bulk_reassign_category',nonce:SGCPM.nonce,
		ids:ids,cat_id:cat_id,append:append
	},function(r){
		$b.prop('disabled',false).text('Reassign Now');
		closeModal('modal-cat-reassign');
		sgcNotice(r.success ? r.data.message : r.data, r.success ? 'success' : 'error');
		if(r.success){ window.sgcPMSelectedIds.clear(); PM.load(); }
	});
});
