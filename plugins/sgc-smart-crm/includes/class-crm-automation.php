<?php
/**
 * SGC Smart CRM Pro — Automation
 *
 * Event-driven email sequences: order follow-ups, inquiry follow-ups,
 * welcome emails, weekly AI product promos (Mon + Thu).
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_Automation
 *
 * @since 1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */
class SGC_CRM_Automation {

	/**
	 * Register AJAX handlers.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function register_ajax() {
		$actions = array(
			'sgc_crm_get_sequences'           => 'ajax_get_sequences',
			'sgc_crm_save_sequence'           => 'ajax_save_sequence',
			'sgc_crm_delete_sequence'         => 'ajax_delete_sequence',
			'sgc_crm_toggle_sequence'         => 'ajax_toggle_sequence',
			'sgc_crm_get_auto_log'            => 'ajax_get_log',
			// Pipeline.
			'sgc_crm_pipeline_save'           => 'ajax_pipeline_save',
			'sgc_crm_pipeline_update_status'  => 'ajax_pipeline_update_status',
			'sgc_crm_pipeline_lookup'         => 'ajax_pipeline_lookup',
			'sgc_crm_pipeline_send_followup'  => 'ajax_pipeline_send_followup',
			'sgc_crm_pipeline_run_overdue'    => 'ajax_pipeline_run_overdue',
		);
		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
		}
	}

	/**
	 * Verify nonce and capability.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private static function verify() {
		check_ajax_referer( 'sgc_crm_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'sgc-smart-crm' ), 403 );
		}
	}

	// =========================================================================
	// HOOKS: ORDER / INQUIRY / NEW CONTACT
	// =========================================================================

	/**
	 * Triggered when a WooCommerce order is placed.
	 *
	 * @since  1.0.0
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function on_new_order( $order_id ) {
		if ( ! get_option( 'sgc_crm_auto_order_followup', '1' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$email = strtolower( $order->get_billing_email() );
		if ( ! is_email( $email ) ) {
			return;
		}

		$products = array();
		foreach ( $order->get_items() as $item ) {
			$products[] = $item->get_name();
		}

		$context = array(
			'order_number' => $order->get_order_number(),
			'order_total'  => $order->get_currency() . ' ' . number_format( (float) $order->get_total(), 2 ),
			'products'     => implode( ', ', $products ),
		);

		$custom = self::get_active_sequences( 'new_order' );
		if ( ! empty( $custom ) ) {
			$contact = SGC_CRM_Contacts::get_by_email( $email );
			if ( $contact ) {
				foreach ( $custom as $seq ) {
					self::log_sequence( $seq, $contact );
				}
			}
			return;
		}

		// Built-in follow-up schedule.
		wp_schedule_single_event( time() + ( 3  * DAY_IN_SECONDS ), 'sgc_crm_send_auto_email', array( $email, 'order_day3',  $context ) );
		wp_schedule_single_event( time() + ( 14 * DAY_IN_SECONDS ), 'sgc_crm_send_auto_email', array( $email, 'order_day14', $context ) );
		wp_schedule_single_event( time() + ( 30 * DAY_IN_SECONDS ), 'sgc_crm_send_auto_email', array( $email, 'order_day30', $context ) );
	}

	/**
	 * Triggered by SGC LABS® inquiry form submission.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $inquiry_data Inquiry form data.
	 * @return void
	 */
	public static function on_new_inquiry( $inquiry_data ) {
		if ( ! get_option( 'sgc_crm_auto_inquiry_followup', '1' ) ) {
			return;
		}

		// v1.3.1: this now also runs on sgclabs_inquiry_received (reach-us / inquiry / get-a-quote /
		// B2B forms only fire that hook), so guard against double handling of the same inquiry.
		$email = strtolower( sanitize_email( is_array( $inquiry_data ) ? ( $inquiry_data['email'] ?? '' ) : '' ) );
		if ( ! is_email( $email ) ) { return; }
		$key = 'sgc_crm_inq_seen_' . md5( $email . '|' . ( $inquiry_data['product'] ?? '' ) );
		if ( get_transient( $key ) ) { return; }
		set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );

		// ── Instant quote acknowledgement (fires immediately, no cron delay) ──
		// Shared lock with Invoice Pro's template auto-reply: the buyer gets ONE reply, the AI one when available.
		if ( get_option( 'sgc_crm_auto_instant_reply', '1' ) && ! get_transient( 'sgc_autoreply_' . md5( $email ) ) ) {
			set_transient( 'sgc_autoreply_' . md5( $email ), 'crm', 10 * MINUTE_IN_SECONDS );
			self::send_instant_quote_reply( $inquiry_data );
		}

