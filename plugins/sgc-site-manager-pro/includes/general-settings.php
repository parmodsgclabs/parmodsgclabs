<?php
/**
 * SGC LABS® Manager Pro — General Settings Page
 * Function: sgclabs_general_settings_page()
 * Registered in admin-menu.php → 'sgclabs-general'
 * v7.3.1 — created to fix "function not found" fatal error
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── SAVE HANDLER ─────────────────────────────────────────────────────────────
add_action( 'admin_post_sgclabs_save_general_settings', 'sgclabs_save_general_settings' );
function sgclabs_save_general_settings() {
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sgclabs_general_save' ) ) {
        wp_die( 'Unauthorized' );
    }

    // Company / Contact
    update_option( 'sgclabs_header_phone',  sanitize_text_field( $_POST['header_phone']  ?? '' ) );
    update_option( 'sgclabs_header_email',  sanitize_email(      $_POST['header_email']  ?? '' ) );
    update_option( 'sgclabs_header_active', isset( $_POST['header_active'] ) ? '1' : '0' );

    // WhatsApp
    update_option( 'sgclabs_wa_active', isset( $_POST['wa_active'] ) ? '1' : '0' );
    update_option( 'sgclabs_wa_number', preg_replace( '/[^0-9]/', '', $_POST['wa_number'] ?? '' ) );

    // Announcement bar
    update_option( 'sgclabs_announcement_active', isset( $_POST['announcement_active'] ) ? '1' : '0' );
    update_option( 'sgclabs_announcement_text',   sanitize_text_field( $_POST['announcement_text'] ?? '' ) );

    // Currency
    update_option( 'sgclabs_wc_currency_override', sanitize_text_field( $_POST['wc_currency'] ?? 'USD' ) );
    update_option( 'sgclabs_currency_default',      sanitize_text_field( $_POST['currency_default'] ?? 'USD' ) );

    // Fonts / Performance
    update_option( 'sgclabs_fonts_active',   isset( $_POST['fonts_active'] )   ? '1' : '0' );
    update_option( 'sgclabs_selected_font',  sanitize_text_field( $_POST['selected_font'] ?? 'Inter' ) );
    update_option( 'sgclabs_cwv_active',     isset( $_POST['cwv_active'] )     ? '1' : '0' );
    update_option( 'sgclabs_lazy_images',    isset( $_POST['lazy_images'] )    ? '1' : '0' );

    // SEO defaults
    update_option( 'sgclabs_canonical_active',   isset( $_POST['canonical_active'] )   ? '1' : '0' );
    update_option( 'sgclabs_indexnow_active',     isset( $_POST['indexnow_active'] )     ? '1' : '0' );
    update_option( 'sgclabs_ext_link_nofollow',   isset( $_POST['ext_link_nofollow'] )   ? '1' : '0' );
    update_option( 'sgclabs_ext_link_newtab',     isset( $_POST['ext_link_newtab'] )     ? '1' : '0' );
    update_option( 'sgclabs_auto_redirect_slug',  isset( $_POST['auto_redirect_slug'] )  ? '1' : '0' );
    update_option( 'sgclabs_max_kw_density',      floatval( $_POST['max_kw_density']     ?? 2.5 ) );
    update_option( 'sgclabs_max_meta_keywords',   intval(   $_POST['max_meta_keywords']  ?? 25  ) );

    // AI Reply defaults
    update_option( 'sgclabs_ai_reply_tone',       sanitize_key(          $_POST['ai_reply_tone']       ?? 'professional' ) );
    update_option( 'sgclabs_ai_reply_from_name',  sanitize_text_field(   $_POST['ai_reply_from_name']  ?? '' ) );
    update_option( 'sgclabs_ai_reply_from_email', sanitize_email(        $_POST['ai_reply_from_email'] ?? '' ) );
    update_option( 'sgclabs_ai_reply_signature',  sanitize_textarea_field( wp_unslash( $_POST['ai_reply_signature'] ?? '' ) ) );

    // Quote notify
    update_option( 'sgclabs_quote_notify_email', sanitize_email( $_POST['quote_notify_email'] ?? '' ) );

    // Data safety (v7.5.1)
    update_option( 'sgclabs_delete_data_on_uninstall', isset( $_POST['delete_data_on_uninstall'] ) ? '1' : '0' );

    wp_redirect( admin_url( 'admin.php?page=sgclabs-general&saved=1' ) );
    exit;
}

// ── PAGE RENDER ───────────────────────────────────────────────────────────────
function sgclabs_general_settings_page() {
    $saved = isset( $_GET['saved'] );
    ?>
    <div class="wrap sgclabs-wrap">
    <h1>⚙️ General Settings</h1>

    <?php if ( $saved ): ?>
        <div class="notice notice-success is-dismissible"><p>✅ Settings saved successfully!</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
        <?php wp_nonce_field( 'sgclabs_general_save' ); ?>
        <input type="hidden" name="action" value="sgclabs_save_general_settings">

        <!-- Company / Contact -->
        <div class="sgclabs-section" style="border-left:4px solid #1a73e8;">
            <h2>🏢 Company Contact Info</h2>
            <p style="color:#666;margin-top:-6px;font-size:13px;">Header bar, email signatures, WhatsApp button — sab yahan se control hote hain.</p>
            <table class="form-table">
                <tr>
                    <th>Show Header Bar</th>
                    <td><label><input type="checkbox" name="header_active" value="1" <?php checked( get_option('sgclabs_header_active','1'), '1' ); ?>> Show top header bar with phone + email</label></td>
                </tr>
                <tr>
                    <th>Phone Number</th>
                    <td><input type="text" name="header_phone" value="<?php echo esc_attr( get_option('sgclabs_header_phone','+91 999 641 6935') ); ?>" class="regular-text" placeholder="+91 999 641 6935"></td>
                </tr>
                <tr>
                    <th>Email Address</th>
                    <td><input type="email" name="header_email" value="<?php echo esc_attr( get_option('sgclabs_header_email','sales@labsglass.com') ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Quote Notify Email</th>
                    <td>
                        <input type="email" name="quote_notify_email" value="<?php echo esc_attr( get_option('sgclabs_quote_notify_email', get_option('admin_email')) ); ?>" class="regular-text">
                        <p class="description">New inquiry/quote notifications jaayenge is email pe.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- WhatsApp -->
        <div class="sgclabs-section" style="border-left:4px solid #25d366;">
            <h2>💬 WhatsApp</h2>
            <table class="form-table">
                <tr>
                    <th>Enable WhatsApp Button</th>
                    <td><label><input type="checkbox" name="wa_active" value="1" <?php checked( get_option('sgclabs_wa_active','1'), '1' ); ?>> Show floating WhatsApp button on frontend</label></td>
                </tr>
                <tr>
                    <th>WhatsApp Number</th>
                    <td>
                        <input type="text" name="wa_number" value="<?php echo esc_attr( get_option('sgclabs_wa_number','919996416935') ); ?>" class="regular-text" placeholder="919996416935">
                        <p class="description">Country code ke saath, koi + ya spaces nahi. E.g.: <code>919996416935</code></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Announcement Bar -->
        <div class="sgclabs-section" style="border-left:4px solid #f39c12;">
            <h2>📢 Announcement Bar</h2>
            <table class="form-table">
                <tr>
                    <th>Enable</th>
                    <td><label><input type="checkbox" name="announcement_active" value="1" <?php checked( get_option('sgclabs_announcement_active','1'), '1' ); ?>> Show announcement bar on frontend</label></td>
                </tr>
                <tr>
                    <th>Announcement Text</th>
                    <td><input type="text" name="announcement_text" value="<?php echo esc_attr( get_option('sgclabs_announcement_text','FREE SHIPPING on orders over 5000! | Worldwide Export Available') ); ?>" class="large-text"></td>
                </tr>
            </table>
        </div>

        <!-- Currency -->
        <div class="sgclabs-section" style="border-left:4px solid #27ae60;">
            <h2>💱 Currency</h2>
            <table class="form-table">
                <tr>
                    <th>WooCommerce Currency Override</th>
                    <td>
                        <select name="wc_currency">
                            <?php foreach ( ['USD','INR','EUR','GBP','AED','SGD','AUD','CAD'] as $cur ): ?>
                                <option value="<?php echo $cur; ?>" <?php selected( get_option('sgclabs_wc_currency_override','USD'), $cur ); ?>><?php echo $cur; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">B2B export pages pe yahi currency dikhegi.</p>
                    </td>
                </tr>
                <tr>
                    <th>Default Display Currency</th>
                    <td>
                        <select name="currency_default">
                            <?php foreach ( ['USD','INR','EUR','GBP','AED'] as $cur ): ?>
                                <option value="<?php echo $cur; ?>" <?php selected( get_option('sgclabs_currency_default','USD'), $cur ); ?>><?php echo $cur; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Performance / Fonts -->
        <div class="sgclabs-section" style="border-left:4px solid #9b59b6;">
            <h2>⚡ Performance & Fonts</h2>
            <table class="form-table">
                <tr>
                    <th>Google Font</th>
                    <td>
                        <label><input type="checkbox" name="fonts_active" value="1" <?php checked( get_option('sgclabs_fonts_active','1'), '1' ); ?>> Apply selected font to entire site</label>
                        <select name="selected_font" style="margin-left:10px;">
                            <?php foreach ( ['Inter','Roboto','Open Sans','Lato','Poppins','Nunito','Source Sans Pro'] as $font ): ?>
                                <option value="<?php echo $font; ?>" <?php selected( get_option('sgclabs_selected_font','Inter'), $font ); ?>><?php echo $font; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Core Web Vitals</th>
                    <td><label><input type="checkbox" name="cwv_active" value="1" <?php checked( get_option('sgclabs_cwv_active','1'), '1' ); ?>> Enable CWV optimizations (LCP, CLS fixes)</label></td>
                </tr>
                <tr>
                    <th>Lazy Load Images</th>
                    <td><label><input type="checkbox" name="lazy_images" value="1" <?php checked( get_option('sgclabs_lazy_images','1'), '1' ); ?>> Enable lazy loading on product images</label></td>
                </tr>
            </table>
        </div>

        <!-- SEO Defaults -->
        <div class="sgclabs-section" style="border-left:4px solid #e74c3c;">
            <h2>🔍 SEO Defaults</h2>
            <table class="form-table">
                <tr>
                    <th>Canonical Tags</th>
                    <td><label><input type="checkbox" name="canonical_active" value="1" <?php checked( get_option('sgclabs_canonical_active','1'), '1' ); ?>> Auto-add canonical URL tags</label></td>
                </tr>
                <tr>
                    <th>IndexNow</th>
                    <td><label><input type="checkbox" name="indexnow_active" value="1" <?php checked( get_option('sgclabs_indexnow_active','1'), '1' ); ?>> Auto-ping IndexNow on publish/update</label></td>
                </tr>
                <tr>
                    <th>External Links</th>
                    <td>
                        <label><input type="checkbox" name="ext_link_nofollow" value="1" <?php checked( get_option('sgclabs_ext_link_nofollow','1'), '1' ); ?>> Add nofollow to external links</label><br>
                        <label><input type="checkbox" name="ext_link_newtab"   value="1" <?php checked( get_option('sgclabs_ext_link_newtab','1'),   '1' ); ?>> Open external links in new tab</label>
                    </td>
                </tr>
                <tr>
                    <th>Auto Redirect on Slug Change</th>
                    <td><label><input type="checkbox" name="auto_redirect_slug" value="1" <?php checked( get_option('sgclabs_auto_redirect_slug','1'), '1' ); ?>> Auto-create 301 redirect when product slug changes</label></td>
                </tr>
                <tr>
                    <th scope="row">🗑️ Delete data on uninstall</th>
                    <td><label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( get_option('sgclabs_delete_data_on_uninstall','0'), '1' ); ?>> Drop inquiries, quotes, keywords, redirects and all settings when the plugin is deleted</label>
                    <p class="description" style="color:#b91c1c">Default OFF — uninstalling only clears cron jobs and caches, your business data stays in the database.</p></td>
                </tr>
                <tr>
                    <th>Max Keyword Density (%)</th>
                    <td><input type="number" name="max_kw_density" value="<?php echo esc_attr( get_option('sgclabs_max_kw_density','2.5') ); ?>" min="0.5" max="10" step="0.1" style="width:80px;"> %</td>
                </tr>
                <tr>
                    <th>Max Meta Keywords</th>
                    <td><input type="number" name="max_meta_keywords" value="<?php echo esc_attr( get_option('sgclabs_max_meta_keywords','25') ); ?>" min="1" max="100" style="width:80px;"></td>
                </tr>
            </table>
        </div>

        <!-- AI Reply Defaults -->
        <div class="sgclabs-section" style="border-left:4px solid #1a73e8;">
            <h2>🤖 AI Reply Defaults</h2>
            <p style="color:#666;margin-top:-6px;font-size:13px;">AI Inquiry Reply Center ke liye default settings.</p>
            <table class="form-table">
                <tr>
                    <th>Reply Tone</th>
                    <td>
                        <select name="ai_reply_tone">
                            <?php foreach ( ['professional'=>'Professional','formal'=>'Formal','friendly'=>'Friendly','technical'=>'Technical'] as $k => $v ): ?>
                                <option value="<?php echo $k; ?>" <?php selected( get_option('sgclabs_ai_reply_tone','professional'), $k ); ?>><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>From Name</th>
                    <td><input type="text" name="ai_reply_from_name" value="<?php echo esc_attr( get_option('sgclabs_ai_reply_from_name','SGC Labs Team') ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>From Email</th>
                    <td><input type="email" name="ai_reply_from_email" value="<?php echo esc_attr( get_option('sgclabs_ai_reply_from_email','sales@labsglass.com') ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Email Signature</th>
                    <td><textarea name="ai_reply_signature" rows="4" class="large-text"><?php echo esc_textarea( get_option('sgclabs_ai_reply_signature', "Best regards,\nSGC Labs® Sales Team\n+91 999 641 6935 | sales@labsglass.com | labsglass.com") ); ?></textarea></td>
                </tr>
            </table>
        </div>

        <?php submit_button( '💾 Save General Settings' ); ?>
    </form>
    </div>
    <?php
}
