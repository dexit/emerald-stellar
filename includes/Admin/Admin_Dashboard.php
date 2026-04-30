<?php

/**
 * Admin Dashboard
 *
 * Provides the top-level SEO Audit admin dashboard, including a summary widget,
 * audit history, background rescan executor, and CSV export endpoint.
 *
 * @package SEOAudit\Admin
 */

namespace SEOAudit\Admin;

class Admin_Dashboard
{
    public function init(): void
    {
        add_action('admin_menu',            [$this, 'register_dashboard_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);

        // Background rescan executor (runs on every admin_init)
        add_action('admin_init', [$this, 'process_queued_rescans']);

        // AJAX: export audit results as CSV
        add_action('wp_ajax_seo_audit_export_csv', [$this, 'export_audit_csv']);

        // AJAX: save per-post focus keyword
        add_action('wp_ajax_seo_audit_save_focus_keyword', [$this, 'save_focus_keyword']);

        // Dashboard widget on WP Dashboard
        add_action('wp_dashboard_setup', [$this, 'register_wp_dashboard_widget']);

        // AJAX: Queue all posts for rescan
        add_action('wp_ajax_seo_audit_queue_all', [$this, 'queue_all_rescans']);
    }

    // -------------------------------------------------------------------------
    // MENU
    // -------------------------------------------------------------------------

    public function register_dashboard_page(): void
    {
        add_menu_page(
            __('SEO Audit Dashboard', 'seo-audit'),
            __('SEO Audit', 'seo-audit'),
            'edit_posts',
            'seo-audit-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-search',
            76
        );
    }

    // -------------------------------------------------------------------------
    // ASSETS
    // -------------------------------------------------------------------------

    public function enqueue_dashboard_assets($hook): void
    {
        if ($hook !== 'toplevel_page_seo-audit-dashboard') {
            return;
        }

        wp_enqueue_style(
            'seo-audit-dashboard',
            SEO_AUDIT_URL . 'assets/css/seo-audit-dashboard.css',
            [],
            SEO_AUDIT_VERSION
        );

        wp_enqueue_script(
            'seo-audit-dashboard',
            SEO_AUDIT_URL . 'assets/js/seo-audit-dashboard.js',
            ['jquery'],
            SEO_AUDIT_VERSION,
            true
        );

        wp_localize_script('seo-audit-dashboard', 'seoAuditDashboard', [
            'apiUrl' => admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('seo_audit_nonce'),
            'i18n'   => [
                'export_success' => __('CSV export started.', 'seo-audit'),
                'rescan_queued'  => __('Posts queued for rescan.', 'seo-audit'),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // DASHBOARD PAGE
    // -------------------------------------------------------------------------

    public function render_dashboard_page(): void
    {
        $stats   = $this->get_audit_stats();
        $history = $this->get_recent_audit_history(20);
        ?>
        <div class="wrap seo-audit-dashboard-wrap">
            <h1 class="seo-dashboard-title">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e('SEO Audit Dashboard', 'seo-audit'); ?>
            </h1>

            <!-- STAT CARDS -->
            <div class="seo-stat-cards">
                <div class="seo-stat-card seo-stat-total">
                    <span class="stat-icon">📄</span>
                    <span class="stat-value"><?php echo esc_html($stats['total_posts']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Total Posts/Pages', 'seo-audit'); ?></span>
                </div>
                <div class="seo-stat-card seo-stat-audited">
                    <span class="stat-icon">✅</span>
                    <span class="stat-value"><?php echo esc_html($stats['audited_count']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Audited', 'seo-audit'); ?></span>
                </div>
                <div class="seo-stat-card seo-stat-pending">
                    <span class="stat-icon">⏳</span>
                    <span class="stat-value"><?php echo esc_html($stats['pending_rescan']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Pending Rescan', 'seo-audit'); ?></span>
                </div>
                <div class="seo-stat-card seo-stat-score">
                    <span class="stat-icon">🏆</span>
                    <span class="stat-value"><?php echo esc_html($stats['avg_score']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Avg. SEO Score', 'seo-audit'); ?></span>
                </div>
            </div>

            <!-- ACTIONS BAR -->
            <div class="seo-dashboard-actions">
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=seo_audit_export_csv&nonce=' . wp_create_nonce('seo_audit_nonce'))); ?>"
                   class="button button-primary" id="seo-export-csv-btn">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export History (CSV)', 'seo-audit'); ?>
                </a>
                <button type="button" class="button" id="seo-queue-all-btn">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Queue All for Rescan', 'seo-audit'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('edit.php')); ?>" class="button">
                    <span class="dashicons dashicons-admin-post"></span>
                    <?php esc_html_e('Manage Posts', 'seo-audit'); ?>
                </a>
            </div>

            <!-- AUDIT HISTORY TABLE -->
            <div class="seo-history-panel">
                <h2><?php esc_html_e('Recent Audit History', 'seo-audit'); ?></h2>
                <?php if (empty($history)) : ?>
                    <p class="seo-empty-state">
                        <?php esc_html_e('No audit data yet. Open any post/page and run the AI SEO Audit from the sidebar meta box.', 'seo-audit'); ?>
                    </p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped seo-history-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Post', 'seo-audit'); ?></th>
                                <th><?php esc_html_e('Type', 'seo-audit'); ?></th>
                                <th><?php esc_html_e('Focus Keyword', 'seo-audit'); ?></th>
                                <th><?php esc_html_e('SEO Score', 'seo-audit'); ?></th>
                                <th><?php esc_html_e('Readability', 'seo-audit'); ?></th>
                                <th><?php esc_html_e('Last Audit', 'seo-audit'); ?></th>
                                <th><?php esc_html_e('Actions', 'seo-audit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row) : ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link($row['post_id'])); ?>">
                                            <?php echo esc_html($row['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($row['post_type']); ?></td>
                                    <td><?php echo esc_html($row['focus_keyword'] ?: '—'); ?></td>
                                    <td>
                                        <span class="seo-score-badge <?php echo $this->score_class($row['seo_score']); ?>">
                                            <?php echo esc_html($row['seo_score'] !== null ? $row['seo_score'] : 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($row['readability'] ?: '—'); ?></td>
                                    <td><?php echo esc_html($row['last_audit'] ?: '—'); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link($row['post_id'])); ?>"
                                           class="button button-small">
                                            <?php esc_html_e('Re-Audit', 'seo-audit'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // WP CORE DASHBOARD WIDGET
    // -------------------------------------------------------------------------

    public function register_wp_dashboard_widget(): void
    {
        wp_add_dashboard_widget(
            'seo_audit_overview_widget',
            __('SEO Audit Overview', 'seo-audit'),
            [$this, 'render_wp_dashboard_widget']
        );
    }

    public function render_wp_dashboard_widget(): void
    {
        $stats = $this->get_audit_stats();
        echo '<div style="display:flex;gap:15px;flex-wrap:wrap;">';
        echo '<div style="text-align:center;flex:1;"><strong style="font-size:22px;">' . esc_html($stats['total_posts']) . '</strong><br><small>' . esc_html__('Total', 'seo-audit') . '</small></div>';
        echo '<div style="text-align:center;flex:1;"><strong style="font-size:22px;color:#10b981;">' . esc_html($stats['audited_count']) . '</strong><br><small>' . esc_html__('Audited', 'seo-audit') . '</small></div>';
        echo '<div style="text-align:center;flex:1;"><strong style="font-size:22px;color:#f59e0b;">' . esc_html($stats['pending_rescan']) . '</strong><br><small>' . esc_html__('Pending', 'seo-audit') . '</small></div>';
        echo '<div style="text-align:center;flex:1;"><strong style="font-size:22px;color:#6366f1;">' . esc_html($stats['avg_score']) . '</strong><br><small>' . esc_html__('Avg Score', 'seo-audit') . '</small></div>';
        echo '</div>';
        echo '<p style="margin-top:12px;"><a href="' . esc_url(admin_url('admin.php?page=seo-audit-dashboard')) . '" class="button">' . esc_html__('View Full Dashboard', 'seo-audit') . '</a></p>';
    }

    // -------------------------------------------------------------------------
    // BACKGROUND RESCAN EXECUTOR
    // -------------------------------------------------------------------------

    /**
     * Processes up to 5 posts that have been flagged for background SEO rescan.
     * Uses stored post meta to persist lightweight audit scores without Gemini Nano.
     */
    public function process_queued_rescans(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        // Only process once per page load and only for admins
        if (!current_user_can('edit_posts')) {
            return;
        }

        $queued = get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'meta_key'       => '_seo_audit_force_rescan',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        if (empty($queued)) {
            return;
        }

        foreach ($queued as $post_id) {
            $this->run_background_audit($post_id);
            delete_post_meta($post_id, '_seo_audit_force_rescan');
        }
    }

    /**
     * Runs a lightweight server-side audit on a post and stores the score.
     */
    private function run_background_audit(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $content       = $post->post_content;
        $plain_content = wp_strip_all_tags($content);
        $word_count    = str_word_count($plain_content);
        $keyword       = get_post_meta($post_id, '_seo_audit_focus_keyword', true);

        $score = 0;

        // Word count (20 pts)
        if ($word_count >= 500) $score += 20;
        elseif ($word_count >= 300) $score += 10;

        // Title length (15 pts)
        $title_len = strlen(get_the_title($post_id));
        if ($title_len >= 30 && $title_len <= 60) $score += 15;
        elseif ($title_len > 0) $score += 7;

        // H1 (15 pts)
        if (preg_match('/<h1[^>]*>/i', $content)) $score += 15;

        // H2 (10 pts)
        if (preg_match('/<h2[^>]*>/i', $content)) $score += 10;

        // Internal links (10 pts)
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        preg_match_all('/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $content, $links);
        $internal = 0;
        foreach ($links[1] as $href) {
            $h = parse_url($href, PHP_URL_HOST);
            if (empty($h) || $h === $site_host) $internal++;
        }
        if ($internal > 0) $score += 10;

        // Images with alt (10 pts)
        $img_count     = preg_match_all('/<img\s/i', $content);
        $missing_alt   = preg_match_all('/<img(?![^>]*\balt=["\'][^"\']+["\'])[^>]*>/i', $content);
        if ($img_count > 0 && $missing_alt === 0) $score += 10;

        // Focus keyword in title (20 pts)
        if ($keyword && stripos(get_the_title($post_id), $keyword) !== false) $score += 20;

        $score = min(100, $score);

        // Readability
        $sentences  = preg_split('/[.!?]+/', $plain_content, -1, PREG_SPLIT_NO_EMPTY);
        $sent_count = max(1, count($sentences));
        $wps        = $word_count / $sent_count;
        $flesch     = round(206.835 - (1.015 * $wps) - (84.6 * 1.5), 1);
        $flesch     = max(0, min(100, $flesch));

        if ($flesch >= 70) $readability = 'Easy';
        elseif ($flesch >= 50) $readability = 'Moderate';
        else $readability = 'Difficult';

        update_post_meta($post_id, '_seo_audit_score',       $score);
        update_post_meta($post_id, '_seo_audit_readability',  $readability);
        update_post_meta($post_id, '_seo_audit_last_run',     current_time('mysql'));
        update_post_meta($post_id, '_seo_audit_word_count',   $word_count);
    }

    // -------------------------------------------------------------------------
    // CSV EXPORT
    // -------------------------------------------------------------------------

    public function export_audit_csv(): void
    {
        check_ajax_referer('seo_audit_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions.', 'seo-audit'));
        }

        $history = $this->get_recent_audit_history(500);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="seo-audit-export-' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Post ID', 'Title', 'Type', 'URL', 'Focus Keyword', 'SEO Score', 'Readability', 'Word Count', 'Last Audit']);

        foreach ($history as $row) {
            fputcsv($out, [
                $row['post_id'],
                $row['title'],
                $row['post_type'],
                get_permalink($row['post_id']),
                $row['focus_keyword'],
                $row['seo_score'],
                $row['readability'],
                $row['word_count'],
                $row['last_audit'],
            ]);
        }

        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------
    // SAVE FOCUS KEYWORD (AJAX)
    // -------------------------------------------------------------------------

    public function save_focus_keyword(): void
    {
        check_ajax_referer('seo_audit_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'seo-audit')]);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');

        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'seo-audit')]);
        }

        update_post_meta($post_id, '_seo_audit_focus_keyword', $keyword);
        
        // Calculate new score immediately
        $keyword_auditor = new \SEOAudit\Audits\KeywordAuditor();
        $score = $keyword_auditor->calculate_keyword_score($post_id, $keyword);
        update_post_meta($post_id, '_seo_audit_score', $score);

        // Flag for full background rescan as well
        update_post_meta($post_id, '_seo_audit_force_rescan', time());

        wp_send_json_success([
            'message' => __('Focus keyword saved and score updated.', 'seo-audit'),
            'score'   => $score,
            'class'   => ($score >= 70) ? 'good' : (($score >= 40) ? 'warning' : 'bad')
        ]);
    }

    /**
     * AJAX: Queue all published posts/pages for rescan
     */
    public function queue_all_rescans(): void
    {
        check_ajax_referer('seo_audit_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'seo-audit')]);
        }

        $posts = get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $count = 0;
        foreach ($posts as $post_id) {
            update_post_meta($post_id, '_seo_audit_force_rescan', time());
            $count++;
        }

        wp_send_json_success(['message' => sprintf(__('%d posts queued for rescan.', 'seo-audit'), $count)]);
    }

    // -------------------------------------------------------------------------
    // DATA HELPERS
    // -------------------------------------------------------------------------

    private function get_audit_stats(): array
    {
        $post_types  = get_post_types(['public' => true], 'names');
        $total_posts = 0;
        foreach ($post_types as $pt) {
            $count = wp_count_posts($pt);
            $total_posts += (int) ($count->publish ?? 0);
        }

        global $wpdb;

        $audited_count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_audit_last_run'"
        );

        $pending_rescan = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_audit_force_rescan'"
        );

        $avg_score_raw = $wpdb->get_var(
            "SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_audit_score' AND meta_value != ''"
        );

        $avg_score = $avg_score_raw !== null ? round((float) $avg_score_raw) : 'N/A';

        return compact('total_posts', 'audited_count', 'pending_rescan', 'avg_score');
    }

    private function get_recent_audit_history(int $limit = 20): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_type,
                        MAX(CASE WHEN pm.meta_key = '_seo_audit_score'         THEN pm.meta_value END) AS seo_score,
                        MAX(CASE WHEN pm.meta_key = '_seo_audit_readability'   THEN pm.meta_value END) AS readability,
                        MAX(CASE WHEN pm.meta_key = '_seo_audit_last_run'      THEN pm.meta_value END) AS last_audit,
                        MAX(CASE WHEN pm.meta_key = '_seo_audit_focus_keyword' THEN pm.meta_value END) AS focus_keyword,
                        MAX(CASE WHEN pm.meta_key = '_seo_audit_word_count'    THEN pm.meta_value END) AS word_count
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_status = 'publish'
                   AND pm.meta_key IN ('_seo_audit_score','_seo_audit_readability','_seo_audit_last_run','_seo_audit_focus_keyword','_seo_audit_word_count')
                 GROUP BY p.ID
                 ORDER BY last_audit DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $history = [];
        foreach ($results as $row) {
            $history[] = [
                'post_id'       => $row['ID'],
                'title'         => $row['post_title'],
                'post_type'     => $row['post_type'],
                'seo_score'     => $row['seo_score'],
                'readability'   => $row['readability'],
                'last_audit'    => $row['last_audit'],
                'focus_keyword' => $row['focus_keyword'],
                'word_count'    => $row['word_count'],
            ];
        }

        return $history;
    }

    private function score_class(?string $score): string
    {
        if ($score === null || $score === '') return 'seo-score-na';
        $val = (int) $score;
        if ($val >= 70) return 'seo-score-good';
        if ($val >= 40) return 'seo-score-warning';
        return 'seo-score-bad';
    }
}
