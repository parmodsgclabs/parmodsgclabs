<?php defined( 'ABSPATH' ) || exit; ?>
<!-- IMPORT TAB -->
<div class="sgc-pm-box">
	<h3 style="margin:0 0 6px;font-size:14px">📥 Import Products — CSV / Excel</h3>
	<p style="font-size:12px;color:#888;margin:0 0 16px">Supports CSV and Excel (.xlsx/.xls). Columns auto-detected. Duplicate products updated by SKU.</p>

	<div id="import-upload-zone" style="border:2px dashed var(--color-border-secondary,#ccc);border-radius:8px;padding:32px;text-align:center;margin-bottom:16px;cursor:pointer">
		<div style="font-size:32px;margin-bottom:8px">📂</div>
		<div style="font-size:15px;font-weight:500;margin-bottom:4px">Drop your CSV/Excel file here</div>
		<div style="font-size:12px;color:#888;margin-bottom:12px">or click to browse</div>
		<input type="file" id="import-file-input" accept=".csv,.xlsx,.xls" style="display:none">
		<button class="sgc-pm-btn secondary" onclick="document.getElementById('import-file-input').click()">Browse File</button>
	</div>

	<!-- Column Mapping (shown after upload) -->
	<div id="import-mapping" style="display:none">
		<h4 style="font-size:13px;margin:0 0 10px">Column Mapping</h4>
		<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px" id="import-mapping-grid"></div>
		<div class="sgc-pm-fg">
			<label>Import Mode</label>
			<select id="import-mode" class="sgc-pm-select" style="width:100%">
				<option value="upsert">Insert new + Update existing (by SKU)</option>
				<option value="insert">Insert new only (skip existing)</option>
				<option value="update_only">Update existing only (skip new)</option>
			</select>
		</div>
		<div style="background:#EAF3DE;border-radius:5px;padding:10px;font-size:12px;margin-bottom:14px" id="import-preview-info"></div>
		<button class="sgc-pm-btn primary" id="btn-import-run">📥 Start Import</button>
	</div>

	<!-- Progress -->
	<div id="import-progress" style="display:none;margin-top:16px">
		<div style="background:#e0e0e0;border-radius:4px;height:10px;overflow:hidden;margin-bottom:8px"><div id="import-bar" style="height:100%;width:0%;background:#1a1a2e;border-radius:4px;transition:width .4s"></div></div>
		<div id="import-status" style="font-size:12px;color:#555">Processing…</div>
	</div>

	<div id="import-result" class="sgc-pm-notice" style="display:none;margin-top:12px"></div>
</div>

<script>
jQuery(function($){
	var jobId='';
	var $zone=$('#import-upload-zone');
	$zone.on('dragover dragenter',function(e){e.preventDefault();$zone.css('border-color','#1a1a2e');});
	$zone.on('dragleave',function(){$zone.css('border-color','');});
	$zone.on('drop',function(e){e.preventDefault();$zone.css('border-color','');var f=e.originalEvent.dataTransfer.files[0];if(f)uploadFile(f);});
	$('#import-file-input').on('change',function(){if(this.files[0])uploadFile(this.files[0]);this.value='';});

	function uploadFile(file){
		var fd=new FormData(); fd.append('action','sgc_pm_import_preview'); fd.append('nonce',SGCPM.nonce); fd.append('import_file',file);
		$zone.html('<div style="padding:16px;color:#888;font-size:13px">Parsing file…</div>');
		$.ajax({url:SGCPM.ajaxUrl,type:'POST',data:fd,processData:false,contentType:false,success:function(r){
			if(!r.success){alert(r.data);location.reload();return;}
			jobId=r.data.job_id;
			var cols=r.data.columns;
			var fields=['name','sku','regular_price','sale_price','stock_quantity','status','description','short_description','category'];
			var grid='';
			fields.forEach(function(f){
				var match=cols.find(function(c){return c.toLowerCase().replace(/[_ ]/g,'')==f.replace(/_/g,'');});
				grid+='<div><label style="font-size:11px;color:#888;display:block;margin-bottom:3px">'+f+'</label><select class="sgc-pm-select mapping-sel" data-field="'+f+'" style="width:100%"><option value="">— skip —</option>';
				cols.forEach(function(c){grid+='<option value="'+c+'"'+(match===c?' selected':'')+'>'+c+'</option>';});
				grid+='</select></div>';
			});
			$('#import-mapping-grid').html(grid);
			$('#import-preview-info').text('File parsed: '+r.data.total+' rows. Preview: '+JSON.stringify(r.data.preview[0]||{}).substring(0,100)+'…');
			$('#import-mapping').show();
			$zone.hide();
		},error:function(){alert('Upload error.');}});
	}

	$('#btn-import-run').on('click',function(){
		var mapping={};
		$('.mapping-sel').each(function(){ var f=$(this).data('field'); var v=$(this).val(); if(v) mapping[f]=v; });
		var $b=$(this).prop('disabled',true).text('Importing…');
		$('#import-progress').show(); $('#import-bar').css('width','10%'); $('#import-status').text('Processing…');
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_import_run',nonce:SGCPM.nonce,job_id:jobId,mapping:mapping,mode:$('#import-mode').val()},function(r){
			$b.prop('disabled',false).text('Start Import'); $('#import-bar').css('width','100%');
			var msg=r.success?'Done: '+r.data.done+' imported, '+r.data.skipped+' skipped, '+r.data.failed+' failed':'Error: '+r.data;
			$('#import-status').text(msg);
			$('#import-result').text(msg).removeClass('success error').addClass(r.success?'success':'error').show();
		}).fail(function(){$b.prop('disabled',false);$('#import-progress').hide();alert('Server error.');});
	});
});
</script>