		// Schedule day-2 & day-7 follow-ups (2-min delay lets contact insert first).
		wp_schedule_single_event( time() + 120, 'sgc_crm_inquiry_followup', array( $inquiry_data ) );
	}

	/**
	 * Send an instant quote-acknowledgement email — no cron, fires right away.
	 *
	 * @since  1.0.8
	 * @param  array<string, mixed> $inquiry_data Inquiry form data.
	 * @return void
	 */
	public static function send_instant_quote_reply( $inquiry_data ) {
		$email = strtolower( sanitize_email( $inquiry_data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return;
		}

		$parts      = explode( ' ', trim( $inquiry_data['name'] ?? '' ), 2 );
		$first_name = sanitize_text_field( $parts[0] ?? '' ) ?: esc_html__( 'Valued Customer', 'sgc-smart-crm' );

		$context = array(
			'product'      => sanitize_text_field( $inquiry_data['product']  ?? $inquiry_data['message'] ?? '' ),
			'qty'          => sanitize_text_field( $inquiry_data['qty']      ?? '' ),
			'name'         => $first_name,
			'country'      => sanitize_text_field( $inquiry_data['country']  ?? '' ),
			'query_type'   => sanitize_text_field( $inquiry_data['query_type'] ?? 'Quote Request' ),
		);

		$content = self::ai_generate_email( 'quote_instant', $first_name, $context );
		if ( ! $content ) {
			return;
		}

		$body_html = SGC_CRM_Campaigns::wrap_in_template(
			$content['body'],
			$content['subject'],
			array( 'email' => $email )
		);

		SGC_CRM_Sender::send_direct( $email, $content['subject'], $body_html );
	}

	/**
	 * Cron handler for inquiry follow-up.
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $inquiry_data Inquiry data.
	 * @return void
	 */
	public static function handle_inquiry_followup( $inquiry_data ) {
		$email   = strtolower( sanitize_email( $inquiry_data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return;
		}

		$context = array(
			'product' => $inquiry_data['product'] ?? '',
			'qty'     => $inquiry_data['qty']     ?? '',
			'name'    => $inquiry_data['name']    ?? '',
		);

		$custom  = self::get_active_sequences( 'new_inquiry' );
		$contact = SGC_CRM_Contacts::get_by_email( $email );

		if ( ! empty( $custom ) && $contact ) {
			foreach ( $custom as $seq ) {
				self::log_sequence( $seq, $contact );
			}
			return;
		}

		wp_schedule_single_event( time() + ( 2 * DAY_IN_SECONDS ), 'sgc_crm_send_auto_email', array( $email, 'inquiry_day2', $context ) );
		wp_schedule_single_event( time() + ( 7 * DAY_IN_SECONDS ), 'sgc_crm_send_auto_email', array( $email, 'inquiry_day7', $context ) );
	}

	/**
	 * Triggered when a new CRM contact is added.
	 *
	 * @since  1.0.0
	 * @param  int $contact_id Contact ID.
	 * @return void
	 */
	public static function on_new_contact( $contact_id ) {
		if ( ! get_option( 'sgc_crm_auto_welcome', '1' ) ) {
			return;
		}

		$contact = SGC_CRM_Contacts::get_by_id( $contact_id );
		if ( ! $contact || ! is_email( $contact->email ) ) {
			return;
		}

		$custom = self::get_active_sequences( 'new_contact' );
		if ( ! empty( $custom ) ) {
			foreach ( $custom as $seq ) {
				self::log_sequence( $seq, $contact );
			}
			return;
		}

		// Built-in welcome — 1 hour delay.
		wp_schedule_single_event(
			time() + HOUR_IN_SECONDS,
			'sgc_crm_send_auto_email',
			array( $contact->email, 'welcome', array() )
		);
	}

	// =========================================================================
	// SEND AUTO EMAIL (cron callback)
	// =========================================================================

	/**
	 * Generate and send an automated email.
	 *
	 * @since  1.0.0
	 * @param  string               $email   Recipient email.
	 * @param  string               $type    Email type key.
	 * @param  array<string, mixed> $context Contextual data.
	 * @return void
	 */
	public static function send_auto_email( $email, $type, $context ) {
		$contact    = SGC_CRM_Contacts::get_by_email( $email );
		if ( $contact && 'subscribed' !== $contact->status ) {
			return;
		}

		$first_name = ( $contact ? $contact->first_name : ( $context['name'] ?? '' ) ) ?: esc_html__( 'Valued Customer', 'sgc-smart-crm' );

		$content = self::ai_generate_email( $type, $first_name, $context );
		if ( ! $content ) {
			return;
		}

		$body_html = SGC_CRM_Campaigns::wrap_in_template( $content['body'], $content['subject'], array( 'email' => $email ) );
		SGC_CRM_Sender::send_direct( $email, $content['subject'], $body_html );
	}

	/**
	 * Generate email content via AI (with static fallback).
	 *
	 * @since  1.0.0
	 * @param  string               $type       Email type key.
	 * @param  string               $first_name Recipient first name.
	 * @param  array<string, mixed> $context    Contextual data.
	 * @return array<string, string>|null
	 */
	private static function ai_generate_email( $type, $first_name, $context ) {
		$site = 'https://labsglass.com';
		// v1.3.0: every prompt below is built eagerly — missing keys produced PHP warnings for other email types.
		$context = array_merge( array( 'country' => '', 'order_number' => '', 'products' => '', 'product' => '', 'qty' => '' ), (array) $context );

		$prompts = array(
			'quote_instant' => "Write an instant quote-acknowledgement email from SGC LABS® (lab glassware manufacturer, Ambala, India) to '{$first_name}' from {$context['country']}. They submitted a {$context['query_type']} for: {$context['product']}" . ( $context['qty'] ? ", qty: {$context['qty']}" : '' ) . ". Tone: warm, professional B2B. Include: (1) confirm we received their inquiry, (2) our team will respond with a detailed quote within 24 hours, (3) for urgent queries WhatsApp: +91 99964 16935, (4) brief mention of ISO 9001:2015 certification & worldwide export since 2003. CTA button: 'Browse Our Catalogue' → labsglass.com. Max 180 words. Return JSON: {\"subject\":\"...\",\"body\":\"<HTML>\"}",
			'order_day3'    => "Write a short warm 3-day post-order follow-up email from SGC LABS® to '{$first_name}'. Order: {$context['order_number']}. Products: {$context['products']}. Ask if received in good condition. Offer help. Professional B2B tone. Return JSON: {\"subject\":\"...\",\"body\":\"<HTML>\"}",
			'order_day14'   => "Write a 14-day order follow-up from SGC LABS® to '{$first_name}'. They ordered: {$context['products']}. Suggest related lab glassware. CTA: labsglass.com. Return JSON: {\"subject\":\"...\",\"body\":\"<HTML>\"}",
			'order_day30'   => "Write a 30-day re-engagement email from SGC LABS® to '{$first_name}'. Mention new products/offers, bulk discounts. Drive to labsglass.com. Max 150 words. Return JSON: {\"subject\":\"...\",\"body\":\"<HTML>\"}",
			'inquiry_day2'  => "Write a 2-day follow-up from SGC LABS® to prospect '{$first_name}' who inquired about: {$context['product']} qty:{$context['qty']}. Be helpful, mention export capability. Ask if they need a quote. Return JSON: {\"subject\":\"...\",\"body\":\"<HTML>\"}",
			'inquiry_day7'  => "Write a 7-day follow-up from SGC LABS® to prospect '{$first_name}'. Mention 7000+ products, worldwide export, competitive pricing. CTA: labsglass.com. Return JSON: {\"subject\":\"...\",\"body\":\"<HTML>\"}",
			'welcome'       => "Write a welcome email from SGC LABS® to new subscriber '{$first_name}'. Introduce SGC LABS® as India's premier scientific glassware manufacturer. Key benefits: 7000+ products, certified quality, worldwide export. CTA: labsglass.com. Return JSON: {\"subject\":\"...\",\"body\":\"<HTML>\"}",
		);

		$product_line = ! empty( $context['product'] ) ? ' for <strong>' . esc_html( $context['product'] ) . '</strong>' . ( ! empty( $context['qty'] ) ? ' (Qty: ' . esc_html( $context['qty'] ) . ')' : '' ) : '';

		$static_fallback = array(
			'quote_instant' => array(
				'subject' => "✅ Quote Request Received — SGC LABS® Will Reply Within 24 Hours",
				'body'    => "
					<p>Dear {$first_name},</p>
					<p>Thank you for reaching out to <strong>SGC LABS®</strong>! We have received your {$context['query_type']}{$product_line} and our team is reviewing it right now.</p>
					<table width='100%' cellpadding='0' cellspacing='0' style='margin:20px 0;background:#f0f7ff;border-left:4px solid #1a1a2e;border-radius:4px'>
						<tr><td style='padding:14px 18px'>
							<p style='margin:0 0 6px;font-weight:700;color:#1a1a2e'>⏱ What happens next?</p>
							<p style='margin:0;color:#444;font-size:14px'>Our exports team will send you a <strong>detailed quotation within 24 hours</strong> with pricing, MOQ, lead time & shipping terms.</p>
						</td></tr>
					</table>
					<p>For urgent queries, reach us directly on WhatsApp:<br>
					<a href='https://api.whatsapp.com/send/?phone=919996416935' style='display:inline-block;margin-top:8px;background:#25D366;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:700'>💬 WhatsApp: +91 99964 16935</a></p>
					<p style='margin-top:20px;font-size:13px;color:#666'>
						✅ ISO 9001:2015 Certified &nbsp;|&nbsp; 🌍 Export to 50+ Countries &nbsp;|&nbsp; 🏭 Since 2003
					</p>
					<p><a href='{$site}' style='background:#1a1a2e;color:#fff;padding:10px 22px;text-decoration:none;border-radius:4px;font-weight:700'>Browse Our Catalogue →</a></p>
					<p style='font-size:13px;color:#999;margin-top:24px'>Warm regards,<br><strong>SGC LABS® Exports Team</strong><br>4/12/2 Gandhi Nagar, Ambala Cantt, Haryana, India 133001</p>",
			),
			'order_day3'   => array( 'subject' => "How is your SGC LABS® order, {$first_name}?", 'body' => "<p>Dear {$first_name},</p><p>We hope your order of <strong>{$context['products']}</strong> arrived safely. Your satisfaction matters to us.</p><p>Any questions? Reply to this email — we're here to help.</p><p><a href='{$site}' style='background:#1a1a2e;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px'>Shop More</a></p>" ),
			'inquiry_day2' => array( 'subject' => "Following up on your inquiry, {$first_name}", 'body' => "<p>Dear {$first_name},</p><p>Thank you for inquiring about <strong>{$context['product']}</strong>. We'd love to assist you further.</p><p>SGC LABS® offers competitive pricing, certified quality, and worldwide export. Ready to quote when you are.</p><p><a href='{$site}/contact' style='background:#1a1a2e;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px'>Request Quote</a></p>" ),
			'welcome'      => array( 'subject' => "Welcome to SGC LABS®, {$first_name}!", 'body' => "<p>Dear {$first_name},</p><p>Welcome! SGC LABS® is India's trusted scientific glassware manufacturer since 1965.</p><p>🔬 7000+ products &bull; ✅ Quality certified &bull; 🌍 Worldwide export</p><p><a href='{$site}' style='background:#1a1a2e;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px'>Explore Products</a></p>" ),
		);

		$prompt = $prompts[ $type ] ?? null;
		if ( ! $prompt ) {
			return $static_fallback[ $type ] ?? null;
		}

		$response = SGC_CRM_AI::call( $prompt, 800, 'B2B email writer. Return ONLY valid JSON: {subject, body}. No markdown.' );

		if ( ! is_wp_error( $response ) ) {
			$clean  = preg_replace( '/```(?:json)?|```/', '', $response );
			$result = json_decode( trim( $clean ), true );
			if ( is_array( $result ) && ! empty( $result['subject'] ) ) {
				return $result;
			}
		}

		return $static_fallback[ $type ] ?? null;
	}

	// =========================================================================
	// WEEKLY PROMO BROADCAST
	// =========================================================================

	/**
	 * Send AI-generated weekly product promo (runs every Mon + Thu).
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function send_weekly_promo() {
		if ( ! get_option( 'sgc_crm_weekly_ai_enabled', '1' ) ) {
			return;
		}

		$products = SGC_CRM_Product_Fetch::get_promo_products( 6 );
		if ( empty( $products ) ) {
			return;
		}

		$product_names = implode( ', ', array_column( $products, 'name' ) );
		$day           = gmdate( 'l' );

		$prompt = "Create a {$day} B2B product promo email for SGC LABS® (lab glassware manufacturer, Ambala, India).\n"
			. "Featured: {$product_names}\n"
			. "Include: product highlights, bulk discount mention, CTA to labsglass.com/products\n"
			. "Target: lab managers, researchers, distributors worldwide\n"
			. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML>\"}";

		$body_html = '';
		$subject   = sprintf(
			/* translators: %s: day name */
			esc_html__( 'SGC LABS® %s Picks — Featured Lab Glassware', 'sgc-smart-crm' ),
			$day
		);
		$preheader = '';

		$response = SGC_CRM_AI::call( $prompt, 2000, 'B2B email writer. Return only valid JSON.' );

		if ( ! is_wp_error( $response ) ) {
			$clean  = preg_replace( '/```(?:json)?|```/', '', $response );
			$result = json_decode( trim( $clean ), true );
			if ( is_array( $result ) && ! empty( $result['subject'] ) ) {
				$subject   = $result['subject'];
				$preheader = $result['preheader'] ?? '';
				$body_html = $result['body_html']  ?? '';
			}
		}

		// Fallback: simple product grid.
		if ( empty( $body_html ) ) {
			$body_html = self::build_product_grid( $products, $day );
		}

		$campaign_id = SGC_CRM_Campaigns::create_internal(
			array(
				'name'           => sprintf( 'Weekly Promo — %s', gmdate( 'M d, Y' ) ),
				'type'           => 'product_promo',
				'subject'        => $subject,
				'preheader'      => $preheader,
				'body_html'      => SGC_CRM_Campaigns::wrap_in_template( $body_html, $subject ),
				'segment_filter' => get_option( 'sgc_crm_weekly_segment', 'all' ),
				'ai_generated'   => 1,
				'status'         => 'draft',
			)
		);

		SGC_CRM_Sender::queue_campaign( $campaign_id );
		update_option( 'sgc_crm_last_weekly_promo', current_time( 'mysql' ) );
	}

	/**
	 * Build a simple HTML product grid (fallback).
	 *
	 * @since  1.0.0
	 * @param  array<int, array<string, mixed>> $products Product data.
	 * @param  string                           $day      Day name.
	 * @return string HTML.
	 */
	private static function build_product_grid( $products, $day ) {
		$cards = '';
		foreach ( array_slice( $products, 0, 6 ) as $p ) {
			$img    = ! empty( $p['image'] )
				? '<img src="' . esc_url( $p['image'] ) . '" alt="' . esc_attr( $p['name'] ) . '" style="width:100%;max-height:120px;object-fit:contain;border-radius:4px;margin-bottom:8px">'
				: '<div style="height:80px;background:#f0f0f0;border-radius:4px;margin-bottom:8px"></div>';
			$price  = ! empty( $p['price'] ) ? '<p style="color:#e4b04a;font-weight:700;margin:4px 0;font-size:13px">' . wp_strip_all_tags( $p['price'] ) . '</p>' : '';
			$cards .= '<td width="33%" valign="top" style="padding:8px">'
				. '<div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:12px">'
				. $img
				. '<p style="font-size:13px;font-weight:700;color:#1a1a2e;margin:0 0 4px">' . esc_html( $p['name'] ) . '</p>'
				. $price
				. '<a href="' . esc_url( $p['url'] ) . '" style="font-size:12px;color:#1a1a2e">' . esc_html__( 'View →', 'sgc-smart-crm' ) . '</a>'
				. '</div></td>';
		}

		return '<p style="font-size:16px"><strong>🔬 ' . esc_html( $day ) . esc_html__( '\'s Featured Lab Glassware', 'sgc-smart-crm' ) . '</strong></p>'
			. '<table width="100%" cellpadding="0" cellspacing="0"><tr>' . $cards . '</tr></table>'
			. '<p style="text-align:center;margin-top:24px"><a href="https://labsglass.com/products" style="background:#1a1a2e;color:#fff;padding:12px 28px;text-decoration:none;border-radius:4px;font-weight:700">'
			. esc_html__( 'Browse All 7000+ Products →', 'sgc-smart-crm' ) . '</a></p>';
	}

	// =========================================================================
	// CUSTOM SEQUENCES CRUD
	// =========================================================================

	/**
	 * AJAX — get all sequences.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_get_sequences() {
		self::verify();
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( 'SELECT * FROM `' . SGC_CRM_DB::sequences() . '` ORDER BY created_at DESC' );
		wp_send_json_success( $rows );
	}

	/**
	 * AJAX — save (create or update) a sequence.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_save_sequence() {
		self::verify();
		global $wpdb;

		$id   = (int) ( $_POST['id'] ?? 0 );
		$data = array(
			'name'          => sanitize_text_field( wp_unslash( $_POST['name']          ?? '' ) ),
			'trigger_event' => sanitize_key( wp_unslash( $_POST['trigger_event']        ?? 'new_order' ) ),
			'delay_hours'   => max( 1, (int) ( $_POST['delay_hours'] ?? 24 ) ),
			'subject'       => sanitize_text_field( wp_unslash( $_POST['subject']        ?? '' ) ),
			'body_html'     => wp_kses_post( wp_unslash( $_POST['body_html']             ?? '' ) ),
			'conditions'    => sanitize_textarea_field( wp_unslash( $_POST['conditions'] ?? '' ) ),
			'active'        => (int) ( $_POST['active'] ?? 1 ),
		);

		if ( $id ) {
			$wpdb->update( SGC_CRM_DB::sequences(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( SGC_CRM_DB::sequences(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id = (int) $wpdb->insert_id;
		}

		wp_send_json_success( array( 'id' => $id ) );
	}

	/**
	 * AJAX — delete a sequence.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_delete_sequence() {
		self::verify();
		global $wpdb;
		$id = (int) ( $_POST['id'] ?? 0 );
		$wpdb->delete( SGC_CRM_DB::sequences(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_send_json_success();
	}

	/**
	 * AJAX — toggle sequence active state.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_toggle_sequence() {
		self::verify();
		global $wpdb;
		$id = (int) ( $_POST['id'] ?? 0 );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'UPDATE `' . SGC_CRM_DB::sequences() . '` SET active = 1 - active WHERE id = %d', $id )
		);
		wp_send_json_success();
	}

	/**
	 * AJAX — get automation log.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_get_log() {
		self::verify();
		global $wpdb;
		$l = SGC_CRM_DB::auto_log();
		$s = SGC_CRM_DB::sequences();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT l.*, s.name as sequence_name FROM `{$l}` l LEFT JOIN `{$s}` s ON s.id = l.sequence_id ORDER BY l.created_at DESC LIMIT 200" );
		wp_send_json_success( $rows );
	}

	// =========================================================================
	// RUN PENDING SEQUENCES (cron)
	// =========================================================================

	/**
	 * Process pending automation log entries.
	 * Runs hourly via WP-Cron.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function run_pending() {
		global $wpdb;

		$l_table = SGC_CRM_DB::auto_log();
		$s_table = SGC_CRM_DB::sequences();

		$due = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT l.*, s.subject, s.body_html
				 FROM `{$l_table}` l
				 INNER JOIN `{$s_table}` s ON s.id = l.sequence_id AND s.active = 1
				 WHERE l.status = 'pending' AND l.scheduled_at <= %s
				 ORDER BY l.scheduled_at ASC
				 LIMIT 50",
				current_time( 'mysql' )
			)
		);

		foreach ( $due as $item ) {
			$contact = SGC_CRM_Contacts::get_by_id( (int) $item->contact_id );
			if ( ! $contact || 'subscribed' !== $contact->status ) {
				$wpdb->update( $l_table, array( 'status' => 'skipped' ), array( 'id' => (int) $item->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				continue;
			}

			$first     = $contact->first_name ?: esc_html__( 'Valued Customer', 'sgc-smart-crm' );
			$subject   = str_replace( array( '{{first_name}}', '{first_name}' ), $first, $item->subject );
			$body      = str_replace( array( '{{first_name}}', '{first_name}', '{{company}}' ), array( $first, $first, $contact->company ), $item->body_html );
			$body_wrap = SGC_CRM_Campaigns::wrap_in_template( $body, $subject, array( 'email' => $contact->email ) );

			$sent = SGC_CRM_Sender::send_direct( $contact->email, $subject, $body_wrap );

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$l_table,
				array(
					'status'  => $sent ? 'sent' : 'failed',
					'sent_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $item->id )
			);

			if ( $sent ) {
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare( "UPDATE `{$s_table}` SET sent_count = sent_count + 1 WHERE id = %d", (int) $item->sequence_id )
				);
			}
		}
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	// =========================================================================
	// PIPELINE: CRON — check_pipeline_followups
	// =========================================================================

	/**
	 * Hourly cron: find contacts with overdue followup_at and send auto email.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function check_pipeline_followups() {
		global $wpdb;
		$ct = $wpdb->prefix . 'sgc_crm_contacts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$due = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name, followup_note, lead_status
				 FROM `{$ct}`
				 WHERE lead_status = 'follow_up'
				   AND followup_at IS NOT NULL
				   AND followup_at <= %s
				 LIMIT 20",
				current_time( 'mysql' )
			)
		);

		foreach ( $due as $contact ) {
			// Schedule async so we don't block cron.
			wp_schedule_single_event(
				time() + wp_rand( 0, 120 ),
				'sgc_crm_pipeline_followup',
				array( (int) $contact->id )
			);
			// Clear followup_at so it doesn't fire again.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$ct,
				array( 'followup_at' => null ),
				array( 'id' => (int) $contact->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Cron callback: send one pipeline follow-up email by contact ID.
	 *
	 * @since  1.1.0
	 * @param  int $contact_id Contact ID.
	 * @return void
	 */
	public static function send_pipeline_followup_email( $contact_id ) {
		$contact = SGC_CRM_Contacts::get_by_id( (int) $contact_id );
		if ( ! $contact || ! is_email( $contact->email ) ) {
			return;
		}
		if ( 'subscribed' !== $contact->status ) {
			return;
		}
		self::do_send_pipeline_email( $contact );
	}

	/**
	 * Internal: generate and send pipeline follow-up email for a contact.
	 *
	 * @since  1.1.0
	 * @param  object $contact Contact DB row.
	 * @return bool
	 */
	private static function do_send_pipeline_email( $contact ) {
		$first   = $contact->first_name ?: esc_html__( 'Valued Customer', 'sgc-smart-crm' );
		$note    = $contact->followup_note ?? '';
		$company = $contact->company ?? '';
		$country = $contact->country ?? '';

		$prompt = "Write a professional B2B follow-up email from SGC LABS® (lab glassware manufacturer, Ambala, India) to '{$first}'" .
			( $company ? " from {$company}" : '' ) .
			( $country ? ", {$country}" : '' ) . ".\n" .
			( $note ? "Context / previous inquiry: {$note}\n" : '' ) .
			"Tone: warm, professional, B2B export. Keep it concise (max 180 words).\n" .
			"Include: (1) reference to their previous inquiry, (2) offer to send Proforma Invoice, (3) WhatsApp +91 99964 16935 for quick response, (4) CTA button 'Get Best Price' → https://labsglass.com/reach-us/\n" .
			'Return JSON: {"subject":"...","body":"<HTML content>"}';

		$content = null;
		$response = SGC_CRM_AI::call( $prompt, 900, 'B2B email writer. Return ONLY valid JSON: {subject, body}. No markdown fences.' );

		if ( ! is_wp_error( $response ) ) {
			$clean  = preg_replace( '/```(?:json)?|```/', '', $response );
			$result = json_decode( trim( $clean ), true );
			if ( is_array( $result ) && ! empty( $result['subject'] ) ) {
				$content = $result;
			}
		}

		// Static fallback.
		if ( ! $content ) {
			$product_line = $note ? ' regarding your inquiry for <strong>' . esc_html( $note ) . '</strong>' : '';
			$content = array(
				'subject' => "Following Up on Your Inquiry — SGC LABS® [Action Required]",
				'body'    => "<p>Dear {$first},</p>
<p>I hope you are doing well. We are following up{$product_line}.</p>
<p>We would love to help finalize your order and are happy to send a <strong>Proforma Invoice</strong> as soon as you give us the go-ahead.</p>
<table width='100%' cellpadding='0' cellspacing='0' style='margin:16px 0;background:#f0f7ff;border-left:4px solid #1a1a2e;border-radius:4px'>
<tr><td style='padding:14px 18px'>
<p style='margin:0;font-weight:700;color:#1a1a2e'>⚡ Ready to proceed?</p>
<p style='margin:6px 0 0;font-size:14px;color:#444'>Simply reply to this email or reach us on WhatsApp for instant assistance.</p>
</td></tr>
</table>
<p><a href='https://api.whatsapp.com/send/?phone=919996416935' style='display:inline-block;background:#25D366;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:700'>💬 WhatsApp: +91 99964 16935</a>
&nbsp;
<a href='https://labsglass.com/reach-us/' style='display:inline-block;background:#1a1a2e;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-weight:700'>Get Best Price →</a></p>
<p style='font-size:13px;color:#999;margin-top:24px'>Warm regards,<br><strong>SGC LABS® Exports Team</strong><br>Ambala Cantt, Haryana, India 133001</p>",
			);
		}

		$body_html = SGC_CRM_Campaigns::wrap_in_template(
			$content['body'],
			$content['subject'],
			array( 'email' => $contact->email )
		);

		return SGC_CRM_Sender::send_direct( $contact->email, $content['subject'], $body_html );
	}

	// =========================================================================
	// PIPELINE: AJAX HANDLERS
	// =========================================================================

	/**
	 * AJAX — save or update a pipeline lead (contact + pipeline fields).
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function ajax_pipeline_save() {
		self::verify();
		global $wpdb;

		$contact_id   = (int) ( $_POST['contact_id'] ?? 0 );
		$email        = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
		$lead_status  = sanitize_key( wp_unslash( $_POST['lead_status']  ?? 'new' ) );
		$followup_raw = sanitize_text_field( wp_unslash( $_POST['followup_at']   ?? '' ) );
		$followup_note= sanitize_textarea_field( wp_unslash( $_POST['followup_note'] ?? '' ) );

		$allowed_statuses = array( 'new', 'contacted', 'quoted', 'follow_up', 'won', 'lost' );
		if ( ! in_array( $lead_status, $allowed_statuses, true ) ) {
			$lead_status = 'new';
		}

		// Parse followup date.
		$followup_at = null;
		if ( $followup_raw ) {
			$ts = strtotime( $followup_raw . ' 09:00:00' );
			if ( $ts ) {
				$followup_at = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		$ct = $wpdb->prefix . 'sgc_crm_contacts';
		$pipeline_data = array(
			'lead_status'   => $lead_status,
			'followup_at'   => $followup_at,
			'followup_note' => $followup_note,
		);

		if ( $contact_id ) {
			// Update existing contact's pipeline fields.
			$wpdb->update( $ct, $pipeline_data, array( 'id' => $contact_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_send_json_success( array( 'id' => $contact_id ) );
		} else {
			// Try to find by email.
			if ( ! is_email( $email ) ) {
				wp_send_json_error( esc_html__( 'Valid email required.', 'sgc-smart-crm' ) );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$ct}` WHERE email = %s LIMIT 1", $email ) );
			if ( $existing ) {
				$wpdb->update( $ct, $pipeline_data, array( 'id' => (int) $existing->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				wp_send_json_success( array( 'id' => (int) $existing->id ) );
			} else {
				// Insert new minimal contact with pipeline data.
				$insert = array_merge(
					array(
						'email'      => $email,
						'first_name' => '',
						'last_name'  => '',
						'segment'    => 'international',
						'source'     => 'pipeline',
						'status'     => 'subscribed',
						'created_at' => current_time( 'mysql' ),
					),
					$pipeline_data
				);
				$wpdb->insert( $ct, $insert ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$new_id = (int) $wpdb->insert_id;
				wp_send_json_success( array( 'id' => $new_id ) );
			}
		}
	}

	/**
	 * AJAX — update lead_status only (inline dropdown change).
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function ajax_pipeline_update_status() {
		self::verify();
		global $wpdb;

		$contact_id  = (int) ( $_POST['contact_id'] ?? 0 );
		$lead_status = sanitize_key( wp_unslash( $_POST['lead_status'] ?? '' ) );

		$allowed = array( 'none', 'new', 'contacted', 'quoted', 'follow_up', 'won', 'lost' );
		if ( ! $contact_id || ! in_array( $lead_status, $allowed, true ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sgc_crm_contacts',
			array( 'lead_status' => $lead_status ),
			array( 'id'          => $contact_id )
		);

		wp_send_json_success();
	}

	/**
	 * AJAX — look up a contact by email (for pipeline modal autocomplete).
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function ajax_pipeline_lookup() {
		self::verify();

		$email   = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
		$contact = is_email( $email ) ? SGC_CRM_Contacts::get_by_email( $email ) : null;

		if ( $contact ) {
			wp_send_json_success( array(
				'id'         => $contact->id,
				'first_name' => $contact->first_name,
				'last_name'  => $contact->last_name,
				'company'    => $contact->company,
				'country'    => $contact->country,
			) );
		} else {
			wp_send_json_error( 'Not found' );
		}
	}

	/**
	 * AJAX — send pipeline follow-up email manually right now.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function ajax_pipeline_send_followup() {
		self::verify();

		$contact_id = (int) ( $_POST['contact_id'] ?? 0 );
		$contact    = $contact_id ? SGC_CRM_Contacts::get_by_id( $contact_id ) : null;

		if ( ! $contact ) {
			wp_send_json_error( esc_html__( 'Contact not found.', 'sgc-smart-crm' ) );
		}

		$sent = self::do_send_pipeline_email( $contact );

		if ( $sent ) {
			wp_send_json_success( esc_html__( 'Follow-up email sent!', 'sgc-smart-crm' ) );
		} else {
			wp_send_json_error( esc_html__( 'Failed to send. Check your email settings.', 'sgc-smart-crm' ) );
		}
	}

	/**
	 * AJAX — manually trigger all overdue pipeline follow-ups now.
	 *
	 * @since  1.1.0
	 * @return void
	 */
	public static function ajax_pipeline_run_overdue() {
		self::verify();
		global $wpdb;
		$ct = $wpdb->prefix . 'sgc_crm_contacts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$due = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$ct}` WHERE lead_status = 'follow_up' AND followup_at IS NOT NULL AND followup_at <= %s LIMIT 20",
				current_time( 'mysql' )
			)
		);

		$sent = 0;
		foreach ( $due as $contact ) {
			if ( self::do_send_pipeline_email( $contact ) ) {
				$sent++;
			}
			$wpdb->update( $ct, array( 'followup_at' => null ), array( 'id' => (int) $contact->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		wp_send_json_success(
			/* translators: %d: number of emails sent */
			sprintf( esc_html__( 'Sent %d follow-up email(s).', 'sgc-smart-crm' ), $sent )
		);
	}

	/**
	 * Get active sequences for a trigger event.
	 *
	 * @since  1.0.0
	 * @param  string $trigger Trigger event key.
	 * @return array<int, object>
	 */
	private static function get_active_sequences( $trigger ) {
		global $wpdb;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM `' . SGC_CRM_DB::sequences() . '` WHERE trigger_event = %s AND active = 1',
				$trigger
			)
		);
	}

	/**
	 * Schedule a sequence execution for a contact (dedup check included).
	 *
	 * @since  1.0.0
	 * @param  object $seq     Sequence row.
	 * @param  object $contact Contact row.
	 * @return void
	 */
	private static function log_sequence( $seq, $contact ) {
		global $wpdb;

		$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM `" . SGC_CRM_DB::auto_log() . "` WHERE sequence_id = %d AND contact_id = %d AND status IN ('pending','sent') LIMIT 1",
				(int) $seq->id,
				(int) $contact->id
			)
		);
		if ( $exists ) {
			return;
		}

		$run_at = time() + ( (int) $seq->delay_hours * HOUR_IN_SECONDS );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SGC_CRM_DB::auto_log(),
			array(
				'sequence_id'   => (int) $seq->id,
				'contact_id'    => (int) $contact->id,
				'email'         => $contact->email,
				'trigger_event' => $seq->trigger_event,
				'status'        => 'pending',
				'scheduled_at'  => gmdate( 'Y-m-d H:i:s', $run_at ),
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}
}

// Register AJAX + hooks.
SGC_CRM_Automation::register_ajax();
add_action( 'woocommerce_order_status_processing', array( 'SGC_CRM_Automation', 'on_new_order' ) );
add_action( 'woocommerce_order_status_completed',  array( 'SGC_CRM_Automation', 'on_new_order' ) );
add_action( 'sgclabs_inquiry_submitted',           array( 'SGC_CRM_Automation', 'on_new_inquiry' ) );
add_action( 'sgclabs_inquiry_received',            array( 'SGC_CRM_Automation', 'on_new_inquiry' ), 5 ); // v1.3.1: all Site Manager forms, before Invoice Pro (20)
add_action( 'sgc_crm_new_contact_added',           array( 'SGC_CRM_Automation', 'on_new_contact' ) );
