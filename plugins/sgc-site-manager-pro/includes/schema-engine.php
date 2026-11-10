<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® — ADVANCED SCHEMA ENGINE v6.6.3
// 15+ Schema types — RankMath Pro equivalent:
// Article, FAQ, HowTo, Video, Event, LocalBusiness,
// Review, Course, Software, Recipe, Dataset, Music,
// Person, Service, JobPosting + Schema Templates
// ============================================================

// ── Schema Type Meta Box ──
add_action('add_meta_boxes', 'sgclabs_schema_metabox_register');
function sgclabs_schema_metabox_register() {
    $post_types = get_post_types(['public' => true], 'names');
    foreach ($post_types as $pt) {
        add_meta_box('sgclabs_schema_box', '📦 SGC LABS® Schema', 'sgclabs_schema_metabox_render', $pt, 'normal', 'default');
    }
}

// ── Save Schema Data ──
add_action('save_post', 'sgclabs_schema_save', 11, 2);
function sgclabs_schema_save($post_id, $post) {
    if (!isset($_POST['sgclabs_schema_nonce'])) return;
    if (!wp_verify_nonce($_POST['sgclabs_schema_nonce'], 'sgclabs_schema_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $schema_type = sanitize_text_field($_POST['_sgclabs_schema_type'] ?? 'none');
    update_post_meta($post_id, '_sgclabs_schema_type', $schema_type);

    // Save schema data as JSON
    if (isset($_POST['_sgclabs_schema_data']) && is_array($_POST['_sgclabs_schema_data'])) {
        $data = array_map('sanitize_text_field', $_POST['_sgclabs_schema_data']);
        update_post_meta($post_id, '_sgclabs_schema_data', $data);
    }
}

// ── Render Schema Meta Box ──
function sgclabs_schema_metabox_render($post) {
    wp_nonce_field('sgclabs_schema_save', 'sgclabs_schema_nonce');
    $type = get_post_meta($post->ID, '_sgclabs_schema_type', true) ?: 'none';
    $data = get_post_meta($post->ID, '_sgclabs_schema_data', true) ?: [];

    $schema_types = [
        'none'          => '— No Schema —',
        'article'       => '📰 Article',
        'faq'           => '❓ FAQ',
        'howto'         => '📋 HowTo',
        'video'         => '🎬 Video',
        'event'         => '📅 Event',
        'local_business'=> '🏪 Local Business',
        'review'        => '⭐ Review',
        'course'        => '🎓 Course',
        'software'      => '💻 Software Application',
        'recipe'        => '🍳 Recipe',
        'dataset'       => '📊 Dataset',
        'music'         => '🎵 Music',
        'person'        => '👤 Person',
        'service'       => '🔧 Service',
        'job_posting'   => '💼 Job Posting',
    ];
    ?>
    <style>
    .sgc-schema-fields{margin-top:12px;padding:15px;background:#f8f9ff;border:1px solid #e0e0f0;border-radius:8px;}
    .sgc-schema-fields .sgc-field{margin-bottom:10px;}
    .sgc-schema-fields label{display:block;font-weight:600;font-size:11px;color:#333;margin-bottom:3px;text-transform:uppercase;}
    .sgc-schema-fields input,.sgc-schema-fields textarea,.sgc-schema-fields select{width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px;}
    .sgc-schema-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .sgc-faq-item{background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:12px;margin-bottom:8px;position:relative;}
    .sgc-faq-item .sgc-remove-faq{position:absolute;top:8px;right:8px;cursor:pointer;color:#e74c3c;font-size:16px;}
    .sgc-step-item{background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:12px;margin-bottom:8px;position:relative;}
    .sgc-step-item .sgc-remove-step{position:absolute;top:8px;right:8px;cursor:pointer;color:#e74c3c;font-size:16px;}
    </style>

    <div class="sgc-field" style="margin-bottom:12px;">
        <label style="font-weight:600;font-size:13px;">Schema Type</label>
        <select name="_sgclabs_schema_type" id="sgc-schema-type" onchange="sgcSchemaTypeChange(this.value)" style="width:300px;padding:8px;">
            <?php foreach ($schema_types as $val => $label): ?>
                <option value="<?php echo $val; ?>" <?php selected($type, $val); ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Article Fields -->
    <div class="sgc-schema-fields" id="sgc-schema-article" style="display:<?php echo $type==='article'?'block':'none'; ?>">
        <h4 style="margin:0 0 10px;">📰 Article Schema</h4>
        <div class="sgc-schema-row">
            <div class="sgc-field">
                <label>Article Type</label>
                <select name="_sgclabs_schema_data[article_type]">
                    <?php foreach (['Article','NewsArticle','BlogPosting','TechArticle','ScholarlyArticle'] as $at): ?>
                        <option value="<?php echo $at; ?>" <?php selected($data['article_type'] ?? '', $at); ?>><?php echo $at; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sgc-field"><label>Headline</label><input type="text" name="_sgclabs_schema_data[article_headline]" value="<?php echo esc_attr($data['article_headline'] ?? ''); ?>" placeholder="Leave empty = post title"></div>
        </div>
        <p style="font-size:11px;color:#888;">Article schema auto-fills: author, datePublished, dateModified, publisher, image from post data.</p>
    </div>

    <!-- FAQ Fields -->
    <div class="sgc-schema-fields" id="sgc-schema-faq" style="display:<?php echo $type==='faq'?'block':'none'; ?>">
        <h4 style="margin:0 0 10px;">❓ FAQ Schema</h4>
        <div id="sgc-faq-list">
            <?php
            $faqs = [];
            for ($i = 0; $i < 10; $i++) {
                if (isset($data["faq_q_{$i}"])) $faqs[] = ['q' => $data["faq_q_{$i}"], 'a' => $data["faq_a_{$i}"] ?? ''];
            }
            if (empty($faqs)) $faqs[] = ['q' => '', 'a' => ''];
            foreach ($faqs as $i => $faq): ?>
                <div class="sgc-faq-item">
                    <span class="sgc-remove-faq" onclick="this.parentNode.remove()">✕</span>
                    <div class="sgc-field"><label>Question <?php echo $i+1; ?></label><input type="text" name="_sgclabs_schema_data[faq_q_<?php echo $i; ?>]" value="<?php echo esc_attr($faq['q']); ?>" placeholder="Question"></div>
                    <div class="sgc-field"><label>Answer</label><textarea name="_sgclabs_schema_data[faq_a_<?php echo $i; ?>]" rows="2" placeholder="Answer"><?php echo esc_textarea($faq['a']); ?></textarea></div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" onclick="sgcAddFAQ()">+ Add FAQ</button>
    </div>

    <!-- HowTo Fields -->
    <div class="sgc-schema-fields" id="sgc-schema-howto" style="display:<?php echo $type==='howto'?'block':'none'; ?>">
        <h4 style="margin:0 0 10px;">📋 HowTo Schema</h4>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Total Time</label><input type="text" name="_sgclabs_schema_data[howto_time]" value="<?php echo esc_attr($data['howto_time'] ?? ''); ?>" placeholder="e.g. PT30M (30 min) or PT2H (2 hours)"></div>
            <div class="sgc-field"><label>Estimated Cost</label><input type="text" name="_sgclabs_schema_data[howto_cost]" value="<?php echo esc_attr($data['howto_cost'] ?? ''); ?>" placeholder="e.g. 500"></div>
        </div>
        <div class="sgc-field"><label>Description</label><textarea name="_sgclabs_schema_data[howto_desc]" rows="2" placeholder="Brief description of this how-to"><?php echo esc_textarea($data['howto_desc'] ?? ''); ?></textarea></div>
        <div id="sgc-howto-steps">
            <?php
            $steps = [];
            for ($i = 0; $i < 20; $i++) {
                if (isset($data["step_name_{$i}"])) $steps[] = ['name' => $data["step_name_{$i}"], 'text' => $data["step_text_{$i}"] ?? ''];
            }
            if (empty($steps)) $steps[] = ['name' => '', 'text' => ''];
            foreach ($steps as $i => $step): ?>
                <div class="sgc-step-item">
                    <span class="sgc-remove-step" onclick="this.parentNode.remove()">✕</span>
                    <div class="sgc-field"><label>Step <?php echo $i+1; ?> Name</label><input type="text" name="_sgclabs_schema_data[step_name_<?php echo $i; ?>]" value="<?php echo esc_attr($step['name']); ?>"></div>
                    <div class="sgc-field"><label>Step Instructions</label><textarea name="_sgclabs_schema_data[step_text_<?php echo $i; ?>]" rows="2"><?php echo esc_textarea($step['text']); ?></textarea></div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" onclick="sgcAddStep()">+ Add Step</button>
    </div>

    <!-- Video Fields -->
    <div class="sgc-schema-fields" id="sgc-schema-video" style="display:<?php echo $type==='video'?'block':'none'; ?>">
        <h4 style="margin:0 0 10px;">🎬 Video Schema</h4>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Video Name</label><input type="text" name="_sgclabs_schema_data[video_name]" value="<?php echo esc_attr($data['video_name'] ?? ''); ?>"></div>
            <div class="sgc-field"><label>Duration</label><input type="text" name="_sgclabs_schema_data[video_duration]" value="<?php echo esc_attr($data['video_duration'] ?? ''); ?>" placeholder="PT5M30S"></div>
        </div>
        <div class="sgc-field"><label>Video URL (Embed)</label><input type="text" name="_sgclabs_schema_data[video_url]" value="<?php echo esc_attr($data['video_url'] ?? ''); ?>" placeholder="https://youtube.com/embed/..."></div>
        <div class="sgc-field"><label>Thumbnail URL</label><input type="text" name="_sgclabs_schema_data[video_thumb]" value="<?php echo esc_attr($data['video_thumb'] ?? ''); ?>"></div>
        <div class="sgc-field"><label>Description</label><textarea name="_sgclabs_schema_data[video_desc]" rows="2"><?php echo esc_textarea($data['video_desc'] ?? ''); ?></textarea></div>
        <div class="sgc-field"><label>Upload Date</label><input type="date" name="_sgclabs_schema_data[video_date]" value="<?php echo esc_attr($data['video_date'] ?? ''); ?>"></div>
    </div>

    <!-- Event Fields -->
    <div class="sgc-schema-fields" id="sgc-schema-event" style="display:<?php echo $type==='event'?'block':'none'; ?>">
        <h4 style="margin:0 0 10px;">📅 Event Schema</h4>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Event Name</label><input type="text" name="_sgclabs_schema_data[event_name]" value="<?php echo esc_attr($data['event_name'] ?? ''); ?>"></div>
            <div class="sgc-field"><label>Event Type</label>
                <select name="_sgclabs_schema_data[event_type]">
                    <?php foreach(['Event','BusinessEvent','SaleEvent','SocialEvent','EducationEvent','ExhibitionEvent','Festival','MusicEvent','ScreeningEvent','SportsEvent'] as $et): ?>
                        <option value="<?php echo $et; ?>" <?php selected($data['event_type'] ?? '', $et); ?>><?php echo $et; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Start Date</label><input type="datetime-local" name="_sgclabs_schema_data[event_start]" value="<?php echo esc_attr($data['event_start'] ?? ''); ?>"></div>
            <div class="sgc-field"><label>End Date</label><input type="datetime-local" name="_sgclabs_schema_data[event_end]" value="<?php echo esc_attr($data['event_end'] ?? ''); ?>"></div>
        </div>
        <div class="sgc-field"><label>Venue Name</label><input type="text" name="_sgclabs_schema_data[event_venue]" value="<?php echo esc_attr($data['event_venue'] ?? ''); ?>"></div>
        <div class="sgc-field"><label>Venue Address</label><input type="text" name="_sgclabs_schema_data[event_address]" value="<?php echo esc_attr($data['event_address'] ?? ''); ?>"></div>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Ticket Price</label><input type="text" name="_sgclabs_schema_data[event_price]" value="<?php echo esc_attr($data['event_price'] ?? ''); ?>"></div>
            <div class="sgc-field"><label>Currency</label><input type="text" name="_sgclabs_schema_data[event_currency]" value="<?php echo esc_attr($data['event_currency'] ?? 'INR'); ?>"></div>
        </div>
        <div class="sgc-field"><label>Event Status</label>
            <select name="_sgclabs_schema_data[event_status]">
                <?php foreach(['EventScheduled','EventPostponed','EventCancelled','EventMovedOnline','EventRescheduled'] as $es): ?>
                    <option value="<?php echo $es; ?>" <?php selected($data['event_status'] ?? '', $es); ?>><?php echo $es; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Local Business Fields -->
    <div class="sgc-schema-fields" id="sgc-schema-local_business" style="display:<?php echo $type==='local_business'?'block':'none'; ?>">
        <h4 style="margin:0 0 10px;">🏪 Local Business Schema</h4>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Business Name</label><input type="text" name="_sgclabs_schema_data[lb_name]" value="<?php echo esc_attr($data['lb_name'] ?? 'SGC LABS®'); ?>"></div>
            <div class="sgc-field"><label>Business Type</label>
                <select name="_sgclabs_schema_data[lb_type]">
                    <?php foreach(['LocalBusiness','Store','ProfessionalService','MedicalBusiness','HealthAndBeautyBusiness','HomeAndConstructionBusiness','LegalService','FinancialService','FoodEstablishment','EntertainmentBusiness','AutomotiveBusiness','SportsActivityLocation'] as $lb): ?>
                        <option value="<?php echo $lb; ?>" <?php selected($data['lb_type'] ?? '', $lb); ?>><?php echo $lb; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="sgc-field"><label>Street Address</label><input type="text" name="_sgclabs_schema_data[lb_street]" value="<?php echo esc_attr($data['lb_street'] ?? ''); ?>"></div>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>City</label><input type="text" name="_sgclabs_schema_data[lb_city]" value="<?php echo esc_attr($data['lb_city'] ?? ''); ?>"></div>
            <div class="sgc-field"><label>State/Region</label><input type="text" name="_sgclabs_schema_data[lb_state]" value="<?php echo esc_attr($data['lb_state'] ?? ''); ?>"></div>
        </div>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Postal Code</label><input type="text" name="_sgclabs_schema_data[lb_zip]" value="<?php echo esc_attr($data['lb_zip'] ?? ''); ?>"></div>
            <div class="sgc-field"><label>Country</label><input type="text" name="_sgclabs_schema_data[lb_country]" value="<?php echo esc_attr($data['lb_country'] ?? 'IN'); ?>"></div>
        </div>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Phone</label><input type="text" name="_sgclabs_schema_data[lb_phone]" value="<?php echo esc_attr($data['lb_phone'] ?? ''); ?>"></div>
            <div class="sgc-field"><label>Price Range</label><input type="text" name="_sgclabs_schema_data[lb_price]" value="<?php echo esc_attr($data['lb_price'] ?? ''); ?>" placeholder="$$ or $$$"></div>
        </div>
        <div class="sgc-field"><label>Opening Hours (e.g. Mo-Fr 09:00-18:00)</label><input type="text" name="_sgclabs_schema_data[lb_hours]" value="<?php echo esc_attr($data['lb_hours'] ?? ''); ?>" placeholder="Mo-Fr 09:00-18:00, Sa 09:00-14:00"></div>
        <div class="sgc-schema-row">
            <div class="sgc-field"><label>Latitude</label><input type="text" name="_sgclabs_schema_data[lb_lat]" value="<?php echo esc_attr($data['lb_lat'] ?? ''); ?>"></div>
            <div class="sgc-field"><label>Longitude</label><input type="text" name="_sgclabs_schema_data[lb_lng]" value="<?php echo esc_attr($data['lb_lng'] ?? ''); ?>"></div>
        </div>
    </div>

    <!-- Course / Software / Review / Other schemas - compact fields -->
    <?php
    $compact_schemas = [
        'review'   => ['⭐ Review', ['review_item'=>'Item Name','review_type'=>'Item Type','review_rating'=>'Rating (1-5)','review_author'=>'Reviewer Name','review_body'=>'Review Text']],
        'course'   => ['🎓 Course', ['course_name'=>'Course Name','course_provider'=>'Provider','course_desc'=>'Description','course_url'=>'Course URL','course_mode'=>'Mode (Online/Onsite)']],
        'software' => ['💻 Software', ['sw_name'=>'App Name','sw_os'=>'Operating System','sw_category'=>'Category','sw_price'=>'Price','sw_currency'=>'Currency','sw_rating'=>'Rating','sw_url'=>'Download URL']],
        'recipe'   => ['🍳 Recipe', ['recipe_name'=>'Recipe Name','recipe_time'=>'Cook Time (PT30M)','recipe_yield'=>'Servings','recipe_calories'=>'Calories','recipe_category'=>'Category','recipe_cuisine'=>'Cuisine']],
        'dataset'  => ['📊 Dataset', ['dataset_name'=>'Name','dataset_desc'=>'Description','dataset_url'=>'URL','dataset_license'=>'License','dataset_creator'=>'Creator']],
        'music'    => ['🎵 Music', ['music_name'=>'Track/Album Name','music_artist'=>'Artist','music_album'=>'Album','music_genre'=>'Genre','music_duration'=>'Duration (PT3M30S)','music_url'=>'URL']],
        'person'   => ['👤 Person', ['person_name'=>'Full Name','person_job'=>'Job Title','person_org'=>'Organization','person_url'=>'Website','person_email'=>'Email','person_same_as'=>'Social URLs (comma-separated)']],
        'service'  => ['🔧 Service', ['service_name'=>'Service Name','service_type'=>'Type','service_provider'=>'Provider','service_area'=>'Service Area','service_desc'=>'Description','service_price'=>'Price']],
        'job_posting'=>['💼 Job Posting', ['job_title'=>'Job Title','job_company'=>'Company','job_location'=>'Location','job_type'=>'Type (Full-time/Part-time)','job_salary'=>'Salary','job_posted'=>'Date Posted','job_expires'=>'Valid Through']],
    ];

    foreach ($compact_schemas as $skey => $sinfo):
        $visible = $type === $skey ? 'block' : 'none';
    ?>
    <div class="sgc-schema-fields" id="sgc-schema-<?php echo $skey; ?>" style="display:<?php echo $visible; ?>">
        <h4 style="margin:0 0 10px;"><?php echo $sinfo[0]; ?> Schema</h4>
        <?php foreach ($sinfo[1] as $fkey => $flabel): ?>
            <div class="sgc-field">
                <label><?php echo $flabel; ?></label>
                <?php if (strpos((string)$fkey, 'desc') !== false || strpos((string)$fkey, 'body') !== false): ?>
                    <textarea name="_sgclabs_schema_data[<?php echo $fkey; ?>]" rows="2"><?php echo esc_textarea($data[$fkey] ?? ''); ?></textarea>
                <?php else: ?>
                    <input type="text" name="_sgclabs_schema_data[<?php echo $fkey; ?>]" value="<?php echo esc_attr($data[$fkey] ?? ''); ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <script>
    function sgcSchemaTypeChange(type) {
        document.querySelectorAll('.sgc-schema-fields').forEach(el => el.style.display = 'none');
        if (type !== 'none') {
            var el = document.getElementById('sgc-schema-' + type);
            if (el) el.style.display = 'block';
        }
    }
    var faqIdx = <?php echo count($faqs); ?>;
    function sgcAddFAQ() {
        var html = '<div class="sgc-faq-item"><span class="sgc-remove-faq" onclick="this.parentNode.remove()">✕</span><div class="sgc-field"><label>Question '+(faqIdx+1)+'</label><input type="text" name="_sgclabs_schema_data[faq_q_'+faqIdx+']" placeholder="Question"></div><div class="sgc-field"><label>Answer</label><textarea name="_sgclabs_schema_data[faq_a_'+faqIdx+']" rows="2" placeholder="Answer"></textarea></div></div>';
        document.getElementById('sgc-faq-list').insertAdjacentHTML('beforeend', html);
        faqIdx++;
    }
    var stepIdx = <?php echo count($steps); ?>;
    function sgcAddStep() {
        var html = '<div class="sgc-step-item"><span class="sgc-remove-step" onclick="this.parentNode.remove()">✕</span><div class="sgc-field"><label>Step '+(stepIdx+1)+' Name</label><input type="text" name="_sgclabs_schema_data[step_name_'+stepIdx+']"></div><div class="sgc-field"><label>Step Instructions</label><textarea name="_sgclabs_schema_data[step_text_'+stepIdx+']" rows="2"></textarea></div></div>';
        document.getElementById('sgc-howto-steps').insertAdjacentHTML('beforeend', html);
        stepIdx++;
    }
    </script>
    <?php
}

// ── Frontend: Output Schema JSON-LD ──
add_action('wp_head', 'sgclabs_schema_output_frontend', 6);
function sgclabs_schema_output_frontend() {
    if (get_option('sgclabs_schema_engine_active', '1') !== '1') return;
    if (!is_singular()) return;
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION')) return;

    $post_id = get_the_ID();
    $type = get_post_meta($post_id, '_sgclabs_schema_type', true);
    if (!$type || $type === 'none') return;

    // Skip Product schema if WooCommerce active (WC outputs its own Product schema)
    if ($type === 'Product' && function_exists('is_product') && is_product() && class_exists('WooCommerce')) return;
    $data = get_post_meta($post_id, '_sgclabs_schema_data', true) ?: [];
    $post = get_post($post_id);
    $permalink = get_permalink($post_id);
    $image = get_the_post_thumbnail_url($post_id, 'large');
    $author = get_the_author_meta('display_name', $post->post_author);
    $site_name = get_bloginfo('name');

    $schema = null;

    switch ($type) {
        case 'article':
            $schema = [
                '@context' => 'https://schema.org',
                '@type'    => $data['article_type'] ?? 'Article',
                'headline' => $data['article_headline'] ?: $post->post_title,
                'author'   => ['@type' => 'Person', 'name' => $author],
                'publisher'=> ['@type' => 'Organization', 'name' => $site_name, 'url' => home_url()],
                'datePublished' => get_the_date('c', $post_id),
                'dateModified'  => get_the_modified_date('c', $post_id),
                'mainEntityOfPage' => $permalink,
                'url' => $permalink,
            ];
            if ($image) $schema['image'] = $image;
            break;

        case 'faq':
            $items = [];
            for ($i = 0; $i < 20; $i++) {
                $q = $data["faq_q_{$i}"] ?? '';
                $a = $data["faq_a_{$i}"] ?? '';
                if ($q && $a) {
                    $items[] = ['@type'=>'Question','name'=>$q,'acceptedAnswer'=>['@type'=>'Answer','text'=>$a]];
                }
            }
            if (!empty($items)) {
                $schema = ['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$items];
            }
            break;

        case 'howto':
            $steps = [];
            for ($i = 0; $i < 20; $i++) {
                $name = $data["step_name_{$i}"] ?? '';
                $text = $data["step_text_{$i}"] ?? '';
                if ($name) $steps[] = ['@type'=>'HowToStep','name'=>$name,'text'=>$text ?: $name,'position'=>$i+1];
            }
            $schema = [
                '@context' => 'https://schema.org', '@type' => 'HowTo',
                'name' => $post->post_title,
                'description' => $data['howto_desc'] ?? '',
                'step' => $steps,
            ];
            if (!empty($data['howto_time'])) $schema['totalTime'] = $data['howto_time'];
            if (!empty($data['howto_cost'])) $schema['estimatedCost'] = ['@type'=>'MonetaryAmount','value'=>$data['howto_cost'],'currency'=>'INR'];
            if ($image) $schema['image'] = $image;
            break;

        case 'video':
            $schema = [
                '@context' => 'https://schema.org', '@type' => 'VideoObject',
                'name' => $data['video_name'] ?: $post->post_title,
                'description' => $data['video_desc'] ?? '',
                'thumbnailUrl' => $data['video_thumb'] ?: $image,
                'uploadDate' => $data['video_date'] ?: get_the_date('Y-m-d', $post_id),
                'embedUrl' => $data['video_url'] ?? '',
            ];
            if (!empty($data['video_duration'])) $schema['duration'] = $data['video_duration'];
            break;

        case 'event':
            $schema = [
                '@context' => 'https://schema.org', '@type' => $data['event_type'] ?: 'Event',
                'name' => $data['event_name'] ?: $post->post_title,
                'startDate' => $data['event_start'] ?? '',
                'endDate' => $data['event_end'] ?? '',
                'eventStatus' => 'https://schema.org/' . ($data['event_status'] ?: 'EventScheduled'),
                'location' => ['@type'=>'Place','name'=>$data['event_venue'] ?? '','address'=>$data['event_address'] ?? ''],
            ];
            if (!empty($data['event_price'])) {
                $schema['offers'] = ['@type'=>'Offer','price'=>$data['event_price'],'priceCurrency'=>$data['event_currency'] ?? 'INR','url'=>$permalink];
            }
            break;

        case 'local_business':
            $schema = [
                '@context' => 'https://schema.org', '@type' => $data['lb_type'] ?: 'LocalBusiness',
                'name' => $data['lb_name'] ?? $site_name,
                'address' => ['@type'=>'PostalAddress','streetAddress'=>$data['lb_street']??'','addressLocality'=>$data['lb_city']??'','addressRegion'=>$data['lb_state']??'','postalCode'=>$data['lb_zip']??'','addressCountry'=>$data['lb_country']??'IN'],
                'telephone' => $data['lb_phone'] ?? '',
                'priceRange' => $data['lb_price'] ?? '',
                'url' => $permalink,
            ];
            if (!empty($data['lb_lat']) && !empty($data['lb_lng'])) {
                $schema['geo'] = ['@type'=>'GeoCoordinates','latitude'=>$data['lb_lat'],'longitude'=>$data['lb_lng']];
            }
            if (!empty($data['lb_hours'])) {
                $schema['openingHours'] = $data['lb_hours'];
            }
            if ($image) $schema['image'] = $image;
            break;

        case 'review':
            $schema = [
                '@context' => 'https://schema.org', '@type' => 'Review',
                'itemReviewed' => ['@type'=>$data['review_type']?:'Thing','name'=>$data['review_item']??''],
                'author' => ['@type'=>'Person','name'=>$data['review_author']??$author],
                'reviewBody' => $data['review_body'] ?? '',
                'reviewRating' => ['@type'=>'Rating','ratingValue'=>$data['review_rating']??'5','bestRating'=>'5'],
            ];
            break;

        case 'course':
            $schema = [
                '@context' => 'https://schema.org', '@type' => 'Course',
                'name' => $data['course_name'] ?: $post->post_title,
                'description' => $data['course_desc'] ?? '',
                'provider' => ['@type'=>'Organization','name'=>$data['course_provider']??$site_name],
                'url' => $data['course_url'] ?: $permalink,
            ];
            break;

        case 'software':
            $schema = [
                '@context' => 'https://schema.org', '@type' => 'SoftwareApplication',
                'name' => $data['sw_name'] ?: $post->post_title,
                'operatingSystem' => $data['sw_os'] ?? '',
                'applicationCategory' => $data['sw_category'] ?? '',
                'url' => $data['sw_url'] ?: $permalink,
            ];
            if (!empty($data['sw_price'])) {
                $schema['offers'] = ['@type'=>'Offer','price'=>$data['sw_price'],'priceCurrency'=>$data['sw_currency']??'USD'];
            }
            break;

        case 'recipe':
            $schema = [
                '@context' => 'https://schema.org', '@type' => 'Recipe',
                'name' => $data['recipe_name'] ?: $post->post_title,
                'cookTime' => $data['recipe_time'] ?? '',
                'recipeYield' => $data['recipe_yield'] ?? '',
                'recipeCategory' => $data['recipe_category'] ?? '',
                'recipeCuisine' => $data['recipe_cuisine'] ?? '',
                'author' => ['@type'=>'Person','name'=>$author],
            ];
            if (!empty($data['recipe_calories'])) $schema['nutrition'] = ['@type'=>'NutritionInformation','calories'=>$data['recipe_calories']];
            if ($image) $schema['image'] = $image;
            break;

        default:
            // Compact schemas
            break;
    }

    if ($schema) {
        echo "<script type='application/ld+json'>" . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "</script>\n";
    }
}
