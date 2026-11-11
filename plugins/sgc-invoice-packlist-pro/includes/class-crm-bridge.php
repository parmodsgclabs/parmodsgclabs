<?php
/**
 * SGC IIP — CRM Bridge v1.2.1
 * Listens to sgc_iip_deal_won and syncs won deal into SGC Smart CRM Pro.
 * Manual "Send to CRM" handler lives in class-pipeline.php (unchanged).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'sgc_iip_deal_won', 'sgc_iip_crm_on_deal_won', 10, 1 );

function sgc_iip_crm_on_deal_won( array $inq ) {
    if ( ! class_exists( 'SGC_CRM_DB' ) ) return;
    global $wpdb;
    $crm_table = $wpdb->prefix . 'sgc_crm_contacts';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$crm_table'" ) !== $crm_table ) return;

    $email      = sanitize_email( $inq['email'] ?? '' );
    $inq_id     = intval( $inq['id'] ?? 0 );
    $deal_value = floatval( $inq['grand_total'] ?? $inq['advance_amount'] ?? 0 );
    if ( ! is_email( $email ) ) return;

    $name_parts  = explode( ' ', trim( $inq['name'] ?? '' ), 2 );
    $products    = json_decode( $inq['product_details'] ?? '[]', true );
    $prod_names  = implode( ', ', array_filter( array_column( (array) $products, 'name' ) ) );
    $contact     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$crm_table` WHERE email=%s LIMIT 1", $email ), ARRAY_A );

    if ( ! $contact ) {
        // ── New contact ───────────────────────────────────────────────────────
        $country_lower = strtolower( trim( $inq['country'] ?? '' ) );
        $is_domestic   = in_array( $country_lower, [ 'india', 'in', 'bharat' ], true );
        $segment       = _sgc_iip_map_segment( $inq['buyer_type'] ?? 'unknown', $is_domestic, $deal_value );

        $wpdb->insert( $crm_table, [
            'email'         => $email,
            'first_name'    => $name_parts[0] ?? '',
            'last_name'     => $name_parts[1] ?? '',
            'company'       => sanitize_text_field( $inq['company']  ?? '' ),
            'phone'         => sanitize_text_field( $inq['phone']    ?? '' ),
            'country'       => sanitize_text_field( $inq['country']  ?? '' ),
            'tags'          => _sgc_iip_build_tags( $inq, $prod_names ),
            'segment'       => $segment,
            'source'        => 'invoice_pro_won',
            'status'        => 'subscribed',
            'ai_score'      => min( 100, intval( $inq['health_score'] ?? 60 ) ),
            'last_order_at' => current_time( 'mysql' ),
            'total_orders'  => 1,
            'total_spent'   => $deal_value,
            'notes'         => 'Won via Invoice Pro. PI: ' . ( $inq['pi_number'] ?? '—' ) . ' | ' . ( $prod_names ?: '—' ),
            'created_at'    => current_time( 'mysql' ),
        ] );
        $contact_id = $wpdb->insert_id;
        $is_new     = true;
    } else {
        // ── Existing contact — update LTV + segment ───────────────────────────
        $contact_id  = intval( $contact['id'] );
        $is_new      = false;
        $new_total   = floatval( $contact['total_spent'] ) + $deal_value;
        $new_orders  = intval( $contact['total_orders'] ) + 1;
        $new_segment = _sgc_iip_upgrade_segment( $contact['segment'], $new_total, $new_orders );
        $merged_tags = _sgc_iip_merge_tags( $contact['tags'], _sgc_iip_build_tags( $inq, $prod_names ) );

        $wpdb->update( $crm_table, [
            'last_order_at' => current_time( 'mysql' ),
            'total_orders'  => $new_orders,
            'total_spent'   => $new_total,
            'segment'       => $new_segment,
            'tags'          => $merged_tags,
            'ai_score'      => min( 100, max( intval( $contact['ai_score'] ), intval( $inq['health_score'] ?? 0 ) ) ),
            'updated_at'    => current_time( 'mysql' ),
        ], [ 'id' => $contact_id ] );
    }

    // ── Trigger 'deal_won' automation sequences ───────────────────────────────
    _sgc_iip_trigger_sequences( 'deal_won', $contact_id, $email );

    // ── Log in IIP timeline ───────────────────────────────────────────────────
    $log = $is_new
        ? "CRM sync: New contact created (#$contact_id). Deal value: \${$deal_value}"
        : "CRM sync: Contact #$contact_id updated. +1 order, LTV +\${$deal_value}";
    SGC_IIP_DB::log( $inq_id, 'crm_won_sync', $log, 'system' );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function _sgc_iip_map_segment( string $buyer_type, bool $is_domestic, float $deal_value ): string {
    if ( $deal_value >= 5000 )  return 'vip';
    if ( $is_domestic )         return 'domestic';
    $map = [ 'oem'=>'dealer','bulk'=>'dealer','dealer'=>'dealer','institution'=>'end_user','retail'=>'end_user','sample'=>'new_lead' ];
    return $map[ $buyer_type ] ?? 'international';
}

function _sgc_iip_upgrade_segment( string $current, float $total_spent, int $total_orders ): string {
    if ( $current === 'vip' )                              return 'vip';   // never downgrade
    if ( $total_spent >= 10000 || $total_orders >= 5 )    return 'vip';
    if ( $total_spent >= 3000  || $total_orders >= 2 )    return 'dealer';
    return $current;
}

function _sgc_iip_build_tags( array $inq, string $prod_names ): string {
    $tags = array_filter( [
        sanitize_text_field( $inq['country']    ?? '' ),
        sanitize_text_field( $inq['buyer_type'] ?? '' ),
        'won_export',
        sanitize_text_field( $inq['incoterm']   ?? '' ),
    ] );
    if ( $prod_names ) {
        $slug   = strtolower( preg_replace( '/[^a-z0-9]/i', '_', strtok( $prod_names, ',' ) ) );
        $tags[] = substr( $slug, 0, 25 );
    }
    return implode( ',', array_unique( array_filter( $tags ) ) );
}

function _sgc_iip_merge_tags( string $existing, string $new ): string {
    $all = array_unique( array_filter( array_merge( explode( ',', $existing ), explode( ',', $new ) ) ) );
    return implode( ',', array_slice( $all, 0, 15 ) );
}

function _sgc_iip_trigger_sequences( string $trigger, int $contact_id, string $email ) {
    if ( ! class_exists( 'SGC_CRM_DB' ) ) return;
    global $wpdb;
    $seq_table = SGC_CRM_DB::sequences();
    $log_table = SGC_CRM_DB::auto_log();

    $sequences = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$seq_table` WHERE trigger_event=%s AND active=1", $trigger ) );
    foreach ( (array) $sequences as $seq ) {
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `$log_table` WHERE sequence_id=%d AND contact_id=%d AND status IN ('pending','sent') LIMIT 1",
            $seq->id, $contact_id
        ) );
        if ( $exists ) continue;
        $wpdb->insert( $log_table, [
            'sequence_id'   => $seq->id,
            'contact_id'    => $contact_id,
            'email'         => $email,
            'trigger_event' => $trigger,
            'status'        => 'pending',
            'scheduled_at'  => gmdate( 'Y-m-d H:i:s', time() + intval( $seq->delay_hours ) * HOUR_IN_SECONDS ),
            'created_at'    => current_time( 'mysql' ),
        ] );
    }
}

// ── v1.8.2: Compose email sent → update CRM contact last_contacted ────────────
add_action( 'sgc_iip_compose_email_sent', 'sgc_iip_crm_on_compose_sent', 10, 2 );

function sgc_iip_crm_on_compose_sent( string $to_email, string $subject ): void {
    if ( ! class_exists( 'SGC_CRM_DB' ) ) return;
    if ( ! is_email( $to_email ) ) return;

    global $wpdb;
    $crm_table = $wpdb->prefix . 'sgc_crm_contacts';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$crm_table'" ) !== $crm_table ) return; // phpcs:ignore

    $contact = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        "SELECT id, notes FROM `$crm_table` WHERE email = %s LIMIT 1",
        sanitize_email( $to_email )
    ), ARRAY_A );

    if ( ! $contact ) return; // not in CRM — don't create, just update if exists

    $note_append = 'IIP email: ' . current_time( 'd M Y' ) . ' — ' . wp_strip_all_tags( $subject );
    $existing    = $contact['notes'] ?? '';
    $merged      = trim( $existing . ( $existing ? "\n" : '' ) . $note_append );

    $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $crm_table,
        [
            'notes'      => substr( $merged, 0, 2000 ), // cap at 2000 chars
            'updated_at' => current_time( 'mysql' ),
        ],
        [ 'id' => (int) $contact['id'] ],
        [ '%s', '%s' ],
        [ '%d' ]
    );
}

// ── v1.8.4: B2B price changed → badge open PIs for that product ──────────────
add_action( 'sgc_pm_b2b_price_changed', 'sgc_iip_on_b2b_price_changed', 10, 3 );

function sgc_iip_on_b2b_price_changed( int $product_id, $old_price, $new_price ): void {
    global $wpdb;

    $inq_tbl = $wpdb->prefix . 'sgc_iip_inquiries';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$inq_tbl}'" ) !== $inq_tbl ) return; // phpcs:ignore

    $product_name = get_the_title( $product_id );
    if ( ! $product_name ) return;

    // Find open inquiries/PIs that mention this product in product_details JSON
    // Status: new | quoted | pi_sent | advance_received (not won/lost/cancelled)
    $open_statuses = [ 'new', 'quoted', 'pi_sent', 'advance_received' ];
    $placeholders  = implode( ',', array_fill( 0, count( $open_statuses ), '%s' ) );

    $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
        "SELECT id, product_details FROM `{$inq_tbl}`
         WHERE status IN ({$placeholders}) AND product_details LIKE %s",
        array_merge( $open_statuses, [ '%' . $wpdb->esc_like( $product_name ) . '%' ] )
    ), ARRAY_A );

    $note = sprintf(
        'B2B price updated for "%s": %s → %s (USD). Review PI pricing.',
        $product_name, $old_price, $new_price
    );

    foreach ( (array) $rows as $row ) {
        SGC_IIP_DB::log( (int) $row['id'], 'b2b_price_alert', $note, 'system' );
    }
}
