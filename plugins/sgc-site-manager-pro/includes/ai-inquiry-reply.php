<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( get_option( 'sgclabs_ai_inquiry_reply_active', '1' ) !== '1' ) return;

/**
 * ================================================================
 * SGC LABS® AI INQUIRY REPLY CENTER — v6.6.3
 * ================================================================
 * Multi-provider: uses whichever AI is set in AI Control Center.
 * No Claude lock. Works with Claude, OpenAI, Gemini, Mistral, DeepSeek.
 *
 * ⚡ One-click:  Generate & Send immediately after confirm
 * 🤖 Review:    Generate → modal → edit → send
 * ================================================================
 */

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( strpos( $hook, 'sgclabs' ) === false ) return;
    $active = function_exists('sgclabs_ai_any_configured') ? sgclabs_ai_any_configured() : false;
    echo '<script>window.sgclabsAIReplyActive=' . ($active?'true':'false') . ';window.sgclabsAiReplyNonce="' . wp_create_nonce('sgclabs_ai_reply') . '";</script>';
} );

// Menu registered centrally in admin-menu.php

function sgclabs_ai_reply_admin_page() {
    global $wpdb;
    $providers = function_exists('sgclabs_ai_providers') ? sgclabs_ai_providers() : [];
    $provider  = function_exists('sgclabs_ai_active_provider') ? sgclabs_ai_active_provider() : 'claude';
    $api_set   = function_exists('sgclabs_ai_any_configured') ? sgclabs_ai_any_configured() : false;
    // v6.9.3 (Bug 4): Read inquiries from the unified Inquiry Store first —
    // this catches everything (shortcode, WooCommerce, manual, imported) and
    // is no longer affected by the legacy table column mismatch that caused
    // shortcode submissions to fail silently. Falls back to the legacy DB
    // table when the store is empty (older installs).
    if ( class_exists( 'SGCLabs_Inquiry_Store' ) ) {
        $store_rows = SGCLabs_Inquiry_Store::all();
        $inquiries = [];
        foreach ( array_slice( $store_rows, 0, 200 ) as $row ) {
            // Reply Center expects objects with these properties — adapt.
            $obj = new stdClass();
            $obj->id          = isset( $row['meta']['legacy_id'] ) && $row['meta']['legacy_id']
                ? (int) $row['meta']['legacy_id']
                : crc32( $row['id'] ?? uniqid() );
            $obj->name        = $row['name']          ?? '';
            $obj->email       = $row['email']         ?? '';
            $obj->phone       = $row['phone']         ?? '';
            $obj->country     = $row['country']       ?? '';
            $obj->product     = $row['product_title'] ?? '';
            $obj->qty         = $row['meta']['qty']   ?? '';
            $obj->message     = $row['message']       ?? '';
            $obj->status      = $row['status']        ?? 'new';
            $obj->created_at  = isset( $row['created_at'] )
                ? gmdate( 'Y-m-d H:i:s', (int) $row['created_at'] )
                : current_time( 'mysql' );
            $obj->_store_id   = $row['id'] ?? '';
            $inquiries[]      = $obj;
        }
        if ( empty( $inquiries ) ) {
            $inquiries = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgclabs_inquiries ORDER BY created_at DESC LIMIT 200" );
        }
    } else {
        $inquiries = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sgclabs_inquiries ORDER BY created_at DESC LIMIT 200" );
    }
    $nonce     = wp_create_nonce( 'sgclabs_ai_reply' );

    if ( isset( $_POST['sgclabs_ai_reply_settings'] ) && current_user_can( 'manage_options' ) ) {
        check_admin_referer( 'sgclabs_ai_reply_settings_nonce' );
        update_option( 'sgclabs_ai_reply_tone',       sanitize_text_field( $_POST['reply_tone'] ?? 'professional' ) );
        update_option( 'sgclabs_ai_reply_from_name',  sanitize_text_field( $_POST['reply_from_name'] ?? 'SGC Labs Team' ) );
        update_option( 'sgclabs_ai_reply_from_email', sanitize_email( $_POST['reply_from_email'] ?? 'sales@labsglass.com' ) );
        update_option( 'sgclabs_ai_reply_signature',  sanitize_textarea_field( $_POST['reply_signature'] ?? '' ) );
        echo "<div class='notice notice-success is-dismissible'><p>Settings saved!</p></div>";
    }

    $tone      = get_option( 'sgclabs_ai_reply_tone', 'professional' );
    $from_name = get_option( 'sgclabs_ai_reply_from_name', 'SGC Labs Team' );
    $from_email= get_option( 'sgclabs_ai_reply_from_email', 'sales@labsglass.com' );
    $signature = get_option( 'sgclabs_ai_reply_signature', "Best regards,\nSGC Labs® Sales Team\n+91 999 641 6935 | sales@labsglass.com | labsglass.com" );
    $total = count($inquiries); $replied = $pending = 0;
    foreach ($inquiries as $inq) { get_option("sgclabs_ai_sent_{$inq->id}") ? $replied++ : $pending++; }
    ?>
    <div class="wrap sgclabs-wrap">
<?php sgclabs_module_control_bar(["module_id"=>"ai-inquiry-reply","module_name"=>"AI Inquiry Reply Center","module_icon"=>"🤖","option_key"=>"sgclabs_ai_inquiry_reply_active","default"=>"1","description"=>"AI-powered automatic reply drafts for customer inquiries","modes"=>[["value"=>"draft","label"=>"Draft Mode","desc"=>"AI generates draft, you review before sending"],["value"=>"auto","label"=>"Auto Reply","desc"=>"AI sends reply automatically (caution)"]],"mode_key"=>"sgclabs_ai_reply_mode","mode_default"=>"draft"]); ?>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <h1 style="margin:0;">🤖 AI Inquiry Reply Center</h1>
            <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control'); ?>" class="button button-primary" style="font-size:13px;background:#1a1a2e;border-color:#1a1a2e;">
                🔑 AI Provider &amp; Keys →
            </a>
        </div>

        <?php if (!$api_set): ?>
        <div class="notice notice-warning"><p>⚠️ <strong>No AI provider configured.</strong>
        <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control'); ?>">→ Click here to add an API key</a> (Claude, OpenAI, Gemini, Mistral, or DeepSeek)</p></div>
        <?php else: ?>
        <div class="notice notice-info is-dismissible" style="border-left-color:#3b82f6;">
            <p>🟢 Active AI: <strong><?php echo esc_html($providers[$provider]['label'] ?? $provider); ?></strong>
            &nbsp;·&nbsp; model: <code><?php echo esc_html($providers[$provider]['model'] ?? ''); ?></code>
            &nbsp;·&nbsp; <a href="<?php echo admin_url('admin.php?page=sgclabs-ai-center&tab=control'); ?>">Change →</a></p>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
            <?php foreach ([['Total',$total,'#fff','#e0e0e0','#1a1a2e'],['Pending',$pending,'#fff3e0','#f0c040','#856404'],['Replied',$replied,'#f0fff4','#86efac','#166534']] as [$l,$v,$bg,$b,$c]): ?>
            <div style="background:<?php echo $bg;?>;border:1px solid <?php echo $b;?>;border-radius:8px;padding:12px 18px;min-width:100px;">
                <div style="font-size:10px;color:<?php echo $c;?>;text-transform:uppercase;opacity:.7;"><?php echo $l;?></div>
                <div style="font-size:26px;font-weight:700;color:<?php echo $c;?>;"><?php echo $v;?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="background:#f0f7ff;border:1px solid #bcd4f0;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:12px;color:#334;display:flex;gap:20px;flex-wrap:wrap;">
            <span>⚡ <strong>One-Click Send</strong> — Auto-generate + confirm + send instantly</span>
            <span>🤖 <strong>Review &amp; Send</strong> — Edit draft in modal before sending</span>
        </div>

        <?php if (empty($inquiries)): ?>
        <div class="sgclabs-section"><p>No inquiries yet.</p></div>
        <?php else: ?>
        <div class="sgclabs-section" style="overflow-x:auto;">
            <table class="wp-list-table widefat fixed striped" style="min-width:860px;">
                <thead><tr>
                    <th style="width:150px;">Buyer</th><th style="width:90px;">Country</th>
                    <th>Product</th><th style="width:70px;">Qty</th>
                    <th style="width:80px;">Status</th><th style="width:65px;">Date</th>
                    <th style="width:230px;">AI Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($inquiries as $inq):
                    $sent_at = get_option("sgclabs_ai_sent_{$inq->id}");
                    $draft   = get_option("sgclabs_ai_draft_{$inq->id}");
                    $is_new  = $inq->status === 'new';
                ?>
                <tr id="airow-<?php echo intval($inq->id); ?>" style="<?php echo $is_new?'background:#fffdf0;':''; ?>">
                    <td><strong><?php echo esc_html($inq->name); ?></strong><br>
                        <a href="mailto:<?php echo esc_attr($inq->email); ?>" style="font-size:11px;color:#666;"><?php echo esc_html($inq->email); ?></a></td>
                    <td><?php echo esc_html($inq->country); ?></td>
                    <td style="font-size:13px;"><?php echo esc_html($inq->product); ?></td>
                    <td style="font-size:13px;"><?php echo esc_html($inq->qty); ?></td>
                    <td>
                        <?php if ($sent_at): ?><span style="background:#f0fff4;color:#166534;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600;">✅ Replied</span>
                        <?php elseif ($draft): ?><span style="background:#fffde7;color:#856404;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600;">📝 Draft</span>
                        <?php elseif ($is_new): ?><span style="background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600;">🔴 New</span>
                        <?php else: ?><span style="background:#f0f0f0;color:#666;padding:3px 8px;border-radius:10px;font-size:11px;">Read</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:11px;color:#888;"><?php echo date('d M', strtotime($inq->created_at)); ?></td>
                    <td>
                        <?php if ($sent_at): ?>
                            <span style="font-size:12px;color:#888;">Sent <?php echo date('d M', strtotime($sent_at)); ?></span>
                        <?php else: ?>
                            <button class="button button-primary button-small" style="margin:2px;font-size:11px;background:#6366f1;border-color:#6366f1;"
                                onclick="sgcOneClickSend(<?php echo intval($inq->id); ?>)"
                                id="aiOneBtn-<?php echo intval($inq->id); ?>"
                                <?php echo !$api_set ? 'disabled title="Add API key in AI Control Center"' : ''; ?>>
                                ⚡ One-Click Send
                            </button>
                            <button class="button button-small" style="margin:2px;font-size:11px;"
                                onclick="sgcGenerateDraft(<?php echo intval($inq->id); ?>)"
                                id="aiBtn-<?php echo intval($inq->id); ?>"
                                <?php echo !$api_set ? 'disabled' : ''; ?>>
                                <?php echo $draft ? '✏️ Edit Draft' : '🤖 Review & Send'; ?>
                            </button>
                            <?php if ($draft): ?>
                            <button class="button button-small" style="margin:2px;font-size:11px;background:#27ae60;border-color:#27ae60;color:#fff;"
                                onclick="sgcQuickSend(<?php echo intval($inq->id); ?>)">📤 Send</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div id="sgcai-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;width:92%;max-width:700px;max-height:90vh;overflow-y:auto;padding:28px;position:relative;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h2 style="margin:0;" id="sgcai-modal-title">Review AI Reply</h2>
                    <button onclick="document.getElementById('sgcai-modal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;line-height:1;">✕</button>
                </div>
                <div id="sgcai-modal-body"></div>
            </div>
        </div>

        <div class="sgclabs-section" style="margin-top:24px;">
            <h3>⚙️ Reply Email Settings</h3>
            <form method="post">
                <?php wp_nonce_field('sgclabs_ai_reply_settings_nonce'); ?>
                <input type="hidden" name="sgclabs_ai_reply_settings" value="1">
                <table class="form-table">
                    <tr><th>Reply Tone</th><td><select name="reply_tone">
                        <?php foreach (['professional'=>'Professional & Formal','friendly'=>'Friendly & Warm','technical'=>'Technical & Detailed','concise'=>'Concise & Direct'] as $v=>$l): ?>
                        <option value="<?php echo $v;?>" <?php selected($tone,$v);?>><?php echo $l;?></option>
                        <?php endforeach; ?></select></td></tr>
                    <tr><th>From Name</th><td><input type="text" name="reply_from_name" value="<?php echo esc_attr($from_name);?>" class="regular-text"></td></tr>
                    <tr><th>From Email</th><td><input type="email" name="reply_from_email" value="<?php echo esc_attr($from_email);?>" class="regular-text"></td></tr>
                    <tr><th>Signature</th><td><textarea name="reply_signature" rows="4" class="large-text"><?php echo esc_textarea($signature);?></textarea>
                        <p class="description">Appended to every AI reply.</p></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">💾 Save Settings</button></p>
            </form>
        </div>
    </div>
    <?php
    $inq_data = [];
    foreach ($inquiries as $inq) {
        $inq_data[$inq->id] = [
            'name'    => $inq->name,   'email'   => $inq->email,
            'country' => $inq->country,'product' => $inq->product,
            'qty'     => $inq->qty,    'message' => $inq->message,
            'draft'   => get_option("sgclabs_ai_draft_{$inq->id}", ''),
        ];
    }
    ?>
    <script>
    var sgcInqData=<?php echo wp_json_encode($inq_data);?>;
    var sgcAiNonce='<?php echo esc_js($nonce);?>';

    function escH(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

    // ⚡ ONE-CLICK: generate (if needed) → confirm snippet → send
    function sgcOneClickSend(id){
        var inq=sgcInqData[id]; if(!inq) return;
        var btn=document.getElementById('aiOneBtn-'+id);
        if(inq.draft){
            if(!confirm('Send AI reply to '+inq.email+'?\n\nPreview:\n'+inq.draft.substring(0,350)+'...\n\nOK = Send now | Cancel = Open editor')) return;
            sgcDoSend(id,inq.draft,btn); return;
        }
        if(btn){btn.disabled=true;btn.textContent='⏳ Generating…';}
        jQuery.post(ajaxurl,{action:'sgclabs_generate_ai_reply',nonce:sgcAiNonce,id:id},function(res){
            if(!res.success){
                if(btn){btn.disabled=false;btn.textContent='⚡ One-Click Send';}
                alert('Error: '+(res.data||'Check API key in AI Control Center.')); return;
            }
            var draft=res.data.draft;
            sgcInqData[id].draft=draft;
            if(!confirm('Send AI reply to '+inq.email+'?\n\nPreview:\n'+draft.substring(0,400)+(draft.length>400?'…':'')+'\n\nOK = Send now | Cancel = Open editor')){
                if(btn){btn.disabled=false;btn.textContent='⚡ One-Click Send';}
                sgcOpenModal(id,draft); return;
            }
            sgcDoSend(id,draft,btn);
        });
    }

    function sgcDoSend(id,draft,callerBtn){
        if(callerBtn){callerBtn.disabled=true;callerBtn.textContent='📤 Sending…';}
        jQuery.post(ajaxurl,{action:'sgclabs_send_ai_reply',nonce:sgcAiNonce,id:id,draft:draft},function(r){
            if(r.success){
                document.getElementById('sgcai-modal').style.display='none';
                var row=document.getElementById('airow-'+id);
                if(row){
                    row.querySelector('td:last-child').innerHTML='<span style="color:#16a34a;font-weight:600;">✅ Sent!</span>';
                    var sc=row.querySelector('td:nth-child(5)');
                    if(sc)sc.innerHTML='<span style="background:#f0fff4;color:#166534;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:600;">✅ Replied</span>';
                }
                alert('✅ Reply sent to '+sgcInqData[id].email);
            } else {
                if(callerBtn){callerBtn.disabled=false;callerBtn.textContent='⚡ One-Click Send';}
                alert('Send failed: '+(r.data||'Check SMTP settings.'));
            }
        });
    }

    // 🤖 REVIEW FLOW
    function sgcGenerateDraft(id){
        var inq=sgcInqData[id]; if(!inq) return;
        var btn=document.getElementById('aiBtn-'+id);
        if(inq.draft){sgcOpenModal(id,inq.draft);return;}
        if(btn){btn.disabled=true;btn.textContent='⏳ Generating…';}
        jQuery.post(ajaxurl,{action:'sgclabs_generate_ai_reply',nonce:sgcAiNonce,id:id},function(res){
            if(btn)btn.disabled=false;
            if(res.success){
                inq.draft=res.data.draft; sgcInqData[id].draft=res.data.draft;
                if(btn)btn.textContent='✏️ Edit Draft';
                sgcOpenModal(id,res.data.draft);
                var cell=document.getElementById('airow-'+id);
                if(cell){cell=cell.querySelector('td:last-child'); if(cell)cell.innerHTML=
                    '<button class="button button-primary button-small" style="margin:2px;font-size:11px;background:#6366f1;border-color:#6366f1;" onclick="sgcOneClickSend('+id+')">⚡ One-Click Send</button>'
                    +'<button class="button button-small" style="margin:2px;font-size:11px;" onclick="sgcGenerateDraft('+id+')">✏️ Edit Draft</button>'
                    +'<button class="button button-small" style="margin:2px;font-size:11px;background:#27ae60;border-color:#27ae60;color:#fff;" onclick="sgcQuickSend('+id+')">📤 Send</button>';}
            } else {
                alert('Error: '+(res.data||'Check AI provider in AI Control Center.'));
                if(btn)btn.textContent='🤖 Review & Send';
            }
        });
    }

    function sgcOpenModal(id,draft){
        var inq=sgcInqData[id]; if(!inq) return;
        var html='<div style="background:#f0f7ff;border:1px solid #bcd4f0;border-radius:6px;padding:12px 16px;margin-bottom:14px;font-size:13px;">'
            +'<strong>To:</strong> '+escH(inq.name)+' &lt;'+escH(inq.email)+'&gt;<br>'
            +'<strong>Re:</strong> '+escH(inq.product)+(inq.qty?' — Qty: '+escH(inq.qty):'')+(inq.country?' — '+escH(inq.country):'')+'</div>'
            +'<div style="margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">'
            +'<label style="font-weight:600;font-size:13px;">Email Body (editable)</label>'
            +'<button class="button button-small" onclick="sgcRegenerateDraft('+id+')">🔄 Regenerate</button></div>'
            +'<textarea id="sgcai-draft-'+id+'" style="width:100%;height:300px;padding:12px;border:1px solid #ccc;border-radius:6px;font-size:13px;font-family:sans-serif;box-sizing:border-box;line-height:1.6;" spellcheck="true">'+escH(draft)+'</textarea>'
            +'<div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">'
            +'<button class="button button-primary" style="background:#27ae60;border-color:#27ae60;" onclick="sgcSendReply('+id+')">📤 Send Email</button>'
            +'<button class="button" onclick="sgcSaveDraft('+id+')">💾 Save Draft</button>'
            +'<button class="button" onclick="document.getElementById(\'sgcai-modal\').style.display=\'none\'">Cancel</button></div>';
        document.getElementById('sgcai-modal-title').textContent='Review AI Reply — '+inq.name;
        document.getElementById('sgcai-modal-body').innerHTML=html;
        document.getElementById('sgcai-modal').style.display='flex';
    }

    function sgcRegenerateDraft(id){
        var ta=document.getElementById('sgcai-draft-'+id);
        if(ta)ta.value='⏳ Regenerating…';
        jQuery.post(ajaxurl,{action:'sgclabs_generate_ai_reply',nonce:sgcAiNonce,id:id,force:1},function(res){
            var ta=document.getElementById('sgcai-draft-'+id);
            if(res.success){if(ta)ta.value=res.data.draft; sgcInqData[id].draft=res.data.draft;}
            else{if(ta)ta.value=sgcInqData[id].draft||''; alert('Error: '+(res.data||'Regeneration failed'));}
        });
    }
    function sgcSaveDraft(id){
        var ta=document.getElementById('sgcai-draft-'+id);
        var draft=ta?ta.value:'';
        jQuery.post(ajaxurl,{action:'sgclabs_save_ai_draft',nonce:sgcAiNonce,id:id,draft:draft},function(r){
            if(r.success){sgcInqData[id].draft=draft; alert('💾 Draft saved!');}
        });
    }
    function sgcSendReply(id){
        var ta=document.getElementById('sgcai-draft-'+id);
        var draft=ta?ta.value:'';
        if(!draft){alert('Draft is empty');return;}
        if(!confirm('Send this email to '+sgcInqData[id].email+'?'))return;
        sgcDoSend(id,draft,null);
    }
    function sgcQuickSend(id){
        var draft=sgcInqData[id]?sgcInqData[id].draft:'';
        if(!draft){alert('No draft. Click "Review & Send" to generate one.');return;}
        sgcOpenModal(id,draft);
    }
    </script>
    <?php
}

// ── AJAX: Generate ────────────────────────────────────────────────────────────
add_action('wp_ajax_sgclabs_generate_ai_reply','sgclabs_generate_ai_reply_handler');
function sgclabs_generate_ai_reply_handler(){
    if(!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    if(!wp_verify_nonce($_POST['nonce']??'','sgclabs_ai_reply')) wp_send_json_error('Invalid nonce');
    $id=intval($_POST['id']??0);
    if(!$id) wp_send_json_error('Bad ID');

    if(empty($_POST['force'])){
        $ex=get_option("sgclabs_ai_draft_{$id}");
        if($ex) wp_send_json_success(['draft'=>$ex]);
    }

    if(!function_exists('sgclabs_ai_any_configured')||!sgclabs_ai_any_configured())
        wp_send_json_error('No AI provider configured. Go to AI Control Center and add an API key.');

    global $wpdb;
    $inq=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sgclabs_inquiries WHERE id=%d",$id));
    if(!$inq) wp_send_json_error('Inquiry not found');

    $tone=$_opts=['professional'=>'formal professional B2B','friendly'=>'warm and friendly','technical'=>'highly technical, spec-focused','concise'=>'concise and direct'];
    $tone=get_option('sgclabs_ai_reply_tone','professional');
    $tone_instruction=['professional'=>'formal professional B2B','friendly'=>'warm and friendly','technical'=>'highly technical, spec-focused','concise'=>'concise and direct'][$tone]??'professional';
    $signature=get_option('sgclabs_ai_reply_signature',"Best regards,\nSGC Labs® Sales Team\n+91 999 641 6935 | sales@labsglass.com | labsglass.com");

    $specs='';
    if(function_exists('wc_get_products')){
        $ps=wc_get_products(['limit'=>1,'search'=>$inq->product,'status'=>'publish']);
        if(!empty($ps)){
            $sp=get_post_meta($ps[0]->get_id(),'_sgclabs_tech_specs',true)?:[];
            $flds=function_exists('sgclabs_specs_fields')?sgclabs_specs_fields():[];
            $ls=[];
            foreach($flds as $k=>$f){ if(!empty($sp[$k])) $ls[]=$f['label'].': '.$sp[$k]; }
            if($ls) $specs=implode(', ',$ls);
        }
    }

    $prompt="You are a sales executive at SGC Labs® (ISO 9001:2015 certified scientific glassware manufacturer, Ambala India, labsglass.com). Write a professional reply email.

INQUIRY:
- Customer: {$inq->name}
- Country: {$inq->country}
- Product: {$inq->product}
- Quantity: {$inq->qty}
- Message: ".($inq->message?:'No message')."\n".($specs?"- Specs: {$specs}":'')."

INSTRUCTIONS:
1. Tone: {$tone_instruction}
2. Address by first name
3. Confirm receipt
4. Highlight SGC Labs® strengths (ISO 9001:2015, 25+ years, export to 40+ countries, custom manufacturing)
5. Ask for: delivery timeline, port of destination, Incoterms, technical specs needed
6. Lead time: 2-4 weeks standard, 4-6 weeks custom
7. NO specific prices — formal quotation will follow
8. Plain text only, NO HTML
9. Start with 'Dear [FirstName],'
10. Stop before signature
11. Max 180 words

Output ONLY the email body.";

    $result=sgclabs_ai_call_for_task( 'quote_reply', $prompt,600);
    if(is_wp_error($result)) wp_send_json_error($result->get_error_message());

    $full=trim($result)."\n\n".$signature;
    update_option("sgclabs_ai_draft_{$id}",$full,false);
    wp_send_json_success(['draft'=>$full]);
}

add_action('wp_ajax_sgclabs_save_ai_draft',function(){
    if(!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    if(!wp_verify_nonce($_POST['nonce']??'','sgclabs_ai_reply')) wp_send_json_error('Invalid nonce');
    $id=intval($_POST['id']??0);
    $draft=sanitize_textarea_field($_POST['draft']??'');
    if(!$id) wp_send_json_error('Bad ID');
    update_option("sgclabs_ai_draft_{$id}",$draft,false);
    wp_send_json_success();
});

add_action('wp_ajax_sgclabs_send_ai_reply',function(){
    if(!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    if(!wp_verify_nonce($_POST['nonce']??'','sgclabs_ai_reply')) wp_send_json_error('Invalid nonce');
    $id=intval($_POST['id']??0);
    $draft=sanitize_textarea_field($_POST['draft']??'');
    if(!$id||!$draft) wp_send_json_error('Missing data');

    global $wpdb;
    $inq=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sgclabs_inquiries WHERE id=%d",$id));
    if(!$inq) wp_send_json_error('Inquiry not found');

    $from_name=get_option('sgclabs_ai_reply_from_name','SGC Labs Team');
    $from_email=get_option('sgclabs_ai_reply_from_email','sales@labsglass.com');

    $html='<html><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.7;max-width:600px;">'
        .'<div style="background:#1a1a2e;padding:18px 24px;margin-bottom:20px;">'
        .'<span style="font-size:20px;font-weight:900;color:#27ae60;letter-spacing:2px;">SGC LABS®</span>'
        .'<span style="color:rgba(255,255,255,.5);font-size:11px;margin-left:10px;">ISO 9001:2015 Certified</span>'
        .'</div><div style="padding:0 8px;">'.nl2br(esc_html($draft)).'</div>'
        .'<div style="margin-top:24px;padding:14px 8px;border-top:2px solid #27ae60;font-size:12px;color:#94a3b8;">'
        .'SGC LABS® · Ambala, India · <a href="https://labsglass.com" style="color:#27ae60;">labsglass.com</a>'
        .'</div></body></html>';

    $sent=wp_mail($inq->email,'Re: Your Inquiry — '.wp_strip_all_tags($inq->product).' | SGC LABS®',$html,
        ['Content-Type: text/html; charset=UTF-8',"From: {$from_name} <{$from_email}>","Reply-To: {$from_email}"]);

    if(!$sent) wp_send_json_error('wp_mail() failed — check your SMTP plugin.');

    update_option("sgclabs_ai_sent_{$id}",current_time('mysql'),false);
    $wpdb->update($wpdb->prefix.'sgclabs_inquiries',['status'=>'read'],['id'=>$id]);
    wp_send_json_success(['sent_at'=>current_time('mysql')]);
});
