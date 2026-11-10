<?php
/**
 * tab-studio.php — Product Studio (merged from SGC Product Studio v1.0.2)
 * Now runs inside SGC Product Manager Pro's tab system.
 *
 * AI status bar reads from PM Pro's unified AI Engine settings.
 */
defined( 'ABSPATH' ) || exit;

$cats   = sgcps_get_categories();
$ai_on  = get_option( 'sgc_pm_ai_enabled', '1' ) === '1';
$ai_prov= ucfirst( get_option( 'sgc_pm_ai_provider', 'deepseek' ) );
?>
<style>
/* ── Studio status strip ─────── */
.sgcps-studio-strip{display:flex;align-items:center;gap:12px;background:#1a1a2e;padding:10px 16px;border-radius:8px;margin-bottom:16px;flex-wrap:wrap}
.sgcps-studio-strip .s-label{font-size:12px;font-weight:600;padding:3px 10px;border-radius:10px}
.s-ai-on{background:#d1fae5;color:#065f46}
.s-ai-off{background:#fee2e2;color:#991b1b}
.sgcps-studio-strip .s-build{font-size:11px;color:#9090aa;margin-left:auto}
</style>

<!-- Studio Status Strip -->
<div class="sgcps-studio-strip">
  <span style="color:#fff;font-size:13px;font-weight:600">🎨 Product Studio</span>
  <span class="s-label <?php echo $ai_on ? 's-ai-on' : 's-ai-off'; ?>">
    <?php echo $ai_on ? '✅ ' . esc_html( $ai_prov ) . ' AI ON' : '❌ AI OFF'; ?>
  </span>
  <span style="color:#9090aa;font-size:12px">Nonce: unified sgc_pm_nonce</span>
  <a href="<?php echo esc_url( admin_url( 'admin.php?page=sgc-product-manager&tab=settings' ) ); ?>" class="sgc-pm-tab" style="padding:4px 12px;font-size:11px;margin:0">⚙ AI Settings</a>
  <span class="s-build">Build <?php echo esc_html( SGC_PM_BUILD ); ?></span>
</div>

<!-- Studio Tabs -->
<div class="sgcps-tabs" id="sgcps-tabs">
    <button class="sgcps-tab active" data-tab="create">+ New Product</button>
    <button class="sgcps-tab" data-tab="image">🖼 Image Manager</button>
    <button class="sgcps-tab" data-tab="edit">✏ Edit Existing</button>
    <button class="sgcps-tab" data-tab="bulk">⚡ Bulk Update</button>
    <button class="sgcps-tab" data-tab="defaults">📋 Sub-Cat Defaults</button>
</div>

<!-- ══ CREATE ══════════════════════════════════════════════════════════ -->
<div id="sgcps-tc-create" class="sgcps-tc active">
<div class="sgcps-layout">
<div class="sgcps-col">
<div class="sgcps-panel">
    <div class="sgcps-ph">
        Product Details
        <span id="sgcps-score-lbl" style="font-size:11px;font-weight:400;color:#888;"></span>
    </div>
    <div class="sgcps-pb">
        <div class="sgcps-fr">
            <label class="sgcps-lbl">Category <span class="sgcps-req">*</span></label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <select id="sgcps-cat">
                    <option value="">— Category —</option>
                    <?php foreach ( $cats as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat['id'] ); ?>"><?php echo esc_html( $cat['name'] ); ?> (<?php echo $cat['count']; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select id="sgcps-sub" disabled>
                    <option value="">— Sub Category —</option>
                </select>
            </div>
        </div>

        <div id="sgcps-cap-wrap" style="display:none" class="sgcps-fr">
            <label class="sgcps-lbl">Capacity / Size <span class="sgcps-req">*</span> <span class="sgcps-pill ok">Generates content</span></label>
            <select id="sgcps-cap"><option value="">— Select Capacity —</option></select>
            <div id="sgcps-cap-chips" style="margin-top:6px;"></div>
        </div>

        <div id="sgcps-def-wrap" style="display:none">
            <label class="sgcps-lbl">Sub-category defaults <span class="sgcps-pill gray">Pre-saved</span></label>
            <div id="sgcps-def-params"></div>
            <hr class="sgcps-hr">
        </div>

        <div id="sgcps-core" style="display:none" class="sgcps-fr">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                <div><label class="sgcps-lbl">SKU</label><input type="text" id="sgcps-sku" placeholder="S4023250"></div>
                <div><label class="sgcps-lbl">Price (INR)</label><input type="number" id="sgcps-price" placeholder="1500"></div>
                <div><label class="sgcps-lbl">Stock</label><input type="number" id="sgcps-stock" placeholder="100"></div>
            </div>
        </div>

        <div id="sgcps-name-wrap" style="display:none" class="sgcps-fr">
            <label class="sgcps-lbl">Product Name <span class="sgcps-pill ok">Auto-filled</span></label>
            <input type="text" id="sgcps-name">
        </div>

        <div id="sgcps-img-wrap" style="display:none" class="sgcps-fr">
            <label class="sgcps-lbl">Images <span style="font-weight:400;color:#888;">(featured + up to 3 gallery)</span></label>
            <div class="sgcps-img-box">
                <div class="sgcps-img-tabs">
                    <button class="sgcps-it active" data-ipanel="up">Upload File</button>
                    <button class="sgcps-it" data-ipanel="url">Paste URL</button>
                </div>
                <div id="sgcps-up" class="sgcps-ip active">
                    <p class="sgcps-notice">Images select karo — Upload karo — Auto rename (SKU-1.jpg) + alt/caption generate</p>
                    <div class="sgcps-img-grid">
                        <?php for ( $i = 0; $i < 4; $i++ ) : $lbl = $i === 0 ? 'Featured Image' : 'Gallery Image ' . ( $i + 1 ); ?>
                        <div class="sgcps-islot">
                            <div class="sgcps-islot-lbl"><?php echo $lbl; ?></div>
                            <input type="file" id="sgcps-fi-<?php echo $i; ?>" accept="image/*">
                            <img id="sgcps-fp-<?php echo $i; ?>" class="sgcps-thumb" src="" style="display:none">
                        </div>
                        <?php endfor; ?>
                    </div>
                    <button class="sgcps-btn ok" id="sgcps-upload-btn" style="margin-top:8px;">Upload All + Assign</button>
                    <span id="sgcps-upload-st" style="font-size:12px;color:#555;margin-left:8px"></span>
                </div>
                <div id="sgcps-url" class="sgcps-ip">
                    <p class="sgcps-notice">URL paste karo → Fetch click karo → Media mein save + product assign + SKU rename</p>
                    <div class="sgcps-img-grid">
                        <?php for ( $i = 0; $i < 4; $i++ ) : $lbl = $i === 0 ? 'Featured Image' : 'Gallery Image ' . ( $i + 1 ); $slot = $i === 0 ? 'featured' : 'gallery-' . ( $i + 1 ); ?>
                        <div class="sgcps-islot">
                            <div class="sgcps-islot-lbl"><?php echo $lbl; ?></div>
                            <input type="url" id="sgcps-ui-<?php echo $i; ?>" placeholder="https://...image.jpg">
                            <button class="sgcps-btn-sm ok sgcps-fetch-btn" data-slot="<?php echo $slot; ?>" data-idx="<?php echo $i; ?>" style="margin-top:4px">Fetch + Assign</button>
                            <img id="sgcps-up-<?php echo $i; ?>" class="sgcps-thumb" src="" style="display:none;margin-top:4px">
                            <span id="sgcps-us-<?php echo $i; ?>" class="sgcps-fstatus"></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <button class="sgcps-btn ok" id="sgcps-create-btn" style="display:none">✅ Create Product</button>
            <span id="sgcps-create-msg" style="font-size:12px;color:#555;align-self:center"></span>
        </div>
    </div>
</div>
</div>
<!-- Right: Generated Content -->
<div class="sgcps-col">
<div class="sgcps-panel">
    <div class="sgcps-ph">
        Generated Content
        <label style="font-size:11px;font-weight:400;float:right;margin:0">
            <input type="checkbox" id="sgcps-ai-tog" <?php checked( $ai_on ); ?>> <?php echo esc_html( $ai_prov ); ?> AI
        </label>
    </div>
    <div class="sgcps-pb">
        <div class="sgcps-sf"><div class="sgcps-sl">SEO Title <span class="sgcps-cc" id="cc-t">0/70</span></div><div class="sgcps-sv" id="g-title" contenteditable="true">Capacity select karo...</div></div>
        <div class="sgcps-sf"><div class="sgcps-sl">Meta Title <span class="sgcps-cc" id="cc-m">0/70</span></div><div class="sgcps-sv" id="g-meta" contenteditable="true">Capacity select karo...</div></div>
        <div class="sgcps-sf"><div class="sgcps-sl">Meta Description <span class="sgcps-cc" id="cc-d">0/160</span></div><div class="sgcps-sv" id="g-desc" contenteditable="true" style="min-height:48px">Capacity select karo...</div></div>
        <div class="sgcps-sf"><div class="sgcps-sl">Short Description</div><div class="sgcps-sv" id="g-short" contenteditable="true" style="min-height:44px">Capacity select karo...</div></div>
        <div class="sgcps-sf"><div class="sgcps-sl">Main Description</div><div class="sgcps-sv" id="g-main" contenteditable="true" style="min-height:80px">Capacity select karo...</div></div>
        <div class="sgcps-sf"><div class="sgcps-sl">Image Alt / Caption <span class="sgcps-pill ok" style="font-size:10px">Auto on image save</span></div><div class="sgcps-sv" id="g-alt" style="font-size:11px;line-height:1.8;color:#777">Image assign hone ke baad...</div></div>
        <div style="margin-top:8px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span style="color:#888">SEO Score</span><span id="sgcps-sc-num" style="font-weight:600">—</span></div>
            <div style="height:5px;background:#eee;border-radius:3px;overflow:hidden"><div id="sgcps-sc-bar" style="height:100%;border-radius:3px;transition:width .4s,background .4s;width:0%"></div></div>
        </div>
    </div>
</div>
</div>
</div>
</div>

<!-- ══ IMAGE MANAGER ══════════════════════════════════════════════════ -->
<div id="sgcps-tc-image" class="sgcps-tc">
<div class="sgcps-panel">
    <div class="sgcps-ph">Image Manager — Any Product</div>
    <div class="sgcps-pb">
        <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
            <input type="text" id="sgcps-img-q" placeholder="Product name ya SKU..." style="flex:1;min-width:180px">
            <button class="sgcps-btn ok" id="sgcps-img-search-btn">Search</button>
            <span id="sgcps-img-count" style="font-size:12px;color:#888;align-self:center"></span>
        </div>
        <div id="sgcps-img-plist"></div>
        <div id="sgcps-img-mgr" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid #eee">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <div><strong id="sgcps-mgr-name" style="font-size:13px"></strong> <span id="sgcps-mgr-sku" class="sgcps-pill gray"></span></div>
                <button class="sgcps-btn-sm" id="sgcps-mgr-close">Close</button>
            </div>
            <div class="sgcps-img-box">
                <div class="sgcps-img-tabs">
                    <button class="sgcps-it active" data-ipanel="mup">Upload File</button>
                    <button class="sgcps-it" data-ipanel="murl">Paste URL</button>
                    <button class="sgcps-it" data-ipanel="massigned">Assigned Images</button>
                </div>
                <div id="sgcps-mup" class="sgcps-ip active">
                    <p class="sgcps-notice">Images select karo — Upload karo — Auto rename (SKU-1.jpg) + alt/caption generate</p>
                    <div class="sgcps-img-grid">
                        <?php for ( $i = 0; $i < 4; $i++ ) : $lbl = $i === 0 ? 'Featured Image' : 'Gallery Image ' . ( $i + 1 ); ?>
                        <div class="sgcps-islot">
                            <div class="sgcps-islot-lbl"><?php echo $lbl; ?></div>
                            <input type="file" id="sgcps-mfi-<?php echo $i; ?>" accept="image/*">
                            <img id="sgcps-mfp-<?php echo $i; ?>" class="sgcps-thumb" src="" style="display:none">
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
                        <button class="sgcps-btn ok" id="sgcps-mgr-upload-btn">Upload + Assign</button>
                        <span id="sgcps-mgr-upload-st" style="font-size:12px;color:#555"></span>
                    </div>
                </div>
                <div id="sgcps-murl" class="sgcps-ip">
                    <p class="sgcps-notice">URL paste karo → Fetch click karo → Media mein save + product assign + SKU rename</p>
                    <div class="sgcps-img-grid">
                        <?php for ( $i = 0; $i < 4; $i++ ) : $lbl = $i === 0 ? 'Featured Image' : 'Gallery Image ' . ( $i + 1 ); $slot = $i === 0 ? 'featured' : 'gallery-' . ( $i + 1 ); ?>
                        <div class="sgcps-islot">
                            <div class="sgcps-islot-lbl"><?php echo $lbl; ?></div>
                            <input type="url" id="sgcps-mui-<?php echo $i; ?>" placeholder="https://...image.jpg">
                            <button class="sgcps-btn-sm ok sgcps-mgr-fetch" data-slot="<?php echo $slot; ?>" data-idx="<?php echo $i; ?>" style="margin-top:4px">Fetch + Assign</button>
                            <img id="sgcps-mup-<?php echo $i; ?>" class="sgcps-thumb" src="" style="display:none;margin-top:4px">
                            <span id="sgcps-mus-<?php echo $i; ?>" class="sgcps-fstatus"></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div id="sgcps-massigned" class="sgcps-ip">
                    <div id="sgcps-mgr-assigned"></div>
                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                        <button class="sgcps-btn-sm" id="sgcps-reload-assigned">Reload Images</button>
                        <button class="sgcps-btn-sm ok" id="sgcps-regen-sizes-btn">Regenerate WooCommerce Sizes</button>
                    </div>
                    <p style="font-size:11px;color:#888;margin-top:6px">Regenerate: Thumbnail, Single Product, Gallery, Zoom — sab WooCommerce sizes auto-correct ho jayengi.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Bulk Regen section -->
<div class="sgcps-panel" style="margin-top:14px">
    <div class="sgcps-ph">⚡ Bulk Regenerate All Product Images</div>
    <div class="sgcps-pb">
        <p style="color:#666;font-size:13px;margin:0 0 10px">Sab products ke images ko WooCommerce sizes mein regenerate karo (thumbnail, single, gallery). Shared hosting pe safe — 5 images at a time.</p>
        <div id="sgcps-bregen-prog" style="display:none;margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span id="sgcps-brt">Starting...</span><span id="sgcps-brp">0%</span></div>
            <div style="height:6px;background:#eee;border-radius:3px;overflow:hidden"><div id="sgcps-brbar" style="height:100%;background:#2e7d32;border-radius:3px;transition:width .3s;width:0%"></div></div>
        </div>
        <button class="sgcps-btn ok" id="sgcps-bulk-regen-btn">Start Bulk Regeneration</button>
    </div>
</div>
</div>

<!-- ══ EDIT EXISTING ═══════════════════════════════════════════════════ -->
<div id="sgcps-tc-edit" class="sgcps-tc">
<div class="sgcps-panel">
    <div class="sgcps-ph">Edit Existing Product</div>
    <div class="sgcps-pb">
        <div style="display:flex;gap:8px;margin-bottom:12px">
            <input type="text" id="sgcps-eq" placeholder="Product name ya SKU search karo..." style="flex:1">
            <button class="sgcps-btn ok" id="sgcps-esearch-btn">Search</button>
        </div>
        <div id="sgcps-elist"></div>
        <div id="sgcps-eform" style="display:none;margin-top:12px;border-top:1px solid #eee;padding-top:12px">
            <input type="hidden" id="sgcps-epid">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <strong id="sgcps-etitle" style="font-size:13px"></strong>
                <div style="display:flex;gap:6px">
                    <button class="sgcps-btn ok" id="sgcps-update-btn">💾 Update Product</button>
                    <button class="sgcps-btn-sm" id="sgcps-eclose">Close</button>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:10px">
                <div><label class="sgcps-lbl">SKU</label><input type="text" id="sgcps-esku"></div>
                <div><label class="sgcps-lbl">Price (INR)</label><input type="number" id="sgcps-eprice"></div>
                <div><label class="sgcps-lbl">Stock</label><input type="number" id="sgcps-estock"></div>
            </div>
            <div class="sgcps-fr"><label class="sgcps-lbl">SEO Title</label><input type="text" id="sgcps-eseo"></div>
            <div class="sgcps-fr"><label class="sgcps-lbl">Meta Title</label><input type="text" id="sgcps-emeta"></div>
            <div class="sgcps-fr"><label class="sgcps-lbl">Meta Description</label><textarea id="sgcps-edesc" rows="3"></textarea></div>
            <div class="sgcps-fr"><label class="sgcps-lbl">Short Description</label><textarea id="sgcps-eshort" rows="3"></textarea></div>
            <div class="sgcps-fr"><label class="sgcps-lbl">Main Description</label><textarea id="sgcps-emain" rows="5"></textarea></div>
            <div id="sgcps-emsg" style="margin-top:8px;font-size:12px"></div>
        </div>
    </div>
</div>
</div>

<!-- ══ BULK UPDATE ═════════════════════════════════════════════════════ -->
<div id="sgcps-tc-bulk" class="sgcps-tc">
<div class="sgcps-panel">
    <div class="sgcps-ph">Bulk Content Update <span style="font-size:11px;font-weight:400;color:#888">— AI: <?php echo $ai_on ? esc_html( $ai_prov ) . ' ON' : 'Template mode (AI OFF)'; ?></span></div>
    <div class="sgcps-pb">
        <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
            <select id="sgcps-bcat" style="width:200px">
                <option value="">— Category select karo —</option>
                <?php foreach ( $cats as $cat ) : ?>
                <option value="<?php echo esc_attr( $cat['id'] ); ?>"><?php echo esc_html( $cat['name'] ); ?></option>
                <?php foreach ( $cat['children'] as $sub ) : ?>
                <option value="<?php echo esc_attr( $sub->term_id ); ?>">&nbsp;&nbsp;— <?php echo esc_html( $sub->name ); ?></option>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <button class="sgcps-btn ok" id="sgcps-bload-btn">Load Products</button>
            <button class="sgcps-btn" id="sgcps-bgen-btn" style="display:none">Generate Content for Selected</button>
            <button class="sgcps-btn" id="sgcps-bsel-all" style="display:none">Select All</button>
            <span id="sgcps-bcount" style="font-size:12px;color:#888;align-self:center"></span>
        </div>
        <div id="sgcps-bprog" style="display:none;margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span id="sgcps-bptxt">Processing...</span><span id="sgcps-bppct">0%</span></div>
            <div style="height:6px;background:#eee;border-radius:3px;overflow:hidden"><div id="sgcps-bpbar" style="height:100%;background:#27ae60;border-radius:3px;transition:width .3s;width:0%"></div></div>
        </div>
        <div id="sgcps-blist"></div>
    </div>
</div>
</div>

<!-- ══ SUB-CAT DEFAULTS ════════════════════════════════════════════════ -->
<div id="sgcps-tc-defaults" class="sgcps-tc">
<div class="sgcps-panel">
    <div class="sgcps-ph">📋 Sub-Category Defaults</div>
    <div class="sgcps-pb">
        <p style="color:#666;font-size:13px;margin:0 0 14px">Ek baar defaults set karo — product create karte waqt automatically load honge (parameters, capacities, unit).</p>
        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
            <select id="sgcps-def-cat" style="width:180px">
                <option value="">— Category —</option>
                <?php foreach ( $cats as $cat ) : ?>
                <option value="<?php echo esc_attr( $cat['id'] ); ?>"><?php echo esc_html( $cat['name'] ); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="sgcps-def-sub" style="width:200px" disabled><option value="">— Sub Category —</option></select>
            <button class="sgcps-btn ok" id="sgcps-def-save">💾 Save Defaults</button>
            <span id="sgcps-def-saved" style="font-size:12px;color:#27ae60;display:none;align-self:center">✅ Saved!</span>
        </div>
        <div id="sgcps-def-body"><p style="color:#aaa;text-align:center;padding:32px">Sub Category select karo to defaults load honge</p></div>
    </div>
</div>
</div>
