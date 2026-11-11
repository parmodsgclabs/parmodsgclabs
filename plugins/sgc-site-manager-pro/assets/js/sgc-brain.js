/**
 * SGC LABS® — sgc-brain.js v6.6.3
 * ============================================================
 * SECURITY FIXES (v6.6.3 hardened):
 *  - Double-init guard (_sgcBrainLoaded flag)
 *  - _post() helper: nonce auto-inject + .fail() on every call
 *  - _esc() on ALL dynamic DOM writes (XSS prevention)
 *  - CFG.ajax_url validated, abort if missing
 *  - $.ajax interceptor: namespaced, installed once only
 *  - setInterval cleanup on beforeunload
 *  - Namespaced events (.sgcToggle/.sgcTab etc) — no duplicates
 *  - No API keys/secrets ever in JS scope
 *  - tab target sanitized before DOM use
 *  - option key regex-validated before AJAX send
 * ============================================================
 */
(function (window, document, $) {
    'use strict';

    /* ── DOUBLE-INIT GUARD ─────────────────────────────────── */
    if (window._sgcBrainLoaded) { return; }
    window._sgcBrainLoaded = true;

    /* ── CONFIG (from wp_localize_script — no secrets) ──────── */
    var CFG = (function () {
        var c = window.sgc_brain || {};
        return {
            ajax_url : (typeof c.ajax_url === 'string' && c.ajax_url) ? c.ajax_url
                       : (typeof ajaxurl !== 'undefined' ? ajaxurl : ''),
            nonce    : typeof c.nonce   === 'string' ? c.nonce   : '',
            version  : typeof c.version === 'string' ? c.version : '6.5.4',
            brand    : typeof c.brand   === 'string' ? c.brand   : '#1a1a2e',
            accent   : typeof c.accent  === 'string' ? c.accent  : '#27ae60',
        };
    })();

    if (!CFG.ajax_url) {
        if (window.console) console.warn('[SGC Brain] ajax_url not available — aborting.');
        return;
    }

    /* ── XSS HELPER ─────────────────────────────────────────── */
    function _esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    /* ── SAFE AJAX HELPER ───────────────────────────────────────
       ALL AJAX calls go through here.
       Guarantees: nonce always sent + error handled.
    ──────────────────────────────────────────────────────────── */
    function _post(data, onSuccess, onFail) {
        data.nonce = CFG.nonce;
        $.ajax({
            url     : CFG.ajax_url,
            type    : 'POST',
            timeout : 90000,
            data    : data,
            success : function (res) { if (typeof onSuccess === 'function') onSuccess(res); },
            error   : function (xhr, status) {
                var msg = 'Network error [' + _esc(status) + ']';
                if (window.console) console.warn('[SGC Brain]', msg);
                if (typeof onFail === 'function') onFail(msg);
            },
        });
    }


    /* ============================================================
       1. ACTIVITY MONITOR
       Wraps $.ajax to log sgclabs_* / sgcacc_* / sgcr_* calls.
       SECURITY: Only logs action name — never logs POST body.
    ============================================================ */
    var SgcActivityMonitor = (function () {
        var entries=[]; var totalReq=0; var totalErr=0; var durations=[]; var MAX=80;
        var installed=false;
        var $log,$reqEl,$errEl,$avgEl;
        var _startTimes={};  // track start times by requestId

        function init() {
            $log=$('#sgc-activity-log'); $reqEl=$('#sgc-mon-req');
            $errEl=$('#sgc-mon-err');   $avgEl=$('#sgc-mon-avg');
            if (!$log.length || installed) return;
            installed = true;

            /* ── Non-invasive approach: ajaxPrefilter + ajaxComplete ─────────
               NEVER override $.ajax globally — breaks third-party plugins.
               ajaxPrefilter fires before request, ajaxComplete fires after.
            ──────────────────────────────────────────────────────────────── */
            $.ajaxPrefilter(function(options, originalOptions) {
                var action = (originalOptions.data && typeof originalOptions.data.action === 'string')
                    ? originalOptions.data.action : '';
                if (!/^(sgc|sgcacc|sgcr)/.test(action)) return;

                var reqId = Date.now() + Math.random();
                var entry = {id:reqId, action:action, status:'running', time:_now(), dur:'—'};
                entries.unshift(entry); if(entries.length>MAX) entries.pop();
                totalReq++; _render(); _meta();

                // Attach reqId so ajaxComplete can match it
                options._sgcReqId  = reqId;
                options._sgcEntry  = entry;
                options._sgcStart  = Date.now();
            });

            $(document).ajaxComplete(function(event, jqXHR, settings) {
                if (!settings._sgcEntry) return;
                var ms    = Date.now() - settings._sgcStart;
                var entry = settings._sgcEntry;
                var res;
                try { res = JSON.parse(jqXHR.responseText); } catch(e) { res = null; }
                entry.status = (res && res.success === false) ? 'error' : 'done';
                entry.dur    = _fmt(ms);
                if (entry.status === 'error') totalErr++;
                durations.push(ms); if(durations.length>50) durations.shift();
                _upd(entry); _meta();
            });

            $(document).ajaxError(function(event, jqXHR, settings) {
                if (!settings._sgcEntry) return;
                var ms    = Date.now() - (settings._sgcStart||Date.now());
                var entry = settings._sgcEntry;
                entry.status = 'error'; entry.dur = _fmt(ms); totalErr++;
                durations.push(ms); if(durations.length>50) durations.shift();
                _upd(entry); _meta();
            });
        }

        function _upd(e){ var i=entries.findIndex(function(x){return x.id===e.id;}); if(i>=0){entries[i]=e;_render();} }

        function _render() {
            if (!$log||!$log.length) return;
            if (!entries.length) { $log.html('<div class="sgc-log-empty">Waiting for activity...</div>'); return; }
            $log.html(entries.slice(0,40).map(function(e){
                var ic=e.status==='running'?'⚡':(e.status==='done'?'✓':'✗');
                var sh=_esc(e.action.replace(/^(sgclabs_|sgcacc_|sgcr_)/,'').replace(/_/g,' '));
                return '<div class="sgc-log-row sgc-log-'+e.status+'">'
                    +'<span class="sgc-log-icon">'+ic+'</span>'
                    +'<span class="sgc-log-action">'+sh+'</span>'
                    +'<span class="sgc-log-time">'+_esc(e.time)+'</span>'
                    +'<span class="sgc-log-dur">'+_esc(e.dur)+'</span></div>';
            }).join(''));
        }

        function _meta() {
            if($reqEl.length) $reqEl.text(totalReq);
            if($errEl.length) $errEl.text(totalErr);
            if(durations.length&&$avgEl.length){
                var avg=Math.round(durations.reduce(function(a,b){return a+b;},0)/durations.length);
                $avgEl.text(_fmt(avg));
            }
        }

        function clear() { entries=[]; totalReq=0; totalErr=0; durations=[]; _render(); _meta(); }

        function _now(){
            return [new Date().getHours(),new Date().getMinutes(),new Date().getSeconds()]
                .map(function(n){return('0'+n).slice(-2);}).join(':');
        }
        function _fmt(ms){ return ms<1000?ms+'ms':(ms/1000).toFixed(1)+'s'; }

        return {init:init,clear:clear};
    })();


    /* ============================================================
       2. MODULE RUNNER — universal batch processor
    ============================================================ */
    function SgcModuleRunner(opts) {
        this.opts=opts||{}; this.offset=0; this.running=false; this.paused=false;
        this.retries=0; this.totalDone=0; this.totalSkip=0; this._bound=false;
        this._bindUI();
    }

    SgcModuleRunner.prototype._bindUI = function(){
        if(this._bound) return; this._bound=true;
        var s=this, id=this.opts.modId;
        $('#sgc-run-'+id).on('click',function(){if(!s.running)s.start();else if(s.paused)s.resume();});
        $('#sgc-stop-'+id).on('click',function(){s.stop();});
        $('#sgc-pause-'+id).on('click',function(){s.pause();});
    };

    SgcModuleRunner.prototype.start=function(){
        if(this.running) return;
        this.offset=0; this.retries=0; this.totalDone=0; this.totalSkip=0;
        this.running=true; this.paused=false; this._setBtn('running'); this._runBatch();
    };
    SgcModuleRunner.prototype.resume=function(){
        if(!this.paused) return;
        this.paused=false; this.running=true; this._setBtn('running'); this._runBatch();
    };
    SgcModuleRunner.prototype.pause=function(){
        this.paused=true; this.running=false; this._setBtn('paused');
        this._status('⏸ Paused — click Resume to continue.');
    };
    SgcModuleRunner.prototype.stop=function(){
        this.running=false; this.paused=false; this.offset=0;
        this._setBtn('idle'); this._status('⏹ Stopped.'); this._setProgress(0);
    };

    SgcModuleRunner.prototype._runBatch=function(){
        if(!this.running||this.paused) return;
        var self=this, o=this.opts;
        var data=$.extend({action:o.action},o.extraData||{});
        if(o.hasBatch){data.offset=self.offset;data.batch=o.batchSize||5;data.batch_size=o.batchSize||5;}

        self._status('<span class="sgc-pulse"></span> Processing batch at offset '+parseInt(self.offset,10)+'...');

        _post(data,function(res){
            self.retries=0;
            if(!res||!res.success){self._retry('Server: '+_esc((res&&res.data)?res.data:'Unknown'));return;}
            var d=res.data||{};
            var pct=Math.min(100,Math.max(0,parseInt(d.percent,10)||0));
            self._setProgress(pct);
            self.totalDone+=(parseInt(d.processed,10)||0);
            self.totalSkip+=(parseInt(d.skipped,10)||0);
            self._setCounter('done',self.totalDone);
            self._setCounter('skipped',self.totalSkip);
            self._setCounter('total',parseInt(d.total,10)||0);
            self._setCounter('errors',(d.errors||[]).length);
            self._status('✅ '+_esc(String(d.done||self.totalDone))+' / '+_esc(String(d.total||'?'))+' ('+pct+'%)');
            if(o.hasBatch) self.offset=parseInt(d.next_offset,10)||(self.offset+(o.batchSize||5));
            if(o.onBatch) o.onBatch(d,self);
            if(!o.hasBatch||d.finished||pct>=100){
                self.running=false; self._setBtn('done'); self._setProgress(100);
                self._status('✅ Complete! '+parseInt(self.totalDone,10)+' processed, '+parseInt(self.totalSkip,10)+' skipped.');
                if(o.onDone) o.onDone(d,self);
                SgcLiveStats.fetch(); return;
            }
            setTimeout(function(){self._runBatch();},o.gapMs||300);
        },function(msg){self._retry(msg);});
    };

    SgcModuleRunner.prototype._retry=function(msg){
        this.retries++;
        if(this.retries<=5){
            var w=this.retries*3;
            this._status('⚠️ Retry '+this.retries+'/5: '+_esc(msg)+' — '+w+'s...');
            var self=this; setTimeout(function(){self._runBatch();},w*1000);
        } else {
            this.running=false; this._setBtn('error');
            this._status('❌ Failed after 5 retries. '+_esc(msg));
        }
    };

    SgcModuleRunner.prototype._setProgress=function(pct){
        var id=this.opts.modId;
        $('#sgc-bar-'+id).css('width',pct+'%');
        $('#sgc-pct-'+id).text(pct+'%');
    };
    SgcModuleRunner.prototype._setCounter=function(type,val){ $('#sgc-cnt-'+this.opts.modId+'-'+type).text(val); };
    /* _status(): accepts only pre-sanitized HTML strings.
       All dynamic values MUST go through _esc() or parseInt() before being passed.
       Never pass raw server/user data directly. */
    SgcModuleRunner.prototype._status=function(safeHtml){ $('#sgc-status-'+this.opts.modId).html(safeHtml); };

    SgcModuleRunner.prototype._setBtn=function(state){
        var id=this.opts.modId,$run=$('#sgc-run-'+id);
        if(!$run.length) return;
        $run.removeClass('sgc-btn-running sgc-btn-paused sgc-btn-done sgc-btn-error sgc-btn-disabled');
        switch(state){
            case 'running':$run.html('⏸ Pause').addClass('sgc-btn-running');$('#sgc-stop-'+id+',#sgc-pause-'+id).show();break;
            case 'paused': $run.html('▶ Resume').addClass('sgc-btn-paused');break;
            case 'done':
                $run.html('✓ Done').addClass('sgc-btn-done');$('#sgc-stop-'+id+',#sgc-pause-'+id).hide();
                var self=this;setTimeout(function(){$run.html('▶ Run').removeClass('sgc-btn-done');},3000);break;
            case 'error':$run.html('↺ Retry').addClass('sgc-btn-error');break;
            default:$run.html('▶ Run');$('#sgc-stop-'+id+',#sgc-pause-'+id).hide();
        }
    };


    /* ============================================================
       3. TOGGLE MANAGER
    ============================================================ */
    var SgcToggleManager=(function(){
        var LS_KEY='sgc_toggles_v2', saved={};

        function init(){
            try{saved=JSON.parse(localStorage.getItem(LS_KEY)||'{}');}catch(e){saved={};}
            $('.sgc-toggle').each(function(){
                var id=$(this).data('toggle-id');
                /* WP DB value (rendered in HTML checked attr) takes priority over localStorage.
                   We only restore from localStorage if the toggle has no data-wp-val set. */
                var wpVal=$(this).data('wp-val');
                if(wpVal !== undefined){
                    // WP DB value is authoritative — sync localStorage to match
                    var isOn = (String(wpVal) === '1');
                    $(this).prop('checked', isOn);
                    if(id){ saved[id]=isOn; }
                } else if(id&&saved.hasOwnProperty(id)){
                    $(this).prop('checked',!!saved[id]);
                }
                _apply($(this));
            });
            /* Namespaced — prevents duplicate binds */
            $(document).off('change.sgcToggle').on('change.sgcToggle','.sgc-toggle',function(){
                var $t=$(this), id=$t.data('toggle-id'), opt=$t.data('option'), val=$t.is(':checked')?'1':'0';
                if(id){ saved[id]=$t.is(':checked'); try{localStorage.setItem(LS_KEY,JSON.stringify(saved));}catch(e){} }
                _apply($t);
                /* Validate option key format before send */
                if(opt&&typeof opt==='string'&&/^[a-z0-9_]{1,64}$/.test(opt)){
                    _post({action:'sgcacc_toggle',option:opt,value:val},null,null);
                }
            });
        }

        function _apply($t){
            var isOn=$t.is(':checked'), id=$t.data('toggle-id');
            var $btn=$('#sgc-run-'+id);
            $btn.prop('disabled',!isOn).toggleClass('sgc-btn-disabled',!isOn);
            $t.closest('.sgc-module-section').toggleClass('sgc-module-disabled',!isOn);
        }

        return {init:init};
    })();


    /* ============================================================
       4. LIVE STATS — 5s polling, cleanup on unload
    ============================================================ */
    var SgcLiveStats=(function(){
        var timer=null, INTERVAL=5000, active=false;

        function init(){
            if(!$('#sgc-stat-total').length||active) return;
            active=true; fetch();
            timer=setInterval(fetch,INTERVAL);
        }

        function fetch(){
            _post({action:'sgcacc_live_stats'},function(res){
                if(!res||!res.success) return;
                var d=res.data;
                _s('sgc-stat-total',    _f(d.total_products));
                _s('sgc-stat-seo',      _p(d.seo_pct));
                _s('sgc-stat-seo-sub',  _f(d.seo_done)+' products');
                _s('sgc-stat-claude',   _p(d.claude_pct));
                _s('sgc-stat-kw',       _p(d.focus_pct));
                _s('sgc-stat-img',      _p(d.img_pct));
                _s('sgc-stat-inquiries',_f(d.new_inquiries));
                _s('sgc-stat-broken',   _f(d.broken_links));
                _s('sgc-stat-ts','Updated: '+_esc(d.timestamp||''));
                _b('sgc-bar-seo',d.seo_pct);_b('sgc-bar-claude',d.claude_pct);
                _b('sgc-bar-kw',d.focus_pct);_b('sgc-bar-img',d.img_pct);
            },null);
        }

        function stop(){ if(timer){clearInterval(timer);timer=null;active=false;} }
        function _s(id,v){var $e=$('#'+id);if($e.length)$e.text(v);}
        function _b(id,p){var $e=$('#'+id);if($e.length)$e.css('width',(parseInt(p,10)||0)+'%');}
        function _f(n){return n!=null?Number(n).toLocaleString():'0';}
        function _p(n){return(parseInt(n,10)||0)+'%';}

        return {init:init,fetch:fetch,stop:stop};
    })();


    /* ============================================================
       5. AI COMMAND — XSS-safe, namespaced events
    ============================================================ */
    var SgcAICommand=(function(){
        var _runners={}, _bound=false;

        function init(runners){
            _runners=runners||{};
            if(_bound) return; _bound=true;

            $('#sgc-ai-run').off('click.sgcAI').on('click.sgcAI',_run);
            $('#sgc-ai-input').off('keydown.sgcAI').on('keydown.sgcAI',function(e){
                if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();_run();}
            });
            $(document).off('click.sgcQuick').on('click.sgcQuick','.sgc-quick-cmd',function(){
                var cmd=String($(this).data('cmd')||'').slice(0,500);
                $('#sgc-ai-input').val(cmd).focus();
            });
        }

        function _run(){
            var cmd=String($('#sgc-ai-input').val()||'').trim().slice(0,500);
            if(!cmd) return;
            var $btn=$('#sgc-ai-run').prop('disabled',true).text('Thinking...');
            var $res=$('#sgc-ai-result').removeClass('sgc-ai-ok sgc-ai-error').addClass('sgc-ai-thinking show')
                .html('<span class="sgc-pulse"></span> Analyzing via Claude...');

            _post({action:'sgcacc_ai_command',command:cmd},function(res){
                $btn.prop('disabled',false).text('Ask AI ↗');
                if(!res||!res.success){
                    $res.removeClass('sgc-ai-thinking').addClass('sgc-ai-error')
                        .text('❌ '+_esc((res&&res.data)?res.data:'Unknown error'));
                    return;
                }
                var d=res.data||{}, ai=d.ai_response||{};
                var mods=Array.isArray(ai.modules)?ai.modules.filter(function(m){return typeof m==='string'&&/^[a-z0-9_]+$/.test(m);}):[];
                var html='<strong>'+_esc((d.provider||'AI').toUpperCase())+' understood:</strong> '+_esc(ai.understood||'')+'<br>'
                    +'<strong>Modules:</strong> '+mods.map(_esc).join(', ')+'<br>'
                    +'<em>'+_esc(ai.message||'')+'</em>';
                if(mods.length) html+='<br><button id="sgc-ai-confirm" class="sgc-btn sgc-btn-go" style="margin-top:8px">▶ Run these modules</button>';
                $res.removeClass('sgc-ai-thinking').addClass('sgc-ai-ok').html(html);
                $('#sgc-ai-confirm').one('click',function(){
                    $(this).remove();
                    mods.forEach(function(id){if(_runners[id])_runners[id].start();});
                });
            },function(msg){
                $btn.prop('disabled',false).text('Ask AI ↗');
                $res.removeClass('sgc-ai-thinking').addClass('sgc-ai-error').text('❌ '+_esc(msg));
            });
        }

        return {init:init};
    })();


    /* ============================================================
       6. CONTROL CENTER
    ============================================================ */
    var SgcControlCenter=(function(){

        var MODULE_DEFS=[
            {id:'title',   action:'sgclabs_ace_bulk_titles',     hasBatch:true},
            {id:'short',   action:'sgclabs_ace_bulk_short_desc', hasBatch:true},
            {id:'main',    action:'sgclabs_ace_bulk_main_desc',  hasBatch:true},
            {id:'claude',  action:'sgclabs_ace_master_batch',    hasBatch:true},
            {id:'gencon',  action:'sgclabs_ace_bulk_content',    hasBatch:true},
            {id:'autoseo', action:'sgclabs_ace_master_batch',    hasBatch:true},
            {id:'kwinj',   action:'sgclabs_ace_bulk_kw',         hasBatch:true},
            {id:'titleinj',action:'sgclabs_ace_bulk_meta_title', hasBatch:true},
            {id:'descinj', action:'sgclabs_ace_bulk_meta',       hasBatch:true},
            {id:'imgalt',  action:'sgclabs_ace_bulk_images',     hasBatch:true},
            {id:'imgseo',  action:'sgclabs_ace_bulk_images',     hasBatch:true},
            {id:'intlinks',action:'sgclabs_rebuild_link_map',    hasBatch:false},
            {id:'related', action:'sgclabs_related_run',         hasBatch:true},
            {id:'svl',     action:'sgclabs_svl_scan',            hasBatch:true},
            {id:'broken',  action:'sgclabs_scan_batch',          hasBatch:true},
            {id:'crawler', action:'sgclabs_crawler_collect',     hasBatch:true},
            {id:'sitemap', action:'sgclabs_sitemap_ping_engines',hasBatch:false},
            {id:'indexnow',action:'sgclabs_indexnow_bulk',       hasBatch:true},
        ];

        function init(){
            if(!$('#sgc-control-wrap').length) return;
            SgcActivityMonitor.init();
            SgcToggleManager.init();
            SgcLiveStats.init();

            var runners={};
            MODULE_DEFS.forEach(function(def){
                runners[def.id]=new SgcModuleRunner({modId:def.id,action:def.action,hasBatch:def.hasBatch,batchSize:5,gapMs:300});
            });
            SgcAICommand.init(runners);

            $('#sgc-monitor-clear').off('click.sgcCC').on('click.sgcCC',function(){SgcActivityMonitor.clear();});

            $('#sgc-api-test').off('click.sgcCC').on('click.sgcCC',function(){
                var $btn=$(this).prop('disabled',true).text('Testing...');
                _post({action:'sgcacc_api_test'},function(res){
                    $btn.prop('disabled',false).text('Test API');
                    if(res&&res.success) _toast('✅ '+_esc(res.data.message||'Connected'),'ok');
                    else _toast('❌ '+_esc((res&&res.data)?res.data:'No API key'),'error');
                },function(msg){$btn.prop('disabled',false).text('Test API');_toast('❌ '+_esc(msg),'error');});
            });

            $('#sgc-run-all').off('click.sgcCC').on('click.sgcCC',function(){
                if(!confirm('Run ALL enabled modules? This will process all products.')) return;
                var delay=0;
                MODULE_DEFS.forEach(function(def){
                    if($('#sgc-tog-'+def.id).length&&!$('#sgc-tog-'+def.id).is(':checked')) return;
                    if(!runners[def.id]) return;
                    setTimeout(function(){runners[def.id].start();},delay); delay+=400;
                });
            });

            $('#sgc-stop-all').off('click.sgcCC').on('click.sgcCC',function(){
                MODULE_DEFS.forEach(function(def){if(runners[def.id])runners[def.id].stop();});
                _toast('⏹ All modules stopped','warn');
            });

            $(document).off('click.sgcTab').on('click.sgcTab','.sgc-tab',function(){
                /* Sanitize tab target — alphanumeric + dash/underscore only */
                var target=String($(this).data('tab')||'').replace(/[^a-z0-9_-]/gi,'');
                if(!target) return;
                $('.sgc-tab').removeClass('sgc-tab-active');$(this).addClass('sgc-tab-active');
                $('.sgc-panel').hide();$('#sgc-panel-'+target).show();
                try{localStorage.setItem('sgc_active_tab',target);}catch(e){}
            });

            try{
                var lt=String(localStorage.getItem('sgc_active_tab')||'').replace(/[^a-z0-9_-]/gi,'');
                if(lt) $('.sgc-tab[data-tab="'+lt+'"]').trigger('click');
            }catch(e){}

            /* Cleanup interval on unload */
            $(window).off('beforeunload.sgcBrain').on('beforeunload.sgcBrain',function(){SgcLiveStats.stop();});
        }

        function _toast(msg,type){
            var allowed={ok:1,error:1,warn:1};
            var $t=$('<div class="sgc-toast sgc-toast-'+(allowed[type]?type:'ok')+'"></div>').text(msg);
            $('body').append($t);
            setTimeout(function(){$t.addClass('sgc-toast-show');},10);
            setTimeout(function(){$t.removeClass('sgc-toast-show');setTimeout(function(){$t.remove();},300);},3000);
        }

        return {init:init};
    })();


    /* ── EXPOSE (no CFG — nonce stays private) ──────────────── */
    window.SgcActivityMonitor = SgcActivityMonitor;
    window.SgcModuleRunner    = SgcModuleRunner;
    window.SgcToggleManager   = SgcToggleManager;
    window.SgcLiveStats       = SgcLiveStats;
    window.SgcAICommand       = SgcAICommand;
    window.SgcControlCenter   = SgcControlCenter;

    $(document).ready(function(){ SgcControlCenter.init(); });

}(window, document, jQuery));
