<?php
if (!defined('ABSPATH')) exit;

// ============================================================
// SGC LABS® — SEO METABOX ENGINE v6.6.3
// RankMath-style per-post SEO editor:
// 1. Focus Keyword (up to 5)
// 2. SEO Score 0-100 with real-time analysis
// 3. SERP Snippet Preview (Google)
// 4. Social Media Preview (FB/Twitter)
// 5. Per-post noindex/nofollow/canonical
// 6. Content Readability Analysis
// 7. Cornerstone Content toggle
// 8. Breadcrumb SEO
// ============================================================

// ── Register Meta Box on all post types ──
add_action('add_meta_boxes', 'sgclabs_seo_metabox_register');
function sgclabs_seo_metabox_register() {
    $post_types = get_post_types(['public' => true], 'names');
    foreach ($post_types as $pt) {
        add_meta_box(
            'sgclabs_seo_metabox',
            '🔍 SGC LABS® SEO',
            'sgclabs_seo_metabox_render',
            $pt,
            'normal',
            'high'
        );
    }
}

// ── Save Meta Box Data ──
add_action('save_post', 'sgclabs_seo_metabox_save', 10, 2);
function sgclabs_seo_metabox_save($post_id, $post) {
    if (!isset($_POST['sgclabs_seo_metabox_nonce'])) return;
    if (!wp_verify_nonce($_POST['sgclabs_seo_metabox_nonce'], 'sgclabs_seo_metabox')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = [
        '_sgclabs_focus_keyword'   => 'sanitize_text_field',
        '_sgclabs_focus_kw_2'      => 'sanitize_text_field',
        '_sgclabs_focus_kw_3'      => 'sanitize_text_field',
        '_sgclabs_focus_kw_4'      => 'sanitize_text_field',
        '_sgclabs_focus_kw_5'      => 'sanitize_text_field',
        '_sgclabs_seo_title'       => 'sanitize_text_field',
        '_sgclabs_seo_description' => 'sanitize_textarea_field',
        '_sgclabs_canonical_url'   => 'esc_url_raw',
        '_sgclabs_robots_noindex'  => 'sanitize_key',
        '_sgclabs_robots_nofollow' => 'sanitize_key',
        '_sgclabs_robots_noarchive'=> 'sanitize_key',
        '_sgclabs_robots_nosnippet'=> 'sanitize_key',
        '_sgclabs_og_title'        => 'sanitize_text_field',
        '_sgclabs_og_description'  => 'sanitize_textarea_field',
        '_sgclabs_og_image'        => 'esc_url_raw',
        '_sgclabs_twitter_title'   => 'sanitize_text_field',
        '_sgclabs_twitter_description' => 'sanitize_textarea_field',
        '_sgclabs_twitter_image'   => 'esc_url_raw',
        '_sgclabs_cornerstone'     => 'sanitize_key',
        '_sgclabs_breadcrumb_title'=> 'sanitize_text_field',
    ];

    foreach ($fields as $key => $sanitizer) {
        if (isset($_POST[$key])) {
            update_post_meta($post_id, $key, $sanitizer($_POST[$key]));
        } else {
            // Checkbox fields
            if (in_array($key, ['_sgclabs_robots_noindex','_sgclabs_robots_nofollow','_sgclabs_robots_noarchive','_sgclabs_robots_nosnippet','_sgclabs_cornerstone'])) {
                delete_post_meta($post_id, $key);
            }
        }
    }

    // Recalculate and cache SEO score
    $score = sgclabs_calculate_seo_score($post_id);
    update_post_meta($post_id, '_sgclabs_seo_score', $score);
}

// ── SEO Score Calculator (0-100) ──
function sgclabs_calculate_seo_score($post_id) {
    $post    = get_post($post_id);
    if (!$post) return 0;

    $content = wp_strip_all_tags($post->post_content);
    $title   = $post->post_title;
    $focus_kw = get_post_meta($post_id, '_sgclabs_focus_keyword', true);
    $seo_title = get_post_meta($post_id, '_sgclabs_seo_title', true) ?: $title;
    $seo_desc  = get_post_meta($post_id, '_sgclabs_seo_description', true);
    $permalink = get_permalink($post_id);

    $score = 0;
    $checks = [];

    // 1. Focus keyword set (10 pts)
    if (!empty($focus_kw)) {
        $score += 10;
        $checks['focus_kw'] = true;
    }

    // 2. Focus KW in SEO title (10 pts)
    if ($focus_kw && stripos((string)$seo_title, $focus_kw) !== false) {
        $score += 10;
        $checks['kw_in_title'] = true;
    }

    // 3. Focus KW in meta description (10 pts)
    if ($focus_kw && $seo_desc && stripos((string)$seo_desc, $focus_kw) !== false) {
        $score += 10;
        $checks['kw_in_desc'] = true;
    }

    // 4. Focus KW in content (5 pts)
    if ($focus_kw && stripos((string)$content, $focus_kw) !== false) {
        $score += 5;
        $checks['kw_in_content'] = true;
    }

    // 5. Focus KW in URL (5 pts)
    $slug = sanitize_title($focus_kw ?: '');
    if ($slug && stripos((string)$permalink, $slug) !== false) {
        $score += 5;
        $checks['kw_in_url'] = true;
    }

    // 6. Focus KW in first paragraph (5 pts)
    $first_para = substr($content, 0, 300);
    if ($focus_kw && stripos((string)$first_para, $focus_kw) !== false) {
        $score += 5;
        $checks['kw_in_intro'] = true;
    }

    // 7. SEO Title length 30-60 chars (8 pts)
    $title_len = strlen($seo_title);
    if ($title_len >= 30 && $title_len <= 60) {
        $score += 8;
        $checks['title_length'] = true;
    } elseif ($title_len >= 20 && $title_len <= 70) {
        $score += 4;
    }

    // 8. Meta description 120-160 chars (8 pts)
    $desc_len = strlen($seo_desc);
    if ($desc_len >= 120 && $desc_len <= 160) {
        $score += 8;
        $checks['desc_length'] = true;
    } elseif ($desc_len >= 80 && $desc_len <= 200) {
        $score += 4;
    }

    // 9. Content length >300 words (7 pts)
    $word_count = str_word_count($content);
    if ($word_count >= 600) {
        $score += 7;
        $checks['content_length'] = true;
    } elseif ($word_count >= 300) {
        $score += 4;
    }

    // 10. Has images (5 pts)
    $img_count = substr_count($post->post_content, '<img');
    if ($img_count > 0) {
        $score += 5;
        $checks['has_images'] = true;
    }

    // 11. Has internal links (5 pts)
    $home_url = home_url();
    if (preg_match_all('/href=["\']([^"\']+)/', $post->post_content, $matches)) {
        foreach ($matches[1] as $url) {
            if (stripos((string)$url, $home_url) !== false || strpos((string)$url, '/') === 0) {
                $score += 5;
                $checks['internal_links'] = true;
                break;
            }
        }
    }

    // 12. Has external links (3 pts)
    if (preg_match_all('/href=["\']https?:\/\/([^"\']+)/', $post->post_content, $ext_matches)) {
        $domain = parse_url($home_url, PHP_URL_HOST);
        foreach ($ext_matches[1] as $ext_url) {
            if (stripos((string)$ext_url, $domain) === false) {
                $score += 3;
                $checks['external_links'] = true;
                break;
            }
        }
    }

    // 13. Has H2/H3 headings (5 pts)
    if (preg_match('/<h[2-3]/i', $post->post_content)) {
        $score += 5;
        $checks['headings'] = true;
    }

    // 14. Keyword density 0.5-2.5% (7 pts)
    if ($focus_kw && $word_count > 0) {
        $kw_count = substr_count(strtolower($content), strtolower($focus_kw));
        $kw_words = str_word_count($focus_kw);
        $density  = ($kw_count * $kw_words / $word_count) * 100;
        if ($density >= 0.5 && $density <= 2.5) {
            $score += 7;
            $checks['kw_density'] = true;
        } elseif ($density > 0 && $density < 3.5) {
            $score += 3;
        }
    }

    // 15. Has featured image (3 pts — WooCommerce product image)
    if (has_post_thumbnail($post_id)) {
        $score += 3;
        $checks['featured_image'] = true;
    }

    // 16. Focus KW in H2/H3 (4 pts)
    if ($focus_kw && preg_match_all('/<h[2-3][^>]*>(.*?)<\/h[2-3]>/si', $post->post_content, $h_matches)) {
        foreach ($h_matches[1] as $heading) {
            if (stripos(wp_strip_all_tags($heading), $focus_kw) !== false) {
                $score += 4;
                $checks['kw_in_heading'] = true;
                break;
            }
        }
    }

    return min(100, $score);
}

// ── Readability Calculator ──
function sgclabs_readability_analysis($content) {
    $content     = wp_strip_all_tags($content);
    $sentences   = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
    $sentence_ct = max(1, count($sentences));
    $words       = str_word_count($content);
    $syllables   = sgclabs_count_syllables($content);

    // Flesch Reading Ease
    if ($words > 0 && $sentence_ct > 0) {
        $flesch = 206.835 - (1.015 * ($words / $sentence_ct)) - (84.6 * ($syllables / max(1, $words)));
    } else {
        $flesch = 0;
    }

    // Average sentence length
    $avg_sentence = round($words / $sentence_ct, 1);

    // Long sentences (>20 words)
    $long_sentences = 0;
    foreach ($sentences as $s) {
        if (str_word_count($s) > 20) $long_sentences++;
    }

    // Paragraphs
    $paragraphs = preg_split('/\n{2,}/', $content, -1, PREG_SPLIT_NO_EMPTY);
    $long_paragraphs = 0;
    foreach ($paragraphs as $p) {
        if (str_word_count($p) > 150) $long_paragraphs++;
    }

    // Passive voice detection (basic)
    $passive_patterns = ['/\bwas\s+\w+ed\b/i', '/\bwere\s+\w+ed\b/i', '/\bbeen\s+\w+ed\b/i', '/\bis\s+\w+ed\b/i', '/\bare\s+\w+ed\b/i'];
    $passive_count = 0;
    foreach ($passive_patterns as $pattern) {
        $passive_count += preg_match_all($pattern, $content);
    }

    // Transition words
    $transition_words = ['however','therefore','furthermore','moreover','consequently','additionally','nevertheless','meanwhile','similarly','likewise','accordingly','hence','thus','indeed','specifically','particularly','notably','importantly','alternatively','conversely','finally','subsequently','first','second','third','also','besides','in addition','for example','for instance','in conclusion','on the other hand'];
    $transition_count = 0;
    $content_lower = strtolower($content);
    foreach ($transition_words as $tw) {
        $transition_count += substr_count($content_lower, $tw);
    }

    return [
        'flesch_score'      => round($flesch, 1),
        'flesch_label'      => $flesch >= 60 ? 'Easy' : ($flesch >= 30 ? 'Moderate' : 'Difficult'),
        'word_count'        => $words,
        'sentence_count'    => $sentence_ct,
        'avg_sentence_len'  => $avg_sentence,
        'long_sentences'    => $long_sentences,
        'long_paragraphs'   => $long_paragraphs,
        'passive_voice'     => $passive_count,
        'transition_words'  => $transition_count,
        'paragraph_count'   => count($paragraphs),
    ];
}

function sgclabs_count_syllables($text) {
    $words = explode(' ', strtolower(preg_replace('/[^a-z ]/i', '', $text)));
    $total = 0;
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) <= 3) { $total += 1; continue; }
        $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
        $word = preg_replace('/^y/', '', $word);
        preg_match_all('/[aeiouy]{1,2}/', $word, $m);
        $total += max(1, count($m[0]));
    }
    return $total;
}

