<?php defined( 'ABSPATH' ) || exit;
global $wpdb;
$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
?>
<div class="sgc-pm-sql-page">
<div style="display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:start">

<!-- SIDEBAR -->
<div>
	<!-- Quick Templates -->
	<div class="sgc-pm-box" style="margin-bottom:12px">
		<div style="font-size:11px;font-weight:600;text-transform:uppercase;color:#888;letter-spacing:.4px;margin-bottom:10px">Quick Queries</div>
		<?php
		$templates = [
			'All Products'         => "SELECT p.ID, p.post_title AS name, pm.meta_value AS sku\nFROM {$wpdb->prefix}posts p\nJOIN {$wpdb->prefix}postmeta pm ON p.ID=pm.post_id AND pm.meta_key='_sku'\nWHERE p.post_type='product' AND p.post_status='publish'\nLIMIT 100",
			'Products with Price'  => "SELECT p.ID, p.post_title, pm1.meta_value AS regular_price, pm2.meta_value AS sale_price\nFROM {$wpdb->prefix}posts p\nLEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID=pm1.post_id AND pm1.meta_key='_regular_price'\nLEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID=pm2.post_id AND pm2.meta_key='_sale_price'\nWHERE p.post_type='product' AND p.post_status='publish'\nLIMIT 100",
			'Out of Stock'         => "SELECT p.ID, p.post_title, pm.meta_value AS stock_status\nFROM {$wpdb->prefix}posts p\nJOIN {$wpdb->prefix}postmeta pm ON p.ID=pm.post_id AND pm.meta_key='_stock_status'\nWHERE p.post_type='product' AND pm.meta_value='outofstock'",
			'No SKU Products'      => "SELECT p.ID, p.post_title\nFROM {$wpdb->prefix}posts p\nLEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID=pm.post_id AND pm.meta_key='_sku'\nWHERE p.post_type='product' AND (pm.meta_value IS NULL OR pm.meta_value='')",
			'Price Below 100'      => "SELECT p.ID, p.post_title, pm.meta_value AS price\nFROM {$wpdb->prefix}posts p\nJOIN {$wpdb->prefix}postmeta pm ON p.ID=pm.post_id AND pm.meta_key='_regular_price'\nWHERE p.post_type='product' AND CAST(pm.meta_value AS DECIMAL)>0 AND CAST(pm.meta_value AS DECIMAL)<100\nORDER BY CAST(pm.meta_value AS DECIMAL) ASC",
			'Increase All Prices 10%' => "UPDATE {$wpdb->prefix}postmeta\nSET meta_value = ROUND(meta_value * 1.10, 2)\nWHERE meta_key = '_regular_price'\nAND post_id IN (\n  SELECT ID FROM {$wpdb->prefix}posts WHERE post_type='product'\n)",
			'Remove All Sale Prices' => "UPDATE {$wpdb->prefix}postmeta\nSET meta_value = ''\nWHERE meta_key = '_sale_price'\nAND post_id IN (\n  SELECT ID FROM {$wpdb->prefix}posts WHERE post_type='product'\n)",
			'Draft Products'       => "SELECT ID, post_title, post_date\nFROM {$wpdb->prefix}posts\nWHERE post_type='product' AND post_status='draft'\nORDER BY post_date DESC",
			'Products This Month'  => "SELECT ID, post_title, post_date\nFROM {$wpdb->prefix}posts\nWHERE post_type='product'\nAND MONTH(post_date)=MONTH(NOW())\nAND YEAR(post_date)=YEAR(NOW())\nORDER BY post_date DESC",
		];
		foreach ( $templates as $label => $sql ) : ?>
		<div class="pm-tpl-btn" data-sql="<?php echo esc_attr( $sql ); ?>" title="<?php echo esc_attr( $sql ); ?>"><?php echo esc_html( $label ); ?></div>
		<?php endforeach; ?>
	</div>

	<!-- Tables List -->
	<div class="sgc-pm-box">
		<div style="font-size:11px;font-weight:600;text-transform:uppercase;color:#888;letter-spacing:.4px;margin-bottom:10px">DB Tables</div>
		<div style="max-height:200px;overflow-y:auto">
		<?php foreach ( $tables as $t ) : ?>
			<div class="pm-tbl-btn" data-table="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( str_replace( $wpdb->prefix, '', $t ) ); ?></div>
		<?php endforeach; ?>
		</div>
	</div>
