<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sgc-pm-b2b-page">
<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start">

<!-- Settings Panel -->
<div class="sgc-pm-box">
	<h3 style="margin:0 0 14px;font-size:14px">⚙️ B2B Settings</h3>
	<div class="sgc-pm-fg"><label>Company Name</label>
		<input type="text" id="b2b-company" class="sgc-pm-input" value="<?php echo esc_attr( get_option('sgc_pm_company_name','SGC LABS® Scientific Glassware') ); ?>" style="width:100%">
	</div>
	<div class="sgc-pm-fg"><label>USD Rate (1 USD = ? INR)</label>
		<input type="number" id="b2b-usd-rate" class="sgc-pm-input" value="<?php echo esc_attr( get_option('sgc_pm_usd_rate','83.5') ); ?>" step="0.1" min="1" style="width:100%">
	</div>
	<div class="sgc-pm-fg"><label>Dealer Discount (%)</label>
		<input type="number" id="b2b-dealer-disc" class="sgc-pm-input" value="<?php echo esc_attr( get_option('sgc_pm_dealer_discount','20') ); ?>" min="0" max="90" style="width:100%">
		<small style="color:#888">Applied on Regular Price for dealer column</small>
	</div>
	<div class="sgc-pm-fg"><label>Default MOQ</label>
		<input type="number" id="b2b-moq" class="sgc-pm-input" value="<?php echo esc_attr( get_option('sgc_pm_default_moq','10') ); ?>" min="1" style="width:100%">
	</div>
	<button class="sgc-pm-btn primary" id="btn-save-b2b-settings" style="width:100%">💾 Save Settings</button>
	<div id="b2b-settings-notice" style="display:none;margin-top:8px;font-size:12px;color:green"></div>
</div>

<!-- Export Panel -->
<div>
	<div class="sgc-pm-box" style="margin-bottom:12px">
		<h3 style="margin:0 0 4px;font-size:14px">📄 B2B Price List Generator</h3>
		<p style="font-size:12px;color:#888;margin:0 0 16px">Select products from the Products tab first, or export all published products.</p>
		<div style="display:flex;gap:10px;flex-wrap:wrap">
			<button class="sgc-pm-btn primary" id="btn-b2b-csv">↗ Export CSV</button>
			<button class="sgc-pm-btn secondary" id="btn-b2b-html">🖨 HTML Preview / Print</button>
		</div>
		<div id="b2b-notice" style="display:none;margin-top:10px;font-size:13px"></div>
	</div>

	<!-- HTML Preview Area -->
	<div class="sgc-pm-box" id="b2b-preview-box" style="display:none;padding:0;overflow:hidden">
		<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid #eee">
			<strong>Price List Preview</strong>
			<div style="display:flex;gap:8px">
				<button class="sgc-pm-btn primary" onclick="window.frames['b2b-iframe'].print()">🖨 Print / Save PDF</button>
				<button class="sgc-pm-btn secondary" id="b2b-preview-close">✕ Close</button>
			</div>
		</div>
		<iframe id="b2b-iframe" style="width:100%;height:500px;border:none;padding:16px;box-sizing:border-box"></iframe>
	</div>
</div>

</div>
</div>
