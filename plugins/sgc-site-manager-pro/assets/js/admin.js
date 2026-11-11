jQuery(document).ready(function($) {
    // Live preview for announcement bar colors
    $('input[name="ann_bg"]').on('input', function() {
        $('#ann-preview').css('background', $(this).val());
    });
    $('input[name="ann_color"]').on('input', function() {
        $('#ann-preview').css('color', $(this).val());
    });
    $('input[name="ann_text"]').on('input', function() {
        $('#ann-preview').text($(this).val() + ' ✕');
    });

    // Live preview for header bar
    $('input[name="header_bg"]').on('input', function() {
        $('#header-preview').css('background', $(this).val());
    });
    $('input[name="header_text_color"]').on('input', function() {
        $('#header-preview').css('color', $(this).val());
    });
    $('input[name="header_phone"]').on('input', function() {
        $('#header-preview').html('📞 ' + $(this).val() + ' &nbsp;|&nbsp; ✉️ ' + $('input[name="header_email"]').val() + ' &nbsp;|&nbsp; 💬 WhatsApp');
    });
    $('input[name="header_email"]').on('input', function() {
        $('#header-preview').html('📞 ' + $('input[name="header_phone"]').val() + ' &nbsp;|&nbsp; ✉️ ' + $(this).val() + ' &nbsp;|&nbsp; 💬 WhatsApp');
    });

    // Character counter for SEO
    $('input[name="seo_title"]').on('input', function() {
        const len = $(this).val().length;
        const info = $(this).next('p');
        info.find('strong').text(len + '/60');
        if (len > 60) { info.removeClass('sgclabs-ok').addClass('sgclabs-warn'); info.find('strong').after(' ⚠️ Too long!'); }
        else { info.removeClass('sgclabs-warn').addClass('sgclabs-ok'); }
    });
    $('textarea[name="seo_description"]').on('input', function() {
        const len = $(this).val().length;
        $(this).next('p').find('strong').text(len + '/160');
    });
});

/* ============================================================
   SGC LABS® — SgcProgressNav Universal Class v6.6.3
   Usage: new SgcProgressNav('container-id', { options })
   ============================================================ */
