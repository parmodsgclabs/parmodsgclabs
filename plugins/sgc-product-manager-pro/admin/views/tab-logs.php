<?php defined( 'ABSPATH' ) || exit; ?>
<!-- LOGS TAB -->
<div class="sgc-pm-box" id="pm-logs-box" style="padding:0;overflow:hidden">
	<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eee">
		<strong style="font-size:13px">📋 Activity Log</strong>
		<div style="display:flex;gap:8px;align-items:center">
			<select id="log-filter" class="sgc-pm-select" style="font-size:12px">
				<option value="">All Actions</option>
				<option value="ai_">AI Tasks</option>
				<option value="automation">Automations</option>
				<option value="competitor">Competitor Scans</option>
				<option value="import">Imports</option>
				<option value="bulk_">Bulk Operations</option>
			</select>
			<button class="sgc-pm-btn secondary" id="btn-clear-logs" style="font-size:11px">Clear Old Logs</button>
		</div>
	</div>
	<div style="overflow-x:auto">
	<table class="sgc-pm-table">
		<thead><tr><th>Action</th><th>Object</th><th>Details</th><th>User</th><th>Time</th></tr></thead>
		<tbody id="logs-tbody"><tr><td colspan="5" style="text-align:center;padding:32px;color:#888">Loading logs…</td></tr></tbody>
	</table>
	</div>
	<div style="display:flex;align-items:center;gap:8px;padding:10px 16px;border-top:1px solid #eee" id="logs-pagination"></div>
</div>
<script>
jQuery(function($){
	var currentPage=1;
	function loadLogs(page){
		currentPage=page||1;
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_get_logs',nonce:SGCPM.nonce,page:currentPage,filter:$('#log-filter').val()},function(r){
			if(!r.success)return;
			var icons={'ai_generate':'🤖','ai_bulk_seo':'🤖','bulk_delete':'🗑','import':'📥','competitor_scan':'🔍','automation_run':'⚡','competitor_apply':'✅'};
			var html='';
			r.data.logs.forEach(function(l){
				var icon=Object.keys(icons).find(function(k){return l.action.indexOf(k)>=0;}); icon=icon?icons[icon]:'📋';
				html+='<tr><td>'+icon+' '+l.action+'</td><td style="font-size:11px;color:#888">'+l.object_type+(l.object_id?' #'+l.object_id:'')+'</td><td style="font-size:11px;max-width:300px">'+l.details+'</td><td style="font-size:11px;color:#aaa">'+l.user_id+'</td><td style="font-size:11px;white-space:nowrap;color:#aaa">'+l.created_at+'</td></tr>';
			});
			$('#logs-tbody').html(html||'<tr><td colspan="5" style="text-align:center;padding:32px;color:#888">No logs found.</td></tr>');
			var pg='<span style="font-size:12px;color:#888">Page '+currentPage+' of '+r.data.pages+' ('+r.data.total+' total)</span>';
			if(currentPage>1) pg='<button class="sgc-pm-btn secondary" style="font-size:11px" onclick="window.loadLogs('+(currentPage-1)+')">‹ Prev</button>'+pg;
			if(currentPage<r.data.pages) pg+=('<button class="sgc-pm-btn secondary" style="font-size:11px" onclick="window.loadLogs('+(currentPage+1)+')">Next ›</button>');
			$('#logs-pagination').html(pg);
		});
	}
	window.loadLogs=loadLogs;
	loadLogs(1);
	$('#log-filter').on('change',function(){loadLogs(1);});
	$('#btn-clear-logs').on('click',function(){
		var days=prompt('Clear logs older than how many days?','30');
		if(!days) return;
		$.post(SGCPM.ajaxUrl,{action:'sgc_pm_clear_logs',nonce:SGCPM.nonce,days:days},function(r){alert(r.success?r.data:'Error.');loadLogs(1);});
	});
});
</script>
