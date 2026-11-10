/* SGC Product Studio v1.0.1 studio.js */
(function($){
'use strict';

var cur     = {id:0,name:'',caps:[],unit:'ml',params:[]};
var genTmr  = null;
var mgrPid  = 0;
var mgrSku  = '';
var mgrName = '';

/* ── TABS ── */
$(document).on('click','#sgcps-tabs .sgcps-tab',function(){
    var tab = $(this).data('tab');
    $('#sgcps-tabs .sgcps-tab').removeClass('active');
    $(this).addClass('active');
    $('.sgcps-tc').removeClass('active');
    $('#sgcps-tc-'+tab).addClass('active');
});

/* ── IMAGE PANEL TABS (create form) ── */
$(document).on('click','#sgcps-iup,#sgcps-iurl,#sgcps-iassigned',function(){});
$(document).on('click','.sgcps-it',function(){
    var panel = $(this).data('ipanel');
    $(this).closest('.sgcps-img-box').find('.sgcps-it').removeClass('active');
    $(this).addClass('active');
    $(this).closest('.sgcps-img-box').find('.sgcps-ip').removeClass('active');
    $('#sgcps-'+panel).addClass('active');
});

/* ── CATEGORY → SUB ── */
$(document).on('change','#sgcps-cat',function(){
    var catId = $(this).val();
    $('#sgcps-sub').prop('disabled',true).html('<option value="">— Sub Category —</option>');
    hideFields();
    if(!catId) return;
    $.post(SGCPS.ajax,{action:'sgcps_get_subcats',nonce:SGCPS.nonce,parent_id:catId},function(res){
        if(!res.success) return;
        var opts='<option value="">— Sub Category —</option>';
        $.each(res.data,function(_,s){opts+='<option value="'+s.id+'">'+s.name+' ('+s.count+')</option>';});
        $('#sgcps-sub').html(opts).prop('disabled',false);
    });
});

/* ── SUB → LOAD DEFAULTS ── */
$(document).on('change','#sgcps-sub',function(){
    var subcatId=$(this).val(), subcatName=$(this).find('option:selected').text();
    hideFields();
    if(!subcatId) return;
    setStatus('orange','Loading defaults...');
    $.post(SGCPS.ajax,{action:'sgcps_load_defaults',nonce:SGCPS.nonce,subcat_id:subcatId},function(res){
        if(!res.success){setStatus('','Error loading defaults');return;}
        var d=res.data;
        cur={id:subcatId,name:subcatName,caps:d.capacities||[],unit:d.cap_unit||'ml',params:d.params||[]};

        var capOpts='<option value="">— Select '+subcatName+' Capacity —</option>';
        var chips='';
        $.each(cur.caps,function(_,c){
            capOpts+='<option value="'+c+'">'+c+cur.unit+'</option>';
            chips+='<span class="sgcps-cap-chip" data-cap="'+c+'">'+c+cur.unit+'</span>';
        });
        $('#sgcps-cap').html(capOpts);
        $('#sgcps-cap-chips').html(chips);
        $('#sgcps-cap-wrap').show();

        var html='';
        $.each(cur.params,function(_,p){
            if(p.locked||p.Locked){
                html+='<div class="sgcps-lock-row"><span style="color:#666">'+esc(p.name||p.n)+'</span><span style="font-weight:600">'+esc(p.value||p.v)+'</span><span class="sgcps-pill gray">locked</span></div>';
            } else {
                var opts2=p.opts||[];
                var sel='<select data-param="'+esc(p.name||p.n)+'" style="width:130px;padding:3px 7px;font-size:11px">';
                sel+='<option value="'+esc(p.value||p.v)+'">'+esc(p.value||p.v)+'</option>';
                $.each(opts2,function(_,o){if(o!==(p.value||p.v))sel+='<option>'+esc(o)+'</option>';});
                sel+='</select>';
                html+='<div class="sgcps-lock-row"><span style="color:#666">'+esc(p.name||p.n)+'</span>'+sel+'</div>';
            }
        });
        $('#sgcps-def-params').html(html);
        if(html) $('#sgcps-def-wrap').show();
        $('#sgcps-core,#sgcps-name-wrap,#sgcps-img-wrap').show();
        $('#sgcps-save-btn').prop('disabled',false);
        setStatus('orange','Capacity select karo — content generate hoga');
    });
});

/* ── CAPACITY CHIP ── */
$(document).on('click','.sgcps-cap-chip',function(){
    $('.sgcps-cap-chip').removeClass('active');
    $(this).addClass('active');
    $('#sgcps-cap').val($(this).data('cap'));
    onCapSelect();
});

$(document).on('change','#sgcps-cap',function(){
    $('.sgcps-cap-chip').removeClass('active');
    $('[data-cap="'+$(this).val()+'"]').addClass('active');
    onCapSelect();
});

function onCapSelect(){
    var cap=$('#sgcps-cap').val();
    if(!cap) return;
    var name=cur.name+(cur.unit?' '+cap+cur.unit:'');
    $('#sgcps-name').val(name);
    liveGen();
}

function liveGen(){
    clearTimeout(genTmr);
    $('#sgcps-gen-status').text('typing...');
    genTmr=setTimeout(genContent,600);
}

$(document).on('input','#sgcps-sku,#sgcps-price',liveGen);
$(document).on('change','.sgcps-lock-row select',liveGen);
$(document).on('input','#sgcps-name',function(){});

/* ── GENERATE CONTENT ── */
function genContent(){
    var cap=$('#sgcps-cap').val(), sub=cur.name;
    if(!cap||!sub){$('#sgcps-gen-status').text('waiting...');return;}
    var name=sub+(cur.unit?' '+cap+cur.unit:'');
    var params=collectParams();
    $('#sgcps-gen-status').text('generating...');
    $.post(SGCPS.ajax,{
        action:'sgcps_gen_content',nonce:SGCPS.nonce,
        subcat_name:sub,capacity:cap,cap_unit:cur.unit,
        sku:$('#sgcps-sku').val(),price:$('#sgcps-price').val(),
        params:JSON.stringify(params),
    },function(res){
        if(!res.success){$('#sgcps-gen-status').text('Error');return;}
        var d=res.data;
        $('#g-title').text(d.seo_title);
        $('#g-meta').text(d.meta_title);
        $('#g-desc').text(d.meta_desc);
        $('#g-short').text(d.short_desc);
        $('#g-main').html(d.main_desc);
        $('#g-alt').html('<strong>Alt:</strong> '+esc(d.alt)+'<br><strong>Caption:</strong> '+esc(d.caption)+'<br><strong>Desc:</strong> '+esc(d.img_desc));
        updCC('g-title','cc-t',70); updCC('g-meta','cc-m',70); updCC('g-desc','cc-d',160);
        var sc=parseInt(d.score)||0;
        $('#sgcps-sc-num').text(sc+'/100');
        $('#sgcps-sc-bar').css({width:sc+'%',background:sc>=70?'#27ae60':sc>=40?'#e67e22':'#e74c3c'});
        $('#sgcps-score-lbl').html('<span style="font-size:11px;font-weight:600;color:'+(sc>=70?'#27ae60':'#e67e22')+'">SEO: '+sc+'/100</span>');
        $('#sgcps-gen-status').text('generated');
        setStatus('green','Content ready — save karo');
    });
}

function collectParams(){
    var ps=[];
    $.each(cur.params,function(_,p){
        var sel=$('[data-param="'+(p.name||p.n)+'"]');
        var val=(p.locked||p.Locked)?(p.value||p.v):(sel.length?sel.val():(p.value||p.v));
        ps.push({name:(p.name||p.n),value:val,locked:p.locked||p.Locked||false});
    });
    return ps;
}

/* ── REGENERATE ── */
$(document).on('click','#sgcps-regen-btn',function(){
    $('#sgcps-gen-status').text('generating...');
    setTimeout(genContent,200);
});

/* ── CHAR COUNT ── */
$(document).on('input','#g-title,#g-meta,#g-desc',function(){
    var id=$(this).attr('id'), ccId=id==='g-title'?'cc-t':id==='g-meta'?'cc-m':'cc-d';
    var max=id==='g-desc'?160:70;
    updCC(id,ccId,max);
});

function updCC(fid,cid,max){
    var n=$('#'+fid).text().length;
    $('#'+cid).text(n+'/'+max).toggleClass('warn',n>max);
}

/* ── FILE PREVIEW ── */
$(document).on('change','[id^="sgcps-fi-"]',function(){
    var idx=$(this).attr('id').replace('sgcps-fi-','');
    var f=this.files[0]; if(!f) return;
    var img=$('#sgcps-fp-'+idx);
    img.attr('src',URL.createObjectURL(f)).show();
    if(idx==0) $('#sgcps-fs-0').text(f.name+' ('+Math.round(f.size/1024)+'KB)');
});

$(document).on('change','[id^="sgcps-mfi-"]',function(){
    var idx=$(this).attr('id').replace('sgcps-mfi-','');
    var f=this.files[0]; if(!f) return;
    $('#sgcps-mfp-'+idx).attr('src',URL.createObjectURL(f)).show();
});

/* ── UPLOAD FILES (create form) ── */
$(document).on('click','#sgcps-upload-btn',function(){
    var pid=0;
    var sku=$('#sgcps-sku').val()||'';
    var name=$('#sgcps-name').val()||'';
    var count=0;
    [0,1,2,3].forEach(function(i){if($('#sgcps-fi-'+i)[0].files[0]) count++;});
    if(!count){$('#sgcps-upload-status').text('Koi file select nahi ki!'); return;}
    $('#sgcps-upload-status').text('Uploading...');
    var done=0;
    [0,1,2,3].forEach(function(i){
        var f=$('#sgcps-fi-'+i)[0].files[0];
        if(!f) return;
        var fd=new FormData();
        fd.append('action','sgcps_upload_image');
        fd.append('nonce',SGCPS.nonce);
        fd.append('pid',pid);
        fd.append('slot',i===0?'featured':'gallery-'+(i+1));
        fd.append('sku',sku);
        fd.append('name',name);
        fd.append('sgcps_file',f);
        $.ajax({url:SGCPS.ajax,method:'POST',data:fd,processData:false,contentType:false,success:function(res){
            done++;
            if(res.success){
                $('#sgcps-fs-'+i).css('color','#27ae60').text('Uploaded!');
                if(res.data&&res.data.thumb) $('#sgcps-fp-'+i).attr('src',res.data.thumb).show();
            } else {
                $('#sgcps-fs-'+i).css('color','#e74c3c').text('Error: '+(res.data||'Failed'));
            }
            if(done===count) $('#sgcps-upload-status').css('color','#27ae60').text(done+' image(s) uploaded!');
        }});
    });
});

/* ── FETCH URL (create form) ── */
$(document).on('click','.sgcps-fetch-btn',function(){
    var idx=$(this).data('idx');
    var slot=$(this).data('slot');
    var url=$('#sgcps-ui-'+idx).val().trim();
    var sku=$('#sgcps-sku').val()||'';
    var name=$('#sgcps-name').val()||'';
    if(!url){alert('URL paste karo pehle');return;}
    var $st=$('#sgcps-us-'+idx);
    $st.css('color','#888').text('Fetching...');
    $.post(SGCPS.ajax,{action:'sgcps_fetch_image',nonce:SGCPS.nonce,url:url,pid:0,slot:slot,sku:sku,name:name},function(res){
        if(res.success){
            $st.css('color','#27ae60').text('Saved in media!');
            if(res.data&&res.data.thumb) $('#sgcps-up-'+idx).attr('src',res.data.thumb).show();
        } else {
            $st.css('color','#e74c3c').text('Error: '+(res.data||'Failed'));
        }
    });
});

/* ── SAVE PRODUCT ── */
$(document).on('click','#sgcps-save-btn',function(){
    var cap=$('#sgcps-cap').val(), name=$('#sgcps-name').val();
    if(!name||!cap){alert('Category, subcategory aur capacity zaroori hai');return;}
    $(this).prop('disabled',true).text('Saving...');
    setStatus('orange','Saving...');
    $.post(SGCPS.ajax,{
        action:'sgcps_create_product',nonce:SGCPS.nonce,
        name:name,sku:$('#sgcps-sku').val(),price:$('#sgcps-price').val(),
        stock:$('#sgcps-stock').val(),subcat_id:cur.id,capacity:cap,cap_unit:cur.unit,
        params:JSON.stringify(collectParams()),
        seo_title:$('#g-title').text(),meta_title:$('#g-meta').text(),
        meta_desc:$('#g-desc').text(),short_desc:$('#g-short').text(),main_desc:$('#g-main').html(),
    },function(res){
        $('#sgcps-save-btn').prop('disabled',false).text('Save to WooCommerce');
        if(res.success){
            setStatus('green','Product saved! ID: '+res.data.pid+(res.data.edit_link?' — <a href="'+res.data.edit_link+'" target="_blank">Edit in WooCommerce</a>':''));
        } else {
            setStatus('orange','Error: '+res.data);
        }
    });
});

/* ── RESET ── */
$(document).on('click','#sgcps-reset-btn',function(){
    $('#sgcps-cat,#sgcps-sub,#sgcps-cap').val('');
    $('#sgcps-sub').prop('disabled',true);
    $('#sgcps-sku,#sgcps-price,#sgcps-stock,#sgcps-name').val('');
    hideFields();
    ['g-title','g-meta','g-desc','g-short','g-main'].forEach(function(id){$('#'+id).text('Capacity select karo...');});
    $('#g-alt').text('Image assign hone ke baad...');
    $('#sgcps-sc-bar').css('width','0%'); $('#sgcps-sc-num').text('—'); $('#sgcps-score-lbl').text('');
    cur={id:0,name:'',caps:[],unit:'ml',params:[]};
});

function hideFields(){
    $('#sgcps-cap-wrap,#sgcps-def-wrap,#sgcps-core,#sgcps-name-wrap,#sgcps-img-wrap').hide();
    $('#sgcps-save-btn').prop('disabled',true);
    setStatus('','Category aur Sub-category select karo');
}

function setStatus(color,txt){
    var d=$('#sgcps-dot');
    d.removeClass('green orange');
    if(color==='green') d.addClass('green');
    else if(color==='orange') d.addClass('orange');
    $('#sgcps-stxt').html(txt);
}

/* ── IMAGE MANAGER SEARCH ── */
$(document).on('click','#sgcps-img-search-btn',function(){
    var q=$('#sgcps-img-q').val();
    $('#sgcps-img-plist').html('<p style="color:#aaa;font-size:12px;padding:12px 0">Searching...</p>');
    $.post(SGCPS.ajax,{action:'sgcps_search_products',nonce:SGCPS.nonce,q:q},function(res){
        if(!res.success){$('#sgcps-img-plist').html('<p style="color:#e74c3c">Error</p>');return;}
        var html='';
        $('#sgcps-img-count').text(res.data.total+' products');
        $.each(res.data.products,function(_,p){
            var img=p.thumb?('<img src="'+p.thumb+'" style="width:32px;height:32px;object-fit:cover;border-radius:3px;margin-right:6px;vertical-align:middle">'):('');
            html+='<div class="sgcps-pitem" data-pid="'+p.id+'" data-sku="'+esc(p.sku)+'" data-name="'+esc(p.name)+'">';
            html+=img+'<div><strong style="font-size:12px">'+esc(p.name)+'</strong><br><span style="font-size:11px;color:#888">SKU: '+esc(p.sku)+'</span></div>';
            html+='<div style="text-align:right"><span style="font-size:11px;color:'+(p.has_img?'#27ae60':'#e74c3c')+'">'+(p.has_img?'Has image':'No image')+'</span><br><span class="sgcps-score '+(p.score>=70?'hi':p.score>=40?'mi':'lo')+'">'+p.score+'/100</span></div>';
            html+='</div>';
        });
        $('#sgcps-img-plist').html(html||'<p style="color:#aaa;font-size:12px">No products found</p>');
    });
});

$(document).on('keydown','#sgcps-img-q',function(e){if(e.key==='Enter')$('#sgcps-img-search-btn').click();});

$(document).on('click','#sgcps-img-plist .sgcps-pitem',function(){
    mgrPid=$(this).data('pid');
    mgrSku=$(this).data('sku')||'';
    mgrName=$(this).data('name')||'';
    $('#sgcps-mgr-name').text(mgrName);
    $('#sgcps-mgr-sku').text(mgrSku);
    $('#sgcps-img-mgr').show();
    loadAssigned();
});

$(document).on('click','#sgcps-mgr-close',function(){$('#sgcps-img-mgr').hide();});

/* ── MGR UPLOAD ── */
$(document).on('click','#sgcps-mgr-upload-btn',function(){
    var count=0;
    [0,1,2,3].forEach(function(i){if($('#sgcps-mfi-'+i)[0].files[0]) count++;});
    if(!count){$('#sgcps-mgr-upload-st').text('File select karo pehle!');return;}
    $('#sgcps-mgr-upload-st').css('color','#888').text('Uploading...');
    var done=0;
    [0,1,2,3].forEach(function(i){
        var f=$('#sgcps-mfi-'+i)[0].files[0];
        if(!f) return;
        var slot=i===0?'featured':'gallery-'+(i+1);
        var fd=new FormData();
        fd.append('action','sgcps_upload_image'); fd.append('nonce',SGCPS.nonce);
        fd.append('pid',mgrPid); fd.append('slot',slot);
        fd.append('sku',mgrSku); fd.append('name',mgrName);
        fd.append('sgcps_file',f);
        $.ajax({url:SGCPS.ajax,method:'POST',data:fd,processData:false,contentType:false,success:function(res){
            done++;
            if(res.success&&res.data.thumb) $('#sgcps-mfp-'+i).attr('src',res.data.thumb).show();
            if(done===count){
                $('#sgcps-mgr-upload-st').css('color','#27ae60').text(done+' uploaded + assigned!');
                loadAssigned();
            }
        }});
    });
});

/* ── MGR URL FETCH ── */
$(document).on('click','.sgcps-mgr-fetch',function(){
    var idx=$(this).data('idx'), slot=$(this).data('slot');
    var url=$('#sgcps-mui-'+idx).val().trim();
    if(!url){alert('URL paste karo');return;}
    var $st=$('#sgcps-mus-'+idx);
    $st.css('color','#888').text('Fetching...');
    $.post(SGCPS.ajax,{action:'sgcps_fetch_image',nonce:SGCPS.nonce,url:url,pid:mgrPid,slot:slot,sku:mgrSku,name:mgrName},function(res){
        if(res.success){
            $st.css('color','#27ae60').text('Saved + assigned!');
            if(res.data&&res.data.thumb) $('#sgcps-mup-'+idx).attr('src',res.data.thumb).show();
            loadAssigned();
        } else {
            $st.css('color','#e74c3c').text('Error: '+(res.data||'Failed'));
        }
    });
});

$(document).on('click','#sgcps-reload-assigned',function(){loadAssigned();});
$(document).on('click','#sgcps-regen-sizes-btn',function(){
    if(!mgrPid){alert('Product select karo pehle');return;}
    var btn=$(this);
    btn.prop('disabled',true).text('Regenerating...');
    $.post(SGCPS.ajax,{action:'sgcps_regen_sizes',nonce:SGCPS.nonce,pid:mgrPid},function(res){
        btn.prop('disabled',false).text('Regenerate WooCommerce Sizes');
        if(res.success){
            alert(res.data.msg+'\nThumbnail, Single, Gallery, Zoom — sab sizes generate ho gayi!');
        } else {
            alert('Error: '+res.data);
        }
    });
});

function loadAssigned(){
    if(!mgrPid) return;
    $.post(SGCPS.ajax,{action:'sgcps_get_product_images',nonce:SGCPS.nonce,pid:mgrPid},function(res){
        if(!res.success||!res.data.length){
            $('#sgcps-mgr-assigned').html('<p style="color:#aaa;font-size:12px">No images assigned yet.</p>');
            return;
        }
        var html='<div style="display:flex;flex-wrap:wrap;gap:8px">';
        $.each(res.data,function(_,img){
            html+='<div style="text-align:center">';
            html+='<img src="'+img.url+'" style="width:60px;height:60px;object-fit:cover;border-radius:4px;border:1px solid #ddd;display:block">';
            html+='<div style="font-size:10px;color:#888;margin-top:3px">'+img.slot+'</div>';
            html+='<button class="sgcps-btn-sm sgcps-rem-img" data-attach="'+img.id+'" data-slot="'+img.slot+'" style="margin-top:3px;color:#e74c3c;border-color:#e74c3c">Remove</button>';
            html+='</div>';
        });
        html+='</div>';
        $('#sgcps-mgr-assigned').html(html);
    });
}

$(document).on('click','.sgcps-rem-img',function(){
    if(!confirm('Remove this image?')) return;
    var attachId=$(this).data('attach'), slot=$(this).data('slot');
    $.post(SGCPS.ajax,{action:'sgcps_remove_image',nonce:SGCPS.nonce,pid:mgrPid,attach_id:attachId,slot:slot},function(res){
        if(res.success) loadAssigned();
    });
});

/* ── EDIT TAB ── */
$(document).on('click','#sgcps-esearch-btn',function(){
    var q=$('#sgcps-eq').val();
    $.post(SGCPS.ajax,{action:'sgcps_search_products',nonce:SGCPS.nonce,q:q},function(res){
        if(!res.success) return;
        var html='';
        $.each(res.data.products,function(_,p){
            html+='<div class="sgcps-pitem sgcps-edit-item" data-pid="'+p.id+'">';
            html+='<div><strong style="font-size:12px">'+esc(p.name)+'</strong><br><span style="font-size:11px;color:#888">SKU: '+esc(p.sku)+'</span></div>';
            html+='<div><span class="sgcps-score '+(p.score>=70?'hi':p.score>=40?'mi':'lo')+'">'+p.score+'/100</span> <button class="sgcps-btn-sm ok">Edit</button></div>';
            html+='</div>';
        });
        $('#sgcps-elist').html(html||'<p style="color:#aaa;font-size:12px">No products found</p>');
        $('#sgcps-eform').hide();
    });
});

$(document).on('keydown','#sgcps-eq',function(e){if(e.key==='Enter')$('#sgcps-esearch-btn').click();});

$(document).on('click','.sgcps-edit-item',function(){
    var pid=$(this).data('pid');
    $.post(SGCPS.ajax,{action:'sgcps_load_product',nonce:SGCPS.nonce,pid:pid},function(res){
        if(!res.success){alert('Product load nahi hua');return;}
        var d=res.data;
        $('#sgcps-epid').val(d.id);
        $('#sgcps-etitle').text('Editing: '+d.name);
        $('#sgcps-esku').val(d.sku);
        $('#sgcps-eprice').val(d.price);
        $('#sgcps-estock').val(d.stock);
        $('#sgcps-eseo').val(d.seo_title);
        $('#sgcps-emeta').val(d.meta_title);
        $('#sgcps-edesc').val(d.meta_desc);
        $('#sgcps-eshort').val(d.short_desc.replace(/<[^>]+>/g,''));
        $('#sgcps-emain').val(d.main_desc.replace(/<[^>]+>/g,''));
        $('#sgcps-emsg').html('');
        $('#sgcps-eform').show();
    });
});

$(document).on('click','#sgcps-eclose',function(){$('#sgcps-eform').hide();});

$(document).on('click','#sgcps-update-btn',function(){
    var pid=$('#sgcps-epid').val();
    $(this).prop('disabled',true).text('Updating...');
    $.post(SGCPS.ajax,{
        action:'sgcps_update_product',nonce:SGCPS.nonce,pid:pid,
        sku:$('#sgcps-esku').val(),price:$('#sgcps-eprice').val(),stock:$('#sgcps-estock').val(),
        seo_title:$('#sgcps-eseo').val(),meta_title:$('#sgcps-emeta').val(),
        meta_desc:$('#sgcps-edesc').val(),short_desc:$('#sgcps-eshort').val(),main_desc:$('#sgcps-emain').val(),
        params:'[]',
    },function(res){
        $('#sgcps-update-btn').prop('disabled',false).text('Update Product');
        $('#sgcps-emsg').html(res.success
            ?'<span style="color:#27ae60">'+res.data.msg+'</span>'
            :'<span style="color:#e74c3c">Error: '+res.data+'</span>');
    });
});

/* ── BULK TAB ── */
$(document).on('click','#sgcps-bload-btn',function(){
    var catId=$('#sgcps-bcat').val();
    $('#sgcps-blist').html('<p style="color:#aaa;font-size:12px;padding:12px 0">Loading...</p>');
    $.post(SGCPS.ajax,{action:'sgcps_bulk_load',nonce:SGCPS.nonce,cat_id:catId},function(res){
        if(!res.success) return;
        var d=res.data;
        $('#sgcps-bcount').text(d.total+' products (batch: '+d.batch_size+')');
        $('#sgcps-bgen-btn,#sgcps-bsel-all').show();
        var html='<div style="margin-bottom:6px"><label style="font-size:12px"><input type="checkbox" id="sgcps-chk-all" style="margin-right:4px"> Select All</label></div>';
        $.each(d.products,function(_,p){
            html+='<div class="sgcps-pitem" style="cursor:default">';
            html+='<label style="display:flex;align-items:center;gap:8px;cursor:pointer;flex:1">';
            html+='<input type="checkbox" class="sgcps-bchk" value="'+p.id+'">';
            html+='<strong style="font-size:12px">'+esc(p.name)+'</strong><span style="font-size:11px;color:#888">'+esc(p.sku)+'</span>';
            html+='</label>';
            html+='<span class="sgcps-score '+(p.score>=70?'hi':p.score>=40?'mi':'lo')+'">'+p.score+'/100</span>';
            html+='</div>';
        });
        $('#sgcps-blist').html(html);
    });
});

$(document).on('change','#sgcps-chk-all',function(){
    var chk=$(this).is(':checked');
    $('.sgcps-bchk').prop('checked',chk);
});

$(document).on('click','#sgcps-bgen-btn',function(){
    var ids=[];
    $('.sgcps-bchk:checked').each(function(){ids.push($(this).val());});
    if(!ids.length){ids=[];$('.sgcps-bchk').each(function(){ids.push($(this).val());});}
    if(!ids.length){alert('Products load karo pehle');return;}
    var batchSize=10, batches=[], done=0, total=ids.length;
    for(var i=0;i<ids.length;i+=batchSize) batches.push(ids.slice(i,i+batchSize));
    $('#sgcps-bprog').show();
    function processBatch(idx){
        if(idx>=batches.length){
            $('#sgcps-bptxt').text('Done! '+total+' products updated.');
            return;
        }
        $.post(SGCPS.ajax,{action:'sgcps_bulk_generate',nonce:SGCPS.nonce,ids:JSON.stringify(batches[idx])},function(res){
            done+=batches[idx].length;
            var pct=Math.round(done/total*100);
            $('#sgcps-bpbar').css('width',pct+'%');
            $('#sgcps-bppct').text(pct+'%');
            $('#sgcps-bptxt').text('Updated '+done+' of '+total+'...');
            setTimeout(function(){processBatch(idx+1);},300);
        }).fail(function(){setTimeout(function(){processBatch(idx);},3000);});
    }
    processBatch(0);
});

/* ── DEFAULTS PAGE ── */
$(document).on('change','#sgcps-def-cat',function(){
    var catId=$(this).val();
    $('#sgcps-def-sub').prop('disabled',!catId).html('<option value="">— Sub Category —</option>');
    $('#sgcps-def-body').html('<p style="color:#aaa;text-align:center;padding:24px">Sub Category select karo</p>');
    if(!catId) return;
    $.post(SGCPS.ajax,{action:'sgcps_get_subcats',nonce:SGCPS.nonce,parent_id:catId},function(res){
        if(!res.success) return;
        var opts='<option value="">— Sub Category —</option>';
        $.each(res.data,function(_,s){opts+='<option value="'+s.id+'">'+s.name+'</option>';});
        $('#sgcps-def-sub').html(opts).prop('disabled',false);
    });
});

$(document).on('change','#sgcps-def-sub',function(){
    var subcatId=$(this).val(), subcatName=$(this).find('option:selected').text();
    if(!subcatId){$('#sgcps-def-body').html('<p style="color:#aaa;text-align:center;padding:24px">Sub Category select karo</p>');return;}
    $.post(SGCPS.ajax,{action:'sgcps_load_defaults',nonce:SGCPS.nonce,subcat_id:subcatId},function(res){
        var d=res.success?res.data:{params:[],capacities:[],cap_unit:'ml'};
        var html='<div style="border:1px solid #e0e0e0;border-radius:6px;overflow:hidden;margin-bottom:12px">';
        html+='<div style="display:grid;grid-template-columns:150px 1fr 90px 36px;gap:8px;padding:7px 12px;background:#f8f8f8;font-size:11px;font-weight:600;color:#888;border-bottom:1px solid #e0e0e0"><span>Parameter</span><span>Default / Options</span><span>Locked?</span><span></span></div>';
        html+='<div id="sgcps-def-rows">';
        $.each(d.params,function(i,p){html+=defRow(i,p.name||p.n,p.value||p.v,p.locked||false,p.opts||[]);});
        html+='</div></div>';
        html+='<div style="display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap">';
        html+='<input type="text" id="sgcps-def-new-n" placeholder="New parameter name..." style="width:150px">';
        html+='<input type="text" id="sgcps-def-new-v" placeholder="Default value (comma-sep for options)...">';
        html+='<button class="sgcps-btn-sm ok" id="sgcps-def-add-row">+ Add</button>';
        html+='</div>';
        html+='<div><strong style="font-size:12px">Capacity Values</strong>';
        html+='<div id="sgcps-cap-list" style="margin:6px 0;display:flex;flex-wrap:wrap;gap:4px">';
        $.each(d.capacities,function(_,c){html+='<span class="sgcps-cap-chip active">'+esc(c+(d.cap_unit||'ml'))+'</span>';});
        html+='</div>';
        html+='<div style="display:flex;gap:6px;align-items:center">';
        html+='<input type="text" id="sgcps-new-cap-val" placeholder="e.g. 250" style="width:80px">';
        html+='<select id="sgcps-new-cap-unit" style="width:70px"><option>ml</option><option>L</option><option>mm</option><option>cm</option><option>g</option></select>';
        html+='<button class="sgcps-btn-sm ok" id="sgcps-def-add-cap">+ Add</button>';
        html+='</div></div>';
        $('#sgcps-def-body').html(html);
    });
});

var defIdx=100;
function defRow(i,name,val,locked,opts){
    var optsStr=opts.join(', ')||val;
    return '<div class="sgcps-lock-row" id="sgcps-drow-'+i+'" style="display:grid;grid-template-columns:150px 1fr 90px 36px;gap:8px;padding:7px 12px;border-bottom:1px solid #f0f0f0;align-items:center">'
        +'<input type="text" value="'+esc(name)+'" id="sgcps-dn-'+i+'" style="padding:4px 7px">'
        +'<input type="text" value="'+esc(optsStr)+'" id="sgcps-dv-'+i+'" placeholder="Default, Option1, Option2...">'
        +'<label style="font-size:12px;display:flex;align-items:center;gap:4px"><input type="checkbox" id="sgcps-dl-'+i+'" '+(locked?'checked':'')+'>Locked</label>'
        +'<button class="sgcps-btn-sm" onclick="$(\'#sgcps-drow-'+i+'\').remove()" style="color:#e74c3c;border-color:#e74c3c">✕</button>'
        +'</div>';
}

$(document).on('click','#sgcps-def-add-row',function(){
    var n=$('#sgcps-def-new-n').val().trim(), v=$('#sgcps-def-new-v').val().trim();
    if(!n) return;
    $('#sgcps-def-rows').append(defRow(defIdx++,n,v,false,[]));
    $('#sgcps-def-new-n').val('');$('#sgcps-def-new-v').val('');
});

$(document).on('click','#sgcps-def-add-cap',function(){
    var v=$('#sgcps-new-cap-val').val().trim(), u=$('#sgcps-new-cap-unit').val();
    if(!v) return;
    $('#sgcps-cap-list').append('<span class="sgcps-cap-chip active">'+esc(v+u)+'</span>');
    $('#sgcps-new-cap-val').val('');
});

$(document).on('click','#sgcps-def-save',function(){
    var subcatId=$('#sgcps-def-sub').val(), subcatName=$('#sgcps-def-sub option:selected').text();
    if(!subcatId){alert('Sub category select karo');return;}
    var params=[];
    $('#sgcps-def-rows [id^="sgcps-drow-"]').each(function(){
        var i=$(this).attr('id').replace('sgcps-drow-','');
        var name=$('#sgcps-dn-'+i).val().trim(), optsStr=$('#sgcps-dv-'+i).val().trim();
        var locked=$('#sgcps-dl-'+i).is(':checked');
        if(!name) return;
        var opts=optsStr?optsStr.split(',').map(function(s){return s.trim();}):[]; 
        params.push({name:name,value:opts[0]||'',locked:locked,opts:opts});
    });
    var caps=[];
    $('#sgcps-cap-list .sgcps-cap-chip').each(function(){caps.push($(this).text().replace(/[a-zA-Z]+$/,'').trim());});
    var unit=$('#sgcps-new-cap-unit').val()||'ml';
    $.post(SGCPS.ajax,{action:'sgcps_save_defaults',nonce:SGCPS.nonce,subcat_id:subcatId,subcat_name:subcatName,params:JSON.stringify(params),capacities:caps.join(','),cap_unit:unit},function(res){
        if(res.success){$('#sgcps-def-saved').show();setTimeout(function(){$('#sgcps-def-saved').hide();},2500);}
        else alert('Error: '+res.data);
    });
});

/* ── UTILS ── */
function esc(s){return $('<div>').text(s||'').html();}

})(jQuery);
