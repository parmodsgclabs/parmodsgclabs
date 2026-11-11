/* SGC IIP — Email Inbox JS (v1.9.0) */
( function ( $ ) {
	'use strict';

	var $root = $( '#iip-inbox' );
	if ( ! $root.length ) { return; }

	var AJAX  = ( window.SGCIIP && SGCIIP.ajaxUrl ) || ajaxurl;
	var NONCE = ( window.SGCIIP && SGCIIP.nonce ) || '';
	var state = { filter: 'all', search: '', activeId: 0, activeMsgId: 0 };

	function esc( s ) {
		return $( '<div>' ).text( s == null ? '' : String( s ) ).html();
	}
	function post( action, data ) {
		return $.post( AJAX, $.extend( { action: action, nonce: NONCE }, data || {} ) );
	}

	/* ───────────── Conversation list ───────────── */
	function loadList() {
		var $list = $( '#iip-inbox-list' ).html( '<div class="iip-inbox-loading">Loading…</div>' );
		post( 'sgc_iip_inbox_list', { filter: state.filter, search: state.search } )
			.done( function ( r ) {
				if ( ! r || ! r.success ) { $list.html( '<div class="iip-inbox-empty">Could not load.</div>' ); return; }
				renderList( r.data.threads );
			} )
			.fail( function () { $list.html( '<div class="iip-inbox-empty">Network error.</div>' ); } );
	}

	function renderList( threads ) {
		var $list = $( '#iip-inbox-list' ).empty();
		if ( ! threads.length ) {
			$list.html( '<div class="iip-inbox-empty">Koi mail nahi. Buyer mail forward karo, phir “Sync now”.</div>' );
			return;
		}
		threads.forEach( function ( t ) {
			var unread = parseInt( t.unread_count, 10 ) > 0;
			var badge  = unread ? '<span class="iip-conv-badge">' + esc( t.unread_count ) + '</span>' : '';
			var time   = t.last_message_at ? t.last_message_at.substring( 5, 16 ) : '';
			var $c = $(
				'<button type="button" class="iip-conv' + ( unread ? ' unread' : '' ) + '" data-id="' + t.id + '">' +
					'<div class="iip-conv-top">' +
						'<span class="iip-conv-name">' + esc( t.name || t.email ) + badge + '</span>' +
						'<span class="iip-conv-time">' + esc( time ) + '</span>' +
					'</div>' +
					'<div class="iip-conv-sub">' + esc( t.last_subject || '(no subject)' ) + '</div>' +
					'<div class="iip-conv-snip">' + esc( t.snippet || '' ) + '</div>' +
				'</button>'
			);
			if ( parseInt( t.id, 10 ) === state.activeId ) { $c.addClass( 'active' ); }
			$list.append( $c );
		} );
	}

	/* ───────────── Open a thread ───────────── */
	function openThread( id ) {
		state.activeId = id;
		$( '.iip-conv' ).removeClass( 'active' );
		$( '.iip-conv[data-id="' + id + '"]' ).removeClass( 'unread' ).addClass( 'active' )
			.find( '.iip-conv-badge' ).remove();

		var $pane = $( '#iip-inbox-pane' ).html( '<div class="iip-inbox-loading">Opening…</div>' );
		post( 'sgc_iip_inbox_thread', { inquiry_id: id } )
			.done( function ( r ) {
				if ( ! r || ! r.success ) { $pane.html( '<div class="iip-inbox-empty">Could not open.</div>' ); return; }
				renderThread( r.data );
			} );
	}

	function renderThread( data ) {
		var tpl  = document.getElementById( 'iip-thread-tpl' );
		var node = tpl.content.cloneNode( true );
		var inq  = data.inquiry;
		var msgs = data.messages || [];

		node.querySelector( '.iip-thread-name' ).textContent  = inq.name || inq.email || 'Unknown';
		node.querySelector( '.iip-thread-email' ).textContent = inq.email || '';
		node.querySelector( '.iip-thread-country' ).textContent = inq.country || '';

		var $msgs = $( node.querySelector( '.iip-thread-msgs' ) );
		var lastIn = 0, lastSubject = '';
		msgs.forEach( function ( m ) {
			var dir = m.direction === 'out' ? 'out' : 'in';
			if ( dir === 'in' ) { lastIn = m.id; lastSubject = m.subject; }
			var att = parseInt( m.has_attachments, 10 ) ? '<div class="iip-msg-att">📎 Has attachment(s) — original mailbox me dekho</div>' : '';
			$msgs.append(
				'<div class="iip-msg ' + dir + '">' +
					'<div class="iip-msg-meta">' +
						'<span class="iip-msg-from">' + esc( m.from_name || m.from_email ) + '</span>' +
						'<span class="iip-msg-dir">' + ( dir === 'out' ? 'Sent ▸' : '◂ Received' ) + ' · ' + esc( ( m.received_at || '' ).substring( 0, 16 ) ) + '</span>' +
					'</div>' +
					'<div class="iip-msg-subject" style="padding:8px 16px 0;font-weight:600;font-size:13px">' + esc( m.subject ) + '</div>' +
					'<div class="iip-msg-body">' + ( m.body_html || '' ) + '</div>' + att +
				'</div>'
			);
		} );

		// Wire reply box to the latest INBOUND message (for threading + AI context).
		state.activeMsgId = lastIn;
		$( node.querySelector( '.iip-reply-subject' ) ).val( lastSubject ? 'Re: ' + lastSubject : '' );
		var canReply = lastIn > 0;
		$( node.querySelectorAll( '.iip-reply [data-act], .iip-reply-editor' ) ).each( function () {
			if ( ! canReply ) { $( this ).attr( 'disabled', true ).css( 'opacity', .5 ); }
		} );

		var $pane = $( '#iip-inbox-pane' ).empty();
		$pane.append( node );
		// Refresh list badge counts.
		loadList();
	}

	/* ───────────── Reply actions ───────────── */
	function doAiDraft( $btn ) {
		if ( ! state.activeMsgId ) { return; }
		$btn.addClass( 'loading' ).text( '✨ Drafting…' );
		post( 'sgc_iip_inbox_ai_draft', { message_id: state.activeMsgId } )
			.done( function ( r ) {
				if ( r && r.success ) {
					$( '.iip-reply-editor' ).html( r.data.body );
					if ( r.data.subject ) { $( '.iip-reply-subject' ).val( r.data.subject ); }
				} else {
					alert( 'AI draft failed: ' + ( ( r && r.data ) || 'unknown' ) );
				}
			} )
			.fail( function () { alert( 'AI draft network error.' ); } )
			.always( function () { $btn.removeClass( 'loading' ).text( '✨ AI Draft' ); } );
	}

	function doSendReply( $btn ) {
		if ( ! state.activeMsgId ) { return; }
		var subject = $( '.iip-reply-subject' ).val();
		var body    = $( '.iip-reply-editor' ).html();
		if ( ! $.trim( $( '.iip-reply-editor' ).text() ) ) { alert( 'Reply body khali hai.' ); return; }

		$btn.addClass( 'loading' ).text( 'Sending…' );
		post( 'sgc_iip_inbox_send_reply', { message_id: state.activeMsgId, subject: subject, body: body } )
			.done( function ( r ) {
				if ( r && r.success ) {
					openThread( state.activeId ); // reload thread with the sent message
				} else {
					alert( 'Send failed: ' + ( ( r && r.data ) || 'unknown' ) );
				}
			} )
			.fail( function () { alert( 'Send network error.' ); } )
			.always( function () { $btn.removeClass( 'loading' ).text( 'Send reply ▸' ); } );
	}

	/* ───────────── Sync now ───────────── */
	function syncNow( $btn ) {
		$btn.addClass( 'loading' ).text( '↻ Syncing…' );
		post( 'sgc_iip_inbox_poll_now' )
			.done( function ( r ) {
				if ( r && r.success ) {
					$( '#iip-last-poll' ).text( 'Synced — ' + r.data.imported + ' new' );
					loadList();
				} else {
					alert( 'Sync: ' + ( ( r && r.data ) || 'failed' ) );
				}
			} )
			.fail( function () { alert( 'Sync network error.' ); } )
			.always( function () { $btn.removeClass( 'loading' ).text( '↻ Sync now' ); } );
	}

	/* ───────────── Events ───────────── */
	$root.on( 'click', '.iip-flt', function () {
		$( '.iip-flt' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		state.filter = $( this ).data( 'filter' );
		loadList();
	} );

	var searchTimer;
	$root.on( 'input', '#iip-inbox-search', function () {
		var v = this.value;
		clearTimeout( searchTimer );
		searchTimer = setTimeout( function () { state.search = v; loadList(); }, 350 );
	} );

	$root.on( 'click', '.iip-conv', function () { openThread( parseInt( $( this ).data( 'id' ), 10 ) ); } );
	$root.on( 'click', '#iip-sync-now', function () { syncNow( $( this ) ); } );
	$root.on( 'click', '[data-act="ai-draft"]', function () { doAiDraft( $( this ) ); } );
	$root.on( 'click', '[data-act="send-reply"]', function () { doSendReply( $( this ) ); } );

	$root.on( 'click', '.iip-reply-tools button', function () {
		var cmd = $( this ).data( 'cmd' );
		if ( cmd === 'createLink' ) {
			var url = prompt( 'Link URL:' );
			if ( url ) { document.execCommand( 'createLink', false, url ); }
		} else {
			document.execCommand( cmd, false, null );
		}
		$( '.iip-reply-editor' ).focus();
	} );

	/* ───────────── Init ───────────── */
	loadList();

}( jQuery ) );
