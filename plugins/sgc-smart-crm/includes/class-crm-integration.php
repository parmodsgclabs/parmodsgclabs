<?php
defined('ABSPATH') || exit;

/**
 * SGC Smart CRM Pro — Integration Status + Changelog
 *
 * @package SGC_Smart_CRM
 * @since   1.0.0
 * @version   1.1.1 | Build: 20260516-0630
 */
class SGC_CRM_Integration {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_page'], 99);
        add_action('admin_init', function(){
            update_option('sgc_crm_version', SGC_CRM_VERSION, false); // v1.1.7: use constant, not hardcoded string
            update_option('sgc_crm_active',  '1',     false);
        });
        // Hook: when Invoice Pro adds contact, update CRM stats
        add_action('sgc_iip_inquiry_processed', [__CLASS__, 'on_inquiry_processed'], 10, 2);
        // v1.1.7: Direct SM fallback — fires when IIP is absent or SM form used directly
        add_action('sgclabs_inquiry_received',  [__CLASS__, 'on_sgclabs_inquiry'],   20, 1);
    }

    // ── When Invoice Pro processes inquiry → create/update CRM contact ─────────
    // v1.2.0: previously this only UPDATED an existing contact, and on_sgclabs_inquiry()
    // bails out when Invoice Pro is active — so with the full SGC stack installed a new
    // inquiry NEVER created a CRM contact. Now both paths share upsert_from_inquiry().
    public static function on_inquiry_processed( $inquiry_id, $inquiry_data ) {
        if ( ! is_array( $inquiry_data ) || empty( $inquiry_data['email'] ) ) return;
        $inquiry_data['product'] = $inquiry_data['product'] ?? ( $inquiry_data['product_title'] ?? '' );
        self::upsert_from_inquiry( $inquiry_data, 'invoice_pro', 'Last inquiry: ' . current_time( 'd M Y' ) . ' (via Invoice Pro)' );
    }

    // ── v1.1.7: Site Manager direct inquiry → CRM (no IIP required) ───────────
    public static function on_sgclabs_inquiry( array $data ): void {
        // Skip if IIP is active — IIP's on_sgclabs_inquiry fires sgc_iip_inquiry_processed,
        // which on_inquiry_processed() handles. Avoid double-entry.
        if ( defined( 'SGC_IIP_VERSION' ) ) return;
        self::upsert_from_inquiry( $data, 'site_manager_form', 'Last SM inquiry: ' . current_time( 'd M Y' ) );
    }

    /**
     * Create the CRM contact for an inquiry payload, or refresh it if the email already exists.
     *
     * @param array  $data   Inquiry payload (email, name, company, phone, country, product/subject).
     * @param string $source Contact source label.
     * @param string $note   Note written on an existing contact.
     * @return int Contact ID (0 on failure / invalid email).
     */
    public static function upsert_from_inquiry( array $data, string $source, string $note ): int {
        $email = sanitize_email( $data['email'] ?? '' );
        if ( ! is_email( $email ) ) return 0;

        global $wpdb;
        $ct = SGC_CRM_DB::contacts();
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ct ) ) !== $ct ) return 0; // phpcs:ignore

        $contact = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT id FROM `{$ct}` WHERE email=%s LIMIT 1", $email
        ), ARRAY_A );

        $country = sanitize_text_field( $data['country'] ?? '' );
        $is_dom  = in_array( strtolower( $country ), [ 'india', 'in', 'bharat' ], true );

        if ( $contact ) {
            $wpdb->update( $ct, [ // phpcs:ignore
                'notes'      => $note,
                'updated_at' => current_time( 'mysql' ),
            ], [ 'id' => (int) $contact['id'] ] );
            return (int) $contact['id'];
        }

        $name_parts = explode( ' ', sanitize_text_field( $data['name'] ?? '' ), 2 );
        $insert = [
            'email'         => $email,
            'first_name'    => $name_parts[0] ?? '',
            'last_name'     => $name_parts[1] ?? '',
            'company'       => sanitize_text_field( $data['company'] ?? '' ),
            'phone'         => sanitize_text_field( $data['phone']   ?? '' ),
            'country'       => $country,
            'segment'       => $is_dom ? 'domestic' : 'new_lead',
            'source'        => $source,
            'tags'          => 'inquiry', // NOT NULL column without default — must always be supplied
            'status'        => 'subscribed',
            'ai_score'      => 30,
            'notes'         => 'Inquiry: ' . sanitize_text_field( $data['product'] ?? $data['subject'] ?? '' ),
            'created_at'    => current_time( 'mysql' ),
            'updated_at'    => current_time( 'mysql' ),
        ];
        $wpdb->insert( $ct, $insert ); // phpcs:ignore
        $contact_id = (int) $wpdb->insert_id;
        if ( $contact_id ) do_action( 'sgc_crm_new_contact_added', $contact_id );
        return $contact_id;
    }

    public static function add_page() {
        add_submenu_page(
            'sgc-smart-crm',
            'Changelog & Integration',
            '📋 Changelog',
            'manage_woocommerce',
            'sgc-crm-changelog',
            [__CLASS__, 'render']
        );
    }

    public static function render() {
        global $wpdb;
        $ct = SGC_CRM_DB::contacts();
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$ct}`");
        $from_invoice = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$ct}` WHERE source=%s", 'invoice_pro'
        ));
        ?>
        <div class="wrap">
        <h1>📋 SGC Smart CRM Pro — Changelog & Integration</h1>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:860px;margin-bottom:20px">
          <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px">
            <h3 style="margin-top:0">🔗 SGC Ecosystem Status</h3>
            <table style="width:100%;font-size:13px;border-collapse:collapse">
              <?php
              $eco = [
                'Manager Pro'    => function_exists('sgclabs_ai_call')  ? '✅ Active'      : '⭕ Not active',
                'Invoice Pro'    => defined('SGC_IIP_VERSION')          ? '✅ v'.SGC_IIP_VERSION : '⭕ Not active',
                'Product Manager'=> defined('SGC_PM_VERSION')           ? '✅ v'.SGC_PM_VERSION  : '⭕ Not active',
                'Total Contacts' => $total,
                'From Invoice Pro'=>$from_invoice,
              ];
              foreach($eco as $k=>$v):
              ?>
              <tr style="border-bottom:1px solid #f0f0f0">
                <td style="padding:7px 0;color:#555"><?php echo esc_html($k); ?></td>
                <td style="padding:7px 0;font-weight:600"><?php echo $v; ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
          <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:16px">
            <h3 style="margin-top:0;color:#1b5e20">ℹ️ Integration Notes</h3>
            <div style="font-size:12px;color:#2e7d32;line-height:1.8">
              <strong>Invoice Pro writes to CRM Pro:</strong><br>
              • Won inquiry → contact auto-created<br>
              • source = "invoice_pro" (filterable)<br>
              • segment auto-mapped from buyer type<br>
              • tags: country, buyer_type, domestic/export<br>
              • first_name + last_name split properly<br>
              • ai_score from inquiry health_score<br><br>
              <strong>CRM segments used by Invoice Pro:</strong><br>
              dealer, international, domestic, new_lead
            </div>
          </div>
        </div>

        <!-- DB Status -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;max-width:860px;margin-bottom:20px">
          <h3 style="margin-top:0">🔧 Database Tables</h3>
          <?php
          $tables = [
            SGC_CRM_DB::contacts()  => 'Contacts',
            SGC_CRM_DB::campaigns() => 'Campaigns',
            SGC_CRM_DB::queue()     => 'Email Queue',
            SGC_CRM_DB::sequences() => 'Sequences',
            SGC_CRM_DB::auto_log()  => 'Automation Log',
          ];
          foreach($tables as $tn=>$label){
              $exists = $wpdb->get_var("SHOW TABLES LIKE '$tn'") === $tn;
              $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM `$tn`") : 0;
              echo '<div style="font-size:12px;padding:4px 0">'.($exists?'✅':'❌').' '.$tn.' ('.$count.' records)</div>';
          }
          ?>
        </div>

        <!-- Changelog -->
        <?php
        $changelog = [
            ['v1.0.6','19 Apr 2026','Integration','#7c3aed',
             ['🔗 source=invoice_pro — contacts from Invoice Pro tracked separately',
              '🔗 sgc_iip_inquiry_processed hook — CRM auto-updates on new inquiry',
              '🔗 Segment mapping: buyer_type (oem/bulk/dealer) → CRM segment (dealer/international)',
              '🔗 Tags auto-set: country, buyer_type, domestic/export',
              '🔗 ai_score synced from Invoice Pro health_score',
             ]],
            ['v1.0.6','18 Apr 2026','Previous','#1a1a2e',
             ['Delete All contacts with CSV backup',
              'Auto-backup before bulk delete',
              'AI score column in contacts list',
              'Bulk selection improvements',
              '4-method CSV/Excel import fallback chain',
             ]],
        ];
        foreach($changelog as $e): ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;margin-bottom:12px;max-width:860px">
          <div style="background:<?php echo esc_attr($e[3]); ?>;padding:10px 16px;display:flex;align-items:center;gap:10px">
            <span style="background:rgba(255,255,255,.2);color:#fff;font-size:12px;font-weight:700;padding:2px 10px;border-radius:10px"><?php echo esc_html($e[0]); ?></span>
            <span style="color:rgba(255,255,255,.7);font-size:11px"><?php echo esc_html($e[1]); ?></span>
            <span style="margin-left:auto;background:rgba(255,255,255,.15);color:#fff;font-size:10px;padding:1px 8px;border-radius:8px"><?php echo esc_html($e[2]); ?></span>
          </div>
          <div style="padding:12px 16px;background:#fafafa">
            <?php foreach($e[4] as $ch): ?>
            <div style="font-size:12px;color:#333;padding:4px 0;border-bottom:1px solid #f0f0f0"><?php echo esc_html($ch); ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php
    }
}

SGC_CRM_Integration::init();
