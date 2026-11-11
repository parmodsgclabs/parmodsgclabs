<?php
/**
 * SGC LABS® Module Control Bar
 *
 * Reusable toggle switch + status bar component.
 * Every module admin page should call sgclabs_module_control_bar()
 * at the top to provide consistent enable/disable + mode controls.
 *
 * @package SGCLabs_Manager_Pro
 * @since   6.6.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render a module control bar with toggle switch + optional radio mode selector.
 *
 * @param array $args {
 *     @type string   $module_id     Unique module identifier (e.g. 'broken-links')
 *     @type string   $module_name   Display name (e.g. 'Broken Link Scanner')
 *     @type string   $module_icon   Emoji icon (e.g. '🔗')
 *     @type string   $option_key    WP option key for active state (e.g. 'sgclabs_broken_links_active')
 *     @type string   $default       Default value for option ('1' or '0')
 *     @type string   $description   Short module description
 *     @type array    $modes         Optional radio mode options: [ ['value'=>'auto','label'=>'Auto','desc'=>'...'], ... ]
 *     @type string   $mode_key      WP option key for mode (e.g. 'sgclabs_broken_links_mode')
 *     @type string   $mode_default  Default mode value
 *     @type array    $extra_toggles Optional extra toggles: [ ['key'=>'option_key','label'=>'Label','default'=>'1'], ... ]
 * }
 */
