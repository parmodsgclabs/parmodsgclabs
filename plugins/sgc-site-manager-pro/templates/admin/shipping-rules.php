<?php
/**
 * Template: Shipping Rules Admin Page
 *
 * @var array  $rules
 * @var array  $categories
 * @var array  $zones
 * @var bool   $woo_active
 * @var string $nonce
 * @var string $brand
 * @var string $accent
 *
 * @package SGCLabs_Manager
 * @since   6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap sgclabs-wrap" style="max-width:1200px;margin:20px auto;">
    <!-- HEADER -->
    <div style="background:linear-gradient(135deg,<?php echo esc_attr( $brand ); ?>,<?php echo esc_attr( $accent ); ?>);padding:30px;border-radius:12px;margin-bottom:24px;">
        <h1 style="color:#fff;margin:0;font-size:28px;">🚚 Automatic Shipping Rules</h1>
        <p style="color:rgba(255,255,255,.85);margin:8px 0 0;font-size:14px;">WooCommerce shipping rules — weight, price, category, zone based. Priority order.</p>
    </div>

    <?php if ( ! $woo_active ) : ?>
        <div class="notice notice-error"><p>⚠️ WooCommerce is not active. Shipping Rules require WooCommerce.</p></div>
        <?php return; ?>
    <?php endif; ?>

    <!-- ADD NEW RULE FORM -->
    <div id="sgc-sr-form" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:24px;">
        <h2 style="margin:0 0 16px;" id="sgc-sr-form-title">➕ Add New Rule</h2>
        <input type="hidden" id="sr-edit-id" value="0">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
                <label style="font-weight:600;display:block;margin-bottom:4px;">Rule Name *</label>
                <input type="text" id="sr-name" class="large-text" placeholder="e.g., Free Shipping India Above ₹5000">
            </div>
            <div>
                <label style="font-weight:600;display:block;margin-bottom:4px;">Rule Type</label>
                <select id="sr-type" class="large-text" style="height:36px;">
                    <option value="free_shipping">🆓 Free Shipping</option>
                    <option value="flat_rate">💰 Flat Rate</option>
                    <option value="per_kg">⚖️ Per KG Rate</option>
                    <option value="percentage">📊 Percentage of Cart</option>
                </select>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:16px;">
            <div>
                <label style="font-weight:600;display:block;margin-bottom:4px;">Rate Amount (₹)</label>
                <input type="number" id="sr-amount" value="0" min="0" step="0.01" class="large-text" placeholder="0 for free shipping">
            </div>
            <div>
                <label style="font-weight:600;display:block;margin-bottom:4px;">Priority (lower = first)</label>
                <input type="number" id="sr-priority" value="10" min="1" max="100" class="large-text">
            </div>
            <div>
                <label style="font-weight:600;display:block;margin-bottom:4px;">Zone</label>
                <select id="sr-zone" class="large-text" style="height:36px;">
                    <option value="all">🌍 All Zones</option>
                    <option value="india">🇮🇳 India Only</option>
                    <option value="international">🌐 International Only</option>
                </select>
            </div>
        </div>

        <!-- CONDITIONS -->
        <div style="margin-top:20px;padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
            <h3 style="margin:0 0 12px;font-size:15px;">📋 Conditions (Optional — leave 0 to skip)</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;">
                <div>
                    <label style="font-size:12px;color:#666;">Min Weight (kg)</label>
                    <input type="number" id="sr-min-weight" value="0" min="0" step="0.1" class="large-text">
                </div>
                <div>
                    <label style="font-size:12px;color:#666;">Max Weight (kg)</label>
                    <input type="number" id="sr-max-weight" value="0" min="0" step="0.1" class="large-text">
                </div>
                <div>
                    <label style="font-size:12px;color:#666;">Min Cart Price (₹)</label>
                    <input type="number" id="sr-min-price" value="0" min="0" step="1" class="large-text">
                </div>
                <div>
                    <label style="font-size:12px;color:#666;">Max Cart Price (₹)</label>
                    <input type="number" id="sr-max-price" value="0" min="0" step="1" class="large-text">
                </div>
            </div>

            <?php if ( ! empty( $categories ) ) : ?>
            <div style="margin-top:12px;">
                <label style="font-size:12px;color:#666;">Categories (hold Ctrl to multi-select)</label>
                <select id="sr-categories" multiple size="4" class="large-text">
                    <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo intval( $cat['id'] ); ?>"><?php echo esc_html( $cat['name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div style="margin-top:12px;">
                <label style="font-size:12px;color:#666;">Country Codes (comma-separated, e.g., US,UK,DE — leave empty for all)</label>
                <input type="text" id="sr-countries" class="large-text" placeholder="US,UK,DE,AE,AU">
            </div>
        </div>

        <div style="margin-top:16px;display:flex;gap:12px;">
            <button id="sgc-sr-save" class="button button-primary button-large" style="background:<?php echo esc_attr( $accent ); ?>;border-color:<?php echo esc_attr( $accent ); ?>;font-weight:bold;">
                💾 Save Rule
            </button>
            <button id="sgc-sr-cancel" class="button button-secondary button-large" style="display:none;">Cancel Edit</button>
        </div>
    </div>

    <!-- EXISTING RULES TABLE -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <h2 style="margin:0 0 16px;">📜 Existing Rules (<?php echo count( $rules ); ?>)</h2>

        <?php if ( empty( $rules ) ) : ?>
            <p style="color:#999;font-style:italic;">No shipping rules created yet. Add your first rule above.</p>
        <?php else : ?>
            <table class="widefat striped" style="border-radius:8px;overflow:hidden;">
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Rule Name</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Zone</th>
                        <th>Conditions</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sgc-sr-tbody">
                    <?php foreach ( $rules as $rule ) :
                        $cond = $rule['conditions'];
                        $type_labels = [
                            'free_shipping' => '🆓 Free',
                            'flat_rate'     => '💰 Flat',
                            'per_kg'        => '⚖️ /KG',
                            'percentage'    => '📊 %',
                        ];
                        $cond_parts = [];
                        if ( ! empty( $cond['min_weight'] ) ) $cond_parts[] = "≥{$cond['min_weight']}kg";
                        if ( ! empty( $cond['max_weight'] ) ) $cond_parts[] = "≤{$cond['max_weight']}kg";
                        if ( ! empty( $cond['min_price'] ) )  $cond_parts[] = "≥₹{$cond['min_price']}";
                        if ( ! empty( $cond['max_price'] ) )  $cond_parts[] = "≤₹{$cond['max_price']}";
                        if ( ! empty( $cond['categories'] ) ) $cond_parts[] = count( $cond['categories'] ) . ' cats';
                        if ( ! empty( $cond['country_codes'] ) ) $cond_parts[] = $cond['country_codes'];
                    ?>
                    <tr data-id="<?php echo intval( $rule['id'] ); ?>">
                        <td><strong><?php echo intval( $rule['priority'] ); ?></strong></td>
                        <td><?php echo esc_html( $rule['rule_name'] ); ?></td>
                        <td><?php echo esc_html( $type_labels[ $rule['rule_type'] ] ?? $rule['rule_type'] ); ?></td>
                        <td><?php echo $rule['rule_type'] === 'free_shipping' ? '—' : '₹' . number_format( $rule['rate_amount'], 2 ); ?></td>
                        <td><?php echo esc_html( ucfirst( $cond['zone'] ?? 'all' ) ); ?></td>
                        <td style="font-size:12px;"><?php echo esc_html( implode( ', ', $cond_parts ) ?: 'No conditions' ); ?></td>
                        <td>
                            <button class="button sgc-sr-toggle" data-id="<?php echo intval( $rule['id'] ); ?>"
                                    style="<?php echo $rule['is_active'] ? 'background:#d4edda;color:#155724;' : 'background:#f8d7da;color:#721c24;'; ?>">
                                <?php echo $rule['is_active'] ? '✅ ON' : '❌ OFF'; ?>
                            </button>
                        </td>
                        <td>
                            <button class="button sgc-sr-edit" data-rule='<?php echo esc_attr( wp_json_encode( $rule ) ); ?>'>✏️</button>
                            <button class="button sgc-sr-delete" data-id="<?php echo intval( $rule['id'] ); ?>" style="color:#dc3545;">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- INFO -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-top:24px;">
        <h3>ℹ️ How Shipping Rules Work</h3>
        <p style="color:#555;line-height:1.8;">
            Rules are matched in <strong>priority order</strong> (lowest number first). The <strong>first matching rule wins</strong>.<br>
            <strong>Free Shipping</strong>: Sets all shipping to ₹0.<br>
            <strong>Flat Rate</strong>: Fixed amount regardless of weight.<br>
            <strong>Per KG</strong>: Rate × total cart weight in kg.<br>
            <strong>Percentage</strong>: Percentage of cart subtotal.<br>
            Leave condition fields at 0 to skip that condition (acts as "any").
        </p>
    </div>
</div>

<script>
(function($) {
    const nonce = '<?php echo esc_js( $nonce ); ?>';
    const ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    // Save rule
    $('#sgc-sr-save').on('click', function(e) {
        e.preventDefault();
        const name = $('#sr-name').val().trim();
        if (!name) { alert('Rule name is required!'); return; }

        const catSelect = document.getElementById('sr-categories');
        const selCats = catSelect ? Array.from(catSelect.selectedOptions).map(o => o.value) : [];

        $.post(ajaxurl, {
            action: 'sgclabs_shipping_rules_save',
            nonce: nonce,
            rule_id: $('#sr-edit-id').val(),
            rule_name: name,
            rule_type: $('#sr-type').val(),
            rate_amount: $('#sr-amount').val(),
            priority: $('#sr-priority').val(),
            is_active: 1,
            cond_min_weight: $('#sr-min-weight').val(),
            cond_max_weight: $('#sr-max-weight').val(),
            cond_min_price: $('#sr-min-price').val(),
            cond_max_price: $('#sr-max-price').val(),
            cond_categories: selCats,
            cond_zone: $('#sr-zone').val(),
            cond_country_codes: $('#sr-countries').val()
        }, function(res) {
            if (res.success) { location.reload(); }
            else { alert(res.data || 'Error saving rule'); }
        });
    });

    // Edit rule
    $(document).on('click', '.sgc-sr-edit', function() {
        const rule = $(this).data('rule');
        $('#sr-edit-id').val(rule.id);
        $('#sr-name').val(rule.rule_name);
        $('#sr-type').val(rule.rule_type);
        $('#sr-amount').val(rule.rate_amount);
        $('#sr-priority').val(rule.priority);
        const c = rule.conditions || {};
        $('#sr-min-weight').val(c.min_weight || 0);
        $('#sr-max-weight').val(c.max_weight || 0);
        $('#sr-min-price').val(c.min_price || 0);
        $('#sr-max-price').val(c.max_price || 0);
        $('#sr-zone').val(c.zone || 'all');
        $('#sr-countries').val(c.country_codes || '');
        if (c.categories && document.getElementById('sr-categories')) {
            const catSel = document.getElementById('sr-categories');
            Array.from(catSel.options).forEach(o => { o.selected = c.categories.includes(parseInt(o.value)); });
        }
        $('#sgc-sr-form-title').text('✏️ Edit Rule #' + rule.id);
        $('#sgc-sr-cancel').show();
        $('html,body').scrollTop(0);
    });

    // Cancel edit
    $('#sgc-sr-cancel').on('click', function(e) {
        e.preventDefault();
        $('#sr-edit-id').val(0);
        $('#sr-name, #sr-countries').val('');
        $('#sr-amount, #sr-min-weight, #sr-max-weight, #sr-min-price, #sr-max-price').val(0);
        $('#sr-priority').val(10);
        $('#sr-type').val('free_shipping');
        $('#sr-zone').val('all');
        $('#sgc-sr-form-title').text('➕ Add New Rule');
        $(this).hide();
    });

    // Toggle
    $(document).on('click', '.sgc-sr-toggle', function() {
        const id = $(this).data('id');
        $.post(ajaxurl, { action: 'sgclabs_shipping_rules_toggle', nonce: nonce, rule_id: id }, function(res) {
            if (res.success) location.reload();
        });
    });

    // Delete
    $(document).on('click', '.sgc-sr-delete', function() {
        if (!confirm('Delete this rule?')) return;
        const id = $(this).data('id');
        $.post(ajaxurl, { action: 'sgclabs_shipping_rules_delete', nonce: nonce, rule_id: id }, function(res) {
            if (res.success) location.reload();
        });
    });
})(jQuery);
</script>
