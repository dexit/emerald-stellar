<?php

/**
 * SEO Audit Experiment Implementation
 * 
 * @package SEOAudit\Experiments\SEO_Audit
 */

namespace SEOAudit\Experiments\SEO_Audit;

use SEOAudit\Abstracts\Abstract_Experiment;
use SEOAudit\Core\Prompt_Manager;

class SEO_Audit extends Abstract_Experiment
{

    protected function load_experiment_metadata(): array
    {
        return [
            'id'          => 'seo-audit',
            'label'       => __('Full SEO Audit', 'seo-audit'),
            'description' => __('Performs a comprehensive SEO audit using AI and traditional checks.', 'seo-audit'),
        ];
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_seo_audit_run_full', [$this, 'run_full_audit']);
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
    }

    public function register_meta_box()
    {
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            add_meta_box('seo_audit_meta_box', __('AI SEO Audit', 'seo-audit'), [$this, 'render_meta_box'], $post_type, 'side', 'high');
        }
    }

    public function render_meta_box($post)
    {
        $focus_keyword = get_post_meta($post->ID, '_seo_audit_focus_keyword', true);
        $last_score    = get_post_meta($post->ID, '_seo_audit_score', true);
        $last_run      = get_post_meta($post->ID, '_seo_audit_last_run', true);
        $readability   = get_post_meta($post->ID, '_seo_audit_readability', true);
        $word_count    = get_post_meta($post->ID, '_seo_audit_word_count', true);

        ?>
        <div id="wp-admin-seo-audit-box" class="seo-audit-sidebar-container">
            <?php if ($last_run) : 
                $score_class = ($last_score >= 70) ? 'good' : (($last_score >= 40) ? 'warning' : 'bad');
            ?>
                <div class="seo-sidebar-stats">
                    <div class="seo-score-circle <?php echo esc_attr($score_class); ?>">
                        <span class="score-value"><?php echo esc_html($last_score); ?></span>
                        <span class="score-label">Score</span>
                    </div>
                    <div class="seo-meta-info">
                        <div class="info-item">
                            <span class="dashicons dashicons-book"></span>
                            <span><?php echo esc_html($readability); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="dashicons dashicons-editor-paragraph"></span>
                            <span><?php echo number_format($word_count); ?> words</span>
                        </div>
                        <div class="info-time">
                            <?php printf(esc_html__('Last checked %s ago', 'seo-audit'), human_time_diff(strtotime($last_run))); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="seo-field-group">
                <label for="seo-audit-focus-keyword"><?php esc_html_e('Focus Keyword', 'seo-audit'); ?></label>
                <div class="seo-input-with-button">
                    <input type="text" id="seo-audit-focus-keyword" value="<?php echo esc_attr($focus_keyword); ?>" placeholder="e.g. WordPress SEO">
                    <button type="button" class="button" id="seo-audit-save-keyword"><?php esc_html_e('Save', 'seo-audit'); ?></button>
                </div>
            </div>

            <button type="button" class="button button-primary button-hero" id="run-wp-admin-seo-audit">
                <span class="dashicons dashicons-performance"></span>
                <?php esc_html_e('Run Full AI Audit', 'seo-audit'); ?>
            </button>
            
            <div class="seo-sidebar-footer">
                <small><?php esc_html_e('Powered by Local Gemini Nano AI', 'seo-audit'); ?></small>
            </div>
        </div>
        <?php
    }

    public function enqueue_assets($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        // SweetAlert2 for the premium reports
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11.0.0', true);

        wp_enqueue_script(
            'seo-audit-main',
            SEO_AUDIT_URL . 'assets/js/seo-audit-main.js',
            ['jquery', 'wp-i18n', 'sweetalert2'],
            SEO_AUDIT_VERSION,
            true
        );

        wp_enqueue_style(
            'seo-audit-main',
            SEO_AUDIT_URL . 'assets/css/seo-audit-main.css',
            [],
            SEO_AUDIT_VERSION
        );

        wp_enqueue_script(
            'seo-audit-sidebar',
            SEO_AUDIT_URL . 'assets/js/seo-audit-sidebar.js',
            ['jquery'],
            SEO_AUDIT_VERSION,
            true
        );

        wp_localize_script('seo-audit-main', 'seoAuditSettings', [
            'apiUrl' => admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('seo_audit_nonce'),
            'systemPrompt' => Prompt_Manager::get_seo_audit_system_prompt(),
        ]);

        $post = get_post();
        wp_localize_script('seo-audit-sidebar', 'seoAuditSidebar', [
            'apiUrl' => admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('seo_audit_nonce'),
            'postId' => $post ? $post->ID : 0,
            'permalink' => $post ? get_permalink($post->ID) : '',
        ]);
    }

    /**
     * AJAX handler for full audit
     */
    public function run_full_audit()
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission to perform audits.', 'seo-audit')]);
        }
        check_ajax_referer('seo_audit_nonce', 'nonce');

        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $url     = isset($_POST['url'])     ? esc_url($_POST['url'])          : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id'])        : 0;
        $title   = isset($_POST['title'])   ? sanitize_text_field($_POST['title']) : '';
        $desc    = isset($_POST['meta_description']) ? sanitize_text_field($_POST['meta_description']) : '';

        if (empty($content)) {
            wp_send_json_error(['message' => __('No content provided for audit.', 'seo-audit')]);
        }

        $audit_service = new \SEOAudit\Services\Audit_Service();
        $results = $audit_service->perform_full_audit($content, $url, [
            'title' => $title,
            'meta_description' => $desc,
            'post_id' => $post_id
        ]);

        // Persist lightweight scores to post meta for dashboard history
        if ($post_id > 0 && current_user_can('edit_post', $post_id)) {
            $seo_checks    = $results['seo'] ?? [];
            $passed_count  = count(array_filter($seo_checks, fn($c) => !empty($c['passed'])));
            $total_checks  = max(1, count($seo_checks));
            $seo_score     = round(($passed_count / $total_checks) * 100);

            $ease          = $results['readability']['flesch_reading_ease'] ?? 0;
            if ($ease >= 70)      $readability_label = 'Easy';
            elseif ($ease >= 50)  $readability_label = 'Moderate';
            else                  $readability_label = 'Difficult';

            update_post_meta($post_id, '_seo_audit_score',       $seo_score);
            update_post_meta($post_id, '_seo_audit_readability',  $readability_label);
            update_post_meta($post_id, '_seo_audit_last_run',     current_time('mysql'));
            update_post_meta($post_id, '_seo_audit_word_count',   $results['readability']['word_count'] ?? 0);
        }

        wp_send_json_success($results);
    }

        $audit['inlineCss'] = [
            'passed' => $crawler->filter('[style]')->count() === 0,
            'shortAnswer' => 'Inline CSS Check',
            'recommendation' => 'Move inline styles to external stylesheets.'
        ];

        // 4. HEADER & CONTENT HIERARCHY (H1-H6)
        if ($post_id > 0) {
            $audit['h1Missing'] = $seo_core->h1_missing($post_id);
            $audit['h1Multiple'] = $seo_core->h1_multiple($post_id);
            $audit['h1Length'] = $seo_core->h1_over_70_characters($post_id);
            $audit['h1Sequential'] = $seo_core->h1_nonsequential($post_id);
        } else {
            $h1_tags = $crawler->filter('h1');
            $h1_count = $h1_tags->count();
            $audit['hasH1Header'] = [
                'value' => $h1_count,
                'passed' => ($h1_count === 1),
                'shortAnswer' => ($h1_count === 0) ? 'No H1 Found' : (($h1_count > 1) ? 'Duplicate H1s' : 'Correct H1 Usage'),
                'recommendation' => 'Each page should have exactly one H1 summarizing the overarching topic.'
            ];
        }

        $h2_count = $crawler->filter('h2')->count();
        $audit['h2Usage'] = [
            'passed' => $h2_count > 0,
            'shortAnswer' => $h2_count > 0 ? "$h2_count H2s Found" : 'No H2 Tags Found',
            'recommendation' => 'Use H2s to indicate the main sections of your page.'
        ];

        // 5. SOCIAL & ANALYTICS
        $og_count = $crawler->filter('meta[property^="og:"]')->count();
        $twitter_count = $crawler->filter('meta[name^="twitter:"]')->count();
        $audit['hasOpenGraph'] = [
            'passed' => $og_count > 0,
            'shortAnswer' => $og_count > 0 ? "OpenGraph Found ($og_count tags)" : 'No OpenGraph Tags',
            'recommendation' => 'Add OpenGraph tags for optimal sharing on Facebook/LinkedIn.'
        ];
        $audit['hasTwitterCards'] = [
            'passed' => $twitter_count > 0,
            'shortAnswer' => $twitter_count > 0 ? "Twitter Cards Found ($twitter_count tags)" : 'No Twitter Cards',
            'recommendation' => 'Add Twitter card meta tags for optimal sharing on Twitter/X.'
        ];
        $audit['hasAnalytics'] = ['passed' => preg_match('/googletagmanager\.com|google-analytics\.com/i', $content)];

        // 6. CONTENT ANALYSIS
        $word_count = str_word_count($plain_content);
        $audit['contentCount'] = [
            'value' => $word_count,
            'passed' => $word_count >= 500,
            'shortAnswer' => $word_count < 500 ? 'Thin Content' : 'Adequate Content',
            'recommendation' => 'Aim for 500+ words of high-quality content.'
        ];

        $audit['loremIpsum'] = $seo_core->content_lorem_ipsum_placeholder($content);
        $audit['spelling'] = $seo_core->content_spelling_errors($content);

        // 7. IMAGE OPTIMIZATION
        if ($post_id > 0) {
            $audit['missingAlt'] = $seo_core->images_missing_alt_text();
            $audit['largeImages'] = $seo_core->images_over_100_kb();
        } else {
            $img_tags = $crawler->filter('img');
            $img_count = $img_tags->count();
            $missing_alt = 0;
            $img_tags->each(function ($node) use (&$missing_alt) {
                if (!$node->attr('alt')) $missing_alt++;
            });
            $audit['hasImageWithoutAlt'] = [
                'value' => $missing_alt,
                'passed' => ($missing_alt === 0),
                'shortAnswer' => $img_count > 0 ? ($missing_alt > 0 ? "Missing $missing_alt Alts" : 'Alt Tags Optimized') : 'No Images Found'
            ];
        }

        // 8. META DESCRIPTION
        if ($post_id > 0) {
            $audit['metaMissing'] = $seo_core->meta_description_missing($post_id);
            $audit['metaLength'] = $seo_core->meta_description_over_155_characters($post_id);
        }

        // 9. LINK ANALYSIS
        $links = $crawler->filter('a');
        $internal_links = 0;
        $external_links = 0;
        
        $links->each(function ($node) use (&$internal_links, &$external_links, $url) {
            $href = $node->attr('href');
            if (!empty($href) && !str_starts_with($href, '#') && !str_starts_with($href, 'javascript:')) {
                $host = parse_url($url, PHP_URL_HOST) ?? '';
                $link_host = parse_url($href, PHP_URL_HOST);
                if (empty($link_host) || $link_host === $host) $internal_links++;
                else $external_links++;
            }
        });

        $audit['linkAnalysis'] = [
            'value' => ["internal" => $internal_links, "external" => $external_links],
            'passed' => $internal_links > 0,
            'shortAnswer' => "Internal: $internal_links | External: $external_links"
        ];

        // Ensure main URL is 200 OK & Security Headers
        $response_status = 'N/A';
        $security_headers = [];
        $sitemap_detected = false;

        if (!empty($url)) {
            $main_response = wp_remote_head($url, ['timeout' => 5]);
            if (!is_wp_error($main_response)) {
                $response_status = wp_remote_retrieve_response_code($main_response);
                $headers = wp_remote_retrieve_headers($main_response);
                
                // Security Header Checks
                $security_headers['hsts'] = isset($headers['strict-transport-security']);
                $security_headers['x_frame'] = isset($headers['x-frame-options']);
                $security_headers['content_type'] = isset($headers['x-content-type-options']);
            }
            
            // Basic Sitemap Detection
            $parsed_url = parse_url($url);
            $base_url = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '');
            $sitemap_response = wp_remote_head($base_url . '/sitemap.xml', ['timeout' => 2]);
            if (!is_wp_error($sitemap_response) && wp_remote_retrieve_response_code($sitemap_response) === 200) {
                $sitemap_detected = true;
            } else {
                // Secondary check for Yoast/RankMath style sitemap indexes
                $sitemap_index = wp_remote_head($base_url . '/sitemap_index.xml', ['timeout' => 2]);
                if (!is_wp_error($sitemap_index) && wp_remote_retrieve_response_code($sitemap_index) === 200) {
                    $sitemap_detected = true;
                }
            }
        }
        
        $audit['responseCode'] = [
            'value' => $response_status,
            'passed' => $response_status === 200,
            'shortAnswer' => "HTTP $response_status",
            'recommendation' => 'The target page must return a 200 OK status to be fully indexable.'
        ];

        $audit['securityHeaders'] = [
            'passed' => !empty($security_headers['hsts']),
            'shortAnswer' => !empty($security_headers['hsts']) ? 'HSTS Active' : 'Missing HSTS/Security',
            'recommendation' => 'Ensure your site forces HTTPS using Strict-Transport-Security headers.'
        ];
        
        $audit['sitemapValidation'] = [
            'passed' => $sitemap_detected,
            'shortAnswer' => $sitemap_detected ? 'Sitemap Found' : 'No Default Sitemap Found',
            'recommendation' => 'Ensure a valid sitemap.xml exists at the root of your domain for search engine mapping.'
        ];
        $audit['hasImageWithoutAlt'] = [
            'value' => $missing_alt,
            'passed' => ($missing_alt === 0),
            'shortAnswer' => $img_count > 0 ? ($missing_alt > 0 ? "Missing $missing_alt Alts" : 'Alt Tags Optimized') : 'No Images Found',
            'recommendation' => 'Always provide descriptive alt text for accessibility and SEO.'
        ];

        $audit['modernImageFormats'] = [
            'value' => $modern_formats,
            'passed' => $legacy_formats === 0,
            'shortAnswer' => "WebP/AVIF: $modern_formats, Legacy (JPG/PNG): $legacy_formats",
            'recommendation' => 'Use next-gen formats like WebP or AVIF for better compression.'
        ];

        $audit['svgUsage'] = [
            'value' => $svg_count,
            'passed' => true,
            'shortAnswer' => $svg_count > 0 ? "SVGs Found ($svg_count)" : 'No SVGs',
            'recommendation' => 'Use SVGs for logos, icons, illustrations, and maps to preserve sharpness at minimal file sizes.'
        ];

        // 8. STRUCTURED DATA / SCHEMA (LD-JSON)
        $schema_tags = $crawler->filter('script[type="application/ld+json"]');
        $schemas_found = [];
        $schema_tags->each(function ($node) use (&$schemas_found) {
            $json = json_decode($node->text(), true);
            if ($json) {
                // If it's a graph array, map out the types
                if (isset($json['@graph'])) {
                    foreach ($json['@graph'] as $g) {
                        if (isset($g['@type'])) $schemas_found[] = $g['@type'];
                    }
                } elseif (isset($json['@type'])) {
                    $schemas_found[] = $json['@type'];
                }
            }
        });
        
        $audit['hasLdJsonSchema'] = [
            'value' => count($schemas_found),
            'passed' => count($schemas_found) > 0,
            'shortAnswer' => count($schemas_found) > 0 ? "Schema Types: " . implode(', ', array_unique($schemas_found)) : 'No LD-JSON Schema Found',
            'recommendation' => 'Implement structured data (Schema.org) using application/ld+json format for richer search results.'
        ];

        // 9. AI DOMAIN COMPATIBILITY & DIRECTIVES
        if (!empty($url)) {
            $parsed_url = parse_url($url);
            $base_url = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '');
            
            // Check for llms.txt (Common standard for defining AI reading context limits)
            $llms_response = wp_remote_head($base_url . '/llms.txt', ['timeout' => 3]);
            $has_llms = !is_wp_error($llms_response) && wp_remote_retrieve_response_code($llms_response) === 200;
            
            // Check for ai.txt or structured AI instructions
            $ai_response = wp_remote_head($base_url . '/ai.txt', ['timeout' => 3]);
            $has_ai_txt = !is_wp_error($ai_response) && wp_remote_retrieve_response_code($ai_response) === 200;

            // Check standard robots.txt for AI bots blockage? Too complex to parse deeply, just acknowledge existence
            $robots_response = wp_remote_get($base_url . '/robots.txt', ['timeout' => 3]);
            $robots_blocked_ai = false;
            
            if (!is_wp_error($robots_response) && wp_remote_retrieve_response_code($robots_response) === 200) {
                $robots_txt = wp_remote_retrieve_body($robots_response);
                if (stripos($robots_txt, 'GPTBot') !== false || stripos($robots_txt, 'CCBot') !== false || stripos($robots_txt, 'Google-Extended') !== false) {
                    $robots_blocked_ai = true; // simplifying logic, assume mentioned == blocked or specified
                }
            }

            $audit['aiDomainDirectives'] = [
                'passed' => $has_llms || $has_ai_txt || $robots_blocked_ai,
                'shortAnswer' => ($has_llms ? 'llms.txt ' : '') . ($has_ai_txt ? 'ai.txt ' : '') . ($robots_blocked_ai ? 'robots.txt AI rules ' : ''),
                'recommendation' => 'Consider hosting a /llms.txt or /ai.txt file at the root to instruct AI models navigating your site.'
            ];
            
            if (empty($audit['aiDomainDirectives']['shortAnswer'])) {
                $audit['aiDomainDirectives']['shortAnswer'] = 'No AI Guidelines Configured';
            }
        }

        return [
            'seo' => $audit,
            'performance' => [
                'gzip' => $this->check_gzip($url),
                'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1'
            ],
            'timestamp' => current_time('mysql')
        ];
    }

    private function extract_keywords($text)
    {
        $words = str_word_count(strtolower($text), 1);
        $stop_words = ['the', 'and', 'a', 'to', 'of', 'in', 'is', 'it', 'for', 'with', 'on', 'this', 'that'];
        $filtered = array_filter($words, fn($w) => !in_array($w, $stop_words) && strlen($w) > 3);
        $counts = array_count_values($filtered);
        arsort($counts);
        return array_slice($counts, 0, 10, true);
    }

    private function check_gzip($url)
    {
        if (empty($url)) return false;
        $response = wp_remote_head($url);
        if (is_wp_error($response)) return false;
        $headers = wp_remote_retrieve_headers($response);
        return isset($headers['content-encoding']) && str_contains($headers['content-encoding'], 'gzip');
    }
}