// ── AJAX: Real-time SEO Analysis ──
add_action('wp_ajax_sgclabs_analyze_seo', function() {
    check_ajax_referer('sgclabs_nonce', 'nonce');
    if (!current_user_can('edit_posts')) { wp_send_json_error('Unauthorized'); return; }

    $post_id    = intval($_POST['post_id'] ?? 0);
    $focus_kw   = sanitize_text_field($_POST['focus_keyword'] ?? '');
    $seo_title  = sanitize_text_field($_POST['seo_title'] ?? '');
    $seo_desc   = sanitize_textarea_field($_POST['seo_description'] ?? '');
    $content    = wp_kses_post($_POST['content'] ?? '');
    $permalink  = esc_url($_POST['permalink'] ?? '');

    $plain = wp_strip_all_tags($content);
    $word_count = str_word_count($plain);

    $results = [];

    // Focus KW checks
    $results[] = ['id'=>'focus_kw', 'pass'=>!empty($focus_kw), 'msg'=> empty($focus_kw) ? 'Focus Keyword set nahi hai' : "Focus Keyword: \"{$focus_kw}\"", 'weight'=>10];
    $results[] = ['id'=>'kw_title', 'pass'=>$focus_kw && stripos((string)$seo_title, $focus_kw)!==false, 'msg'=> ($focus_kw && stripos((string)$seo_title, $focus_kw)!==false) ? 'Focus KW SEO Title mein hai ✓' : 'Focus KW SEO Title mein nahi hai', 'weight'=>10];
    $results[] = ['id'=>'kw_desc', 'pass'=>$focus_kw && $seo_desc && stripos((string)$seo_desc, $focus_kw)!==false, 'msg'=> ($focus_kw && $seo_desc && stripos((string)$seo_desc, $focus_kw)!==false) ? 'Focus KW Meta Description mein hai ✓' : 'Focus KW Meta Description mein add karo', 'weight'=>10];
    $results[] = ['id'=>'kw_content', 'pass'=>$focus_kw && stripos((string)$plain, $focus_kw)!==false, 'msg'=> ($focus_kw && stripos((string)$plain, $focus_kw)!==false) ? 'Focus KW Content mein found ✓' : 'Focus KW Content mein use karo', 'weight'=>5];
    $results[] = ['id'=>'kw_url', 'pass'=>$focus_kw && stripos((string)$permalink, sanitize_title($focus_kw))!==false, 'msg'=> ($focus_kw && stripos((string)$permalink, sanitize_title($focus_kw))!==false) ? 'Focus KW URL/Slug mein hai ✓' : 'Focus KW URL mein nahi hai', 'weight'=>5];

    // First paragraph
    $first300 = substr($plain, 0, 300);
    $results[] = ['id'=>'kw_intro', 'pass'=>$focus_kw && stripos((string)$first300, $focus_kw)!==false, 'msg'=> ($focus_kw && stripos((string)$first300, $focus_kw)!==false) ? 'Focus KW pehle paragraph mein hai ✓' : 'Focus KW pehle paragraph mein daalo', 'weight'=>5];

    // Title length
    $tl = strlen($seo_title);
    $results[] = ['id'=>'title_len', 'pass'=>$tl>=30 && $tl<=60, 'msg'=> "SEO Title: {$tl} chars " . ($tl>=30 && $tl<=60 ? '(Good ✓)' : ($tl<30 ? '(Too short, 30-60 chahiye)' : '(Too long, 60 max)')), 'weight'=>8];

    // Desc length
    $dl = strlen($seo_desc);
    $results[] = ['id'=>'desc_len', 'pass'=>$dl>=120 && $dl<=160, 'msg'=> "Meta Description: {$dl} chars " . ($dl>=120 && $dl<=160 ? '(Perfect ✓)' : ($dl<120 ? '(Too short, 120-160 chahiye)' : '(Too long, 160 max)')), 'weight'=>8];

    // Content length
    $results[] = ['id'=>'content_len', 'pass'=>$word_count>=300, 'msg'=> "Content: {$word_count} words " . ($word_count>=600 ? '(Excellent ✓)' : ($word_count>=300 ? '(Good ✓)' : '(300+ words chahiye)')), 'weight'=>7];

    // KW density
    if ($focus_kw && $word_count > 0) {
        $kw_count = substr_count(strtolower($plain), strtolower($focus_kw));
        $kw_words = str_word_count($focus_kw);
        $density  = round(($kw_count * $kw_words / $word_count) * 100, 2);
        $good = $density >= 0.5 && $density <= 2.5;
        $results[] = ['id'=>'kw_density', 'pass'=>$good, 'msg'=> "KW Density: {$density}% ({$kw_count} times) " . ($good ? '✓' : ($density < 0.5 ? '— Aur use karo' : '— Zyada hai, kam karo')), 'weight'=>7];
    }

    // Headings
    $has_h2 = preg_match('/<h[2-3]/i', $content);
    $results[] = ['id'=>'headings', 'pass'=>(bool)$has_h2, 'msg'=> $has_h2 ? 'H2/H3 headings present ✓' : 'H2/H3 headings add karo', 'weight'=>5];

    // KW in headings
    if ($focus_kw && preg_match_all('/<h[2-3][^>]*>(.*?)<\/h[2-3]>/si', $content, $hm)) {
        $found = false;
        foreach ($hm[1] as $h) { if (stripos(wp_strip_all_tags($h), $focus_kw) !== false) { $found = true; break; } }
        $results[] = ['id'=>'kw_heading', 'pass'=>$found, 'msg'=> $found ? 'Focus KW ek heading mein hai ✓' : 'Focus KW kisi H2/H3 mein daalo', 'weight'=>4];
    }

    // Images
    $img_ct = substr_count($content, '<img');
    $results[] = ['id'=>'images', 'pass'=>$img_ct>0, 'msg'=> $img_ct>0 ? "{$img_ct} image(s) found ✓" : 'Koi image nahi hai — add karo', 'weight'=>5];

    // Internal links
    $home = home_url();
    $has_internal = false;
    if (preg_match_all('/href=["\']([^"\']+)/', $content, $lm)) {
        foreach ($lm[1] as $u) {
            if (stripos((string)$u, $home) !== false || (strpos((string)$u, '/') === 0 && strpos((string)$u, '//') !== 0)) { $has_internal = true; break; }
        }
    }
    $results[] = ['id'=>'int_links', 'pass'=>$has_internal, 'msg'=> $has_internal ? 'Internal links present ✓' : 'Internal links add karo (SEO boost)', 'weight'=>5];

    // External links
    $has_external = false;
    $domain = parse_url($home, PHP_URL_HOST);
    if (preg_match_all('/href=["\']https?:\/\/([^"\']+)/', $content, $em)) {
        foreach ($em[1] as $eu) { if (stripos((string)$eu, $domain) === false) { $has_external = true; break; } }
    }
    $results[] = ['id'=>'ext_links', 'pass'=>$has_external, 'msg'=> $has_external ? 'External links present ✓' : 'External authority link add karo', 'weight'=>3];

    // Featured image
    $results[] = ['id'=>'feat_img', 'pass'=>has_post_thumbnail($post_id), 'msg'=> has_post_thumbnail($post_id) ? 'Featured Image set hai ✓' : 'Featured Image lagao', 'weight'=>3];

    // Calculate score
    $total_weight = array_sum(array_column($results, 'weight'));
    $earned = 0;
    foreach ($results as $r) { if ($r['pass']) $earned += $r['weight']; }
    $score = $total_weight > 0 ? round(($earned / $total_weight) * 100) : 0;

    // Readability
    $readability = sgclabs_readability_analysis($content);

    wp_send_json_success([
        'score'       => $score,
        'checks'      => $results,
        'readability' => $readability,
    ]);
});