function sgclabs_module_control_bar( array $args ) {
    $args = wp_parse_args( $args, [
        'module_id'     => '',
        'module_name'   => '',
        'module_icon'   => '⚙️',
        'option_key'    => '',
        'default'       => '1',
        'description'   => '',
        'modes'         => [],
        'mode_key'      => '',
        'mode_default'  => '',
        'extra_toggles' => [],
    ] );

    $is_active = get_option( $args['option_key'], $args['default'] ) === '1';
    $nonce     = wp_create_nonce( 'sgclabs_mcb_nonce' );
    $accent    = '#16a34a'; // green for ON
    ?>
    <div class="sgc-mcb" id="sgc-mcb-<?php echo esc_attr( $args['module_id'] ); ?>" style="
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.04);">

        <!-- Row 1: Module name + master toggle -->
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span style="font-size:22px;"><?php echo $args['module_icon']; ?></span>
            <div style="flex:1; min-width:150px;">
                <div style="font-size:15px; font-weight:700; color:#1e293b;">
                    <?php echo esc_html( $args['module_name'] ); ?>
                </div>
                <?php if ( $args['description'] ): ?>
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                        <?php echo esc_html( $args['description'] ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Toggle Switch -->
            <div style="display:flex; align-items:center; gap:8px;">
                <span id="sgc-mcb-status-<?php echo esc_attr( $args['module_id'] ); ?>"
                    style="font-size:11px; font-weight:600; padding:3px 10px; border-radius:6px;
                    <?php echo $is_active
                        ? 'background:#dcfce7; color:#166534;'
                        : 'background:#fee2e2; color:#991b1b;'; ?>">
                    <?php echo $is_active ? '● Active' : '○ Inactive'; ?>
                </span>

                <div style="position:relative; flex-shrink:0;">
                    <input type="checkbox" class="sgc-mcb-toggle"
                        id="sgc-mcb-tog-<?php echo esc_attr( $args['module_id'] ); ?>"
                        data-option="<?php echo esc_attr( $args['option_key'] ); ?>"
                        data-module="<?php echo esc_attr( $args['module_id'] ); ?>"
                        <?php checked( $is_active ); ?>
                        style="opacity:0; width:0; height:0; position:absolute;">
                    <label for="sgc-mcb-tog-<?php echo esc_attr( $args['module_id'] ); ?>"
                        style="display:inline-block; width:44px; height:24px;
                        background:<?php echo $is_active ? $accent : '#cbd5e1'; ?>;
                        border-radius:12px; cursor:pointer; position:relative; transition:background .25s;"
                        id="sgc-mcb-lbl-<?php echo esc_attr( $args['module_id'] ); ?>">
                        <span style="position:absolute; top:3px; left:<?php echo $is_active ? '23px' : '3px'; ?>;
                            width:18px; height:18px; background:#fff; border-radius:50%;
                            transition:left .25s; box-shadow:0 1px 3px rgba(0,0,0,.2);"
                            id="sgc-mcb-dot-<?php echo esc_attr( $args['module_id'] ); ?>"></span>
                    </label>
                </div>
            </div>
        </div>

        <?php
        // Row 2: Radio mode selector (if modes provided)
        if ( ! empty( $args['modes'] ) && $args['mode_key'] ):
            $current_mode = get_option( $args['mode_key'], $args['mode_default'] );
        ?>
        <div style="margin-top:14px; padding-top:12px; border-top:1px solid #f1f5f9;">
            <div style="font-size:11px; font-weight:600; color:#64748b; margin-bottom:8px; text-transform:uppercase; letter-spacing:.5px;">
                Mode
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <?php foreach ( $args['modes'] as $mode ): ?>
                    <label style="display:flex; align-items:flex-start; gap:6px; cursor:pointer;
                        background:<?php echo $current_mode === $mode['value'] ? '#f0f9ff' : '#f8fafc'; ?>;
                        border:1.5px solid <?php echo $current_mode === $mode['value'] ? '#3b82f6' : '#e2e8f0'; ?>;
                        border-radius:8px; padding:10px 14px; flex:1; min-width:140px; transition:all .2s;">
                        <input type="radio" name="sgc-mcb-mode-<?php echo esc_attr( $args['module_id'] ); ?>"
                            value="<?php echo esc_attr( $mode['value'] ); ?>"
                            class="sgc-mcb-radio"
                            data-mode-key="<?php echo esc_attr( $args['mode_key'] ); ?>"
                            data-module="<?php echo esc_attr( $args['module_id'] ); ?>"
                            <?php checked( $current_mode, $mode['value'] ); ?>
                            style="margin:2px 0 0 0; flex-shrink:0;">
                        <div>
                            <div style="font-size:12px; font-weight:600; color:#1e293b;">
                                <?php echo esc_html( $mode['label'] ); ?>
                            </div>
                            <?php if ( ! empty( $mode['desc'] ) ): ?>
                                <div style="font-size:10px; color:#94a3b8; margin-top:2px; line-height:1.4;">
                                    <?php echo esc_html( $mode['desc'] ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Row 3: Extra toggles (if provided)
        if ( ! empty( $args['extra_toggles'] ) ):
        ?>
        <div style="margin-top:14px; padding-top:12px; border-top:1px solid #f1f5f9;">
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <?php foreach ( $args['extra_toggles'] as $et ):
                    $et_val = get_option( $et['key'], $et['default'] ?? '1' ) === '1';
                ?>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#374151;">
                    <div style="position:relative; flex-shrink:0;">
                        <input type="checkbox" class="sgc-mcb-extra-toggle"
                            data-option="<?php echo esc_attr( $et['key'] ); ?>"
                            data-module="<?php echo esc_attr( $args['module_id'] ); ?>"
                            <?php checked( $et_val ); ?>
                            style="opacity:0; width:0; height:0; position:absolute;">
                        <span style="display:inline-block; width:36px; height:20px;
                            background:<?php echo $et_val ? $accent : '#cbd5e1'; ?>;
                            border-radius:10px; position:relative; transition:background .25s; vertical-align:middle;"
                            class="sgc-mcb-et-track">
                            <span style="position:absolute; top:2px; left:<?php echo $et_val ? '18px' : '2px'; ?>;
                                width:16px; height:16px; background:#fff; border-radius:50%;
                                transition:left .25s; box-shadow:0 1px 2px rgba(0,0,0,.2);"
                                class="sgc-mcb-et-dot"></span>
                        </span>
                    </div>
                    <?php echo esc_html( $et['label'] ); ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php
    // Only output JS once
    static $js_output = false;
    if ( ! $js_output ):
        $js_output = true;
    ?>
    <script>
    (function(){
        var mcbNonce = '<?php echo esc_js( $nonce ); ?>';
        var ajaxUrl  = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';

        // Master toggle handler
        document.querySelectorAll('.sgc-mcb-toggle').forEach(function(cb){
            cb.addEventListener('change', function(){
                var mod    = this.dataset.module;
                var option = this.dataset.option;
                var isOn   = this.checked;
                var lbl    = document.getElementById('sgc-mcb-lbl-' + mod);
                var dot    = document.getElementById('sgc-mcb-dot-' + mod);
                var status = document.getElementById('sgc-mcb-status-' + mod);

                // Instant UI update
                lbl.style.background = isOn ? '#16a34a' : '#cbd5e1';
                dot.style.left = isOn ? '23px' : '3px';
                status.textContent = isOn ? '● Active' : '○ Inactive';
                status.style.background = isOn ? '#dcfce7' : '#fee2e2';
                status.style.color = isOn ? '#166534' : '#991b1b';

                // Save via AJAX
                var fd = new FormData();
                fd.append('action', 'sgclabs_mcb_save');
                fd.append('nonce', mcbNonce);
                fd.append('option', option);
                fd.append('value', isOn ? '1' : '0');
                fetch(ajaxUrl, {method:'POST', body:fd});
            });
        });

        // Radio mode handler
        document.querySelectorAll('.sgc-mcb-radio').forEach(function(radio){
            radio.addEventListener('change', function(){
                var modeKey = this.dataset.modeKey;
                var mod     = this.dataset.module;

                // Update visual selection
                var name = this.name;
                document.querySelectorAll('input[name="'+name+'"]').forEach(function(r){
                    var card = r.closest('label');
                    if (r.checked) {
                        card.style.background = '#f0f9ff';
                        card.style.borderColor = '#3b82f6';
                    } else {
                        card.style.background = '#f8fafc';
                        card.style.borderColor = '#e2e8f0';
                    }
                });

                // Save via AJAX
                var fd = new FormData();
                fd.append('action', 'sgclabs_mcb_save');
                fd.append('nonce', mcbNonce);
                fd.append('option', modeKey);
                fd.append('value', this.value);
                fetch(ajaxUrl, {method:'POST', body:fd});
            });
        });

        // Extra toggle handler
        document.querySelectorAll('.sgc-mcb-extra-toggle').forEach(function(cb){
            cb.addEventListener('change', function(){
                var option = this.dataset.option;
                var isOn   = this.checked;
                var track  = this.nextElementSibling;
                var dot    = track.querySelector('.sgc-mcb-et-dot');

                track.style.background = isOn ? '#16a34a' : '#cbd5e1';
                dot.style.left = isOn ? '18px' : '2px';

                var fd = new FormData();
                fd.append('action', 'sgclabs_mcb_save');
                fd.append('nonce', mcbNonce);
                fd.append('option', option);
                fd.append('value', isOn ? '1' : '0');
                fetch(ajaxUrl, {method:'POST', body:fd});
            });
        });
    })();
    </script>
    <?php
    endif;
}

// ============================================================
// AJAX: Save toggle/radio changes
// ============================================================
add_action( 'wp_ajax_sgclabs_mcb_save', function() {
    check_ajax_referer( 'sgclabs_mcb_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $option = sanitize_key( $_POST['option'] ?? '' );
    $value  = sanitize_text_field( $_POST['value'] ?? '' );

    // Whitelist — only allow sgclabs_ prefixed options
    if ( strpos( $option, 'sgclabs_' ) !== 0 ) {
        wp_send_json_error( 'Invalid option key' );
    }

    update_option( $option, $value );
    wp_send_json_success( [ 'option' => $option, 'value' => $value ] );
} );
