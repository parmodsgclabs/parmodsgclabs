<?php
/**
 * SGC Smart CRM Pro — Campaigns
 *
 * Campaign creation, AI generation, scheduling, send management.
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SGC_CRM_Campaigns
 *
 * @since 1.0.0
 * @version   1.0.4 | Build: 20260516-0630
 */
class SGC_CRM_Campaigns {

	/**
	 * Register AJAX handlers.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function register_ajax() {
		$actions = array(
			'sgc_crm_create_campaign'   => 'ajax_create',
			'sgc_crm_update_campaign'   => 'ajax_update',
			'sgc_crm_delete_campaign'   => 'ajax_delete',
			'sgc_crm_ai_generate'       => 'ajax_ai_generate',
			'sgc_crm_schedule_campaign' => 'ajax_schedule',
			'sgc_crm_send_campaign_now' => 'ajax_send_now',
			'sgc_crm_send_test_email'   => 'ajax_send_test',
			'sgc_crm_get_campaigns'     => 'ajax_get_list',
			'sgc_crm_get_campaign'      => 'ajax_get_single',
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
	// AI GENERATION
	// =========================================================================

	/**
	 * AJAX — generate campaign content via AI.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_ai_generate() {
		self::verify();

		$type     = sanitize_key( wp_unslash( $_POST['type']     ?? 'newsletter' ) );
		$topic    = sanitize_text_field( wp_unslash( $_POST['topic']    ?? '' ) );
		$segment  = sanitize_key( wp_unslash( $_POST['segment']  ?? 'all' ) );
		$tone     = sanitize_text_field( wp_unslash( $_POST['tone']     ?? 'professional' ) );
		$audience = sanitize_key( wp_unslash( $_POST['audience'] ?? 'all' ) );

		// Audience context for AI.
		$audience_ctx = array(
			'student'      => 'college/university students and research scholars. Focus on affordability, academic quality, bulk student pricing, COD option.',
			'teacher'      => 'professors and academic lab instructors. Emphasize accuracy, research-grade quality, institutional purchase support, GST invoicing.',
			'dealer'       => 'scientific glassware dealers and distributors. Highlight bulk discounts, MOQ flexibility, trade pricing, consistent quality, fast dispatch.',
			'end_user'     => 'direct lab users in hospitals, pharma, industry. Focus on quality certification, delivery speed, after-sales support.',
			'glass_blower' => 'scientific glass blowing workshops and fabricators. Emphasize raw tubing, rods, semi-finished blanks, borosilicate 3.3 material specs.',
			'semi_finished' => 'custom fabrication clients needing semi-finished goods. Highlight custom shapes, bulk supply of blanks, export-ready packaging.',
			'international' => 'international buyers and export clients. Emphasize export experience since 1965, CE marking, worldwide shipping, USD pricing.',
			'domestic'     => 'domestic Indian buyers. Focus on GST compliance, pan-India delivery, rupee pricing, bulk purchase benefits.',
			'all'          => 'B2B scientific glassware buyers. Professional tone.',
		);
		$aud_desc = $audience_ctx[ $audience ] ?? $audience_ctx['all'];

		$prompts = array(
			'newsletter' =>
				"Create a modern B2B newsletter email for SGC LABS® (scientific glassware manufacturer since 1965, Ambala, India).\n"
				. "Topic: {$topic}\nAudience: {$aud_desc}\nTone: {$tone}\n"
				. "Design: 2025 modern email — clean header, 2-3 content cards, product spotlight, footer CTA.\n"
				. "CTA destination: labsglass.com\n"
				. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML with inline CSS>\"}",

			'product_promo' =>
				"Create a modern product promotion email for SGC LABS®.\n"
				. "Products: {$topic}\nAudience: {$aud_desc}\nTone: {$tone}\n"
				. "Include: hero section, product grid cards, pricing/discount highlight, strong CTA.\n"
				. "Design: 2025 style — bold typography, product cards with specs, urgency element.\n"
				. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML with inline CSS>\"}",

			'reorder_reminder' =>
				"Create a reorder reminder email for SGC LABS® to a customer due for restock.\n"
				. "Products they likely need: {$topic}\nAudience: {$aud_desc}\nTone: warm and helpful\n"
				. "Include: personalized greeting with {{first_name}}, reminder their last order is running low,\n"
				. "easy reorder CTA, mention bulk discount if they increase qty.\n"
				. "Design: short, personal, mobile-friendly 2025 style.\n"
				. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML with inline CSS>\"}",

			'announcement' =>
				"Create a business announcement email for SGC LABS®.\n"
				. "Announcement: {$topic}\nAudience: {$aud_desc}\nTone: {$tone}\n"
				. "Design: clean, professional, single-column 2025 style with bold announcement headline.\n"
				. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML with inline CSS>\"}",

			'student_special' =>
				"Create a special offer email for students and academic labs from SGC LABS®.\n"
				. "Topic: {$topic}\n"
				. "Include: student discount offer (upto 15% on catalogue price), academic quality assurance,\n"
				. "mention lab practicals, research projects, fast delivery to college addresses.\n"
				. "Tone: friendly, encouraging, academic yet professional.\n"
				. "Design: bright, modern 2025 — inspire curiosity.\n"
				. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML with inline CSS>\"}",

			'dealer_special' =>
				"Create a dealer/distributor exclusive email from SGC LABS®.\n"
				. "Topic: {$topic}\n"
				. "Include: trade pricing tiers, bulk order benefits, exclusive dealer margin mention,\n"
				. "dedicated support, export/import capability, consistent quality supply.\n"
				. "Tone: business-to-business, professional, partnership-focused.\n"
				. "Design: corporate modern 2025 style.\n"
				. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML with inline CSS>\"}",

			'glass_blower_special' =>
				"Create an email for scientific glass blowing workshops from SGC LABS®.\n"
				. "Topic: {$topic}\n"
				. "Include: borosilicate 3.3 raw tubes/rods specs, semi-finished blanks availability,\n"
				. "custom profile options, bulk raw material supply, technical dimensions.\n"
				. "Tone: technical, peer-to-peer, craftsperson language.\n"
				. "Design: clean technical 2025 style.\n"
				. "Return JSON: {\"subject\":\"...\",\"preheader\":\"...\",\"body_html\":\"<full responsive HTML with inline CSS>\"}",
		);

		$prompt = $prompts[ $type ] ?? $prompts['newsletter'];
		$system = 'Expert B2B email copywriter for SGC LABS® scientific glassware. Create modern 2025-style emails with inline CSS. Return ONLY valid JSON {subject, preheader, body_html}. No markdown, no explanation.';

		$response = SGC_CRM_AI::call( $prompt, 2000, $system );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$clean  = preg_replace( '/```(?:json)?|```/', '', $response );
		$result = json_decode( trim( $clean ), true );

		if ( ! is_array( $result ) || empty( $result['subject'] ) ) {
			wp_send_json_error( esc_html__( 'AI returned unexpected format. Please try again.', 'sgc-smart-crm' ) );
		}

		$result['body_html'] = self::wrap_in_template( $result['body_html'] ?? '', $result['subject'] );
		wp_send_json_success( $result );
	}

	// =========================================================================
	// EMAIL TEMPLATE
	// =========================================================================

	/**
	 * Wrap email body in SGC LABS® branded HTML template.
	 *
	 * @since  1.0.0
	 * @param  string               $body_html   Inner HTML content.
	 * @param  string               $subject     Email subject (for <title>).
	 * @param  array<string, mixed> $options     Optional: email, track_token.
	 * @return string Full HTML email.
	 */
	public static function wrap_in_template( $body_html, $subject = '', $options = array() ) {
	// Normalize variable names.
	$email_addr = $options['email']       ?? '';
	$token      = $options['track_token'] ?? '';

	// Build unsubscribe URL.
	$unsub_url = add_query_arg(
		array_filter(
			array(
				'sgc_crm_unsub' => '1',
				'token'         => $token,
				'email'         => $email_addr,
			)
		),
		home_url( '/' )
	);

	// Open tracking pixel.
	$track_pixel_html = '';
	if ( $token && get_option( 'sgc_crm_track_opens', '1' ) ) {
		$pixel_url        = admin_url( 'admin-ajax.php' ) . '?action=sgc_crm_track_open&token=' . rawurlencode( $token );
		$track_pixel_html = '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" style="display:none;border:0" alt="">';
	}

	return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . esc_html( $subject ) . '</title>
</head>
<body style="margin:0;padding:0;background-color:#F0EDE8;font-family:\'Helvetica Neue\',Arial,sans-serif;-webkit-font-smoothing:antialiased">

<!-- Email wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#F0EDE8;padding:32px 16px">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%">

  <!-- TOP PREHEADER (hidden) -->
  <tr><td style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;color:#F0EDE8;line-height:1px">
    ' . esc_html( $subject ) . ' &#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;
  </td></tr>

  <!-- HEADER -->
  <tr><td style="background-color:#1a1a2e;border-radius:12px 12px 0 0;padding:0;overflow:hidden">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td style="padding:28px 36px">
          <!-- Logo row -->
          <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
              <td>
                <div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#e4b04a;margin-bottom:6px">Since 1965</div>
                <div style="font-size:26px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;line-height:1">SGC LABS<sup style="font-size:14px;vertical-align:super">®</sup></div>
                <div style="font-size:12px;color:#a0a0c0;margin-top:4px;letter-spacing:1px">SCIENTIFIC GLASSWARE</div>
              </td>
              <td align="right" style="vertical-align:middle">
                <div style="background:rgba(228,176,74,0.15);border:1px solid rgba(228,176,74,0.4);border-radius:6px;padding:6px 14px;display:inline-block">
                  <a href="https://labsglass.com" style="color:#e4b04a;text-decoration:none;font-size:12px;font-weight:600">labsglass.com &#x2192;</a>
                </div>
              </td>
            </tr>
          </table>
          <!-- Divider -->
          <div style="height:1px;background:rgba(255,255,255,0.1);margin:20px 0"></div>
          <!-- Subject as hero text -->
          <div style="font-size:22px;font-weight:700;color:#ffffff;line-height:1.3">' . esc_html( $subject ) . '</div>
        </td>
      </tr>
    </table>
  </td></tr>

  <!-- BODY -->
  <tr><td style="background-color:#ffffff;padding:36px">
' . $body_html . '
  </td></tr>

  <!-- DIVIDER BAND -->
  <tr><td style="background-color:#1a1a2e;padding:0;height:4px">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td width="33%" style="background:#1a1a2e;height:4px"></td>
        <td width="33%" style="background:#e4b04a;height:4px"></td>
        <td width="34%" style="background:#27ae60;height:4px"></td>
      </tr>
    </table>
  </td></tr>

  <!-- FOOTER -->
  <tr><td style="background-color:#2c2c3e;border-radius:0 0 12px 12px;padding:24px 36px">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td>
          <!-- Footer links -->
          <table cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:14px">
            <tr>
              <td><a href="https://labsglass.com/products" style="color:#a0a0c0;text-decoration:none;font-size:11px;margin-right:16px">Products</a></td>
              <td><a href="https://labsglass.com/contact" style="color:#a0a0c0;text-decoration:none;font-size:11px;margin-right:16px">Contact</a></td>
              <td><a href="https://labsglass.com/about" style="color:#a0a0c0;text-decoration:none;font-size:11px;margin-right:16px">About</a></td>
              <td><a href="https://sgclabglass.com" style="color:#a0a0c0;text-decoration:none;font-size:11px">sgclabglass.com</a></td>
            </tr>
          </table>
          <!-- Footer text -->
          <div style="font-size:11px;color:#666680;line-height:1.6">
            ' . esc_html( get_option( 'sgc_crm_footer_text', 'SGC LABS® | Scientific Glassware | Ambala, Haryana, India' ) ) . '<br>
            <a href="mailto:sales@labsglass.com" style="color:#e4b04a;text-decoration:none">sales@labsglass.com</a>
            &nbsp;&bull;&nbsp;
            <a href="https://labsglass.com" style="color:#e4b04a;text-decoration:none">labsglass.com</a>
          </div>
        </td>
        <td align="right" style="vertical-align:bottom">
          <a href="' . esc_url( $unsub_url ) . '" style="color:#555570;text-decoration:none;font-size:10px">Unsubscribe</a>
        </td>
      </tr>
    </table>
  </td></tr>

</table>
</td></tr>
</table>

' . $track_pixel_html . '
</body>
</html>';
	}

