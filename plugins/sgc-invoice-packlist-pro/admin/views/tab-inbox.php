<?php
/**
 * Admin view — Email Inbox (Gmail-style) — v1.9.0
 * @package SGC_IIP
 */
defined( 'ABSPATH' ) || exit;

$inbox_enabled = get_option( 'sgc_iip_inbox_enabled', '0' ) === '1';
$imap_ok       = class_exists( 'SGC_IIP_Inbox' ) && SGC_IIP_Inbox::imap_available();
$last_poll     = get_option( 'sgc_iip_inbox_last_poll', '' );
$last_error    = get_option( 'sgc_iip_inbox_last_error', '' );
$fwd_address   = get_option( 'sgc_iip_inbox_user', 'iip@labsglass.com' );
$settings_url  = admin_url( 'admin.php?page=sgc-iip&tab=settings' );
?>
<div class="iip-inbox" id="iip-inbox">

	<?php if ( ! $imap_ok ) : ?>
		<div class="iip-inbox-banner warn">
			<strong>PHP IMAP extension missing.</strong>
			Inbox sync needs <code>php-imap</code>. Apne hosting (Bluehost/CyberPanel) se enable karwao, phir yeh tab kaam karega.
		</div>
	<?php elseif ( ! $inbox_enabled ) : ?>
		<div class="iip-inbox-banner info">
			<strong>Inbox abhi off hai.</strong>
			Settings me IMAP details bharo aur enable karo →
			<a href="<?php echo esc_url( $settings_url ); ?>">Settings → Email Inbox</a>.
			<br>Buyer mail ko Gmail/Zoho se <code><?php echo esc_html( $fwd_address ); ?></code> par <em>forward</em> karo — wahi yahan aayegi.
		</div>
	<?php endif; ?>

	<?php if ( $last_error ) : ?>
		<div class="iip-inbox-banner warn">Last sync error: <?php echo esc_html( $last_error ); ?></div>
	<?php endif; ?>

	<div class="iip-inbox-toolbar">
		<div class="iip-inbox-filters">
			<button type="button" class="iip-flt active" data-filter="all">All</button>
			<button type="button" class="iip-flt" data-filter="unread">Unread</button>
		</div>
		<div class="iip-inbox-search">
			<input type="search" id="iip-inbox-search" placeholder="Search name or email…">
		</div>
		<div class="iip-inbox-actions">
			<span class="iip-inbox-meta" id="iip-last-poll">
				<?php echo $last_poll ? 'Last sync: ' . esc_html( $last_poll ) : 'Not synced yet'; ?>
			</span>
			<button type="button" class="iip-btn" id="iip-sync-now" <?php disabled( ! $inbox_enabled ); ?>>↻ Sync now</button>
		</div>
	</div>

	<div class="iip-inbox-grid">

		<!-- LEFT: conversation list -->
		<aside class="iip-inbox-list" id="iip-inbox-list">
			<div class="iip-inbox-loading">Loading…</div>
		</aside>

		<!-- RIGHT: reading pane -->
		<section class="iip-inbox-pane" id="iip-inbox-pane">
			<div class="iip-inbox-empty">
				<div class="iip-inbox-empty-ico">📭</div>
				<p>Left se koi conversation chuno.</p>
			</div>
		</section>
	</div>

	<!-- Thread template (cloned by JS) -->
	<template id="iip-thread-tpl">
		<div class="iip-thread-head">
			<div>
				<h2 class="iip-thread-name"></h2>
				<span class="iip-thread-email"></span>
			</div>
			<span class="iip-thread-country"></span>
		</div>
		<div class="iip-thread-msgs"></div>

		<div class="iip-reply">
			<div class="iip-reply-bar">
				<input type="text" class="iip-reply-subject" placeholder="Subject">
				<button type="button" class="iip-btn ai" data-act="ai-draft">✨ AI Draft</button>
			</div>
			<div class="iip-reply-tools">
				<button type="button" data-cmd="bold"><b>B</b></button>
				<button type="button" data-cmd="italic"><i>I</i></button>
				<button type="button" data-cmd="insertUnorderedList">• List</button>
				<button type="button" data-cmd="createLink">🔗 Link</button>
			</div>
			<div class="iip-reply-editor" contenteditable="true" data-ph="Type your reply… (branded HTML frame me bahega)"></div>
			<div class="iip-reply-foot">
				<span class="iip-reply-note">Reply branded SGC frame me jaayega + Zoho Sent me bhi dikhega.</span>
				<button type="button" class="iip-btn primary" data-act="send-reply">Send reply ▸</button>
			</div>
		</div>
	</template>

</div>