window.SgcProgressNav = (function() {

    /**
     * @param {string} containerId  — DOM id of .sgc-pnav-wrap element
     * @param {object} opts
     *   label        {string}   Module label e.g. "Auto SEO"
     *   icon         {string}   Emoji icon
     *   onPlay       {function} Called when Play/Resume clicked — receives (nav) instance
     *   onPause      {function} Called when Pause clicked
     *   onStop       {function} Called when Stop clicked
     *   onReset      {function} Called when Reset clicked (optional)
     *   showSpeed    {boolean}  Show speed selector (default true)
     *   counters     {array}    [{id, label}] extra counter boxes
     */
    function SgcProgressNav(containerId, opts) {
        this.id       = containerId;
        this.opts     = opts || {};
        this.state    = 'idle';   // idle | running | paused | stopped | done
        this.percent  = 0;
        this.startTs  = null;
        this._el      = null;
        this._build();
    }

    SgcProgressNav.prototype._build = function() {
        var o = this.opts;
        var speedOpts = o.showSpeed !== false
            ? '<select class="sgc-pnav-speed" title="Batch speed">'
              + '<option value="5">🐢 Slow (5/batch)</option>'
              + '<option value="20" selected>⚡ Normal (20/batch)</option>'
              + '<option value="50">🚀 Fast (50/batch)</option>'
              + '</select>'
            : '';

        var countersHtml = '';
        if (o.counters && o.counters.length) {
            countersHtml = '<div class="sgc-pnav-counters">';
            o.counters.forEach(function(c) {
                countersHtml += '<div class="sgc-pnav-counter" id="' + c.id + '">'
                    + '<strong>—</strong><span>' + c.label + '</span></div>';
            });
            countersHtml += '</div>';
        }

        var html = ''
            + '<div class="sgc-pnav-top">'
            +   '<span class="sgc-pnav-label">'
            +     '<span class="sgc-pnav-icon">' + (o.icon || '▶') + '</span>'
            +     '<span class="sgc-pnav-title">' + (o.label || 'Process') + '</span>'
            +   '</span>'
            +   '<span class="sgc-pnav-pct">0%</span>'
            + '</div>'
            + '<div class="sgc-pnav-bar-track"><div class="sgc-pnav-bar-fill" style="width:0%"></div></div>'
            + '<div class="sgc-pnav-controls">'
            +   '<button class="sgc-pnav-btn sgc-btn-play"  data-action="play">▶ Start</button>'
            +   '<button class="sgc-pnav-btn sgc-btn-pause" data-action="pause" disabled>⏸ Pause</button>'
            +   '<button class="sgc-pnav-btn sgc-btn-stop"  data-action="stop"  disabled>⏹ Stop</button>'
            +   '<button class="sgc-pnav-btn sgc-btn-reset" data-action="reset" disabled>↺ Reset</button>'
            +   speedOpts
            +   '<span class="sgc-pnav-eta"></span>'
            + '</div>'
            + '<div class="sgc-pnav-status" style="margin-top:8px;">Ready — press Start to begin.</div>'
            + countersHtml;

        var wrap = document.getElementById(this.id);
        if (!wrap) { console.warn('SgcProgressNav: element #' + this.id + ' not found'); return; }
        wrap.innerHTML = html;
        wrap.classList.add('sgc-pnav-wrap');
        this._el = wrap;
        this._bind();
    };

    SgcProgressNav.prototype._bind = function() {
        var self = this;
        this._el.querySelectorAll('.sgc-pnav-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var a = this.getAttribute('data-action');
                if (a === 'play')  self.play();
                if (a === 'pause') self.pause();
                if (a === 'stop')  self.stop();
                if (a === 'reset') self.reset();
            });
        });
    };

    SgcProgressNav.prototype.getSpeed = function() {
        var sel = this._el && this._el.querySelector('.sgc-pnav-speed');
        return sel ? parseInt(sel.value) : 20;
    };

    SgcProgressNav.prototype.play = function() {
        if (this.state === 'idle' || this.state === 'stopped') this.startTs = Date.now();
        this._setState('running');
        this._btn('play').disabled  = true;
        this._btn('pause').disabled = false;
        this._btn('stop').disabled  = false;
        this._btn('reset').disabled = true;
        this.status('<span class="sgc-pulse"></span> Running…');
        if (this.opts.onPlay) this.opts.onPlay(this);
    };

    SgcProgressNav.prototype.pause = function() {
        this._setState('paused');
        this._btn('play').innerHTML  = '▶ Resume';
        this._btn('play').disabled   = false;
        this._btn('pause').disabled  = true;
        this.status('⏸ Paused — press Resume to continue.', 'sgc-status-warn');
        if (this.opts.onPause) this.opts.onPause(this);
    };

    SgcProgressNav.prototype.stop = function() {
        this._setState('stopped');
        this._btn('play').innerHTML  = '▶ Start';
        this._btn('play').disabled   = false;
        this._btn('pause').disabled  = true;
        this._btn('stop').disabled   = true;
        this._btn('reset').disabled  = false;
        this.status('⏹ Stopped at ' + this.percent + '%.', 'sgc-status-warn');
        if (this.opts.onStop) this.opts.onStop(this);
    };

    SgcProgressNav.prototype.done = function(msg) {
        this.setPercent(100);
        this._setState('done');
        this._btn('play').disabled  = true;
        this._btn('pause').disabled = true;
        this._btn('stop').disabled  = true;
        this._btn('reset').disabled = false;
        this._el.querySelector('.sgc-pnav-icon').textContent = '✓';
        this.status('✅ ' + (msg || 'Complete!'), 'sgc-status-ok');
        this._el.querySelector('.sgc-pnav-eta').textContent = '';
    };

    SgcProgressNav.prototype.setPercent = function(pct, processed, total) {
        pct = Math.min(100, Math.max(0, Math.round(pct)));
        this.percent = pct;
        if (this._el) {
            this._el.querySelector('.sgc-pnav-bar-fill').style.width = pct + '%';
            this._el.querySelector('.sgc-pnav-pct').textContent = pct + '%';
        }
        // ETA calculation
        if (processed && total && this.startTs && pct > 2 && pct < 100) {
            var elapsed = (Date.now() - this.startTs) / 1000;
            var rate    = processed / elapsed;
            var remain  = total - processed;
            var eta     = rate > 0 ? Math.round(remain / rate) : 0;
            var etaStr  = eta > 60
                ? Math.floor(eta/60) + 'm ' + (eta%60) + 's left'
                : eta + 's left';
            var etaEl = this._el.querySelector('.sgc-pnav-eta');
            if (etaEl) etaEl.textContent = '⏱ ' + etaStr;
        }
    };

    SgcProgressNav.prototype.setCounter = function(id, value) {
        var el = document.getElementById(id);
        if (el) el.querySelector('strong').textContent = value;
    };

    SgcProgressNav.prototype.status = function(msg, cls) {
        var el = this._el && this._el.querySelector('.sgc-pnav-status');
        if (!el) return;
        el.className = 'sgc-pnav-status' + (cls ? ' ' + cls : '');
        el.innerHTML = msg;
    };

    SgcProgressNav.prototype.reset = function() {
        this.percent = 0;
        this.startTs = null;
        this._setState('idle');
        this._el.querySelector('.sgc-pnav-bar-fill').style.width = '0%';
        this._el.querySelector('.sgc-pnav-pct').textContent = '0%';
        this._el.querySelector('.sgc-pnav-icon').textContent = this.opts.icon || '▶';
        this._el.querySelector('.sgc-pnav-eta').textContent = '';
        this._btn('play').innerHTML  = '▶ Start';
        this._btn('play').disabled   = false;
        this._btn('pause').disabled  = true;
        this._btn('stop').disabled   = true;
        this._btn('reset').disabled  = true;
        this.status('Ready — press Start to begin.');
        // Reset counters
        if (this.opts.counters) {
            this.opts.counters.forEach(function(c) {
                var el = document.getElementById(c.id);
                if (el) el.querySelector('strong').textContent = '—';
            });
        }
        if (this.opts.onReset) this.opts.onReset(this);
    };

    SgcProgressNav.prototype.error = function(msg) {
        this.status('❌ ' + msg, 'sgc-status-err');
        // Keep pause/stop available so user can retry
        this._btn('play').disabled   = false;
        this._btn('play').innerHTML  = '▶ Retry';
    };

    SgcProgressNav.prototype.isPaused  = function() { return this.state === 'paused';  };
    SgcProgressNav.prototype.isStopped = function() { return this.state === 'stopped'; };
    SgcProgressNav.prototype.isRunning = function() { return this.state === 'running'; };

    SgcProgressNav.prototype._setState = function(s) {
        this.state = s;
        if (!this._el) return;
        this._el.classList.remove('sgc-running','sgc-paused','sgc-stopped','sgc-done');
        if (s === 'running') this._el.classList.add('sgc-running');
        if (s === 'paused')  this._el.classList.add('sgc-paused');
        if (s === 'stopped') this._el.classList.add('sgc-stopped');
        if (s === 'done')    this._el.classList.add('sgc-done');
    };

    SgcProgressNav.prototype._btn = function(action) {
        return this._el.querySelector('[data-action="' + action + '"]');
    };

    return SgcProgressNav;
})();