	// =========================================================================
	// CRUD
	// =========================================================================

	/**
	 * AJAX — create a campaign.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_create() {
		self::verify();
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SGC_CRM_DB::campaigns(),
			array(
				'name'           => sanitize_text_field( wp_unslash( $_POST['name']           ?? esc_html__( 'New Campaign', 'sgc-smart-crm' ) ) ),
				'type'           => sanitize_key( wp_unslash( $_POST['type']                  ?? 'newsletter' ) ),
				'audience'       => sanitize_key( wp_unslash( $_POST['audience']              ?? 'all' ) ),
				'subject'        => sanitize_text_field( wp_unslash( $_POST['subject']        ?? '' ) ),
				'preheader'      => sanitize_text_field( wp_unslash( $_POST['preheader']      ?? '' ) ),
				'body_html'      => wp_kses_post( wp_unslash( $_POST['body_html']             ?? '' ) ),
				'from_name'      => sanitize_text_field( wp_unslash( $_POST['from_name']      ?? get_option( 'sgc_crm_from_name' ) ) ),
				'from_email'     => sanitize_email( wp_unslash( $_POST['from_email']          ?? get_option( 'sgc_crm_from_email' ) ) ),
				'reply_to'       => sanitize_email( wp_unslash( $_POST['reply_to']            ?? get_option( 'sgc_crm_reply_to' ) ) ),
				'segment_filter' => sanitize_key( wp_unslash( $_POST['segment_filter']        ?? 'all' ) ),
				'tags_filter'    => sanitize_text_field( wp_unslash( $_POST['tags_filter']    ?? '' ) ),
				'ai_generated'   => (int) ( $_POST['ai_generated'] ?? 0 ),
				'status'         => 'draft',
				'created_at'     => current_time( 'mysql' ),
			)
		);

		wp_send_json_success( array( 'id' => (int) $wpdb->insert_id ) );
	}

	/**
	 * AJAX — update an existing campaign.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_update() {
		self::verify();
		global $wpdb;

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'Invalid campaign ID.', 'sgc-smart-crm' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SGC_CRM_DB::campaigns(),
			array(
				'name'           => sanitize_text_field( wp_unslash( $_POST['name']           ?? '' ) ),
				'audience'       => sanitize_key( wp_unslash( $_POST['audience']              ?? 'all' ) ),
				'subject'        => sanitize_text_field( wp_unslash( $_POST['subject']        ?? '' ) ),
				'preheader'      => sanitize_text_field( wp_unslash( $_POST['preheader']      ?? '' ) ),
				'body_html'      => wp_kses_post( wp_unslash( $_POST['body_html']             ?? '' ) ),
				'segment_filter' => sanitize_key( wp_unslash( $_POST['segment_filter']        ?? 'all' ) ),
				'tags_filter'    => sanitize_text_field( wp_unslash( $_POST['tags_filter']    ?? '' ) ),
			),
			array( 'id' => $id )
		);

		wp_send_json_success();
	}

	/**
	 * AJAX — delete a campaign.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_delete() {
		self::verify();
		global $wpdb;

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'Invalid campaign ID.', 'sgc-smart-crm' ) );
		}

		$wpdb->delete( SGC_CRM_DB::campaigns(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_send_json_success();
	}

	/**
	 * AJAX — schedule a campaign to send at a future datetime.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_schedule() {
		self::verify();
		global $wpdb;

		$id       = (int) ( $_POST['id'] ?? 0 );
		$datetime = sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) );

		if ( ! $id || ! $datetime ) {
			wp_send_json_error( esc_html__( 'Campaign ID and datetime are required.', 'sgc-smart-crm' ) );
		}

		$timestamp = strtotime( $datetime );
		if ( ! $timestamp || $timestamp <= time() ) {
			wp_send_json_error( esc_html__( 'Scheduled time must be in the future.', 'sgc-smart-crm' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SGC_CRM_DB::campaigns(),
			array( 'status' => 'scheduled', 'scheduled_at' => $datetime ),
			array( 'id'     => $id )
		);

		wp_schedule_single_event( $timestamp, 'sgc_crm_fire_campaign', array( $id ) );
		wp_send_json_success( array( 'scheduled_at' => $datetime ) );
	}

	/**
	 * AJAX — queue a campaign to send immediately.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_send_now() {
		self::verify();

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'Invalid campaign ID.', 'sgc-smart-crm' ) );
		}

		$queued = SGC_CRM_Sender::queue_campaign( $id );
		wp_send_json_success(
			array(
				'queued'  => $queued,
				/* translators: %d: number of emails queued */
				'message' => sprintf( esc_html__( '%d emails queued. Sending in batches.', 'sgc-smart-crm' ), $queued ),
			)
		);
	}

	/**
	 * AJAX — send a test email for a campaign.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_send_test() {
		self::verify();

		$id    = (int) ( $_POST['id'] ?? 0 );
		$email = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );

		if ( ! $id || ! is_email( $email ) ) {
			wp_send_json_error( esc_html__( 'Valid campaign ID and email required.', 'sgc-smart-crm' ) );
		}

		$sent = SGC_CRM_Sender::send_test( $id, $email );
		if ( $sent ) {
			wp_send_json_success(
				/* translators: %s: email address */
				sprintf( esc_html__( 'Test email sent to %s', 'sgc-smart-crm' ), $email )
			);
		} else {
			wp_send_json_error( esc_html__( 'Send failed. Check SMTP / wp_mail() configuration.', 'sgc-smart-crm' ) );
		}
	}

	/**
	 * AJAX — get campaign list.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_get_list() {
		self::verify();
		global $wpdb;

		$type   = sanitize_key( wp_unslash( $_POST['type']   ?? '' ) );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$table  = SGC_CRM_DB::campaigns();
		$where  = '1=1';
		$values = array();

		if ( $type   ) { $where .= ' AND type = %s';   $values[] = $type; }
		if ( $status ) { $where .= ' AND status = %s'; $values[] = $status; }

		$sql = "SELECT id,name,type,subject,status,segment_filter,total_sent,total_opened,total_clicked,scheduled_at,sent_at,ai_generated,created_at
		        FROM `{$table}` WHERE {$where} ORDER BY created_at DESC LIMIT 100";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = empty( $values ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) );
		wp_send_json_success( $rows );
	}

	/**
	 * AJAX — get single campaign with body HTML.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function ajax_get_single() {
		self::verify();
		global $wpdb;

		$id  = (int) ( $_POST['id'] ?? 0 );
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM `' . SGC_CRM_DB::campaigns() . '` WHERE id = %d', $id )
		);
		wp_send_json_success( $row );
	}

	/**
	 * Create a campaign programmatically (used by automation).
	 *
	 * @since  1.0.0
	 * @param  array<string, mixed> $data Campaign data.
	 * @return int Inserted campaign ID.
	 */
	public static function create_internal( $data ) {
		global $wpdb;
		$defaults = array(
			'status'         => 'draft',
			'from_name'      => get_option( 'sgc_crm_from_name',  'SGC LABS® Glassware' ),
			'from_email'     => get_option( 'sgc_crm_from_email', 'sales@labsglass.com' ),
			'reply_to'       => get_option( 'sgc_crm_reply_to',   '' ),
			'segment_filter' => 'all',
			'created_at'     => current_time( 'mysql' ),
		);
		$wpdb->insert( SGC_CRM_DB::campaigns(), array_merge( $defaults, $data ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}
}

SGC_CRM_Campaigns::register_ajax();

// Cron: fire scheduled campaign.
add_action(
	'sgc_crm_fire_campaign',
	function ( $campaign_id ) {
		SGC_CRM_Sender::queue_campaign( (int) $campaign_id );
	}
);
