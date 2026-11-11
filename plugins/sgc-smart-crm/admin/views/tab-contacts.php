<?php
/**
 * SGC Smart CRM Pro — Contacts Tab v1.0.3
 * One-click bulk import with live background progress
 * @package SGC_Smart_CRM
 * @since   1.0.3
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$active_job = get_option( 'sgc_crm_import_job', null );
$job_active = $active_job && in_array( $active_job['status'] ?? '', array( 'pending', 'running' ), true );
$job_pct    = $job_active && $active_job['total'] > 0 ? intval( $active_job['processed'] / $active_job['total'] * 100 ) : 0;
?>
<div class="sgc-crm-contacts-page">

<!-- ONE-CLICK IMPORT ZONE -->
<div class="sgc-crm-box sgc-import-box" id="import-zone">

  <!-- DROP TARGET -->
  <div id="drop-target">
    <input type="file" id="bulk-import-file" accept=".csv,.xlsx,.xls" style="display:none">
    <div class="sgc-drop-icon">&#x2B06;</div>
    <div class="sgc-drop-headline"><?php esc_html_e( 'Drop your contacts file here', 'sgc-smart-crm' ); ?></div>
    <div class="sgc-drop-sub"><?php esc_html_e( 'Google Contacts CSV · Excel · Any CSV — handles 50,000+ contacts in background', 'sgc-smart-crm' ); ?></div>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:16px 0 12px">
      <button class="sgc-crm-btn primary" id="btn-browse-file" style="padding:10px 24px;font-size:14px">
        &#x1F4C2; <?php esc_html_e( 'Browse File', 'sgc-smart-crm' ); ?>
      </button>
      <a href="https://contacts.google.com" target="_blank" rel="noopener noreferrer"
         class="sgc-crm-btn secondary" style="padding:10px 18px;display:inline-flex;align-items:center;gap:6px;font-size:13px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        <?php esc_html_e( 'Export from Google Contacts &#x2197;', 'sgc-smart-crm' ); ?>
      </a>
    </div>
    <div class="sgc-drop-hint">
      <strong><?php esc_html_e( 'Google Contacts:', 'sgc-smart-crm' ); ?></strong>
      contacts.google.com &#x2192; Export &#x2192; Google CSV &#x2192; Upload here
    </div>
    <div class="sgc-drop-hint" style="margin-top:4px;color:#bbb">
      <?php esc_html_e( 'Supports: Google CSV · Excel XLSX · Generic CSV. Auto-detects columns. No mapping needed.', 'sgc-smart-crm' ); ?>
    </div>
  </div>

  <!-- PROGRESS PANEL (shown during import) -->
  <div id="import-progress-panel" style="display:<?php echo $job_active ? 'block' : 'none'; ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
      <div>
        <div style="font-size:16px;font-weight:600;color:#1a1a2e" id="prog-title">
          <?php echo $job_active ? esc_html__( 'Import running in background…', 'sgc-smart-crm' ) : esc_html__( 'Processing…', 'sgc-smart-crm' ); ?>
        </div>
        <div style="font-size:12px;color:#888;margin-top:3px" id="prog-file">
          <?php echo $job_active ? esc_html( $active_job['filename'] ?? '' ) : ''; ?>
        </div>
      </div>
      <button class="sgc-crm-btn secondary" id="btn-cancel-import" style="font-size:12px;padding:4px 12px">
        &#x2715; <?php esc_html_e( 'Cancel', 'sgc-smart-crm' ); ?>
      </button>
    </div>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="flex:1;height:12px;background:#e0e0e0;border-radius:6px;overflow:hidden">
        <div id="prog-fill" style="height:100%;width:<?php echo $job_pct; ?>%;background:#1a1a2e;border-radius:6px;transition:width .4s ease"></div>
      </div>
      <span id="prog-pct" style="font-size:15px;font-weight:700;color:#1a1a2e;min-width:40px;text-align:right"><?php echo $job_pct; ?>%</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:14px">
      <?php
      $pstats = array(
        array( 'id' => 'prog-processed', 'label' => __('Processed','sgc-smart-crm'), 'color' => '#1a1a2e' ),
        array( 'id' => 'prog-added',     'label' => __('Added','sgc-smart-crm'),     'color' => '#27ae60' ),
        array( 'id' => 'prog-updated',   'label' => __('Updated','sgc-smart-crm'),   'color' => '#2271b1' ),
        array( 'id' => 'prog-skipped',   'label' => __('Skipped','sgc-smart-crm'),   'color' => '#aaa'    ),
        array( 'id' => 'prog-total',     'label' => __('Total','sgc-smart-crm'),      'color' => '#888'    ),
      );
      $pvals = array(
        'prog-processed' => number_format( $active_job['processed'] ?? 0 ),
        'prog-added'     => number_format( $active_job['added']     ?? 0 ),
        'prog-updated'   => number_format( $active_job['updated']   ?? 0 ),
        'prog-skipped'   => number_format( $active_job['skipped']   ?? 0 ),
        'prog-total'     => number_format( $active_job['total']      ?? 0 ),
      );
      foreach ( $pstats as $ps ) : ?>
      <div style="background:#f8f8f8;border-radius:8px;padding:10px;text-align:center">
        <div id="<?php echo esc_attr($ps['id']); ?>" style="font-size:20px;font-weight:700;color:<?php echo esc_attr($ps['color']); ?>;line-height:1">
          <?php echo esc_html( $pvals[ $ps['id'] ] ?? '0' ); ?>
        </div>
        <div style="font-size:10px;color:#aaa;margin-top:3px;text-transform:uppercase;letter-spacing:.3px"><?php echo esc_html($ps['label']); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="background:#f0f7ff;border-radius:6px;padding:10px 14px;font-size:12px;color:#2271b1;text-align:center">
      &#x1F504; <?php esc_html_e( 'Running in background — you can safely close this page. Import will complete automatically.', 'sgc-smart-crm' ); ?>
    </div>
  </div>

  <!-- DONE PANEL -->
  <div id="import-done-panel" style="display:none;text-align:center;padding:8px 0">
    <div style="font-size:40px;margin-bottom:10px">&#x2705;</div>
    <div style="font-size:20px;font-weight:700;color:#27ae60;margin-bottom:8px"><?php esc_html_e( 'Import Complete!', 'sgc-smart-crm' ); ?></div>
    <div id="done-stats" style="font-size:14px;color:#444;line-height:2"></div>
    <button class="sgc-crm-btn secondary" id="btn-import-another" style="margin-top:14px">
      &#x1F4C2; <?php esc_html_e( 'Import Another File', 'sgc-smart-crm' ); ?>
    </button>
  </div>

</div>

<!-- TOOLBAR -->
<div class="sgc-crm-toolbar">
  <div class="sgc-crm-toolbar-left">
    <input type="text" id="contact-search" placeholder="<?php esc_attr_e( 'Search email, name, company…', 'sgc-smart-crm' ); ?>" class="sgc-crm-input" style="width:240px">
    <select id="contact-filter-segment" class="sgc-crm-select">
      <option value=""><?php esc_html_e( 'All Audiences', 'sgc-smart-crm' ); ?></option>
      <option value="student">&#x1F393; <?php esc_html_e( 'Students', 'sgc-smart-crm' ); ?></option>
      <option value="teacher">&#x1F4DA; <?php esc_html_e( 'Teachers', 'sgc-smart-crm' ); ?></option>
      <option value="dealer">&#x1F3EA; <?php esc_html_e( 'Dealers', 'sgc-smart-crm' ); ?></option>
      <option value="end_user">&#x1F52C; <?php esc_html_e( 'End Users', 'sgc-smart-crm' ); ?></option>
      <option value="glass_blower">&#x1F525; <?php esc_html_e( 'Glass Blowers', 'sgc-smart-crm' ); ?></option>
      <option value="semi_finished">&#x2697;&#xFE0F; <?php esc_html_e( 'Semi-finished', 'sgc-smart-crm' ); ?></option>
      <option value="international">&#x1F30D; <?php esc_html_e( 'International', 'sgc-smart-crm' ); ?></option>
      <option value="domestic">&#x1F1EE;&#x1F1F3; <?php esc_html_e( 'Domestic', 'sgc-smart-crm' ); ?></option>
    </select>
    <select id="contact-filter-status" class="sgc-crm-select">
      <option value=""><?php esc_html_e( 'All Status', 'sgc-smart-crm' ); ?></option>
      <option value="subscribed"><?php esc_html_e( 'Subscribed', 'sgc-smart-crm' ); ?></option>
      <option value="unsubscribed"><?php esc_html_e( 'Unsubscribed', 'sgc-smart-crm' ); ?></option>
    </select>
  </div>
  <div class="sgc-crm-toolbar-right">
    <button class="sgc-crm-btn primary" id="btn-add-contact">&#x2795; <?php esc_html_e( 'Add Contact', 'sgc-smart-crm' ); ?></button>
    <button class="sgc-crm-btn secondary" id="btn-ai-segment">&#x1F916; <?php esc_html_e( 'AI Re-Segment', 'sgc-smart-crm' ); ?></button>
    <button class="sgc-crm-btn secondary" id="btn-sync-woo">&#x1F504; <?php esc_html_e( 'Sync WooCommerce', 'sgc-smart-crm' ); ?></button>
    <button class="sgc-crm-btn secondary" id="btn-sync-inq">&#x1F4E5; <?php esc_html_e( 'Sync Inquiries', 'sgc-smart-crm' ); ?></button>
    <button class="sgc-crm-btn secondary" id="btn-export-csv">&#x2B07;&#xFE0F; <?php esc_html_e( 'Export All CSV', 'sgc-smart-crm' ); ?></button>
    <button class="sgc-crm-btn" id="btn-delete-all-contacts" style="background:#c0392b;color:#fff;border-color:#c0392b">&#x1F5D1; <?php esc_html_e( 'Delete All Contacts', 'sgc-smart-crm' ); ?></button>
  </div>
</div>

<div id="contacts-notice" class="sgc-crm-notice" style="display:none"></div>

<!-- BULK ACTION BAR (visible only when contacts selected) -->
<div id="sgc-bulk-bar" style="display:none;background:#1a1a2e;color:#fff;padding:10px 18px;border-radius:6px;margin-bottom:10px;display:none;align-items:center;gap:12px;flex-wrap:wrap">
  <span style="font-size:13px;font-weight:600"><span id="sgc-bulk-count">0</span> <?php esc_html_e( 'selected', 'sgc-smart-crm' ); ?></span>
  <button id="btn-bulk-export" class="sgc-crm-btn secondary" style="font-size:12px;padding:5px 12px">&#x2B07; <?php esc_html_e( 'Export Selected', 'sgc-smart-crm' ); ?></button>
  <button id="btn-bulk-assign" class="sgc-crm-btn secondary" style="font-size:12px;padding:5px 12px">&#x1F3F7; <?php esc_html_e( 'Assign Segment', 'sgc-smart-crm' ); ?></button>
  <button id="btn-bulk-delete" class="sgc-crm-btn" style="font-size:12px;padding:5px 12px;background:#c0392b;color:#fff;border-color:#c0392b">&#x1F5D1; <?php esc_html_e( 'Delete Selected', 'sgc-smart-crm' ); ?></button>
  <button id="btn-bulk-clear" class="sgc-crm-btn secondary" style="font-size:12px;padding:5px 12px;margin-left:auto">&#x2715; <?php esc_html_e( 'Clear', 'sgc-smart-crm' ); ?></button>
</div>

<!-- TABLE -->
<div class="sgc-crm-box">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong id="contact-count-label"><?php esc_html_e( 'Contacts', 'sgc-smart-crm' ); ?></strong>
    <div id="contact-pagination"></div>
  </div>
  <div class="sgc-crm-table-wrap">
    <table class="sgc-crm-table">
      <thead><tr>
        <th style="width:36px"><input type="checkbox" id="sgc-check-all" title="<?php esc_attr_e( 'Select all on this page', 'sgc-smart-crm' ); ?>"></th>
        <th><?php esc_html_e( 'Email', 'sgc-smart-crm' ); ?></th>
        <th><?php esc_html_e( 'Name', 'sgc-smart-crm' ); ?></th>
        <th><?php esc_html_e( 'Company', 'sgc-smart-crm' ); ?></th>
        <th><?php esc_html_e( 'Country', 'sgc-smart-crm' ); ?></th>
        <th><?php esc_html_e( 'Audience', 'sgc-smart-crm' ); ?></th>
        <th><?php esc_html_e( 'Score', 'sgc-smart-crm' ); ?></th>
        <th><?php esc_html_e( 'Source', 'sgc-smart-crm' ); ?></th>
        <th><?php esc_html_e( 'Status', 'sgc-smart-crm' ); ?></th>
        <th><?php esc_html_e( 'Actions', 'sgc-smart-crm' ); ?></th>
      </tr></thead>
      <tbody id="contacts-tbody">
        <tr><td colspan="10" style="text-align:center;padding:32px;color:#888"><?php esc_html_e( 'Loading…', 'sgc-smart-crm' ); ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

</div><!-- end page -->

<!-- Assign Segment Modal -->
<div class="sgc-crm-modal" id="assign-segment-modal" style="display:none">
  <div class="sgc-crm-modal-inner" style="max-width:380px">
    <div class="sgc-crm-modal-header">
      <h3><?php esc_html_e( 'Assign Segment', 'sgc-smart-crm' ); ?></h3>
      <button class="sgc-crm-modal-close" data-modal="assign-segment-modal">&#x2715;</button>
    </div>
    <div class="sgc-crm-modal-body">
      <p style="font-size:13px;color:#666;margin-bottom:14px"><?php esc_html_e( 'Choose a segment to assign to all selected contacts:', 'sgc-smart-crm' ); ?></p>
      <div class="sgc-crm-form-row">
        <label><?php esc_html_e( 'Audience Segment', 'sgc-smart-crm' ); ?></label>
        <select id="bulk-assign-segment" class="sgc-crm-select" style="width:100%">
          <option value="student">&#x1F393; <?php esc_html_e( 'Student', 'sgc-smart-crm' ); ?></option>
          <option value="teacher">&#x1F4DA; <?php esc_html_e( 'Teacher / Professor', 'sgc-smart-crm' ); ?></option>
          <option value="dealer">&#x1F3EA; <?php esc_html_e( 'Dealer / Distributor', 'sgc-smart-crm' ); ?></option>
          <option value="end_user">&#x1F52C; <?php esc_html_e( 'End User (Lab)', 'sgc-smart-crm' ); ?></option>
          <option value="glass_blower">&#x1F525; <?php esc_html_e( 'Glass Blower', 'sgc-smart-crm' ); ?></option>
          <option value="semi_finished">&#x2697;&#xFE0F; <?php esc_html_e( 'Semi-finished Buyer', 'sgc-smart-crm' ); ?></option>
          <option value="international">&#x1F30D; <?php esc_html_e( 'International', 'sgc-smart-crm' ); ?></option>
          <option value="domestic">&#x1F1EE;&#x1F1F3; <?php esc_html_e( 'Domestic (India)', 'sgc-smart-crm' ); ?></option>
          <option value="warm_lead">&#x1F9E1; <?php esc_html_e( 'Warm Lead', 'sgc-smart-crm' ); ?></option>
          <option value="new_lead">&#x1F7E2; <?php esc_html_e( 'New Lead', 'sgc-smart-crm' ); ?></option>
        </select>
      </div>
    </div>
    <div class="sgc-crm-modal-footer">
      <button class="sgc-crm-btn secondary sgc-crm-modal-close" data-modal="assign-segment-modal"><?php esc_html_e( 'Cancel', 'sgc-smart-crm' ); ?></button>
      <button class="sgc-crm-btn primary" id="btn-bulk-assign-confirm"><?php esc_html_e( 'Assign', 'sgc-smart-crm' ); ?></button>
    </div>
  </div>
</div>

<!-- Delete ALL Contacts Modal (v1.0.6) -->
<div class="sgc-crm-modal" id="delete-all-modal" style="display:none">
  <div class="sgc-crm-modal-inner" style="max-width:440px">
    <div class="sgc-crm-modal-header" style="border-bottom:2px solid #f5c6cb">
      <h3 style="color:#721c24">&#x26A0;&#xFE0F; <?php esc_html_e( 'Delete ALL Contacts', 'sgc-smart-crm' ); ?></h3>
      <button class="sgc-crm-modal-close" data-modal="delete-all-modal">&#x2715;</button>
    </div>
    <div class="sgc-crm-modal-body">
      <div style="background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;padding:14px;margin-bottom:16px">
        <p style="margin:0 0 6px;font-size:13px;color:#721c24;font-weight:600"><?php esc_html_e( 'This will permanently delete ALL contacts from the database.', 'sgc-smart-crm' ); ?></p>
        <p style="margin:0;font-size:12px;color:#856404"><?php esc_html_e( '✓ A CSV backup will be auto-saved to /sgc-crm-backups/ before deletion.', 'sgc-smart-crm' ); ?></p>
      </div>
      <div class="sgc-crm-form-row">
        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:8px">
          <?php esc_html_e( 'Type', 'sgc-smart-crm' ); ?> <code style="background:#f8d7da;padding:2px 6px;border-radius:3px;color:#721c24;font-weight:700">DELETE</code> <?php esc_html_e( 'to confirm:', 'sgc-smart-crm' ); ?>
        </label>
        <input type="text" id="delete-all-confirm-input" class="sgc-crm-input"
          placeholder="<?php esc_attr_e( 'Type DELETE here', 'sgc-smart-crm' ); ?>"
          autocomplete="off" style="letter-spacing:2px;font-weight:600;text-transform:uppercase">
        <div id="delete-all-input-error" style="display:none;color:#721c24;font-size:12px;margin-top:6px">
          <?php esc_html_e( '⚠ Please type DELETE exactly.', 'sgc-smart-crm' ); ?>
        </div>
      </div>
    </div>
    <div class="sgc-crm-modal-footer">
      <button class="sgc-crm-btn secondary sgc-crm-modal-close" data-modal="delete-all-modal"><?php esc_html_e( 'Cancel', 'sgc-smart-crm' ); ?></button>
      <button class="sgc-crm-btn" id="btn-delete-all-confirm" style="background:#c0392b;color:#fff;border-color:#c0392b">
        &#x1F5D1; <?php esc_html_e( 'Delete All Contacts', 'sgc-smart-crm' ); ?>
      </button>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="sgc-crm-modal" id="contact-modal" style="display:none">
  <div class="sgc-crm-modal-inner">
    <div class="sgc-crm-modal-header">
      <h3 id="contact-modal-title"><?php esc_html_e( 'Edit Contact', 'sgc-smart-crm' ); ?></h3>
      <button class="sgc-crm-modal-close" data-modal="contact-modal">&#x2715;</button>
    </div>
    <div class="sgc-crm-modal-body">
      <input type="hidden" id="edit-contact-id">
      <div class="sgc-crm-form-row"><label><?php esc_html_e('Email','sgc-smart-crm'); ?></label><input type="email" id="edit-email" class="sgc-crm-input"></div>
      <div class="sgc-crm-form-row sgc-crm-form-2col">
        <div><label><?php esc_html_e('First Name','sgc-smart-crm'); ?></label><input type="text" id="edit-first-name" class="sgc-crm-input"></div>
        <div><label><?php esc_html_e('Last Name','sgc-smart-crm'); ?></label><input type="text" id="edit-last-name" class="sgc-crm-input"></div>
      </div>
      <div class="sgc-crm-form-row"><label><?php esc_html_e('Company','sgc-smart-crm'); ?></label><input type="text" id="edit-company" class="sgc-crm-input"></div>
      <div class="sgc-crm-form-row sgc-crm-form-2col">
        <div><label><?php esc_html_e('Phone','sgc-smart-crm'); ?></label><input type="text" id="edit-phone" class="sgc-crm-input"></div>
        <div><label><?php esc_html_e('Country','sgc-smart-crm'); ?></label><input type="text" id="edit-country" class="sgc-crm-input"></div>
      </div>
      <div class="sgc-crm-form-row"><label><?php esc_html_e('Tags','sgc-smart-crm'); ?></label><input type="text" id="edit-tags" class="sgc-crm-input" placeholder="dealer,india"></div>
      <div class="sgc-crm-form-row"><label><?php esc_html_e('Audience Segment','sgc-smart-crm'); ?></label>
        <select id="edit-segment" class="sgc-crm-select">
          <option value="student">&#x1F393; <?php esc_html_e('Student','sgc-smart-crm'); ?></option>
          <option value="teacher">&#x1F4DA; <?php esc_html_e('Teacher / Professor','sgc-smart-crm'); ?></option>
          <option value="dealer">&#x1F3EA; <?php esc_html_e('Dealer / Distributor','sgc-smart-crm'); ?></option>
          <option value="end_user">&#x1F52C; <?php esc_html_e('End User (Lab)','sgc-smart-crm'); ?></option>
          <option value="glass_blower">&#x1F525; <?php esc_html_e('Glass Blower','sgc-smart-crm'); ?></option>
          <option value="semi_finished">&#x2697;&#xFE0F; <?php esc_html_e('Semi-finished Buyer','sgc-smart-crm'); ?></option>
          <option value="international">&#x1F30D; <?php esc_html_e('International','sgc-smart-crm'); ?></option>
          <option value="domestic">&#x1F1EE;&#x1F1F3; <?php esc_html_e('Domestic (India)','sgc-smart-crm'); ?></option>
        </select>
      </div>
      <div class="sgc-crm-form-row"><label><?php esc_html_e('Notes','sgc-smart-crm'); ?></label><textarea id="edit-notes" class="sgc-crm-textarea" rows="3"></textarea></div>
    </div>
    <div class="sgc-crm-modal-footer">
      <button class="sgc-crm-btn secondary sgc-crm-modal-close" data-modal="contact-modal"><?php esc_html_e('Cancel','sgc-smart-crm'); ?></button>
      <button class="sgc-crm-btn primary" id="save-contact-btn"><?php esc_html_e('Save','sgc-smart-crm'); ?></button>
    </div>
  </div>
</div>

<style>
.sgc-import-box{border:2px dashed #c8d4e0;border-radius:12px;padding:32px;text-align:center;transition:border-color .2s,background .2s}
.sgc-import-box.drag-over{border-color:#1a1a2e;background:#f0f7ff}
.sgc-drop-icon{font-size:36px;margin-bottom:10px;color:#1a1a2e}
.sgc-drop-headline{font-size:20px;font-weight:700;color:#1a1a2e;margin-bottom:6px}
.sgc-drop-sub{font-size:13px;color:#888}
.sgc-drop-hint{font-size:12px;color:#aaa;margin-top:10px}
</style>

<script>
jQuery(function($){
var pollTimer=null, currentPage=1;
var selectedIds = new Set();

var segLbl={student:'&#x1F393; Student',teacher:'&#x1F4DA; Teacher',dealer:'&#x1F3EA; Dealer',end_user:'&#x1F52C; End User',glass_blower:'&#x1F525; Glass Blower',semi_finished:'&#x2697; Semi-finished',international:'&#x1F30D; International',domestic:'&#x1F1EE;&#x1F1F3; Domestic',warm_lead:'&#x1F9E1; Warm Lead',new_lead:'&#x1F7E2; New Lead'};
var segBg={student:'#e6f9ee;color:#27500A',teacher:'#E6F1FB;color:#0C447C',dealer:'#FAEEDA;color:#412402',end_user:'#F1EFE8;color:#444441',glass_blower:'#FCEBEB;color:#791F1F',semi_finished:'#EEEDFE;color:#26215C',international:'#e6f9ee;color:#085041',domestic:'#E6F1FB;color:#185FA5',warm_lead:'#FFF0E0;color:#7A4000',new_lead:'#E8F5E9;color:#1B5E20'};

function escHtml(s){return $('<div>').text(s||'').html();}
function fmtNum(n){return Number(n||0).toLocaleString();}
function showNotice(m,t){var $n=$('#contacts-notice');$n.html(m).removeClass('success error').addClass(t||'success').show();setTimeout(function(){$n.fadeOut();},5000);}
function scoreColor(s){s=parseInt(s)||0;if(s>=70)return'#27ae60';if(s>=40)return'#e67e22';return'#c0392b';}

// ── BULK SELECTION ───────────────────────────────────────────────────────
function updateBulkBar(){
  var n=selectedIds.size;
  if(n>0){$('#sgc-bulk-count').text(n);$('#sgc-bulk-bar').css('display','flex');}
  else{$('#sgc-bulk-bar').hide();$('#sgc-check-all').prop('checked',false);}
}

$(document).on('change','#sgc-check-all',function(){
  var checked=this.checked;
  $('#contacts-tbody .sgc-row-check').each(function(){
    $(this).prop('checked',checked);
    var id=String($(this).val());
    if(checked)selectedIds.add(id);else selectedIds.delete(id);
    $(this).closest('tr').toggleClass('sgc-selected-row',checked);
  });
  updateBulkBar();
});

$(document).on('change','.sgc-row-check',function(){
  var id=String($(this).val());
  if(this.checked){selectedIds.add(id);$(this).closest('tr').addClass('sgc-selected-row');}
  else{selectedIds.delete(id);$(this).closest('tr').removeClass('sgc-selected-row');}
  var total=$('#contacts-tbody .sgc-row-check').length;
  var checked=$('#contacts-tbody .sgc-row-check:checked').length;
  $('#sgc-check-all').prop('checked',total>0&&total===checked);
  updateBulkBar();
});

$('#btn-bulk-clear').on('click',function(){
  selectedIds.clear();
  $('.sgc-row-check').prop('checked',false);
  $('#sgc-check-all').prop('checked',false);
  $('#contacts-tbody tr').removeClass('sgc-selected-row');
  updateBulkBar();
});

$('#btn-bulk-export').on('click',function(){
  if(!selectedIds.size)return;
  var $b=$(this).prop('disabled',true).text('Exporting\u2026');
  $.post(ajaxurl,{action:'sgc_crm_bulk_export',nonce:SGC_CRM.nonce,ids:Array.from(selectedIds)},function(r){
    $b.prop('disabled',false).html('&#x2B07; Export Selected');
    if(!r.success){showNotice(r.data,'error');return;}
    var bytes=atob(r.data.csv),arr=new Uint8Array(bytes.length);
    for(var i=0;i<bytes.length;i++)arr[i]=bytes.charCodeAt(i);
    var blob=new Blob([arr],{type:'text/csv;charset=utf-8;'});
    var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=r.data.filename;a.click();
    showNotice('Exported '+r.data.count+' contacts.');
  }).fail(function(){$b.prop('disabled',false).html('&#x2B07; Export Selected');showNotice('Server error.','error');});
});

$('#btn-bulk-delete').on('click',function(){
  if(!selectedIds.size)return;
  if(!confirm('Delete '+selectedIds.size+' selected contacts? This cannot be undone.'))return;
  var $b=$(this).prop('disabled',true).text('Deleting\u2026');
  $.post(ajaxurl,{action:'sgc_crm_bulk_delete',nonce:SGC_CRM.nonce,ids:Array.from(selectedIds)},function(r){
    $b.prop('disabled',false).html('&#x1F5D1; Delete Selected');
    if(r.success){selectedIds.clear();updateBulkBar();showNotice(r.data.message);loadContacts(currentPage);}
    else showNotice(r.data,'error');
  }).fail(function(){$b.prop('disabled',false);showNotice('Server error.','error');});
});

$('#btn-bulk-assign').on('click',function(){
  if(!selectedIds.size)return;
  $('#assign-segment-modal').show();
});

$('#btn-bulk-assign-confirm').on('click',function(){
  var seg=$('#bulk-assign-segment').val();
  $('#assign-segment-modal').hide();
  $.post(ajaxurl,{action:'sgc_crm_bulk_assign',nonce:SGC_CRM.nonce,ids:Array.from(selectedIds),segment:seg},function(r){
    if(r.success){showNotice(r.data.message);loadContacts(currentPage);}
    else showNotice(r.data,'error');
  }).fail(function(){showNotice('Server error.','error');});
});

// ── ADD CONTACT button ───────────────────────────────────────────────────
$('#btn-add-contact').on('click',function(){
  $('#edit-contact-id').val('');
  $('#edit-email,#edit-first-name,#edit-last-name,#edit-company,#edit-phone,#edit-country,#edit-tags,#edit-notes').val('');
  $('#edit-email').prop('readonly',false);
  $('#contact-modal-title').text('Add Contact');
  $('#contact-modal').show();
});

// ── DRAG & DROP ──────────────────────────────────────────────────────────
var $box=$('.sgc-import-box');
$box.on('dragover dragenter',function(e){e.preventDefault();e.stopPropagation();$box.addClass('drag-over');});
$box.on('dragleave',function(e){e.preventDefault();$box.removeClass('drag-over');});
$box.on('drop',function(e){e.preventDefault();e.stopPropagation();$box.removeClass('drag-over');var f=e.originalEvent.dataTransfer.files[0];if(f)startImport(f);});
$('#btn-browse-file').on('click',function(){$('#bulk-import-file').click();});
$('#bulk-import-file').on('change',function(){if(this.files[0])startImport(this.files[0]);this.value='';});

var currentJobId = null;

function startImport(file){
  var ext=file.name.split('.').pop().toLowerCase();
  if(!['csv','xlsx','xls'].includes(ext)){showNotice('Only CSV and Excel files supported.','error');return;}
  $('#drop-target').hide();$('#import-done-panel').hide();$('#import-progress-panel').show();
  $('#prog-title').text('Uploading\u2026');$('#prog-file').text(file.name);resetStats();

  var fd=new FormData();
  fd.append('action','sgc_crm_upload_import'); // SGC_CRM_Importer::ajax_upload — proper batch system
  fd.append('nonce',SGC_CRM.nonce);
  fd.append('crm_file',file);

  $.ajax({
    url:ajaxurl,type:'POST',data:fd,processData:false,contentType:false,timeout:120000,
    xhr:function(){
      var x=new XMLHttpRequest();
      x.upload.addEventListener('progress',function(e){
        if(e.lengthComputable){var p=Math.round(e.loaded/e.total*20);$('#prog-fill').css('width',p+'%');$('#prog-pct').text(p+'%');}
      });
      return x;
    },
    success:function(r){
      if(!r.success){showErr(r.data||'Upload failed. Try again.');return;}
      $('#prog-title').text('Import running in background\u2026 ('+fmtNum(r.data.total)+' contacts)');
      $('#prog-total').text(fmtNum(r.data.total));
      startPolling();
    },
    error:function(xhr){
      showErr('Upload error ('+xhr.status+'). Check file size and try again.');
    }
  });
}

function startPolling(){stopPolling();pollTimer=setInterval(pollProgress,2500);pollProgress();}
function stopPolling(){if(pollTimer){clearInterval(pollTimer);pollTimer=null;}}
function pollProgress(){
  $.post(ajaxurl,{action:'sgc_crm_import_progress',nonce:SGC_CRM.nonce},function(r){
    if(!r.success)return;
    var d=r.data;
    if(!d||d.status==='idle'){stopPolling();return;}
    var pct=d.pct||0;
    $('#prog-processed').text(fmtNum(d.processed));
    $('#prog-added').text(fmtNum(d.added));
    $('#prog-updated').text(fmtNum(d.updated));
    $('#prog-skipped').text(fmtNum(d.skipped));
    $('#prog-total').text(fmtNum(d.total));
    $('#prog-fill').css('width',pct+'%');
    $('#prog-pct').text(pct+'%');
    if(d.status==='done'){
      stopPolling();showDone(d);loadContacts(1);
    } else if(d.status==='cancelled'){
      stopPolling();resetUI();showNotice('Import cancelled.','error');
    } else {
      var eta='';
      if(d.processed>0&&d.total>d.processed){
        var rem=d.total-d.processed;
        var sec=Math.round(rem/Math.max(d.processed,1)*5);
        eta=sec<60?' \u00b7 ~'+sec+'s left':' \u00b7 ~'+Math.ceil(sec/60)+'min left';
      }
      $('#prog-title').text('Importing\u2026'+eta);
    }
  });
}
function showDone(d){
  $('#import-progress-panel').hide();$('#import-done-panel').show();
  $('#done-stats').html('<span style="color:#27ae60">+'+fmtNum(d.added)+' added</span> &middot; <span style="color:#2271b1">'+fmtNum(d.updated)+' updated</span> &middot; <span style="color:#aaa">'+fmtNum(d.skipped)+' skipped</span> &middot; '+fmtNum(d.total)+' total');
}
function resetUI(){$('#drop-target').show();$('#import-progress-panel').hide();$('#import-done-panel').hide();resetStats();}
function resetStats(){$('#prog-processed,#prog-added,#prog-updated,#prog-skipped,#prog-total').text('0');$('#prog-fill').css('width','0%');$('#prog-pct').text('0%');}
function showErr(m){resetUI();showNotice(m,'error');}

$('#btn-import-another').on('click',function(){resetUI();});
$('#btn-cancel-import').on('click',function(){
  if(!confirm(SGC_CRM.confirm_cancel))return;
  $.post(ajaxurl,{action:'sgc_crm_cancel_import',nonce:SGC_CRM.nonce},function(){stopPolling();resetUI();showNotice('Cancelled.','error');});
});
<?php if($job_active): ?>startPolling();<?php endif; ?>

// ── CONTACTS TABLE ───────────────────────────────────────────────────────
function loadContacts(page){
  page=page||1;currentPage=page;
  selectedIds.clear();updateBulkBar();$('#sgc-check-all').prop('checked',false);
  $.post(ajaxurl,{action:'sgc_crm_get_contacts',nonce:SGC_CRM.nonce,page:page,search:$('#contact-search').val(),segment:$('#contact-filter-segment').val(),status:$('#contact-filter-status').val()},function(r){
    if(!r.success)return;var d=r.data,tb='';
    if(!d.contacts||!d.contacts.length){
      tb='<tr><td colspan="10" style="text-align:center;padding:28px;color:#888">No contacts yet. Import your file above.</td></tr>';
    } else {
      d.contacts.forEach(function(c){
        var seg=c.segment||'end_user',bg=segBg[seg]||'#f0f0f0;color:#333',lbl=segLbl[seg]||seg;
        var score=parseInt(c.ai_score)||0;
        var scoreBadge=score>0?'<span style="background:'+scoreColor(score)+';color:#fff;padding:2px 7px;border-radius:10px;font-size:11px;font-weight:700">'+score+'</span>':'<span style="color:#ccc;font-size:11px">—</span>';
        var src=c.source==='google_contacts'?'<svg width="11" height="11" viewBox="0 0 24 24" style="vertical-align:middle" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg> Google':'<span style="font-size:10px;color:#aaa">'+escHtml(c.source||'manual')+'</span>';
        var sb=c.status==='subscribed'?'<span class="sgc-badge green">&#x2713;</span>':'<span class="sgc-badge red">Off</span>';
        var isChecked=selectedIds.has(String(c.id))?'checked':'';
        var rowCls=selectedIds.has(String(c.id))?' class="sgc-selected-row"':'';
        tb+='<tr'+rowCls+'>'
          +'<td><input type="checkbox" class="sgc-row-check" value="'+c.id+'" '+isChecked+'></td>'
          +'<td><strong style="font-size:12px">'+escHtml(c.email)+'</strong></td>'
          +'<td>'+escHtml((c.first_name+' '+c.last_name).trim())+'</td>'
          +'<td>'+escHtml(c.company)+'</td>'
          +'<td>'+escHtml(c.country)+'</td>'
          +'<td><span style="background:'+bg+';padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;white-space:nowrap">'+lbl+'</span></td>'
          +'<td>'+scoreBadge+'</td>'
          +'<td>'+src+'</td>'
          +'<td>'+sb+'</td>'
          +'<td class="sgc-crm-actions"><button class="sgc-btn-icon edit-contact" data-id="'+c.id+'">&#x270F;&#xFE0F;</button> <button class="sgc-btn-icon delete-contact" data-id="'+c.id+'">&#x1F5D1;&#xFE0F;</button></td>'
          +'</tr>';
      });
    }
    $('#contacts-tbody').html(tb);
    $('#contact-count-label').text(fmtNum(d.total)+' <?php esc_html_e("Contacts","sgc-smart-crm"); ?>');
    var pg='';
    if(d.pages>1){
      if(d.page>1)pg+='<button class="sgc-page-btn" data-page="'+(d.page-1)+'">&#x2039;</button>';
      pg+='<span style="padding:0 8px;font-size:12px">'+d.page+' / '+d.pages+'</span>';
      if(d.page<d.pages)pg+='<button class="sgc-page-btn" data-page="'+(d.page+1)+'">&#x203A;</button>';
    }
    $('#contact-pagination').html(pg);
  });
}
loadContacts(1);
var st;$('#contact-search').on('input',function(){clearTimeout(st);st=setTimeout(function(){loadContacts(1);},400);});
$('#contact-filter-segment,#contact-filter-status').on('change',function(){loadContacts(1);});
$(document).on('click','.sgc-page-btn',function(){loadContacts($(this).data('page'));});

$('#btn-ai-segment').on('click',function(){var $b=$(this).text('\u23F3 Running\u2026').prop('disabled',true);$.post(ajaxurl,{action:'sgc_crm_ai_segment',nonce:SGC_CRM.nonce},function(r){$b.text('\uD83E\uDD16 AI Re-Segment').prop('disabled',false);if(r.success)showNotice('Updated '+r.data.updated+' contacts ('+r.data.method+')');else showNotice(r.data,'error');loadContacts(currentPage);});});
$('#btn-sync-woo').on('click',function(){
  if(!confirm('Sync all WooCommerce customers + orders to CRM?\n\nThis runs in background — you can close the page anytime.')) return;
  var $b=$(this).text('⏳ Starting…').prop('disabled',true);
  $.post(SGC_CRM.ajax_url,{action:'sgc_crm_sync_woo_customers',nonce:SGC_CRM.nonce},function(r){
    if(!r.success){$b.text('🔄 Sync WooCommerce').prop('disabled',false);showNotice(r.data,'error');return;}
    showNotice('✓ '+r.data.message);
    $b.text('⏳ Syncing…');
    // Start polling for progress.
    var poll=setInterval(function(){
      $.post(SGC_CRM.ajax_url,{action:'sgc_crm_woo_sync_status',nonce:SGC_CRM.nonce},function(s){
        if(!s.success||!s.data||s.data.status==='none'){clearInterval(poll);$b.text('🔄 Sync WooCommerce').prop('disabled',false);return;}
        var d=s.data;
        var pct=d.total>0?Math.min(100,Math.round(d.processed/d.total*100)):0;
        var phase=d.phase==='users'?'👥 Users':'📦 Orders';
        $b.text('⏳ '+phase+' '+pct+'% ('+d.processed+'/'+d.total+')');
        if(d.status==='done'||d.status==='cancelled'){
          clearInterval(poll);
          $b.text('🔄 Sync WooCommerce').prop('disabled',false);
          showNotice('✅ Sync complete! Added: '+d.added+', Updated: '+d.updated);
          loadContacts(1);
        }
      });
    },3000);
  }).fail(function(){$b.text('🔄 Sync WooCommerce').prop('disabled',false);showNotice('Network error','error');});
});
$('#btn-sync-inq').on('click',function(){var $b=$(this).text('\u23F3').prop('disabled',true);$.post(ajaxurl,{action:'sgc_crm_sync_inquiries',nonce:SGC_CRM.nonce},function(r){$b.text('\uD83D\uDCE5 Sync Inquiries').prop('disabled',false);if(r.success)showNotice('Added: '+r.data.added+', Updated: '+r.data.updated);else showNotice(r.data,'error');loadContacts(currentPage);});});
$('#btn-export-csv').on('click',function(){$.post(ajaxurl,{action:'sgc_crm_export_contacts',nonce:SGC_CRM.nonce},function(r){if(!r.success){showNotice(r.data,'error');return;}var b=new Blob([r.data.csv],{type:'text/csv'}),a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='sgc-crm-contacts-'+Date.now()+'.csv';a.click();});});

// ── DELETE ALL CONTACTS (v1.0.6) ─────────────────────────────────────────
$('#btn-delete-all-contacts').on('click', function(){
  $('#delete-all-confirm-input').val('');
  $('#delete-all-input-error').hide();
  $('#delete-all-modal').show();
});

// Live uppercase transform on input
$(document).on('input','#delete-all-confirm-input',function(){
  $(this).val($(this).val().toUpperCase());
  $('#delete-all-input-error').hide();
});

$('#btn-delete-all-confirm').on('click', function(){
  var typed = $('#delete-all-confirm-input').val().trim();
  if(typed !== 'DELETE'){
    $('#delete-all-input-error').show();
    $('#delete-all-confirm-input').focus();
    return;
  }
  var $b = $(this).prop('disabled', true).text('Deleting\u2026');
  $.post(ajaxurl, {
    action: 'sgc_crm_delete_all',
    nonce: SGC_CRM.nonce,
    confirm_token: 'DELETE'
  }, function(r){
    $b.prop('disabled', false).html('&#x1F5D1; <?php esc_html_e("Delete All Contacts","sgc-smart-crm"); ?>');
    $('#delete-all-modal').hide();
    if(r.success){
      showNotice(r.data.message);
      selectedIds.clear();
      updateBulkBar();
      loadContacts(1);
    } else {
      showNotice(r.data || 'Error occurred.', 'error');
    }
  }).fail(function(){
    $b.prop('disabled', false);
    $('#delete-all-modal').hide();
    showNotice('Server error. Try again.', 'error');
  });
});

$(document).on('click','.edit-contact',function(){
  $('#edit-contact-id').val($(this).data('id'));
  $('#edit-email').prop('readonly',true);
  $('#contact-modal-title').text('Edit Contact');
  $('#contact-modal').show();
});
$(document).on('click','.delete-contact',function(){
  if(!confirm(SGC_CRM.confirm_delete))return;
  $.post(ajaxurl,{action:'sgc_crm_delete_contact',nonce:SGC_CRM.nonce,id:$(this).data('id')},function(r){
    if(r.success){showNotice('Deleted.');loadContacts(currentPage);}else showNotice(r.data,'error');
  });
});
$('#save-contact-btn').on('click',function(){
  var id=$('#edit-contact-id').val();
  $.post(ajaxurl,{action:id?'sgc_crm_update_contact':'sgc_crm_add_contact',nonce:SGC_CRM.nonce,id:id,email:$('#edit-email').val(),first_name:$('#edit-first-name').val(),last_name:$('#edit-last-name').val(),company:$('#edit-company').val(),phone:$('#edit-phone').val(),country:$('#edit-country').val(),tags:$('#edit-tags').val(),segment:$('#edit-segment').val(),notes:$('#edit-notes').val()},
  function(r){if(r.success){$('#contact-modal').hide();showNotice('Saved.');loadContacts(currentPage);}else showNotice(r.data,'error');});
});

$(document).on('click','.sgc-crm-modal-close',function(){$('#'+$(this).data('modal')).hide();});
$(document).on('click','.sgc-crm-modal',function(e){if($(e.target).hasClass('sgc-crm-modal'))$(this).hide();});
});
</script>