</div>

<!-- EDITOR -->
<div>
	<div class="sgc-pm-box" style="margin-bottom:12px">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
			<strong style="font-size:14px">⚡ SQL Editor</strong>
			<div style="display:flex;gap:8px">
				<button class="sgc-pm-btn secondary" id="sql-btn-save">💾 Save Query</button>
				<button class="sgc-pm-btn secondary" id="sql-btn-history">📋 History</button>
				<button class="sgc-pm-btn secondary" id="sql-btn-clear-editor">✕ Clear</button>
				<button class="sgc-pm-btn primary" id="sql-btn-run">▶ Run</button>
			</div>
		</div>
		<textarea id="sql-editor" class="sgc-pm-input" rows="8" style="width:100%;font-family:monospace;font-size:12px;resize:vertical;line-height:1.6" placeholder="SELECT * FROM <?php echo esc_attr( $wpdb->prefix ); ?>posts WHERE post_type='product' LIMIT 10"></textarea>
		<div style="margin-top:6px;font-size:11px;color:#aaa">
			🚫 Blocked: DROP · TRUNCATE · ALTER · CREATE · RENAME &nbsp;|&nbsp; ⚠ UPDATE/DELETE requires confirmation
		</div>
	</div>

	<!-- Results -->
	<div class="sgc-pm-box" id="sql-results-box" style="display:none;padding:0;overflow:hidden">
		<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid #eee">
			<span id="sql-result-label" style="font-size:13px;font-weight:500"></span>
			<div style="display:flex;gap:8px">
				<button class="sgc-pm-btn secondary" id="sql-export-csv" style="display:none">↗ Export CSV</button>
			</div>
		</div>
		<div id="sql-results-wrap" style="overflow-x:auto;max-height:400px;overflow-y:auto">
			<table class="sgc-pm-table" id="sql-results-table"></table>
		</div>
	</div>

	<!-- Confirm panel for UPDATE/DELETE -->
	<div class="sgc-pm-box" id="sql-confirm-box" style="display:none;border:1px solid #f5c6cb;background:#fff5f5">
		<div style="font-size:13px;color:#721c24;margin-bottom:12px" id="sql-confirm-msg"></div>
		<div style="display:flex;gap:10px">
			<button class="sgc-pm-btn secondary" id="sql-confirm-cancel">Cancel</button>
			<button class="sgc-pm-btn" style="background:#c0392b;color:#fff;border-color:#c0392b" id="sql-confirm-run">⚡ Execute Anyway</button>
		</div>
	</div>

	<!-- Saved queries panel -->
	<div class="sgc-pm-box" id="sql-saved-box" style="display:none">
		<div style="display:flex;justify-content:space-between;margin-bottom:10px">
			<strong>Saved Queries</strong>
			<button class="sgc-pm-btn secondary" style="font-size:11px" id="sql-saved-close">✕ Close</button>
		</div>
		<div id="sql-saved-list"></div>
	</div>
</div>
</div>
</div>

<!-- Save Query Modal -->
<div class="sgc-pm-modal" id="modal-save-query" style="display:none">
	<div class="sgc-pm-modal-inner" style="max-width:400px">
		<div class="sgc-pm-modal-hdr"><h3>💾 Save Query</h3><button class="pm-modal-close" data-modal="modal-save-query">✕</button></div>
		<div class="sgc-pm-modal-body">
			<div class="sgc-pm-fg">
				<label>Query Name</label>
				<input type="text" id="save-query-name" class="sgc-pm-input" placeholder="e.g. Out of stock products" style="width:100%">
			</div>
		</div>
		<div class="sgc-pm-modal-ftr">
			<button class="sgc-pm-btn secondary pm-modal-close" data-modal="modal-save-query">Cancel</button>
			<button class="sgc-pm-btn primary" id="btn-save-query-confirm">Save</button>
		</div>
	</div>
</div>