// ── Render Meta Box ──
function sgclabs_seo_metabox_render($post) {
    wp_nonce_field('sgclabs_seo_metabox', 'sgclabs_seo_metabox_nonce');

    $focus_kw = get_post_meta($post->ID, '_sgclabs_focus_keyword', true);
    $focus_kw_2 = get_post_meta($post->ID, '_sgclabs_focus_kw_2', true);
    $focus_kw_3 = get_post_meta($post->ID, '_sgclabs_focus_kw_3', true);
    $focus_kw_4 = get_post_meta($post->ID, '_sgclabs_focus_kw_4', true);
    $focus_kw_5 = get_post_meta($post->ID, '_sgclabs_focus_kw_5', true);
    $seo_title = get_post_meta($post->ID, '_sgclabs_seo_title', true) ?: $post->post_title;
    $seo_desc  = get_post_meta($post->ID, '_sgclabs_seo_description', true);
    $canonical = get_post_meta($post->ID, '_sgclabs_canonical_url', true);
    $noindex   = get_post_meta($post->ID, '_sgclabs_robots_noindex', true);
    $nofollow  = get_post_meta($post->ID, '_sgclabs_robots_nofollow', true);
    $noarchive = get_post_meta($post->ID, '_sgclabs_robots_noarchive', true);
    $nosnippet = get_post_meta($post->ID, '_sgclabs_robots_nosnippet', true);
    $og_title  = get_post_meta($post->ID, '_sgclabs_og_title', true);
    $og_desc   = get_post_meta($post->ID, '_sgclabs_og_description', true);
    $og_image  = get_post_meta($post->ID, '_sgclabs_og_image', true);
    $tw_title  = get_post_meta($post->ID, '_sgclabs_twitter_title', true);
    $tw_desc   = get_post_meta($post->ID, '_sgclabs_twitter_description', true);
    $tw_image  = get_post_meta($post->ID, '_sgclabs_twitter_image', true);
    $cornerstone = get_post_meta($post->ID, '_sgclabs_cornerstone', true);
    $bc_title  = get_post_meta($post->ID, '_sgclabs_breadcrumb_title', true);
    $seo_score = get_post_meta($post->ID, '_sgclabs_seo_score', true) ?: 0;
    $permalink = get_permalink($post->ID);
    $slug      = basename(parse_url($permalink, PHP_URL_PATH));
    ?>
    <style>
    .sgc-seo-box{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
    .sgc-seo-tabs{display:flex;border-bottom:2px solid #e0e0e0;margin-bottom:15px;gap:0;}
    .sgc-seo-tab{padding:10px 18px;cursor:pointer;font-size:13px;font-weight:600;color:#666;border-bottom:3px solid transparent;transition:all .2s;}
    .sgc-seo-tab:hover{color:#1a1a2e;}
    .sgc-seo-tab.active{color:#1a1a2e;border-bottom-color:#6c5ce7;}
    .sgc-seo-panel{display:none;padding:0;}
    .sgc-seo-panel.active{display:block;}
    .sgc-field{margin-bottom:14px;}
    .sgc-field label{display:block;font-weight:600;font-size:12px;color:#333;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.3px;}
    .sgc-field input[type="text"],.sgc-field textarea{width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;transition:border .2s;}
    .sgc-field input:focus,.sgc-field textarea:focus{border-color:#6c5ce7;outline:none;box-shadow:0 0 0 2px rgba(108,92,231,0.1);}
    .sgc-char-count{font-size:11px;color:#888;margin-top:2px;}
    .sgc-char-count.good{color:#27ae60;}
    .sgc-char-count.warn{color:#e67e22;}
    .sgc-char-count.bad{color:#e74c3c;}
    .sgc-score-ring{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:18px;color:white;margin-right:15px;flex-shrink:0;}
    .sgc-score-good{background:linear-gradient(135deg,#27ae60,#2ecc71);}
    .sgc-score-ok{background:linear-gradient(135deg,#f39c12,#e67e22);}
    .sgc-score-bad{background:linear-gradient(135deg,#e74c3c,#c0392b);}
    .sgc-serp-preview{background:#fff;border:1px solid #dfe1e5;border-radius:8px;padding:16px;max-width:600px;font-family:Arial,sans-serif;}
    .sgc-serp-url{color:#202124;font-size:12px;line-height:1.3;}
    .sgc-serp-url span{color:#5f6368;}
    .sgc-serp-title{color:#1a0dab;font-size:18px;line-height:1.3;margin:3px 0;cursor:pointer;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .sgc-serp-title:hover{text-decoration:underline;}
    .sgc-serp-desc{color:#4d5156;font-size:13px;line-height:1.58;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .sgc-check-list{list-style:none;padding:0;margin:0;}
    .sgc-check-list li{padding:6px 10px;font-size:12px;border-radius:4px;margin-bottom:3px;display:flex;align-items:center;gap:8px;}
    .sgc-check-pass{background:#e8f8e8;color:#27ae60;}
    .sgc-check-fail{background:#fde8e8;color:#c0392b;}
    .sgc-social-preview{border:1px solid #ddd;border-radius:8px;overflow:hidden;max-width:500px;}
    .sgc-social-img{background:#f0f0f0;height:250px;display:flex;align-items:center;justify-content:center;color:#999;font-size:13px;}
    .sgc-social-img img{width:100%;height:100%;object-fit:cover;}
    .sgc-social-text{padding:12px 14px;border-top:1px solid #eee;}
    .sgc-social-text .site{color:#65676b;font-size:11px;text-transform:uppercase;}
    .sgc-social-text .title{font-weight:600;font-size:14px;color:#1c1e21;margin:2px 0;}
    .sgc-social-text .desc{color:#65676b;font-size:12px;}
    .sgc-robots-grid{display:flex;flex-wrap:wrap;gap:15px;margin-top:8px;}
    .sgc-robots-grid label{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:normal;}
    .sgc-additional-kw{margin-top:8px;}
    .sgc-additional-kw input{margin-bottom:5px;}
    .sgc-toggle-more{color:#6c5ce7;cursor:pointer;font-size:12px;font-weight:600;margin-top:4px;display:inline-block;}
    .sgc-readability-item{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:12px;}
    .sgc-readability-val{font-weight:600;}
    .sgc-readability-good{color:#27ae60;}
    .sgc-readability-warn{color:#e67e22;}
    .sgc-readability-bad{color:#e74c3c;}
    </style>

    <div class="sgc-seo-box">
        <!-- Score Bar -->
        <div style="display:flex;align-items:center;margin-bottom:15px;padding:12px;background:linear-gradient(135deg,#f8f9ff,#f0f1ff);border-radius:8px;">
            <div class="sgc-score-ring <?php echo $seo_score >= 70 ? 'sgc-score-good' : ($seo_score >= 40 ? 'sgc-score-ok' : 'sgc-score-bad'); ?>" id="sgc-score-display">
                <?php echo intval($seo_score); ?>
            </div>
            <div>
                <strong style="font-size:14px;color:#1a1a2e;">SEO Score</strong>
                <div style="font-size:12px;color:#666;" id="sgc-score-label">
                    <?php echo $seo_score >= 80 ? 'Excellent! 🎉' : ($seo_score >= 60 ? 'Good — thoda improve karo' : ($seo_score >= 40 ? 'OK — kaafi scope hai' : 'Weak — SEO improve karo!')); ?>
                </div>
            </div>
            <button type="button" class="button" style="margin-left:auto;" onclick="sgcAnalyzeSEO()">🔄 Re-analyze</button>
        </div>

        <!-- ✨ ONE-CLICK AI SEO GENERATOR -->
        <div style="background:linear-gradient(135deg,#1a1a2e,#2d3561);border-radius:10px;padding:14px 16px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <div>
                    <div style="color:#fff;font-size:13px;font-weight:700;margin-bottom:3px;">✨ AI One-Click SEO Generator</div>
                    <div style="color:#a0aec0;font-size:11px;">AI se auto-generate: Title, Meta, Keywords, Description, Short Desc</div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" id="sgc-ai-preview-btn"
                        style="background:#6c5ce7;color:#fff;border:none;border-radius:7px;padding:9px 16px;font-size:12px;font-weight:600;cursor:pointer;"
                        onclick="sgcAiPreview(<?php echo $post->ID; ?>)">
                        👁 Preview
                    </button>
                    <button type="button" id="sgc-ai-generate-btn"
                        style="background:#27ae60;color:#fff;border:none;border-radius:7px;padding:9px 16px;font-size:12px;font-weight:600;cursor:pointer;"
                        onclick="sgcAiOneClick(<?php echo $post->ID; ?>)">
                        🤖 Generate &amp; Save All
                    </button>
                </div>
            </div>
            <div id="sgc-ai-status" style="margin-top:10px;display:none;font-size:12px;color:#81e6d9;padding:8px 12px;background:rgba(255,255,255,0.08);border-radius:6px;"></div>
            <!-- Preview Panel -->
            <div id="sgc-ai-preview-panel" style="display:none;margin-top:12px;background:rgba(255,255,255,0.06);border-radius:8px;padding:12px;">
                <div style="color:#e2e8f0;font-size:11px;font-weight:600;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">Preview — Click Generate to Save</div>
                <div id="sgc-preview-content" style="color:#cbd5e0;font-size:12px;line-height:1.8;"></div>
            </div>
        </div>
        <div class="sgc-seo-tabs">
            <div class="sgc-seo-tab active" data-tab="general">📝 General</div>
            <div class="sgc-seo-tab" data-tab="social">📱 Social</div>
            <div class="sgc-seo-tab" data-tab="advanced">⚙️ Advanced</div>
            <div class="sgc-seo-tab" data-tab="analysis">📊 Analysis</div>
        </div>

        <!-- TAB 1: General -->
        <div class="sgc-seo-panel active" id="sgc-tab-general">
            <!-- Focus Keywords -->
            <div class="sgc-field">
                <label>🎯 Focus Keyword</label>
                <input type="text" name="_sgclabs_focus_keyword" id="sgc-focus-kw" value="<?php echo esc_attr($focus_kw); ?>" placeholder="Primary keyword for this page">
                <span class="sgc-toggle-more" onclick="document.getElementById('sgc-extra-kw').style.display=document.getElementById('sgc-extra-kw').style.display==='none'?'block':'none'">+ Additional Keywords (up to 4 more)</span>
                <div id="sgc-extra-kw" class="sgc-additional-kw" style="display:<?php echo ($focus_kw_2||$focus_kw_3||$focus_kw_4||$focus_kw_5) ? 'block' : 'none'; ?>;">
                    <input type="text" name="_sgclabs_focus_kw_2" value="<?php echo esc_attr($focus_kw_2); ?>" placeholder="Focus Keyword 2">
                    <input type="text" name="_sgclabs_focus_kw_3" value="<?php echo esc_attr($focus_kw_3); ?>" placeholder="Focus Keyword 3">
                    <input type="text" name="_sgclabs_focus_kw_4" value="<?php echo esc_attr($focus_kw_4); ?>" placeholder="Focus Keyword 4">
                    <input type="text" name="_sgclabs_focus_kw_5" value="<?php echo esc_attr($focus_kw_5); ?>" placeholder="Focus Keyword 5">
                </div>
            </div>

            <!-- SERP Preview -->
            <div class="sgc-field">
                <label>👁️ Google SERP Preview</label>
                <div class="sgc-serp-preview">
                    <div class="sgc-serp-url"><?php echo esc_url(home_url()); ?><span> › <?php echo esc_html($slug); ?></span></div>
                    <div class="sgc-serp-title" id="sgc-serp-title"><?php echo esc_html($seo_title); ?></div>
                    <div class="sgc-serp-desc" id="sgc-serp-desc"><?php echo esc_html($seo_desc ?: 'Meta description yahan dikhega...'); ?></div>
                </div>
            </div>

            <!-- SEO Title -->
            <div class="sgc-field">
                <label>SEO Title</label>
                <input type="text" name="_sgclabs_seo_title" id="sgc-seo-title" value="<?php echo esc_attr($seo_title); ?>" placeholder="SEO Title — 30-60 characters optimal">
                <div class="sgc-char-count" id="sgc-title-count"><?php echo strlen($seo_title); ?>/60 characters</div>
            </div>

            <!-- Meta Description -->
            <div class="sgc-field">
                <label>Meta Description</label>
                <textarea name="_sgclabs_seo_description" id="sgc-seo-desc" rows="3" placeholder="Meta description — 120-160 characters optimal"><?php echo esc_textarea($seo_desc); ?></textarea>
                <div class="sgc-char-count" id="sgc-desc-count"><?php echo strlen($seo_desc); ?>/160 characters</div>
            </div>

            <!-- Cornerstone -->
            <div class="sgc-field">
                <label style="display:inline-flex;align-items:center;gap:6px;text-transform:none;font-size:13px;">
                    <input type="checkbox" name="_sgclabs_cornerstone" value="1" <?php checked($cornerstone, '1'); ?>>
                    ⭐ Mark as Cornerstone Content <span style="color:#999;font-weight:normal;">(prioritized for internal linking)</span>
                </label>
            </div>
        </div>

        <!-- TAB 2: Social -->
        <div class="sgc-seo-panel" id="sgc-tab-social">
            <h4 style="margin:0 0 12px;color:#4267B2;">📘 Facebook / Open Graph</h4>
            <div class="sgc-field">
                <label>OG Title</label>
                <input type="text" name="_sgclabs_og_title" value="<?php echo esc_attr($og_title); ?>" placeholder="Facebook title (leave empty = SEO Title)">
            </div>
            <div class="sgc-field">
                <label>OG Description</label>
                <textarea name="_sgclabs_og_description" rows="2" placeholder="Facebook description"><?php echo esc_textarea($og_desc); ?></textarea>
            </div>
            <div class="sgc-field">
                <label>OG Image URL</label>
                <input type="text" name="_sgclabs_og_image" id="sgc-og-image" value="<?php echo esc_attr($og_image); ?>" placeholder="https://... (1200x630 recommended)">
                <button type="button" class="button" style="margin-top:4px;" onclick="sgcSelectImage('sgc-og-image')">📷 Select Image</button>
            </div>
            <!-- FB Preview -->
            <div class="sgc-social-preview" style="margin-bottom:20px;">
                <div class="sgc-social-img" id="sgc-fb-preview-img">
                    <?php if ($og_image): ?><img src="<?php echo esc_url($og_image); ?>"><?php else: ?>1200×630 Image Preview<?php endif; ?>
                </div>
                <div class="sgc-social-text">
                    <div class="site"><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></div>
                    <div class="title" id="sgc-fb-preview-title"><?php echo esc_html($og_title ?: $seo_title); ?></div>
                    <div class="desc" id="sgc-fb-preview-desc"><?php echo esc_html($og_desc ?: $seo_desc); ?></div>
                </div>
            </div>

            <h4 style="margin:0 0 12px;color:#1DA1F2;">🐦 Twitter Card</h4>
            <div class="sgc-field">
                <label>Twitter Title</label>
                <input type="text" name="_sgclabs_twitter_title" value="<?php echo esc_attr($tw_title); ?>" placeholder="Twitter title (leave empty = SEO Title)">
            </div>
            <div class="sgc-field">
                <label>Twitter Description</label>
                <textarea name="_sgclabs_twitter_description" rows="2" placeholder="Twitter description"><?php echo esc_textarea($tw_desc); ?></textarea>
            </div>
            <div class="sgc-field">
                <label>Twitter Image URL</label>
                <input type="text" name="_sgclabs_twitter_image" id="sgc-tw-image" value="<?php echo esc_attr($tw_image); ?>" placeholder="https://... (1200x600 recommended)">
                <button type="button" class="button" style="margin-top:4px;" onclick="sgcSelectImage('sgc-tw-image')">📷 Select Image</button>
            </div>
        </div>

        <!-- TAB 3: Advanced -->
        <div class="sgc-seo-panel" id="sgc-tab-advanced">
            <div class="sgc-field">
                <label>🔗 Canonical URL</label>
                <input type="text" name="_sgclabs_canonical_url" value="<?php echo esc_attr($canonical); ?>" placeholder="Leave empty for default (recommended)">
                <span style="font-size:11px;color:#999;">Custom canonical URL — only fill if this page content exists on another URL</span>
            </div>

            <div class="sgc-field">
                <label>🤖 Robots Meta Tags</label>
                <div class="sgc-robots-grid">
                    <label><input type="checkbox" name="_sgclabs_robots_noindex" value="1" <?php checked($noindex, '1'); ?>> No Index</label>
                    <label><input type="checkbox" name="_sgclabs_robots_nofollow" value="1" <?php checked($nofollow, '1'); ?>> No Follow</label>
                    <label><input type="checkbox" name="_sgclabs_robots_noarchive" value="1" <?php checked($noarchive, '1'); ?>> No Archive</label>
                    <label><input type="checkbox" name="_sgclabs_robots_nosnippet" value="1" <?php checked($nosnippet, '1'); ?>> No Snippet</label>
                </div>
                <span style="font-size:11px;color:#999;margin-top:4px;display:block;">⚠️ No Index = Google index se remove | No Follow = Links ko crawl mat karo</span>
            </div>

            <div class="sgc-field">
                <label>🍞 Breadcrumb Title</label>
                <input type="text" name="_sgclabs_breadcrumb_title" value="<?php echo esc_attr($bc_title); ?>" placeholder="Custom breadcrumb title (leave empty = post title)">
            </div>
        </div>

        <!-- TAB 4: Analysis -->
        <div class="sgc-seo-panel" id="sgc-tab-analysis">
            <h4 style="margin:0 0 10px;">📋 SEO Checks</h4>
            <ul class="sgc-check-list" id="sgc-check-list">
                <li style="color:#999;font-size:12px;">Click "Re-analyze" to see results...</li>
            </ul>

            <h4 style="margin:15px 0 10px;">📖 Readability</h4>
            <div id="sgc-readability-results">
                <div style="color:#999;font-size:12px;">Click "Re-analyze" to see readability...</div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        // Tab switching
        document.querySelectorAll('.sgc-seo-tab').forEach(function(tab){
            tab.addEventListener('click', function(){
                document.querySelectorAll('.sgc-seo-tab').forEach(t=>t.classList.remove('active'));
                document.querySelectorAll('.sgc-seo-panel').forEach(p=>p.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('sgc-tab-'+this.dataset.tab).classList.add('active');
            });
        });

        // Live SERP preview
        var titleEl = document.getElementById('sgc-seo-title');
        var descEl  = document.getElementById('sgc-seo-desc');
        if(titleEl){
            titleEl.addEventListener('input', function(){
                document.getElementById('sgc-serp-title').textContent = this.value || 'Page Title';
                var len = this.value.length;
                var ct = document.getElementById('sgc-title-count');
                ct.textContent = len + '/60 characters';
                ct.className = 'sgc-char-count ' + (len>=30&&len<=60?'good':(len>60?'bad':'warn'));
            });
        }
        if(descEl){
            descEl.addEventListener('input', function(){
                document.getElementById('sgc-serp-desc').textContent = this.value || 'Meta description yahan dikhega...';
                var len = this.value.length;
                var ct = document.getElementById('sgc-desc-count');
                ct.textContent = len + '/160 characters';
                ct.className = 'sgc-char-count ' + (len>=120&&len<=160?'good':(len>160?'bad':'warn'));
            });
        }
        // Initial char count colors
        if(titleEl) titleEl.dispatchEvent(new Event('input'));
        if(descEl) descEl.dispatchEvent(new Event('input'));
    })();

    function sgcSelectImage(targetId) {
        if(typeof wp !== 'undefined' && wp.media) {
            var frame = wp.media({title:'Select Image',multiple:false,library:{type:'image'}});
            frame.on('select', function(){
                var url = frame.state().get('selection').first().toJSON().url;
                document.getElementById(targetId).value = url;
            });
            frame.open();
        } else {
            alert('Media library not available');
        }
    }

    function sgcAnalyzeSEO() {
        var content = '';
        // Try to get content from classic editor or Gutenberg
        if(typeof tinymce !== 'undefined' && tinymce.get('content')) {
            content = tinymce.get('content').getContent();
        } else if(typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            content = wp.data.select('core/editor').getEditedPostContent();
        } else {
            var el = document.getElementById('content');
            if(el) content = el.value;
        }

        jQuery.ajax({
            url: ajaxurl, type: 'POST',
            data: {
                action: 'sgclabs_analyze_seo',
                nonce: '<?php echo esc_js( wp_create_nonce( "sgclabs_nonce" ) ); ?>',
                post_id: <?php echo $post->ID; ?>,
                focus_keyword: document.getElementById('sgc-focus-kw').value,
                seo_title: document.getElementById('sgc-seo-title').value,
                seo_description: document.getElementById('sgc-seo-desc').value,
                content: content,
                permalink: '<?php echo esc_js($permalink); ?>'
            },
            success: function(res) {
                if(!res.success) return;
                var d = res.data;
                // Update score
                var ring = document.getElementById('sgc-score-display');
                ring.textContent = d.score;
                ring.className = 'sgc-score-ring ' + (d.score>=70?'sgc-score-good':(d.score>=40?'sgc-score-ok':'sgc-score-bad'));
                var label = document.getElementById('sgc-score-label');
                label.textContent = d.score>=80?'Excellent! 🎉':(d.score>=60?'Good — thoda improve karo':(d.score>=40?'OK — scope hai':'Weak — SEO improve karo!'));

                // Checks
                var list = document.getElementById('sgc-check-list');
                list.innerHTML = '';
                d.checks.forEach(function(c){
                    var li = document.createElement('li');
                    li.className = c.pass ? 'sgc-check-pass' : 'sgc-check-fail';
                    li.innerHTML = (c.pass?'✅':'❌') + ' ' + c.msg;
                    list.appendChild(li);
                });

                // Readability
                var r = d.readability;
                var rDiv = document.getElementById('sgc-readability-results');
                var fClass = r.flesch_score>=60?'sgc-readability-good':(r.flesch_score>=30?'sgc-readability-warn':'sgc-readability-bad');
                rDiv.innerHTML =
                    '<div class="sgc-readability-item"><span>Flesch Reading Ease</span><span class="sgc-readability-val '+fClass+'">'+r.flesch_score+' ('+r.flesch_label+')</span></div>'+
                    '<div class="sgc-readability-item"><span>Word Count</span><span class="sgc-readability-val">'+r.word_count+'</span></div>'+
                    '<div class="sgc-readability-item"><span>Sentences</span><span class="sgc-readability-val">'+r.sentence_count+'</span></div>'+
                    '<div class="sgc-readability-item"><span>Avg Sentence Length</span><span class="sgc-readability-val '+(r.avg_sentence_len<=20?'sgc-readability-good':'sgc-readability-warn')+'">'+r.avg_sentence_len+' words</span></div>'+
                    '<div class="sgc-readability-item"><span>Long Sentences (>20 words)</span><span class="sgc-readability-val '+(r.long_sentences<=2?'sgc-readability-good':'sgc-readability-warn')+'">'+r.long_sentences+'</span></div>'+
                    '<div class="sgc-readability-item"><span>Passive Voice</span><span class="sgc-readability-val '+(r.passive_voice<=3?'sgc-readability-good':'sgc-readability-warn')+'">'+r.passive_voice+' instances</span></div>'+
                    '<div class="sgc-readability-item"><span>Transition Words</span><span class="sgc-readability-val '+(r.transition_words>=3?'sgc-readability-good':'sgc-readability-warn')+'">'+r.transition_words+'</span></div>';

                // Switch to analysis tab
                document.querySelectorAll('.sgc-seo-tab').forEach(t=>t.classList.remove('active'));
                document.querySelectorAll('.sgc-seo-panel').forEach(p=>p.classList.remove('active'));
                document.querySelector('[data-tab="analysis"]').classList.add('active');
                document.getElementById('sgc-tab-analysis').classList.add('active');
            }
        });
    }

    // ── ONE-CLICK AI: Preview ──────────────────────────────────
    function sgcAiPreview(pid) {
        var btn = document.getElementById('sgc-ai-preview-btn');
        var status = document.getElementById('sgc-ai-status');
        var panel = document.getElementById('sgc-ai-preview-panel');
        var content = document.getElementById('sgc-preview-content');

        btn.textContent = '⏳ Loading...';
        btn.disabled = true;
        status.style.display = 'block';
        status.textContent = '🤖 AI se preview generate ho raha hai...';

        jQuery.post(ajaxurl, {
            action: 'sgclabs_ai_preview_fix',
            nonce: '<?php echo wp_create_nonce("sgclabs_nonce"); ?>',
            post_id: pid
        }, function(res) {
            btn.textContent = '👁 Preview';
            btn.disabled = false;
            if (res.success) {
                status.style.display = 'none';
                panel.style.display = 'block';
                content.innerHTML =
                    '<b style="color:#90cdf4;">Title:</b> ' + (res.data.title||'—') + '<br>' +
                    '<b style="color:#90cdf4;">Meta Desc:</b> ' + (res.data.desc||'—') + '<br>' +
                    '<b style="color:#90cdf4;">Focus KW:</b> ' + (res.data.focus_kw||'—') + '<br>' +
                    '<b style="color:#90cdf4;">Short Desc:</b> ' + (res.data.short_desc||'—').substring(0,200) + '...';
            } else {
                status.textContent = '❌ Error: ' + (res.data||'Unknown error');
            }
        }).fail(function() {
            btn.textContent = '👁 Preview';
            btn.disabled = false;
            status.textContent = '❌ Request failed — API key check karo';
        });
    }

    // ── ONE-CLICK AI: Generate & Save All ─────────────────────
    function sgcAiOneClick(pid) {
        var btn = document.getElementById('sgc-ai-generate-btn');
        var status = document.getElementById('sgc-ai-status');
        var panel = document.getElementById('sgc-ai-preview-panel');

        btn.textContent = '⏳ Generating...';
        btn.disabled = true;
        status.style.display = 'block';
        status.textContent = '🤖 AI sab kuch generate kar raha hai — Title, Meta, Keywords, Description...';
        panel.style.display = 'none';

        jQuery.post(ajaxurl, {
            action: 'sgclabs_ai_one_click_fix',
            nonce: '<?php echo wp_create_nonce("sgclabs_nonce"); ?>',
            post_id: pid
        }, function(res) {
            btn.textContent = '🤖 Generate & Save All';
            btn.disabled = false;
            if (res.success) {
                status.textContent = '✅ All fields updated! Page refresh ho rahi hai...';
                // Fields update karo agar visible hain
                var d = res.data;
                if (d) {
                    var titleField = document.getElementById('sgclabs_seo_title');
                    var descField  = document.getElementById('sgclabs_seo_description');
                    var kwField    = document.getElementById('sgclabs_focus_keyword');
                    if (titleField && d.seo_title) titleField.value = d.seo_title;
                    if (descField  && d.meta_desc)  descField.value  = d.meta_desc;
                    if (kwField    && d.focus_kw)   kwField.value    = d.focus_kw;
                }
                setTimeout(function() {
                    location.reload();
                }, 1800);
            } else {
                status.textContent = '❌ Error: ' + (res.data||'API key check karo — SGC LABS → AI Center');
            }
        }).fail(function() {
            btn.textContent = '🤖 Generate & Save All';
            btn.disabled = false;
            status.textContent = '❌ Request failed — Internet ya API key check karo';
        });
    }
    </script>
    <?php
}

// ── Frontend: Inject per-post SEO meta tags ──
add_action('wp_head', 'sgclabs_metabox_inject_frontend', 3);
function sgclabs_metabox_inject_frontend() {
    if (get_option('sgclabs_seo_metabox_active', '1') !== '1') return;
    if (is_admin() || !is_singular()) return;
    // Skip if other SEO plugins handle this
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')) return;

    $post_id = get_the_ID();
    if (!$post_id) return;

    $seo_title = get_post_meta($post_id, '_sgclabs_seo_title', true);
    $seo_desc  = get_post_meta($post_id, '_sgclabs_seo_description', true);
    $canonical = get_post_meta($post_id, '_sgclabs_canonical_url', true);
    $noindex   = get_post_meta($post_id, '_sgclabs_robots_noindex', true);
    $nofollow  = get_post_meta($post_id, '_sgclabs_robots_nofollow', true);
    $noarchive = get_post_meta($post_id, '_sgclabs_robots_noarchive', true);
    $nosnippet = get_post_meta($post_id, '_sgclabs_robots_nosnippet', true);
    $og_title  = get_post_meta($post_id, '_sgclabs_og_title', true);
    $og_desc   = get_post_meta($post_id, '_sgclabs_og_description', true);
    $og_image  = get_post_meta($post_id, '_sgclabs_og_image', true);
    $tw_title  = get_post_meta($post_id, '_sgclabs_twitter_title', true);
    $tw_desc   = get_post_meta($post_id, '_sgclabs_twitter_description', true);
    $tw_image  = get_post_meta($post_id, '_sgclabs_twitter_image', true);

    // Meta description
    if ($seo_desc) echo "<meta name='description' content='" . esc_attr($seo_desc) . "'>\n";

    // Canonical
    if ($canonical) echo "<link rel='canonical' href='" . esc_url($canonical) . "'>\n";

    // Robots
    $robots = [];
    if ($noindex)   $robots[] = 'noindex';
    if ($nofollow)  $robots[] = 'nofollow';
    if ($noarchive) $robots[] = 'noarchive';
    if ($nosnippet) $robots[] = 'nosnippet';
    if (!empty($robots)) echo "<meta name='robots' content='" . esc_attr(implode(', ', $robots)) . "'>\n";

    // Open Graph
    echo "<meta property='og:title' content='" . esc_attr($og_title ?: $seo_title ?: get_the_title()) . "'>\n";
    echo "<meta property='og:type' content='article'>\n";
    echo "<meta property='og:url' content='" . esc_url(get_permalink()) . "'>\n";
    if ($og_desc || $seo_desc) echo "<meta property='og:description' content='" . esc_attr($og_desc ?: $seo_desc) . "'>\n";
    if ($og_image) echo "<meta property='og:image' content='" . esc_url($og_image) . "'>\n";
    elseif (has_post_thumbnail($post_id)) echo "<meta property='og:image' content='" . esc_url(get_the_post_thumbnail_url($post_id, 'large')) . "'>\n";

    // Twitter Card
    echo "<meta name='twitter:card' content='summary_large_image'>\n";
    echo "<meta name='twitter:title' content='" . esc_attr($tw_title ?: $og_title ?: $seo_title ?: get_the_title()) . "'>\n";
    if ($tw_desc || $og_desc || $seo_desc) echo "<meta name='twitter:description' content='" . esc_attr($tw_desc ?: $og_desc ?: $seo_desc) . "'>\n";
    if ($tw_image) echo "<meta name='twitter:image' content='" . esc_url($tw_image) . "'>\n";
    elseif ($og_image) echo "<meta name='twitter:image' content='" . esc_url($og_image) . "'>\n";
}

// ── Frontend: Override title tag ──
add_filter('pre_get_document_title', 'sgclabs_metabox_title_override', 99);
add_filter('document_title_parts', 'sgclabs_metabox_title_parts', 99);
function sgclabs_metabox_title_override($title) {
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) return $title;
    if (!is_singular()) return $title;
    $seo_title = get_post_meta(get_the_ID(), '_sgclabs_seo_title', true);
    return $seo_title ?: $title;
}
function sgclabs_metabox_title_parts($parts) {
    if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION')) return $parts;
    if (!is_singular()) return $parts;
    $seo_title = get_post_meta(get_the_ID(), '_sgclabs_seo_title', true);
    if ($seo_title) $parts['title'] = $seo_title;
    return $parts;
}

// ── Show SEO Score in Post List Column ──
add_filter('manage_posts_columns', 'sgclabs_add_seo_score_column');
add_filter('manage_pages_columns', 'sgclabs_add_seo_score_column');
add_action('manage_posts_custom_column', 'sgclabs_seo_score_column_data', 10, 2);
add_action('manage_pages_custom_column', 'sgclabs_seo_score_column_data', 10, 2);

function sgclabs_add_seo_score_column($columns) {
    $columns['sgc_seo_score'] = 'SEO';
    return $columns;
}
function sgclabs_seo_score_column_data($column, $post_id) {
    if ($column !== 'sgc_seo_score') return;
    $score = get_post_meta($post_id, '_sgclabs_seo_score', true) ?: 0;
    $color = $score >= 70 ? '#27ae60' : ($score >= 40 ? '#f39c12' : '#e74c3c');
    echo "<span style='display:inline-block;width:36px;height:36px;border-radius:50%;background:{$color};color:#fff;text-align:center;line-height:36px;font-weight:bold;font-size:12px;'>{$score}</span>";
}

// ── Breadcrumbs ──
function sgclabs_breadcrumbs($args = []) {
    if (!is_singular() && !is_archive()) return;
    $sep = $args['separator'] ?? ' › ';
    $home_text = $args['home'] ?? 'Home';

    echo '<nav class="sgclabs-breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">';

    $pos = 1;
    // Home
    echo '<span itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
    echo '<a itemprop="item" href="' . esc_url(home_url('/')) . '"><span itemprop="name">' . esc_html($home_text) . '</span></a>';
    echo '<meta itemprop="position" content="' . $pos . '">';
    echo '</span>';

    if (is_singular()) {
        $post_id = get_the_ID();
        $bc_title = get_post_meta($post_id, '_sgclabs_breadcrumb_title', true) ?: get_the_title();

        // Category
        if (get_post_type() === 'product' && function_exists('wc_get_product')) {
            $cats = wp_get_post_terms($post_id, 'product_cat');
            if (!empty($cats)) {
                $pos++;
                echo $sep;
                echo '<span itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
                echo '<a itemprop="item" href="' . esc_url(get_term_link($cats[0])) . '"><span itemprop="name">' . esc_html($cats[0]->name) . '</span></a>';
                echo '<meta itemprop="position" content="' . $pos . '">';
                echo '</span>';
            }
        } elseif (get_post_type() === 'post') {
            $cats = get_the_category($post_id);
            if (!empty($cats)) {
                $pos++;
                echo $sep;
                echo '<span itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
                echo '<a itemprop="item" href="' . esc_url(get_category_link($cats[0]->term_id)) . '"><span itemprop="name">' . esc_html($cats[0]->name) . '</span></a>';
                echo '<meta itemprop="position" content="' . $pos . '">';
                echo '</span>';
            }
        }

        // Current page
        $pos++;
        echo $sep;
        echo '<span itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        echo '<span itemprop="name">' . esc_html($bc_title) . '</span>';
        echo '<meta itemprop="position" content="' . $pos . '">';
        echo '</span>';
    }

    echo '</nav>';

    // BreadcrumbList schema JSON-LD
    if (is_singular() && !(defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION'))) {
        // Already handled in auto-seo-engine for products
    }
}
